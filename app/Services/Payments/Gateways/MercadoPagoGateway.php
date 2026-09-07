<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentConnectionStatus;
use App\Exceptions\Payments\PaymentProviderNotConfiguredException;
use App\Models\Tenant;
use App\Models\TenantPaymentConnection;
use App\Models\Order;
use App\Models\Payment;
use App\Models\EcommerceSetting;
use App\Services\Payments\MercadoPagoOAuthService;
use App\Services\Payments\MarketplaceFeeCalculator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    private const PROVIDER = TenantPaymentConnection::PROVIDER_MERCADOPAGO;
    public function __construct(
        protected Tenant $tenant,
        protected ?TenantPaymentConnection $connection = null,
        protected array $config = []
    ) {
        $this->connection ??= $this->tenant->paymentConnections()
            ->where('provider', TenantPaymentConnection::PROVIDER_MERCADOPAGO)
            ->first();
    }

    public function provider(): string
    {
        return TenantPaymentConnection::PROVIDER_MERCADOPAGO;
    }

    public function connection(): TenantPaymentConnection
    {
        if (! $this->connection || $this->connection->status !== PaymentConnectionStatus::Connected) {
            throw new PaymentProviderNotConfiguredException('Mercado Pago no está conectado para esta tienda.');
        }

        return $this->connection;
    }

    public function validAccessToken(): string
    {
        return app(MercadoPagoOAuthService::class)->getValidAccessToken($this->tenant);
    }

    public function createPreference(Order $order, string $storefrontOrigin): array
    {
        abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');
        abort_unless($order->items()->exists(), 422, 'El pedido no tiene productos.');
        abort_unless((float) $order->total > 0, 422, 'El total del pedido debe ser mayor a cero.');
        abort_unless($this->isEnabledForCheckout(), 422, 'Mercado Pago no está habilitado para esta tienda.');

        return DB::transaction(function () use ($order, $storefrontOrigin) {
            $order = Order::query()
                ->with(['items', 'user'])
                ->lockForUpdate()
                ->findOrFail($order->id);
            abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');

            if ($payment = $this->reusablePayment($order)) {
                return $this->checkoutPayload($order, $payment, true);
            }

            $this->cancelStalePendingPayments($order);
            $externalReference = 'cloudishop_order_'.Str::uuid();
            $platformFee = app(MarketplaceFeeCalculator::class)->calculateCents($this->tenant, $order, self::PROVIDER);
            $payload = $this->preferencePayload($order, $externalReference, $storefrontOrigin, $platformFee);
            $accessToken = $this->validAccessToken();

            try {
                $response = Http::withToken($accessToken)
                    ->connectTimeout((int) config('services.mercadopago.connect_timeout', 10))
                    ->timeout((int) config('services.mercadopago.timeout', 20))
                    ->post('https://api.mercadopago.com/checkout/preferences', $payload);
            } catch (ConnectionException) {
                throw new HttpException(422, 'No fue posible iniciar el pago con Mercado Pago.');
            }

            if (! $response->successful() || blank(data_get($response->json(), 'id')) || blank(data_get($response->json(), 'init_point'))) {
                $providerResponse = $response->json();

                Log::warning('Mercado Pago preference creation failed.', [
                    'tenant_id' => $this->tenant->id,
                    'order_id' => $order->id,
                    'provider' => self::PROVIDER,
                    'http_status' => $response->status(),
                    'provider_error' => data_get($providerResponse, 'error'),
                    'provider_message' => data_get($providerResponse, 'message'),
                    'provider_causes' => collect(data_get($providerResponse, 'cause', []))
                        ->map(fn (array $cause) => [
                            'code' => data_get($cause, 'code'),
                            'description' => data_get($cause, 'description'),
                        ])
                        ->values()
                        ->all(),
                ]);

                throw new HttpException(422, 'No fue posible iniciar el pago con Mercado Pago.');
            }

            $preference = $response->json();
            $metadata = $order->metadata ?? [];
            $metadata['mercadopago'] = ['external_reference' => $externalReference];
            $order->forceFill([
                'payment_method' => self::PROVIDER,
                'metadata' => $metadata,
            ])->save();

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => self::PROVIDER,
                'status' => Order::PAYMENT_PENDING,
                'payment_method' => self::PROVIDER,
                'provider_reference' => data_get($preference, 'id'),
                'external_reference' => $externalReference,
                'amount' => (float) $order->total,
                'platform_fee' => $this->amountFromCents($platformFee),
                'currency' => strtoupper($order->currency),
                'provider_payload' => [
                    'preference_id' => data_get($preference, 'id'),
                    'init_point' => data_get($preference, 'init_point'),
                    'checkout_total' => (float) $order->total,
                    'checkout_created_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info('Mercado Pago preference created.', [
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'provider' => self::PROVIDER,
                'preference_id' => $payment->provider_reference,
            ]);

            return $this->checkoutPayload($order, $payment, false);
        });
    }

    protected function isEnabledForCheckout(): bool
    {
        return (bool) data_get(EcommerceSetting::paymentMethodSettings(), 'methods.mercadopago.enabled', false);
    }

    protected function reusablePayment(Order $order): ?Payment
    {
        $payment = $order->payments()
            ->where('provider', self::PROVIDER)
            ->where('status', Order::PAYMENT_PENDING)
            ->whereNotNull('provider_reference')
            ->latest('id')
            ->first();

        if (! $payment || $this->amountInCents(data_get($payment->provider_payload, 'checkout_total')) !== $this->amountInCents($order->total)) {
            return null;
        }

        return $payment;
    }

    protected function cancelStalePendingPayments(Order $order): void
    {
        $order->payments()
            ->where('provider', self::PROVIDER)
            ->where('status', Order::PAYMENT_PENDING)
            ->where(function ($query) use ($order) {
                $query->whereNull('provider_payload->checkout_total')
                    ->orWhere('provider_payload->checkout_total', '!=', (float) $order->total);
            })
            ->update([
                'status' => 'cancelled',
                'provider_status' => 'superseded',
                'updated_at' => now(),
            ]);
    }

    protected function preferencePayload(Order $order, string $externalReference, string $origin, int $platformFee = 0): array
    {
        $currency = strtoupper($order->currency);
        $totalCents = $this->amountInCents($order->total);
        $shippingCents = min($this->amountInCents($order->shipping), $totalCents);
        $items = $this->discountedProductItems($order, $totalCents - $shippingCents, $currency);

        if ($shippingCents > 0) {
            $items[] = [
                'id' => 'shipping',
                'title' => 'Envío',
                'quantity' => 1,
                'unit_price' => $this->amountFromCents($shippingCents),
                'currency_id' => $currency,
            ];
        }

        $payload = [
            'items' => $items,
            'external_reference' => $externalReference,
            'back_urls' => [
                'success' => rtrim($origin, '/').'/checkout/payment/success',
                'failure' => rtrim($origin, '/').'/checkout/payment/failure',
                'pending' => rtrim($origin, '/').'/checkout/payment/pending',
            ],
        ];

        // Mercado Pago only accepts automatic returns to publicly reachable HTTPS URLs.
        if ($this->supportsAutoReturn($origin)) {
            $payload['auto_return'] = 'approved';
        }

        if ($platformFee > 0) {
            $payload['marketplace_fee'] = $this->amountFromCents($platformFee);
        }

        if (filled(config('services.mercadopago.notification_url'))) {
            $payload['notification_url'] = config('services.mercadopago.notification_url');
        }

        $email = $order->user?->email ?: data_get($order->metadata, 'guest.email');
        if (filled($email)) {
            $payload['payer'] = ['email' => $email];
        }

        return $payload;
    }

    protected function discountedProductItems(Order $order, int $targetCents, string $currency): array
    {
        $sourceItems = $order->items->map(fn ($item) => [
            'id' => (string) ($item->product_id ?: $item->id),
            'title' => Str::limit((string) $item->name_snapshot, 240, ''),
            'quantity' => (float) $item->quantity,
            'amount_cents' => $this->amountInCents($item->line_total),
        ])->filter(fn (array $item) => $item['amount_cents'] > 0)->values();
        $sourceTotal = $sourceItems->sum('amount_cents');

        abort_unless($sourceTotal > 0 || $targetCents === 0, 422, 'El pedido no tiene importes de productos válidos.');

        $remainingTarget = $targetCents;
        $remainingSource = $sourceTotal;

        return $sourceItems->map(function (array $item, int $index) use ($sourceItems, &$remainingTarget, &$remainingSource, $currency) {
            $isLast = $index === $sourceItems->count() - 1;
            $allocatedCents = $isLast
                ? $remainingTarget
                : (int) floor($item['amount_cents'] * $remainingTarget / max(1, $remainingSource));
            $remainingTarget -= $allocatedCents;
            $remainingSource -= $item['amount_cents'];

            return [
                'id' => $item['id'],
                'title' => $item['title'].($item['quantity'] > 1 ? ' (x'.rtrim(rtrim(number_format($item['quantity'], 2, '.', ''), '0'), '.').')' : ''),
                'quantity' => 1,
                'unit_price' => $this->amountFromCents($allocatedCents),
                'currency_id' => $currency,
            ];
        })->filter(fn (array $item) => $item['unit_price'] > 0)->values()->all();
    }

    protected function amountInCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function amountFromCents(int $amount): float
    {
        return round($amount / 100, 2);
    }

    protected function supportsAutoReturn(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);

        return parse_url($origin, PHP_URL_SCHEME) === 'https'
            && is_string($host)
            && ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with(strtolower($host), '.localhost');
    }

    protected function checkoutPayload(Order $order, Payment $payment, bool $reused): array
    {
        return [
            'provider' => self::PROVIDER,
            'preference_id' => $payment->provider_reference,
            'checkout_url' => data_get($payment->provider_payload, 'init_point'),
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'reused' => $reused,
        ];
    }


    public function createCheckout(array $payload = []): array
    {
        $this->connection();

        throw new PaymentProviderNotConfiguredException('Checkout Pro de Mercado Pago todavía no está implementado.');
    }

    public function getPayment(string $paymentId): array
    {
        $this->connection();

        throw new PaymentProviderNotConfiguredException('Consulta de pagos de Mercado Pago todavía no está implementada.');
    }

    public function refund(string $paymentId, array $payload = []): array
    {
        $this->connection();

        throw new PaymentProviderNotConfiguredException('Reembolsos de Mercado Pago todavía no están implementados.');
    }

    public function handleWebhook(array $payload = []): array
    {
        $this->connection();

        throw new PaymentProviderNotConfiguredException('Webhooks de Mercado Pago todavía no están implementados.');
    }
}
