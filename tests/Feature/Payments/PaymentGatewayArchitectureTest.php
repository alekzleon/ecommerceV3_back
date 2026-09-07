<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentConnectionStatus;
use App\Exceptions\Payments\PaymentProviderNotConfiguredException;
use App\Exceptions\Payments\PaymentProviderNotSupportedException;
use App\Http\Resources\Payments\PaymentProviderConnectionResource;
use App\Models\Tenant;
use App\Models\TenantPaymentConnection;
use App\Models\PlatformPaymentFeeSetting;
use App\Services\Payments\Gateways\MercadoPagoGateway;
use App\Services\Payments\MarketplaceFeeCalculator;
use App\Services\Payments\MercadoPagoOAuthService;
use App\Services\Payments\MercadoPagoPaymentStatusMapper;
use App\Services\Payments\MercadoPagoWebhookSignatureValidator;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentGatewayArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('cache.default', 'array');
        Http::swap(new HttpFactory());
        config()->set('services.mercadopago', [
            'client_id' => 'mp-client-id',
            'client_secret' => 'mp-client-secret',
            'redirect_uri' => 'https://api.cloudishop.test/api/v1/oauth/mercadopago/callback',
            'oauth_success_url' => 'https://app.cloudishop.test/settings/payments',
            'oauth_error_url' => 'https://app.cloudishop.test/settings/payments',
            'oauth_state_ttl_seconds' => 600,
            'connect_timeout' => 10,
            'timeout' => 20,
            'webhook_secret' => 'mp-webhook-secret',
        ]);

        Schema::dropIfExists('tenant_payment_connections');
        Schema::dropIfExists('platform_payment_fee_settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('ecommerce_settings');
        Schema::dropIfExists('tenants');

        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('plan_key')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_payment_connections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('provider', 40);
            $table->string('provider_account_id')->nullable();
            $table->longText('credentials')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 40);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'provider']);
        });

        Schema::create('platform_payment_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('plan_key', 80)->default('*');
            $table->string('fee_type', 20)->default('none');
            $table->decimal('fee_value', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['provider', 'plan_key']);
        });

        Schema::create('ecommerce_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('number')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('currency')->default('MXN');
            $table->decimal('shipping', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name_snapshot');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('status', 40)->default('pending');
            $table->string('payment_method', 40)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_status', 60)->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->string('currency', 10)->default('MXN');
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
            $table->unique(['provider', 'provider_payment_id']);
        });
    }

    public function test_tenant_can_have_a_mercado_pago_connection(): void
    {
        $tenant = $this->createTenant('tienda-mp');
        $connection = $tenant->paymentConnections()->create($this->mercadoPagoConnectionData());

        $this->assertSame($tenant->id, $connection->tenant_id);
        $this->assertSame(TenantPaymentConnection::PROVIDER_MERCADOPAGO, $connection->provider);
        $this->assertTrue($connection->isConnected());
    }

    public function test_tenant_cannot_have_two_connections_for_the_same_provider(): void
    {
        $tenant = $this->createTenant('tienda-unica');
        $tenant->paymentConnections()->create($this->mercadoPagoConnectionData());

        $this->expectException(QueryException::class);

        $tenant->paymentConnections()->create($this->mercadoPagoConnectionData());
    }

    public function test_different_tenants_can_each_have_a_mercado_pago_connection(): void
    {
        $first = $this->createTenant('tienda-uno');
        $second = $this->createTenant('tienda-dos');

        $first->paymentConnections()->create($this->mercadoPagoConnectionData());
        $second->paymentConnections()->create($this->mercadoPagoConnectionData());

        $this->assertSame(2, TenantPaymentConnection::query()
            ->where('provider', TenantPaymentConnection::PROVIDER_MERCADOPAGO)
            ->count());
    }

    public function test_credentials_are_encrypted_and_hidden_from_serialization(): void
    {
        $tenant = $this->createTenant('tienda-segura');
        $connection = $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'credentials' => ['access_token' => 'APP_USR-secret-token'],
        ]));

        $storedCredentials = DB::table('tenant_payment_connections')
            ->where('id', $connection->id)
            ->value('credentials');

        $this->assertStringNotContainsString('APP_USR-secret-token', $storedCredentials);
        $this->assertSame(['access_token' => 'APP_USR-secret-token'], $connection->fresh()->credentials);
        $this->assertArrayNotHasKey('credentials', $connection->fresh()->toArray());
    }

    public function test_factory_resolves_mercado_pago_gateway(): void
    {
        $tenant = $this->createTenant('tienda-factory');
        $tenant->paymentConnections()->create($this->mercadoPagoConnectionData());

        $gateway = app(PaymentGatewayFactory::class)->make($tenant, 'mercadopago');

        $this->assertInstanceOf(MercadoPagoGateway::class, $gateway);
        $this->assertSame('mercadopago', $gateway->provider());
    }

    public function test_factory_rejects_unknown_provider_with_controlled_exception(): void
    {
        $tenant = $this->createTenant('tienda-no-soportada');

        $this->expectException(PaymentProviderNotSupportedException::class);

        app(PaymentGatewayFactory::class)->make($tenant, 'unknown-provider');
    }

    public function test_mercado_pago_gateway_detects_missing_connection(): void
    {
        $tenant = $this->createTenant('tienda-sin-mp');
        $gateway = app(PaymentGatewayFactory::class)->make($tenant, 'mercadopago');

        $this->expectException(PaymentProviderNotConfiguredException::class);

        $gateway->connection();
    }

    public function test_oauth_authorization_url_uses_pkce_and_keeps_verifier_server_side(): void
    {
        $tenant = $this->createTenant('tienda-oauth-url');
        $returnUrl = 'https://tienda-oauth-url.cloudishop.test/settings/payments';
        $url = $this->oauth()->createAuthorizationUrl($tenant, 42, $returnUrl);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('https://auth.mercadopago.com/authorization', strtok($url, '?'));
        $this->assertSame('mp-client-id', $query['client_id']);
        $this->assertSame('https://api.cloudishop.test/api/v1/oauth/mercadopago/callback', $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('code_challenge', $query);
        $this->assertArrayNotHasKey('code_verifier', $query);
        $this->assertStringNotContainsString('code_verifier', $url);
        $this->assertTrue($this->oauth()->hasPendingState($query['state']));
        $this->assertSame($returnUrl, $this->oauth()->returnUrlForState($query['state']));
    }

    public function test_valid_oauth_callback_exchanges_code_and_persists_encrypted_connection(): void
    {
        $tenant = $this->createTenant('tienda-oauth-callback');
        $state = $this->authorizationState($tenant);

        Http::fake([
            'https://api.mercadopago.com/oauth/token' => Http::response($this->tokenResponse(), 200),
        ]);

        $connection = $this->oauth()->completeConnection($state, 'authorization-code');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.mercadopago.com/oauth/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['redirect_uri'] === 'https://api.cloudishop.test/api/v1/oauth/mercadopago/callback'
                && filled($request['code_verifier']);
        });
        $this->assertSame($tenant->id, $connection->tenant_id);
        $this->assertSame('998877', $connection->provider_account_id);
        $this->assertSame(now()->addSeconds(3600)->timestamp, $connection->expires_at->timestamp);
        $this->assertSame('APP_USR-access-token', $connection->fresh()->credentials['access_token']);
        $this->assertArrayNotHasKey('credentials', $connection->fresh()->toArray());
        $this->assertFalse($this->oauth()->hasPendingState($state));
    }

    public function test_invalid_or_reused_state_does_not_create_connection(): void
    {
        $tenant = $this->createTenant('tienda-oauth-invalid');
        Http::fake(['https://api.mercadopago.com/oauth/token' => Http::response($this->tokenResponse(), 200)]);

        try {
            $this->oauth()->completeConnection(str_repeat('a', 64), 'authorization-code');
            $this->fail('Expected invalid OAuth state to fail.');
        } catch (\App\Exceptions\Payments\MercadoPagoOAuthException $exception) {
            $this->assertSame('invalid_state', $exception->reason());
        }

        $state = $this->authorizationState($tenant);
        $this->oauth()->completeConnection($state, 'authorization-code');

        try {
            $this->oauth()->completeConnection($state, 'authorization-code');
            $this->fail('Expected reused OAuth state to fail.');
        } catch (\App\Exceptions\Payments\MercadoPagoOAuthException $exception) {
            $this->assertSame('invalid_state', $exception->reason());
        }

        $this->assertSame(1, TenantPaymentConnection::query()->count());
    }

    public function test_failed_token_exchange_or_missing_access_token_never_creates_connection(): void
    {
        $tenant = $this->createTenant('tienda-oauth-failure');
        $state = $this->authorizationState($tenant);
        Http::fakeSequence('https://api.mercadopago.com/oauth/token')
            ->push(['error' => 'invalid_grant'], 400)
            ->push(['user_id' => 998877], 200);

        try {
            $this->oauth()->completeConnection($state, 'authorization-code');
            $this->fail('Expected Mercado Pago token error to fail.');
        } catch (\App\Exceptions\Payments\MercadoPagoOAuthException $exception) {
            $this->assertSame('oauth_failed', $exception->reason());
        }

        $state = $this->authorizationState($tenant);

        try {
            $this->oauth()->completeConnection($state, 'authorization-code');
            $this->fail('Expected response without access token to fail.');
        } catch (\App\Exceptions\Payments\MercadoPagoOAuthException $exception) {
            $this->assertSame('oauth_invalid_response', $exception->reason());
        }

        $this->assertSame(0, TenantPaymentConnection::query()->count());
    }

    public function test_reconnection_updates_existing_connection_without_duplicates_and_keeps_tenants_isolated(): void
    {
        $first = $this->createTenant('tienda-oauth-uno');
        $second = $this->createTenant('tienda-oauth-dos');
        Http::fakeSequence('https://api.mercadopago.com/oauth/token')
            ->push($this->tokenResponse(), 200)
            ->push($this->tokenResponse(), 200)
            ->push($this->tokenResponse([
                'access_token' => 'APP_USR-new-token',
                'user_id' => 112233,
            ]), 200);

        $firstState = $this->authorizationState($first);
        $firstConnection = $this->oauth()->completeConnection($firstState, 'first-code');
        $secondState = $this->authorizationState($second);
        $this->oauth()->completeConnection($secondState, 'second-code');

        $reconnectState = $this->authorizationState($first);
        $reconnected = $this->oauth()->completeConnection($reconnectState, 'reconnect-code');

        $this->assertSame($firstConnection->id, $reconnected->id);
        $this->assertSame('112233', $reconnected->provider_account_id);
        $this->assertSame('APP_USR-new-token', $reconnected->fresh()->credentials['access_token']);
        $this->assertSame(1, TenantPaymentConnection::query()->where('tenant_id', $first->id)->count());
        $this->assertSame(1, TenantPaymentConnection::query()->where('tenant_id', $second->id)->count());
    }

    public function test_connection_resource_has_a_stable_public_contract_without_credentials(): void
    {
        $tenant = $this->createTenant('tienda-publica');
        $connection = $tenant->paymentConnections()->create($this->mercadoPagoConnectionData());

        $payload = (new PaymentProviderConnectionResource($connection))->resolve();

        $this->assertTrue($payload['connected']);
        $this->assertSame('connected', $payload['status']);
        $this->assertArrayNotHasKey('credentials', $payload);
        $this->assertArrayNotHasKey('access_token', $payload);
        $this->assertArrayNotHasKey('refresh_token', $payload);
    }

    public function test_local_disconnect_clears_credentials_and_is_idempotent(): void
    {
        $tenant = $this->createTenant('tienda-disconnect');
        $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'expires_at' => now()->addDays(30),
        ]));

        $disconnected = $this->oauth()->disconnect($tenant);
        $secondAttempt = $this->oauth()->disconnect($tenant);

        $this->assertSame(PaymentConnectionStatus::Disconnected, $disconnected->status);
        $this->assertNull($disconnected->credentials);
        $this->assertNull($disconnected->expires_at);
        $this->assertNull($disconnected->provider_account_id);
        $this->assertSame(PaymentConnectionStatus::Disconnected, $secondAttempt->status);
    }

    public function test_valid_access_token_skips_refresh_when_expiry_is_far_away(): void
    {
        $tenant = $this->createTenant('tienda-token-vigente');
        $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'expires_at' => now()->addDays(8),
        ]));
        Http::fake();

        $token = $this->oauth()->getValidAccessToken($tenant);

        $this->assertSame('APP_USR-example-token', $token);
        Http::assertNothingSent();
    }

    public function test_lazy_refresh_rotates_both_tokens_and_updates_expiration(): void
    {
        $tenant = $this->createTenant('tienda-refresh');
        $connection = $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'credentials' => [
                'access_token' => 'APP_USR-old-token',
                'refresh_token' => 'TG-old-refresh-token',
            ],
            'expires_at' => now()->addDays(2),
        ]));
        Http::fake(['https://api.mercadopago.com/oauth/token' => Http::response($this->tokenResponse([
            'access_token' => 'APP_USR-new-token',
            'refresh_token' => 'TG-new-refresh-token',
            'expires_in' => 7200,
        ]), 200)]);

        $token = $this->oauth()->getValidAccessToken($tenant);
        $fresh = $connection->fresh();

        Http::assertSent(fn (Request $request) => $request['grant_type'] === 'refresh_token'
            && $request['client_id'] === 'mp-client-id'
            && $request['refresh_token'] === 'TG-old-refresh-token');
        $this->assertSame('APP_USR-new-token', $token);
        $this->assertSame('TG-new-refresh-token', $fresh->credentials['refresh_token']);
        $this->assertSame(now()->addSeconds(7200)->timestamp, $fresh->expires_at->timestamp);
        $this->assertSame(PaymentConnectionStatus::Connected, $fresh->status);
    }

    public function test_temporary_refresh_failure_keeps_connection_connected(): void
    {
        $tenant = $this->createTenant('tienda-refresh-errors');
        $connection = $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'expires_at' => now()->addDay(),
            'credentials' => [
                'access_token' => 'APP_USR-example-token',
                'refresh_token' => 'TG-refresh-token',
            ],
        ]));
        Http::fake(['https://api.mercadopago.com/oauth/token' => Http::response([], 500)]);

        try {
            $this->oauth()->getValidAccessToken($tenant);
            $this->fail('Expected temporary refresh failure.');
        } catch (\App\Exceptions\Payments\MercadoPagoOAuthException $exception) {
            $this->assertSame('refresh_unavailable', $exception->reason());
        }
        $this->assertSame(PaymentConnectionStatus::Connected, $connection->fresh()->status);

    }

    public function test_invalid_grant_marks_connection_as_reauthorization_required(): void
    {
        $tenant = $this->createTenant('tienda-refresh-invalid-grant');
        $connection = $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'expires_at' => now()->addDay(),
            'credentials' => [
                'access_token' => 'APP_USR-example-token',
                'refresh_token' => 'TG-refresh-token',
            ],
        ]));
        Http::fake(['https://api.mercadopago.com/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            $this->oauth()->getValidAccessToken($tenant);
            $this->fail('Expected invalid refresh token to require OAuth again.');
        } catch (\App\Exceptions\Payments\MercadoPagoOAuthException $exception) {
            $this->assertSame('reauthorization_required', $exception->reason());
        }
        $this->assertSame(PaymentConnectionStatus::ReauthorizationRequired, $connection->fresh()->status);
    }

    public function test_refresh_command_processes_due_connections_and_skips_the_rest(): void
    {
        $dueTenant = $this->createTenant('tienda-command-due');
        $farTenant = $this->createTenant('tienda-command-far');
        $dueTenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'credentials' => [
                'access_token' => 'APP_USR-old-token',
                'refresh_token' => 'TG-refresh-token',
            ],
            'expires_at' => now()->addDay(),
        ]));
        $farTenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'expires_at' => now()->addDays(30),
        ]));
        Http::fake(['https://api.mercadopago.com/oauth/token' => Http::response($this->tokenResponse([
            'refresh_token' => 'TG-rotated-token',
        ]), 200)]);

        $this->artisan('payments:refresh-tokens')
            ->expectsOutput('Processed: 2')
            ->assertExitCode(0);

        $this->assertSame('TG-rotated-token', $dueTenant->paymentConnections()->first()->credentials['refresh_token']);
    }

    public function test_mercado_pago_preference_uses_order_data_and_is_idempotent(): void
    {
        $tenant = $this->createTenant('tienda-checkout-mp');
        $tenant->paymentConnections()->create($this->mercadoPagoConnectionData([
            'credentials' => ['access_token' => 'APP_USR-tenant-token'],
        ]));
        \App\Models\EcommerceSetting::setValue('payment_methods', [
            'methods' => ['mercadopago' => ['enabled' => true]],
        ]);
        $order = \App\Models\Order::query()->create([
            'number' => 'ORD-100',
            'status' => \App\Models\Order::STATUS_PENDING_PAYMENT,
            'payment_status' => \App\Models\Order::PAYMENT_PENDING,
            'currency' => 'MXN',
            'shipping' => 10,
            'total' => 17.45,
        ]);
        $order->items()->create([
            'product_id' => 123,
            'name_snapshot' => 'Zapato negro',
            'quantity' => 2,
            'unit_price' => 799,
            'line_total' => 1598,
        ]);
        Http::fake(['https://api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'PREF-123',
            'init_point' => 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=PREF-123',
        ], 201)]);

        $gateway = app(PaymentGatewayFactory::class)->make($tenant, 'mercadopago');
        $first = $gateway->createPreference($order, 'https://tienda.cloudishop.test');
        $second = $gateway->createPreference($order->fresh(), 'https://tienda.cloudishop.test');

        $this->assertSame('PREF-123', $first['preference_id']);
        $this->assertFalse($first['reused']);
        $this->assertTrue($second['reused']);
        $this->assertSame(1, \App\Models\Payment::query()->count());
        $this->assertSame(\App\Models\Order::PAYMENT_PENDING, $order->fresh()->payment_status);
        $this->assertStringStartsWith('cloudishop_order_', \App\Models\Payment::query()->first()->external_reference);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && $request['auto_return'] === 'approved'
            && $request['back_urls']['success'] === 'https://tienda.cloudishop.test/checkout/payment/success'
            && $request['items'][0]['quantity'] === 1
            && $request['items'][0]['unit_price'] === 7.45
            && $request['items'][1]['unit_price'] === 10.0);
    }

    public function test_mercado_pago_webhook_signature_is_validated_from_its_manifest(): void
    {
        $timestamp = '1710000000';
        $manifest = 'id:payment-123;request-id:request-abc;ts:'.$timestamp.';';
        $signature = 'ts='.$timestamp.',v1='.hash_hmac('sha256', $manifest, 'mp-webhook-secret');
        $request = \Illuminate\Http\Request::create('/api/v1/webhooks/mercadopago?data.id=PAYMENT-123', 'POST');
        $request->headers->set('x-request-id', 'request-abc');
        $request->headers->set('x-signature', $signature);

        $this->assertTrue(app(MercadoPagoWebhookSignatureValidator::class)->isValid($request));

        $request->headers->set('x-signature', 'ts='.$timestamp.',v1=invalid');
        $this->assertFalse(app(MercadoPagoWebhookSignatureValidator::class)->isValid($request));
    }

    public function test_mercado_pago_statuses_have_a_stable_internal_mapping(): void
    {
        $statuses = app(MercadoPagoPaymentStatusMapper::class);

        $this->assertSame('paid', $statuses->map('approved'));
        $this->assertSame('pending', $statuses->map('in_process'));
        $this->assertSame('failed', $statuses->map('rejected'));
        $this->assertSame('chargeback', $statuses->map('charged_back'));
        $this->assertSame('pending', $statuses->map('unknown_status'));
    }

    public function test_marketplace_fee_defaults_to_zero_and_calculates_percentages_in_cents(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::query()->create(['id' => 'tienda-comision', 'plan_key' => 'shop']));
        $order = \App\Models\Order::query()->create(['total' => '1000.00']);
        $calculator = app(MarketplaceFeeCalculator::class);

        $this->assertSame(0, $calculator->calculateCents($tenant, $order, 'mercadopago'));

        PlatformPaymentFeeSetting::query()->create([
            'provider' => 'mercadopago',
            'plan_key' => 'shop',
            'fee_type' => PlatformPaymentFeeSetting::TYPE_PERCENTAGE,
            'fee_value' => '0.5000',
            'is_active' => true,
        ]);

        $this->assertSame(500, $calculator->calculateCents($tenant, $order, 'mercadopago'));
        $this->assertSame('5.00', $calculator->calculate($tenant, $order, 'mercadopago'));
    }

    private function createTenant(string $id): Tenant
    {
        return Tenant::withoutEvents(fn () => Tenant::query()->create(['id' => $id]));
    }

    private function mercadoPagoConnectionData(array $overrides = []): array
    {
        return array_replace([
            'provider' => TenantPaymentConnection::PROVIDER_MERCADOPAGO,
            'provider_account_id' => 'mp-account-123',
            'credentials' => ['access_token' => 'APP_USR-example-token'],
            'metadata' => ['country' => 'MX'],
            'status' => PaymentConnectionStatus::Connected,
            'connected_at' => now(),
        ], $overrides);
    }

    private function oauth(): MercadoPagoOAuthService
    {
        return app(MercadoPagoOAuthService::class);
    }

    private function authorizationState(Tenant $tenant): string
    {
        $url = $this->oauth()->createAuthorizationUrl($tenant, 42);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['state'];
    }

    private function tokenResponse(array $overrides = []): array
    {
        return array_replace([
            'access_token' => 'APP_USR-access-token',
            'refresh_token' => 'TG-refresh-token',
            'public_key' => 'APP_USR-public-key',
            'user_id' => 998877,
            'expires_in' => 3600,
            'scope' => 'read write offline_access',
            'token_type' => 'bearer',
        ], $overrides);
    }
}
