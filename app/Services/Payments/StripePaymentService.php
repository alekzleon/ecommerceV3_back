<?php

namespace App\Services\Payments;

use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StripeWebhookEvent;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantStripeAccount;
use App\Models\EcommerceSetting;
use App\Services\Orders\OrderNotificationService;
use App\Services\TenantNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripePaymentService
{
    public function __construct(
        protected OrderNotificationService $orderNotificationService,
        protected TenantNotificationService $tenantNotifications
    ) {
    }

    public function createCheckoutSession(Order $order, ?string $storefrontOrigin = null): array
    {
        abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');
        abort_unless((float) $order->total > 0, 422, 'El total del pedido debe ser mayor a cero.');

        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 422, $this->invalidStorePaymentMethodMessage());

        $order->loadMissing(['items', 'user']);
        $this->validateOrderStock($order);
        $stripeAccountId = $this->stripeAccountIdForStoreCheckout();

        if ($existingSession = $this->activeCheckoutSessionPayload($order, $secretKey, $storefrontOrigin, $stripeAccountId)) {
            return $existingSession;
        }

        $stripeMetadata = $this->stripeMetadata($order, $stripeAccountId);

        $payload = [
            'mode' => 'payment',
            'success_url' => $this->successUrl($storefrontOrigin),
            'cancel_url' => $this->cancelUrl($order, $storefrontOrigin),
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->user?->email ?: data_get($order->metadata, 'guest.email'),
            'metadata' => $stripeMetadata,
            'payment_intent_data' => [
                'metadata' => $stripeMetadata,
            ],
            'line_items' => $this->lineItems($order),
        ];

        try {
            $session = Http::asForm()
                ->withToken($secretKey)
                ->when($stripeAccountId, fn ($request) => $request->withHeaders([
                    'Stripe-Account' => $stripeAccountId,
                ]))
                ->timeout(20)
                ->post('https://api.stripe.com/v1/checkout/sessions', $this->flatten($payload))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::warning('Stripe checkout session failed for store checkout.', [
                'order_id' => $order->id,
                'tenant_id' => tenant('id'),
                'stripe_account_id' => $stripeAccountId,
                'stripe_message' => data_get($exception->response?->json(), 'error.message'),
            ]);

            throw new HttpException(422, $this->invalidStorePaymentMethodMessage(), $exception);
        }

        $order->forceFill([
            'stripe_session_id' => data_get($session, 'id'),
            'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
            'payment_method' => 'stripe',
            'metadata' => $this->orderMetadataWithStripeAccount($order, $stripeAccountId),
        ])->save();

        $providerPayload = $this->providerPayloadWithStripeAccount($session, $stripeAccountId);

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
                'provider_payload' => $providerPayload,
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
            'stripe_account_id' => $stripeAccountId,
            'charge_type' => $stripeAccountId ? 'direct' : 'platform',
            'reused' => false,
        ];
    }

    public function createSubscriptionCheckoutSession(
        Tenant $tenant,
        string $planKey,
        string $billingPeriod = 'monthly',
        ?string $billingOrigin = null,
        ?string $customerEmail = null
    ): array
    {
        $plan = config("plans.plans.{$planKey}");

        abort_unless($plan, 422, 'El plan seleccionado no existe.');
        $billingOption = $this->subscriptionBillingOption($plan, $billingPeriod);
        abort_if((int) ($billingOption['price'] ?? 0) <= 0, 422, 'El plan seleccionado no requiere pago.');

        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');

        $metadata = [
            'tenant_id' => (string) $tenant->id,
            'plan_key' => $planKey,
            'billing_period' => $billingOption['key'],
            'months_charged' => (string) ($billingOption['months_charged'] ?? ''),
            'months_free' => (string) ($billingOption['months_free'] ?? 0),
            'checkout_type' => 'cloudishop_subscription',
        ];

        $payload = [
            'mode' => 'subscription',
            'success_url' => $this->subscriptionSuccessUrl($billingOrigin),
            'cancel_url' => $this->subscriptionCancelUrl($billingOrigin),
            'client_reference_id' => (string) $tenant->id,
            'customer' => $tenant->provider_customer_id,
            'customer_email' => $tenant->provider_customer_id ? null : $customerEmail,
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower((string) ($billingOption['currency'] ?? $plan['currency'] ?? 'MXN')),
                    'product_data' => [
                        'name' => 'CloudiShop ' . ($plan['name'] ?? $planKey) . ' - ' . ($billingOption['name'] ?? $billingOption['label']),
                        'metadata' => [
                            'plan_key' => $planKey,
                            'billing_period' => $billingOption['key'],
                        ],
                    ],
                    'recurring' => [
                        'interval' => (string) ($billingOption['interval'] ?? 'month'),
                    ],
                    'unit_amount' => (int) $billingOption['price'],
                ],
                'quantity' => 1,
            ]],
        ];

        try {
            $session = Http::asForm()
                ->withToken($secretKey)
                ->timeout(20)
                ->post('https://api.stripe.com/v1/checkout/sessions', $this->flatten($payload))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible iniciar la suscripción con Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        $tenant->forceFill([
            'payment_provider' => 'stripe',
            'provider_customer_id' => data_get($session, 'customer') ?: $tenant->provider_customer_id,
        ])->save();

        return [
            'tenant_id' => $tenant->id,
            'plan_key' => $planKey,
            'billing_period' => $billingOption['key'],
            'stripe_session_id' => data_get($session, 'id'),
            'url' => data_get($session, 'url'),
            'amount' => round(((int) $billingOption['price']) / 100, 2),
            'currency' => strtolower((string) ($billingOption['currency'] ?? $plan['currency'] ?? 'MXN')),
            'interval' => $billingOption['interval'],
            'months_charged' => $billingOption['months_charged'],
            'months_free' => $billingOption['months_free'],
            'savings_label' => $billingOption['savings_label'] ?? null,
        ];
    }

    public function cancelSubscription(Tenant $tenant, bool $cancelAtPeriodEnd = true): array
    {
        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');
        abort_if(blank($tenant->provider_subscription_id), 422, 'La tienda no tiene una suscripción activa en Stripe.');
        abort_unless($tenant->payment_provider === 'stripe', 422, 'La suscripción actual no pertenece a Stripe.');

        $subscriptionId = (string) $tenant->provider_subscription_id;

        try {
            $subscription = $cancelAtPeriodEnd
                ? Http::asForm()
                    ->withToken($secretKey)
                    ->timeout(20)
                    ->post("https://api.stripe.com/v1/subscriptions/{$subscriptionId}", [
                        'cancel_at_period_end' => 'true',
                    ])
                    ->throw()
                    ->json()
                : Http::withToken($secretKey)
                    ->timeout(20)
                    ->delete("https://api.stripe.com/v1/subscriptions/{$subscriptionId}")
                    ->throw()
                    ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible cancelar la suscripción en Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        $periodEnd = data_get($subscription, 'current_period_end');
        $endsAt = $periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : $tenant->subscription_ends_at;

        $tenant->forceFill([
            'subscription_status' => $cancelAtPeriodEnd ? Tenant::STATUS_ACTIVE : Tenant::STATUS_CANCELED,
            'subscription_ends_at' => $endsAt,
            'suspended_at' => $cancelAtPeriodEnd ? null : now(),
            'data' => $this->tenantDataWithSubscriptionCancellation($tenant, $subscription),
        ])->save();

        return [
            'tenant' => $tenant->fresh(),
            'stripe_subscription_id' => data_get($subscription, 'id', $subscriptionId),
            'stripe_status' => data_get($subscription, 'status'),
            'cancel_at_period_end' => (bool) data_get($subscription, 'cancel_at_period_end', $cancelAtPeriodEnd),
            'cancel_at' => $this->timestampToIso(data_get($subscription, 'cancel_at')),
            'canceled_at' => $this->timestampToIso(data_get($subscription, 'canceled_at')),
            'current_period_end' => $this->timestampToIso($periodEnd),
        ];
    }

    public function handleWebhook(string $payload, ?string $signatureHeader): array
    {
        $this->validateSignature($payload, $signatureHeader);

        $event = json_decode($payload, true);

        abort_unless(is_array($event), 400, 'Payload inválido.');

        $tenant = $this->resolveTenantFromWebhookEvent($event);
        tenancy()->initialize($tenant);

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
                    'tenant_id' => tenant('id'),
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
                'customer.subscription.created',
                'customer.subscription.updated' => $this->handleCustomerSubscriptionUpdated(data_get($event, 'data.object', [])),
                'customer.subscription.deleted' => $this->handleCustomerSubscriptionDeleted(data_get($event, 'data.object', [])),
                'invoice.paid' => $this->handleInvoicePaid(data_get($event, 'data.object', [])),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed(data_get($event, 'data.object', [])),
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
                'tenant_id' => tenant('id'),
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
            $this->expireStripeSessionById(
                $order->stripe_session_id,
                $secretKey,
                null,
                $this->stripeAccountIdForOrder($order)
            );
        } catch (\Throwable) {
            report('No fue posible expirar la sesión de Stripe ' . $order->stripe_session_id);
        }
    }

    public function syncCheckoutSession(string $sessionId): ?Order
    {
        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');

        $stripeAccountId = $this->stripeAccountIdForSession($sessionId);

        try {
            $session = Http::withToken($secretKey)
                ->when($stripeAccountId, fn ($request) => $request->withHeaders([
                    'Stripe-Account' => $stripeAccountId,
                ]))
                ->timeout(20)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::warning('Stripe existing checkout session lookup failed for store checkout.', [
                'order_id' => $order->id,
                'tenant_id' => tenant('id'),
                'stripe_session_id' => $order->stripe_session_id,
                'stripe_account_id' => $stripeAccountId,
                'stripe_message' => data_get($exception->response?->json(), 'error.message'),
            ]);

            throw new HttpException(422, $this->invalidStorePaymentMethodMessage(), $exception);
        }

        $order = $this->findOrder($session);

        if (! $order) {
            return null;
        }

        $this->handleCheckoutSessionCompleted($session);

        return $order->fresh(['items', 'payments']);
    }

    public function syncSubscriptionCheckoutSession(Tenant $tenant, string $sessionId): Tenant
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

        abort_unless(
            data_get($session, 'mode') === 'subscription'
                || data_get($session, 'metadata.checkout_type') === 'cloudishop_subscription',
            422,
            'La sesión de Stripe no corresponde a una suscripción.'
        );

        abort_unless(
            (string) data_get($session, 'metadata.tenant_id') === (string) $tenant->id,
            403,
            'La sesión de Stripe no pertenece a esta tienda.'
        );

        abort_unless(
            data_get($session, 'payment_status') === 'paid',
            422,
            'La sesión de Stripe todavía no aparece como pagada.'
        );

        $this->handleSubscriptionCheckoutSessionCompleted($session);

        $subscriptionId = (string) data_get($session, 'subscription');

        if (filled($subscriptionId)) {
            $this->syncStripeSubscriptionById($subscriptionId, $secretKey);
        }

        return $tenant->fresh();
    }

    protected function handleCheckoutSessionCompleted(array $session): void
    {
        if (data_get($session, 'mode') === 'subscription'
            || data_get($session, 'metadata.checkout_type') === 'cloudishop_subscription') {
            $this->handleSubscriptionCheckoutSessionCompleted($session);

            return;
        }

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

    protected function handleSubscriptionCheckoutSessionCompleted(array $session): void
    {
        $tenant = tenant();
        $planKey = (string) data_get($session, 'metadata.plan_key');

        if (blank($planKey)) {
            return;
        }

        $tenant->forceFill([
            'payment_provider' => 'stripe',
            'provider_customer_id' => data_get($session, 'customer') ?: $tenant->provider_customer_id,
        ])->save();

        $tenant->activatePlan(
            $planKey,
            $this->fallbackSubscriptionEndsAt((string) data_get($session, 'metadata.billing_period', 'monthly')),
            'stripe',
            data_get($session, 'subscription')
        );

        $this->notifySubscriptionPurchased($tenant->fresh(), $planKey, data_get($session, 'subscription'));
    }

    protected function syncStripeSubscriptionById(string $subscriptionId, string $secretKey): void
    {
        try {
            $subscription = Http::withToken($secretKey)
                ->timeout(20)
                ->get("https://api.stripe.com/v1/subscriptions/{$subscriptionId}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible consultar la suscripción de Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        $this->handleCustomerSubscriptionUpdated($subscription);
    }

    protected function handleCustomerSubscriptionUpdated(array $subscription): void
    {
        $tenant = tenant();
        $planKey = (string) data_get($subscription, 'metadata.plan_key', $tenant->plan_key);
        $status = (string) data_get($subscription, 'status');
        $periodEnd = data_get($subscription, 'current_period_end');
        $endsAt = $periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : $tenant->subscription_ends_at;

        $tenant->forceFill([
            'payment_provider' => 'stripe',
            'provider_customer_id' => data_get($subscription, 'customer') ?: $tenant->provider_customer_id,
            'provider_subscription_id' => data_get($subscription, 'id') ?: $tenant->provider_subscription_id,
            'subscription_ends_at' => $endsAt,
            'data' => $this->tenantDataWithSubscriptionCancellation($tenant, $subscription),
        ])->save();

        if (in_array($status, ['active', 'trialing'], true)) {
            $tenant->activatePlan($planKey, $endsAt, 'stripe', data_get($subscription, 'id'));

            return;
        }

        if (in_array($status, ['past_due', 'unpaid', 'canceled', 'incomplete_expired'], true)) {
            $tenant->suspendSubscription($status === 'past_due' ? Tenant::STATUS_PAST_DUE : Tenant::STATUS_SUSPENDED);
        }
    }

    protected function handleCustomerSubscriptionDeleted(array $subscription): void
    {
        $tenant = tenant();

        $tenant->forceFill([
            'provider_subscription_id' => data_get($subscription, 'id') ?: $tenant->provider_subscription_id,
            'subscription_ends_at' => data_get($subscription, 'current_period_end')
                ? Carbon::createFromTimestamp((int) data_get($subscription, 'current_period_end'))
                : $tenant->subscription_ends_at,
            'data' => $this->tenantDataWithSubscriptionCancellation($tenant, $subscription),
        ])->save();

        $tenant->suspendSubscription(Tenant::STATUS_CANCELED);
    }

    protected function handleInvoicePaid(array $invoice): void
    {
        $tenant = tenant();
        $this->syncSubscriptionInvoice($tenant, $invoice, 'paid');

        $planKey = (string) data_get($invoice, 'subscription_details.metadata.plan_key', $tenant->plan_key);
        $periodEnd = data_get($invoice, 'lines.data.0.period.end');
        $subscriptionId = data_get($invoice, 'subscription')
            ?: data_get($invoice, 'parent.subscription_details.subscription')
            ?: $tenant->provider_subscription_id;

        $tenant->activatePlan(
            $planKey,
            $periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : now()->addMonth(),
            'stripe',
            $subscriptionId
        );

        $tenant->forceFill([
            'provider_customer_id' => data_get($invoice, 'customer') ?: $tenant->provider_customer_id,
        ])->save();

        $this->notifySubscriptionPurchased($tenant->fresh(), $planKey, $subscriptionId);
    }

    protected function handleInvoicePaymentFailed(array $invoice): void
    {
        $tenant = tenant();
        $this->syncSubscriptionInvoice($tenant, $invoice, 'payment_failed');

        $tenant->forceFill([
            'provider_customer_id' => data_get($invoice, 'customer') ?: $tenant->provider_customer_id,
            'provider_subscription_id' => data_get($invoice, 'subscription')
                ?: data_get($invoice, 'parent.subscription_details.subscription')
                ?: $tenant->provider_subscription_id,
        ])->save();

        $tenant->suspendSubscription(Tenant::STATUS_PAST_DUE);
    }

    protected function syncSubscriptionInvoice(Tenant $tenant, array $invoice, string $status): void
    {
        $invoiceId = data_get($invoice, 'id');

        if (blank($invoiceId)) {
            return;
        }

        $amount = data_get($invoice, 'amount_paid', data_get($invoice, 'amount_due', 0));
        $periodStart = data_get($invoice, 'lines.data.0.period.start');
        $periodEnd = data_get($invoice, 'lines.data.0.period.end');
        $subscriptionId = data_get($invoice, 'subscription')
            ?: data_get($invoice, 'parent.subscription_details.subscription')
            ?: $tenant->provider_subscription_id;

        SubscriptionPayment::updateOrCreate(
            [
                'provider' => 'stripe',
                'provider_invoice_id' => $invoiceId,
            ],
            [
                'tenant_id' => $tenant->id,
                'provider_subscription_id' => $subscriptionId,
                'provider_customer_id' => data_get($invoice, 'customer') ?: $tenant->provider_customer_id,
                'status' => $status,
                'amount' => round(((int) $amount) / 100, 2),
                'currency' => strtoupper((string) data_get($invoice, 'currency', 'mxn')),
                'period_start' => $periodStart ? Carbon::createFromTimestamp((int) $periodStart) : null,
                'period_end' => $periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : null,
                'paid_at' => $status === 'paid' ? now() : null,
                'hosted_invoice_url' => data_get($invoice, 'hosted_invoice_url'),
                'invoice_pdf' => data_get($invoice, 'invoice_pdf'),
                'provider_payload' => $invoice,
            ]
        );
    }

    protected function subscriptionBillingOption(array $plan, string $billingPeriod): array
    {
        $billingPeriod = in_array($billingPeriod, ['monthly', 'annual'], true) ? $billingPeriod : 'monthly';
        $options = $plan['billing_options'] ?? [];

        if (isset($options[$billingPeriod])) {
            return [
                'key' => $billingPeriod,
                'name' => $billingPeriod === 'annual' ? 'Anual' : 'Mensual',
                'label' => $options[$billingPeriod]['label'] ?? ($billingPeriod === 'annual' ? 'Anual' : 'Mensual'),
                'price' => (int) ($options[$billingPeriod]['price'] ?? 0),
                'currency' => $options[$billingPeriod]['currency'] ?? ($plan['currency'] ?? 'MXN'),
                'interval' => $options[$billingPeriod]['interval'] ?? ($billingPeriod === 'annual' ? 'year' : 'month'),
                'months_charged' => (int) ($options[$billingPeriod]['months_charged'] ?? ($billingPeriod === 'annual' ? 12 : 1)),
                'months_free' => (int) ($options[$billingPeriod]['months_free'] ?? 0),
                'savings_label' => $options[$billingPeriod]['savings_label'] ?? null,
            ];
        }

        return [
            'key' => 'monthly',
            'name' => 'Mensual',
            'label' => $plan['label'] ?? 'Mensual',
            'price' => (int) ($plan['price'] ?? 0),
            'currency' => $plan['currency'] ?? 'MXN',
            'interval' => $plan['interval'] ?? 'month',
            'months_charged' => 1,
            'months_free' => 0,
            'savings_label' => null,
        ];
    }

    protected function fallbackSubscriptionEndsAt(string $billingPeriod): Carbon
    {
        return $billingPeriod === 'annual' ? now()->addYear() : now()->addMonth();
    }

    protected function tenantDataWithSubscriptionCancellation(Tenant $tenant, array $subscription): array
    {
        $data = $tenant->data ?? [];

        $data['subscription_cancellation'] = [
            'cancel_at_period_end' => (bool) data_get($subscription, 'cancel_at_period_end', false),
            'cancel_at' => $this->timestampToIso(data_get($subscription, 'cancel_at')),
            'canceled_at' => $this->timestampToIso(data_get($subscription, 'canceled_at')),
            'current_period_end' => $this->timestampToIso(data_get($subscription, 'current_period_end')),
            'stripe_status' => data_get($subscription, 'status'),
        ];

        return $data;
    }

    protected function timestampToIso(mixed $timestamp): ?string
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp)->toISOString() : null;
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

        $this->orderNotificationService->sendPurchaseNotifications($order->fresh(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress', 'items', 'payments']));
    }

    protected function activeCheckoutSessionPayload(Order $order, string $secretKey, ?string $storefrontOrigin = null, ?string $stripeAccountId = null): ?array
    {
        if (blank($order->stripe_session_id)) {
            return null;
        }

        $stripeAccountId ??= $this->stripeAccountIdForOrder($order);

        try {
            $session = Http::withToken($secretKey)
                ->when($stripeAccountId, fn ($request) => $request->withHeaders([
                    'Stripe-Account' => $stripeAccountId,
                ]))
                ->timeout(20)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$order->stripe_session_id}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error.message', 'No fue posible consultar la sesión de Stripe.');
            throw new HttpException(422, $message, $exception);
        }

        if (data_get($session, 'payment_status') === 'paid') {
            $this->handleCheckoutSessionCompleted($session);

            abort(422, 'El pedido ya fue pagado.');
        }

        if (data_get($session, 'status') === 'open' && filled(data_get($session, 'url'))) {
            if (! $this->sessionMatchesStorefrontOrigin($session, $storefrontOrigin)) {
                $this->expireStripeSessionById((string) data_get($session, 'id'), $secretKey, $session, $stripeAccountId);

                return null;
            }

            $this->syncPendingPaymentFromSession($order, $session);

            return [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'stripe_session_id' => data_get($session, 'id'),
                'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
                'url' => data_get($session, 'url'),
                'payment_status' => $order->payment_status,
                'amount' => (float) $order->total,
                'currency' => strtolower($order->currency),
                'stripe_account_id' => $stripeAccountId,
                'charge_type' => $stripeAccountId ? 'direct' : 'platform',
                'reused' => true,
            ];
        }

        $this->markPaymentSessionExpired($order->stripe_session_id, $session);

        return null;
    }

    protected function expireStripeSessionById(string $sessionId, string $secretKey, ?array $session = null, ?string $stripeAccountId = null): void
    {
        Http::asForm()
            ->withToken($secretKey)
            ->when($stripeAccountId, fn ($request) => $request->withHeaders([
                'Stripe-Account' => $stripeAccountId,
            ]))
            ->timeout(10)
            ->post("https://api.stripe.com/v1/checkout/sessions/{$sessionId}/expire");

        $this->markPaymentSessionExpired($sessionId, $session);
    }

    protected function sessionMatchesStorefrontOrigin(array $session, ?string $storefrontOrigin = null): bool
    {
        if (blank($storefrontOrigin)) {
            return true;
        }

        $successUrl = (string) data_get($session, 'success_url');

        return str_starts_with($successUrl, rtrim($storefrontOrigin, '/') . '/');
    }

    protected function syncPendingPaymentFromSession(Order $order, array $session): void
    {
        $stripeAccountId = $this->stripeAccountIdForOrder($order);

        $order->forceFill([
            'stripe_session_id' => data_get($session, 'id'),
            'stripe_payment_intent_id' => data_get($session, 'payment_intent'),
            'payment_method' => 'stripe',
            'metadata' => $this->orderMetadataWithStripeAccount($order, $stripeAccountId),
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
                'provider_payload' => $this->providerPayloadWithStripeAccount($session, $stripeAccountId),
            ]
        );
    }

    protected function markPaymentSessionExpired(?string $sessionId, ?array $session = null): void
    {
        if (blank($sessionId)) {
            return;
        }

        $payment = Payment::query()
            ->where('provider', 'stripe')
            ->where('stripe_session_id', $sessionId)
            ->first();

        if (! $payment || $payment->status === Order::PAYMENT_PAID) {
            return;
        }

        $payload = $payment->provider_payload ?? [];

        if ($session) {
            $payload = array_replace_recursive($payload, $session);
        }

        $payment->forceFill([
            'status' => 'expired',
            'provider_payload' => $payload,
        ])->save();
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
            'provider_payload' => $this->providerPayloadWithStripeAccount(
                $data['payload'],
                $this->stripeAccountIdForOrder($order)
            ),
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

    protected function stripeMetadata(Order $order, ?string $stripeAccountId = null): array
    {
        $metadata = [
            'tenant_id' => (string) tenant('id'),
            'order_id' => (string) $order->id,
            'order_number' => $order->number,
            'user_id' => (string) $order->user_id,
            'guest_token' => (string) $order->guest_token,
            'checkout_mode' => $order->guest_token ? 'guest' : 'authenticated',
            'checkout_type' => 'store_order',
        ];

        if (filled($stripeAccountId)) {
            $metadata['stripe_account_id'] = $stripeAccountId;
            $metadata['charge_type'] = 'direct';
        }

        return $metadata;
    }

    protected function stripeAccountIdForStoreCheckout(): ?string
    {
        abort_unless(
            (bool) data_get(EcommerceSetting::paymentMethodSettings(), 'methods.stripe.enabled', false),
            422,
            $this->invalidStorePaymentMethodMessage()
        );

        $connectAccount = tenant()?->stripeAccount;

        if (! $connectAccount || ! $connectAccount->isReadyForCharges()) {
            abort(422, $this->invalidStorePaymentMethodMessage());
        }

        return $connectAccount->stripe_account_id;
    }

    protected function invalidStorePaymentMethodMessage(): string
    {
        return 'Esta tienda no cuenta con una configuración de método de pago válida, puedes comunicarte con su soporte.';
    }

    protected function stripeAccountIdForSession(string $sessionId): ?string
    {
        $payment = Payment::query()
            ->where('provider', 'stripe')
            ->where('stripe_session_id', $sessionId)
            ->first();

        if ($payment) {
            return data_get($payment->provider_payload, '_cloudishop_connect.stripe_account_id')
                ?: data_get($payment->provider_payload, 'metadata.stripe_account_id');
        }

        $order = Order::query()->where('stripe_session_id', $sessionId)->first();

        return $order ? $this->stripeAccountIdForOrder($order) : null;
    }

    protected function stripeAccountIdForOrder(Order $order): ?string
    {
        return data_get($order->metadata, 'stripe_connect.stripe_account_id')
            ?: data_get($order->metadata, 'stripe_account_id');
    }

    protected function orderMetadataWithStripeAccount(Order $order, ?string $stripeAccountId): array
    {
        $metadata = $order->metadata ?? [];

        if (filled($stripeAccountId)) {
            data_set($metadata, 'stripe_connect.stripe_account_id', $stripeAccountId);
            data_set($metadata, 'stripe_connect.charge_type', 'direct');
        }

        return $metadata;
    }

    protected function providerPayloadWithStripeAccount(array $payload, ?string $stripeAccountId): array
    {
        if (filled($stripeAccountId)) {
            $payload['_cloudishop_connect'] = [
                'stripe_account_id' => $stripeAccountId,
                'charge_type' => 'direct',
            ];
        }

        return $payload;
    }

    protected function resolveTenantFromWebhookEvent(array $event): Tenant
    {
        $object = data_get($event, 'data.object', []);
        $tenantId = data_get($object, 'metadata.tenant_id')
            ?: data_get($object, 'subscription_details.metadata.tenant_id')
            ?: data_get($object, 'parent.subscription_details.metadata.tenant_id');

        abort_if(blank($tenantId), 400, 'El evento de Stripe no incluye tenant_id.');

        $tenant = Tenant::query()->find($tenantId);

        abort_unless($tenant, 404, 'Tenant no encontrado para el evento de Stripe.');

        return $tenant;
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

    protected function successUrl(?string $storefrontOrigin = null): string
    {
        if (filled($storefrontOrigin)) {
            return rtrim($storefrontOrigin, '/') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}';
        }

        return (string) config('services.stripe.success_url');
    }

    protected function cancelUrl(Order $order, ?string $storefrontOrigin = null): string
    {
        $url = filled($storefrontOrigin)
            ? rtrim($storefrontOrigin, '/') . '/checkout/cancel'
            : (string) config('services.stripe.cancel_url');

        $separator = Str::contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query([
            'order_id' => $order->id,
            'order_number' => $order->number,
        ]);
    }

    protected function subscriptionSuccessUrl(?string $billingOrigin = null): string
    {
        if (filled($billingOrigin)) {
            return rtrim($billingOrigin, '/') . '/billing/success?session_id={CHECKOUT_SESSION_ID}';
        }

        return (string) config('services.stripe.subscription_success_url');
    }

    protected function subscriptionCancelUrl(?string $billingOrigin = null): string
    {
        if (filled($billingOrigin)) {
            return rtrim($billingOrigin, '/') . '/billing/cancel';
        }

        return (string) config('services.stripe.subscription_cancel_url');
    }

    protected function notifySubscriptionPurchased(Tenant $tenant, string $planKey, mixed $subscriptionId = null): void
    {
        $notificationKey = (string) ($subscriptionId ?: $planKey.'-'.$tenant->subscription_ends_at?->timestamp);

        if (blank($notificationKey) || data_get($tenant->data, 'notifications.subscription_purchase_key') === $notificationKey) {
            return;
        }

        $planName = (string) data_get(config("plans.plans.{$planKey}", []), 'name', $planKey);

        $this->tenantNotifications->sendToTenantOwner(
            $tenant,
            'Tu suscripcion de Cloudi Shop esta activa',
            'Suscripcion activa',
            "Tu compra del plan {$planName} se proceso correctamente. Tu tienda ya cuenta con los beneficios del plan.",
            null,
            null,
            [
                'Plan' => $planName,
                'Vigencia' => $tenant->subscription_ends_at?->format('d/m/Y') ?: 'Sin fecha de termino',
            ],
        );

        $data = $tenant->data ?: [];
        data_set($data, 'notifications.subscription_purchase_key', $notificationKey);
        data_set($data, 'notifications.subscription_purchase_sent_at', now()->toISOString());

        $tenant->forceFill(['data' => $data])->save();
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
