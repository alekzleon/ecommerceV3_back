<?php

namespace App\Services\Payments;

use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StripeWebhookEvent;
use App\Services\Orders\DoctoVeService;
use App\Services\Orders\OrderNotificationService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripePaymentService
{
    public function __construct(
        protected OrderNotificationService $orderNotificationService,
        protected DoctoVeService $doctoVeService
    ) {
    }

    public function createCheckoutSession(Order $order): array
    {
        abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');
        abort_unless((float) $order->total > 0, 422, 'El total del pedido debe ser mayor a cero.');

        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');

        $order->loadMissing(['items', 'user']);
        $this->validateOrderStock($order);

        $payload = [
            'mode' => 'payment',
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => $this->cancelUrl($order),
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->user?->email,
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->number,
                'user_id' => (string) $order->user_id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->number,
                    'user_id' => (string) $order->user_id,
                ],
            ],
            'line_items' => $this->lineItems($order),
        ];

        try {
            $session = Http::asForm()
                ->withToken($secretKey)
                ->timeout(20)
                ->post('https://api.stripe.com/v1/checkout/sessions', $this->flatten($payload))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible iniciar el pago con Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        $order->forceFill([
            'stripe_session_id' => data_get($session, 'id'),
            'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
            'payment_method' => 'stripe',
        ])->save();

        Payment::updateOrCreate(
            [
                'provider' => 'stripe',
                'stripe_session_id' => data_get($session, 'id'),
            ],
            [
                'order_id' => $order->id,
                'status' => Order::PAYMENT_PENDING,
                'payment_method' => 'stripe',
                'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
                'amount' => (float) $order->total,
                'currency' => strtoupper($order->currency),
                'provider_payload' => $session,
            ]
        );

        return [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'stripe_session_id' => data_get($session, 'id'),
            'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
            'url' => data_get($session, 'url'),
            'payment_status' => $order->payment_status,
            'amount' => (float) $order->total,
            'currency' => strtolower($order->currency),
        ];
    }

    public function handleWebhook(string $payload, ?string $signatureHeader): array
    {
        $this->validateSignature($payload, $signatureHeader);

        $event = json_decode($payload, true);

        abort_unless(is_array($event), 400, 'Payload inválido.');

        return DB::transaction(function () use ($event) {
            $stripeEventId = (string) data_get($event, 'id');
            $type = (string) data_get($event, 'type');

            abort_if(blank($stripeEventId) || blank($type), 400, 'Evento inválido.');

            $webhookEvent = StripeWebhookEvent::query()
                ->where('stripe_event_id', $stripeEventId)
                ->lockForUpdate()
                ->first();

            if ($webhookEvent && $webhookEvent->status === 'processed') {
                return [
                    'ok' => true,
                    'duplicate' => true,
                    'event_id' => $stripeEventId,
                    'type' => $type,
                ];
            }

            $webhookEvent ??= StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $type,
                'status' => 'processing',
                'payload' => $event,
            ]);

            match ($type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted(data_get($event, 'data.object', [])),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded(data_get($event, 'data.object', [])),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed(data_get($event, 'data.object', [])),
                default => null,
            };

            $webhookEvent->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'payload' => $event,
            ])->save();

            return [
                'ok' => true,
                'duplicate' => false,
                'event_id' => $stripeEventId,
                'type' => $type,
            ];
        });
    }

    public function expireCheckoutSession(Order $order): void
    {
        if (blank($order->stripe_session_id) || $order->payment_status === Order::PAYMENT_PAID) {
            return;
        }

        $secretKey = config('services.stripe.secret_key');

        if (blank($secretKey)) {
            return;
        }

        try {
            Http::asForm()
                ->withToken($secretKey)
                ->timeout(10)
                ->post("https://api.stripe.com/v1/checkout/sessions/{$order->stripe_session_id}/expire");
        } catch (\Throwable) {
            report('No fue posible expirar la sesión de Stripe ' . $order->stripe_session_id);
        }
    }

    public function syncCheckoutSession(string $sessionId): ?Order
    {
        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');

        try {
            $session = Http::withToken($secretKey)
                ->timeout(20)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible consultar la sesión de Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        $order = $this->findOrder($session);

        if (! $order) {
            return null;
        }

        $this->handleCheckoutSessionCompleted($session);

        return $order->fresh(['items', 'payments']);
    }

    protected function handleCheckoutSessionCompleted(array $session): void
    {
        $order = $this->findOrder($session);

        if (! $order) {
            return;
        }

        $paymentIntentId = data_get($session, 'payment_intent');
        $paymentStatus = data_get($session, 'payment_status');

        $this->syncPayment($order, [
            'status' => $paymentStatus === 'paid' ? Order::PAYMENT_PAID : Order::PAYMENT_PENDING,
            'stripe_session_id' => data_get($session, 'id'),
            'stripe_payment_intent_id' => $paymentIntentId,
            'amount' => $this->amountFromStripe(data_get($session, 'amount_total'), $order),
            'currency' => strtoupper((string) data_get($session, 'currency', $order->currency)),
            'payload' => $session,
        ]);

        if ($paymentStatus === 'paid') {
            $this->markOrderPaid($order, data_get($session, 'id'), $paymentIntentId);
        }
    }

    protected function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        $order = $this->findOrder($paymentIntent);

        if (! $order) {
            return;
        }

        $this->syncPayment($order, [
            'status' => Order::PAYMENT_PAID,
            'stripe_payment_intent_id' => data_get($paymentIntent, 'id'),
            'amount' => $this->amountFromStripe(data_get($paymentIntent, 'amount_received'), $order),
            'currency' => strtoupper((string) data_get($paymentIntent, 'currency', $order->currency)),
            'payload' => $paymentIntent,
        ]);

        $this->markOrderPaid($order, $order->stripe_session_id, data_get($paymentIntent, 'id'));
    }

    protected function handlePaymentIntentFailed(array $paymentIntent): void
    {
        $order = $this->findOrder($paymentIntent);

        if (! $order || $order->payment_status === Order::PAYMENT_PAID) {
            return;
        }

        $this->syncPayment($order, [
            'status' => Order::PAYMENT_FAILED,
            'stripe_payment_intent_id' => data_get($paymentIntent, 'id'),
            'amount' => $this->amountFromStripe(data_get($paymentIntent, 'amount'), $order),
            'currency' => strtoupper((string) data_get($paymentIntent, 'currency', $order->currency)),
            'payload' => $paymentIntent,
        ]);

        $order->forceFill([
            'status' => Order::STATUS_PAYMENT_FAILED,
            'payment_status' => Order::PAYMENT_FAILED,
            'payment_method' => 'stripe',
            'stripe_payment_intent_id' => data_get($paymentIntent, 'id'),
        ])->save();
    }

    protected function markOrderPaid(Order $order, ?string $sessionId, ?string $paymentIntentId): void
    {
        if ($order->payment_status === Order::PAYMENT_PAID) {
            $this->deductOrderStock($order->fresh(['items.product']));
            $this->activateCashbackTransactions($order);
            $this->doctoVeService->createFromPaidOrder($order->fresh(['user.customerProfile', 'items.product']));
            $this->orderNotificationService->sendPurchaseNotifications($order->fresh(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress', 'items', 'payments']));

            return;
        }

        $order->forceFill([
            'status' => Order::STATUS_PAID,
            'payment_status' => Order::PAYMENT_PAID,
            'payment_method' => 'stripe',
            'stripe_session_id' => $sessionId ?: $order->stripe_session_id,
            'stripe_payment_intent_id' => $paymentIntentId ?: $order->stripe_payment_intent_id,
            'paid_at' => now(),
        ])->save();

        $this->deductOrderStock($order->fresh(['items.product']));
        $this->activateCashbackTransactions($order);

        $this->doctoVeService->createFromPaidOrder($order->fresh(['user.customerProfile', 'items.product']));

        $this->orderNotificationService->sendPurchaseNotifications($order->fresh(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress', 'items', 'payments']));
    }

    protected function activateCashbackTransactions(Order $order): void
    {
        CashbackTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', CashbackTransaction::STATUS_PENDING)
            ->update(['status' => CashbackTransaction::STATUS_AVAILABLE]);
    }

    protected function validateOrderStock(Order $order): void
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product || $product->stock === null) {
                continue;
            }

            abort_if((float) $product->stock <= 0, 422, "El producto {$item->name_snapshot} ya no tiene inventario disponible.");
            abort_if((float) $product->stock < (float) $item->quantity, 422, "El producto {$item->name_snapshot} solo tiene {$product->stock} pieza(s) disponibles.");
        }
    }

    protected function deductOrderStock(Order $order): void
    {
        $metadata = $order->metadata ?? [];

        if (data_get($metadata, 'stock_deducted_at')) {
            return;
        }

        DB::transaction(function () use ($order, $metadata) {
            $deducted = [];

            foreach ($order->items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->stock === null) {
                    continue;
                }

                $before = (float) $product->stock;
                $quantity = (float) $item->quantity;
                $after = max(0, round($before - $quantity, 2));

                $product->forceFill(['stock' => $after])->save();

                $deducted[] = [
                    'product_id' => $product->id,
                    'sku' => $item->sku_snapshot,
                    'quantity' => $quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                ];
            }

            $metadata['stock_deducted_at'] = now()->toDateTimeString();
            $metadata['stock_deductions'] = $deducted;

            $order->forceFill(['metadata' => $metadata])->save();
        });
    }

    protected function syncPayment(Order $order, array $data): Payment
    {
        $sessionId = $data['stripe_session_id'] ?? $order->stripe_session_id;
        $paymentIntentId = $data['stripe_payment_intent_id'] ?? $order->stripe_payment_intent_id;

        $payment = Payment::query()
            ->where('provider', 'stripe')
            ->where(function ($query) use ($sessionId, $paymentIntentId) {
                $query
                    ->when(filled($sessionId), fn ($subQuery) => $subQuery->orWhere('stripe_session_id', $sessionId))
                    ->when(filled($paymentIntentId), fn ($subQuery) => $subQuery->orWhere('stripe_payment_intent_id', $paymentIntentId));
            })
            ->first();

        $payment ??= new Payment(['provider' => 'stripe']);

        $payment->fill([
            'order_id' => $order->id,
            'status' => $data['status'],
            'payment_method' => 'stripe',
            'stripe_session_id' => $sessionId,
            'stripe_payment_intent_id' => $paymentIntentId,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'paid_at' => $data['status'] === Order::PAYMENT_PAID ? now() : null,
            'provider_payload' => $data['payload'],
        ])->save();

        return $payment;
    }

    protected function findOrder(array $stripeObject): ?Order
    {
        $orderId = data_get($stripeObject, 'metadata.order_id');

        if ($orderId) {
            return Order::query()->find($orderId);
        }

        $sessionId = data_get($stripeObject, 'id');
        $paymentIntentId = data_get($stripeObject, 'payment_intent') ?: data_get($stripeObject, 'id');

        return Order::query()
            ->where('stripe_session_id', $sessionId)
            ->orWhere('stripe_payment_intent_id', $paymentIntentId)
            ->first();
    }

    protected function lineItems(Order $order): array
    {
        return [[
            'price_data' => [
                'currency' => strtolower($order->currency),
                'product_data' => [
                    'name' => 'Pedido ' . $order->number,
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'order_number' => $order->number,
                    ],
                ],
                'unit_amount' => $this->amountToStripeCents((float) $order->total),
            ],
            'quantity' => 1,
        ]];
    }

    protected function cancelUrl(Order $order): string
    {
        $url = (string) config('services.stripe.cancel_url');
        $separator = Str::contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query([
            'order_id' => $order->id,
            'order_number' => $order->number,
        ]);
    }

    protected function validateSignature(string $payload, ?string $signatureHeader): void
    {
        $secret = config('services.stripe.webhook_secret');

        abort_if(blank($secret), 500, 'Stripe webhook no está configurado.');
        abort_if(blank($signatureHeader), 400, 'Falta la firma de Stripe.');

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signatures = collect(explode(',', $signatureHeader))
            ->filter(fn (string $part) => str_starts_with($part, 'v1='))
            ->map(fn (string $part) => substr($part, 3));

        abort_if(blank($timestamp) || $signatures->isEmpty(), 400, 'Firma de Stripe inválida.');
        abort_if(abs(time() - (int) $timestamp) > 300, 400, 'Firma de Stripe expirada.');

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $valid = $signatures->contains(fn (string $signature) => hash_equals($expected, $signature));

        abort_unless($valid, 400, 'Firma de Stripe inválida.');
    }

    protected function flatten(array $payload, ?string $prefix = null): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            $name = $prefix === null ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $result += $this->flatten($value, $name);
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    protected function amountToStripeCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function amountFromStripe(mixed $amount, Order $order): float
    {
        if ($amount === null) {
            return (float) $order->total;
        }

        return round(((int) $amount) / 100, 2);
    }
}
