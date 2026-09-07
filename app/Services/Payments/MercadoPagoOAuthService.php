<?php

namespace App\Services\Payments;

use App\Enums\PaymentConnectionStatus;
use App\Exceptions\Payments\MercadoPagoOAuthException;
use App\Exceptions\Payments\PaymentProviderNotConfiguredException;
use App\Models\Tenant;
use App\Models\TenantPaymentConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class MercadoPagoOAuthService
{
    private const AUTHORIZATION_URL = 'https://auth.mercadopago.com/authorization';
    private const TOKEN_URL = 'https://api.mercadopago.com/oauth/token';
    private const PROVIDER = TenantPaymentConnection::PROVIDER_MERCADOPAGO;

    /**
     * Creates a one-time OAuth flow stored in the central cache.
     * The returned URL deliberately exposes only the PKCE challenge, never the verifier.
     */
    public function createAuthorizationUrl(Tenant $tenant, int|string $userId, ?string $returnUrl = null): string
    {
        $clientId = $this->requiredConfig('client_id');
        $redirectUri = $this->requiredConfig('redirect_uri');
        $state = $this->randomBase64Url(48);
        $verifier = $this->randomBase64Url(64);
        $ttl = max(60, (int) config('services.mercadopago.oauth_state_ttl_seconds', 600));

        $this->centralCachePut($state, [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $userId,
            'provider' => self::PROVIDER,
            'code_verifier' => $verifier,
            'return_url' => $returnUrl,
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
        ], now()->addSeconds($ttl));

        return self::AUTHORIZATION_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'platform_id' => 'mp',
            'scope' => 'offline_access',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $this->codeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function completeConnection(string $state, string $code): TenantPaymentConnection
    {
        if (blank($code)) {
            throw new MercadoPagoOAuthException('missing_code', 'No se recibió el código de autorización de Mercado Pago.');
        }

        $flow = $this->consumeState($state);
        $tenant = Tenant::query()->find($flow['tenant_id']);

        if (! $tenant) {
            throw new MercadoPagoOAuthException('invalid_state', 'El flujo de conexión ya no es válido.');
        }

        $token = $this->exchangeAuthorizationCode($code, $flow['code_verifier']);

        return $this->persistConnection($tenant, $token);
    }

    public function discardState(?string $state): void
    {
        if (filled($state)) {
            $this->centralCachePull($state);
        }
    }

    public function hasPendingState(string $state): bool
    {
        return $this->centralCacheHas($state);
    }

    public function returnUrlForState(?string $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        $flow = $this->centralCacheGet($state);
        $returnUrl = data_get($flow, 'return_url');

        return is_string($returnUrl) && filter_var($returnUrl, FILTER_VALIDATE_URL)
            ? $returnUrl
            : null;
    }

    public function getValidAccessToken(Tenant $tenant): string
    {
        $connection = $tenant->paymentConnections()
            ->where('provider', self::PROVIDER)
            ->first();

        if (! $connection || $connection->status !== PaymentConnectionStatus::Connected) {
            throw new PaymentProviderNotConfiguredException('Mercado Pago no está conectado para esta tienda.');
        }

        $connection = $this->refreshConnectionIfNeeded($connection);
        $accessToken = data_get($connection->credentials, 'access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new PaymentProviderNotConfiguredException('Mercado Pago no tiene credenciales válidas para esta tienda.');
        }

        return $accessToken;
    }

    public function refreshConnectionIfNeeded(TenantPaymentConnection $connection): TenantPaymentConnection
    {
        if ($connection->status !== PaymentConnectionStatus::Connected || ! $this->shouldRefresh($connection)) {
            return $connection;
        }

        return $this->withRefreshLock($connection, function (TenantPaymentConnection $freshConnection) {
            if (! $this->shouldRefresh($freshConnection)) {
                return $freshConnection;
            }

            return $this->refreshConnection($freshConnection);
        });
    }

    public function refreshDueConnection(TenantPaymentConnection $connection): TenantPaymentConnection
    {
        return $this->refreshConnectionIfNeeded($connection);
    }

    public function shouldRefresh(TenantPaymentConnection $connection): bool
    {
        if ($connection->status !== PaymentConnectionStatus::Connected || ! $connection->expires_at) {
            return false;
        }

        return $connection->expires_at->lte(now()->addDays((int) config('payment_providers.mercadopago.refresh_before_days', 7)));
    }

    public function disconnect(Tenant $tenant): ?TenantPaymentConnection
    {
        $connection = $tenant->paymentConnections()
            ->where('provider', self::PROVIDER)
            ->first();

        if (! $connection) {
            return null;
        }

        $connection->forceFill([
            'provider_account_id' => null,
            'credentials' => null,
            'metadata' => null,
            'status' => PaymentConnectionStatus::Disconnected,
            'connected_at' => null,
            'expires_at' => null,
        ])->save();

        return $connection->fresh();
    }

    protected function consumeState(string $state): array
    {
        if (! is_string($state) || strlen($state) < 43) {
            throw new MercadoPagoOAuthException('invalid_state', 'El flujo de conexión no es válido.');
        }

        $flow = $this->centralCachePull($state);

        if (! is_array($flow)
            || data_get($flow, 'provider') !== self::PROVIDER
            || blank(data_get($flow, 'tenant_id'))
            || blank(data_get($flow, 'code_verifier'))
            || Carbon::parse((string) data_get($flow, 'expires_at'))->isPast()) {
            throw new MercadoPagoOAuthException('invalid_state', 'El flujo de conexión no es válido o expiró.');
        }

        return $flow;
    }

    protected function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('services.mercadopago.connect_timeout', 10))
                ->timeout((int) config('services.mercadopago.timeout', 20))
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->requiredConfig('client_id'),
                    'client_secret' => $this->requiredConfig('client_secret'),
                    'code' => $code,
                    'redirect_uri' => $this->requiredConfig('redirect_uri'),
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (ConnectionException) {
            throw new MercadoPagoOAuthException('oauth_unavailable', 'Mercado Pago no respondió al intentar conectar la cuenta.');
        }

        if (! $response->successful()) {
            throw new MercadoPagoOAuthException('oauth_failed', 'Mercado Pago rechazó la conexión de la cuenta.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ! filled(data_get($payload, 'access_token'))) {
            throw new MercadoPagoOAuthException('oauth_invalid_response', 'Mercado Pago no devolvió credenciales válidas.');
        }

        return $payload;
    }

    protected function persistConnection(Tenant $tenant, array $token): TenantPaymentConnection
    {
        $credentials = array_filter([
            'access_token' => data_get($token, 'access_token'),
            'refresh_token' => data_get($token, 'refresh_token'),
            'public_key' => data_get($token, 'public_key'),
        ], fn ($value) => filled($value));

        $expiresIn = data_get($token, 'expires_in');
        $expiresAt = is_numeric($expiresIn) && (int) $expiresIn > 0
            ? now()->addSeconds((int) $expiresIn)
            : null;
        $providerAccountId = data_get($token, 'user_id');

        return $tenant->paymentConnections()->updateOrCreate(
            ['provider' => self::PROVIDER],
            [
                'provider_account_id' => $providerAccountId !== null ? (string) $providerAccountId : null,
                'credentials' => $credentials,
                'metadata' => array_filter([
                    'scope' => data_get($token, 'scope'),
                    'token_type' => data_get($token, 'token_type'),
                    'mercadopago_user_id' => $providerAccountId !== null ? (string) $providerAccountId : null,
                ], fn ($value) => filled($value)),
                'status' => PaymentConnectionStatus::Connected,
                'connected_at' => now(),
                'expires_at' => $expiresAt,
            ]
        );
    }

    protected function withRefreshLock(TenantPaymentConnection $connection, callable $callback): TenantPaymentConnection
    {
        return $this->inCentralContext(function () use ($connection, $callback) {
            $lock = Cache::lock('payment-oauth:mercadopago:refresh:'.$connection->tenant_id, 30);

            if (! $lock->get()) {
                throw new MercadoPagoOAuthException('refresh_in_progress', 'La renovación de Mercado Pago ya está en proceso.');
            }

            try {
                $freshConnection = TenantPaymentConnection::query()->find($connection->id);

                if (! $freshConnection || $freshConnection->status !== PaymentConnectionStatus::Connected) {
                    throw new PaymentProviderNotConfiguredException('Mercado Pago no está conectado para esta tienda.');
                }

                return $callback($freshConnection);
            } finally {
                $lock->release();
            }
        });
    }

    protected function refreshConnection(TenantPaymentConnection $connection): TenantPaymentConnection
    {
        $refreshToken = data_get($connection->credentials, 'refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            $connection->forceFill(['status' => PaymentConnectionStatus::ReauthorizationRequired])->save();
            throw new MercadoPagoOAuthException('reauthorization_required', 'Mercado Pago requiere autorizar nuevamente la cuenta.');
        }

        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('services.mercadopago.connect_timeout', 10))
                ->timeout((int) config('services.mercadopago.timeout', 20))
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->requiredConfig('client_id'),
                    'client_secret' => $this->requiredConfig('client_secret'),
                    'refresh_token' => $refreshToken,
                ]);
        } catch (ConnectionException) {
            throw new MercadoPagoOAuthException('refresh_unavailable', 'Mercado Pago no respondió al renovar la conexión.');
        }

        if (! $response->successful()) {
            $error = (string) data_get($response->json(), 'error');

            if (in_array($error, ['invalid_grant', 'invalid_token'], true)) {
                $connection->forceFill(['status' => PaymentConnectionStatus::ReauthorizationRequired])->save();
                throw new MercadoPagoOAuthException('reauthorization_required', 'Mercado Pago requiere autorizar nuevamente la cuenta.');
            }

            if ($response->serverError()) {
                throw new MercadoPagoOAuthException('refresh_unavailable', 'Mercado Pago no pudo renovar la conexión.');
            }

            throw new MercadoPagoOAuthException('refresh_failed', 'Mercado Pago rechazó la renovación de la conexión.');
        }

        $token = $response->json();

        if (! is_array($token)
            || ! filled(data_get($token, 'access_token'))
            || ! filled(data_get($token, 'refresh_token'))
            || ! is_numeric(data_get($token, 'expires_in'))) {
            throw new MercadoPagoOAuthException('refresh_invalid_response', 'Mercado Pago no devolvió credenciales renovadas válidas.');
        }

        return $connection->getConnection()->transaction(function () use ($connection, $token) {
            $connection->refresh();
            $credentials = $connection->credentials ?? [];
            $credentials['access_token'] = data_get($token, 'access_token');
            $credentials['refresh_token'] = data_get($token, 'refresh_token');

            if (filled(data_get($token, 'public_key'))) {
                $credentials['public_key'] = data_get($token, 'public_key');
            }

            $metadata = $connection->metadata ?? [];
            foreach (['scope', 'token_type'] as $field) {
                if (filled(data_get($token, $field))) {
                    $metadata[$field] = data_get($token, $field);
                }
            }

            $connection->forceFill([
                'credentials' => $credentials,
                'metadata' => $metadata,
                'status' => PaymentConnectionStatus::Connected,
                'expires_at' => now()->addSeconds((int) data_get($token, 'expires_in')),
            ])->save();

            return $connection->fresh();
        });
    }

    protected function requiredConfig(string $key): string
    {
        $value = config("services.mercadopago.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new MercadoPagoOAuthException('configuration_invalid', 'La configuración de Mercado Pago no es válida.');
        }

        return $value;
    }

    protected function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    protected function randomBase64Url(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    protected function cacheKey(string $state): string
    {
        return 'payment-oauth:mercadopago:'.hash('sha256', $state);
    }

    protected function centralCachePut(string $state, array $flow, \DateTimeInterface $expiresAt): void
    {
        $this->inCentralContext(fn () => Cache::put($this->cacheKey($state), $flow, $expiresAt));
    }

    protected function centralCachePull(string $state): mixed
    {
        return $this->inCentralContext(fn () => Cache::pull($this->cacheKey($state)));
    }

    protected function centralCacheGet(string $state): mixed
    {
        return $this->inCentralContext(fn () => Cache::get($this->cacheKey($state)));
    }

    protected function centralCacheHas(string $state): bool
    {
        return $this->inCentralContext(fn () => Cache::has($this->cacheKey($state)));
    }

    protected function inCentralContext(callable $callback): mixed
    {
        return tenancy()->initialized ? tenancy()->central($callback) : $callback();
    }
}
