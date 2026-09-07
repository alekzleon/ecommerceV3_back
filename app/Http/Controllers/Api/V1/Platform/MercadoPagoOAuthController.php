<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Exceptions\Payments\MercadoPagoOAuthException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payments\PaymentProviderConnectionResource;
use App\Enums\PaymentConnectionStatus;
use App\Models\TenantPaymentConnection;
use App\Services\Payments\MercadoPagoOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoOAuthController extends Controller
{
    public function __construct(private MercadoPagoOAuthService $oauth)
    {
    }

    public function connect(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'authorization_url' => $this->oauth->createAuthorizationUrl(
                    tenant(),
                    $request->user()->getKey(),
                    $this->tenantSettingsUrl($request)
                ),
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                $this->connectionResource()->resolve(),
            ],
        ]);
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->connectionResource()->resolve(),
        ]);
    }

    public function disconnect(): JsonResponse
    {
        $connection = $this->oauth->disconnect(tenant());

        return response()->json([
            'success' => true,
            'data' => $this->connectionResource($connection)->resolve(),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $returnUrl = $this->oauth->returnUrlForState(is_string($state) ? $state : null);

        try {
            if (filled($request->query('error')) || blank($request->query('code'))) {
                $this->oauth->discardState(is_string($state) ? $state : null);

                return $this->errorRedirect(filled($request->query('error')) ? 'oauth_cancelled' : 'missing_code', $returnUrl);
            }

            $connection = $this->oauth->completeConnection((string) $state, (string) $request->query('code'));

            Log::info('Mercado Pago OAuth connection completed.', [
                'tenant_id' => $connection->tenant_id,
                'provider' => $connection->provider,
                'provider_account_id' => $connection->provider_account_id,
            ]);

            return redirect()->away($this->redirectUrl($returnUrl, 'oauth_success_url', [
                'provider' => 'mercadopago',
                'status' => 'connected',
            ]));
        } catch (MercadoPagoOAuthException $exception) {
            Log::warning('Mercado Pago OAuth connection failed.', [
                'provider' => 'mercadopago',
                'reason' => $exception->reason(),
            ]);

            return $this->errorRedirect($exception->reason(), $returnUrl);
        }
    }

    protected function errorRedirect(string $reason, ?string $returnUrl = null): RedirectResponse
    {
        return redirect()->away($this->redirectUrl($returnUrl, 'oauth_error_url', [
            'provider' => 'mercadopago',
            'status' => 'error',
            'reason' => $reason,
        ]));
    }

    protected function redirectUrl(?string $returnUrl, string $configKey, array $query): string
    {
        $url = $returnUrl ?: (string) config("services.mercadopago.{$configKey}");
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    protected function tenantSettingsUrl(Request $request): ?string
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('X-Store-Origin');
        $tenantHost = (string) $request->attributes->get('tenant_host');

        if (! is_string($origin) || $origin === '' || $tenantHost === '') {
            return null;
        }

        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host !== $tenantHost) {
            return null;
        }

        return rtrim($origin, '/').'/settings/payments';
    }

    protected function connectionResource(?TenantPaymentConnection $connection = null): PaymentProviderConnectionResource
    {
        $connection ??= tenant()->paymentConnections()
            ->where('provider', TenantPaymentConnection::PROVIDER_MERCADOPAGO)
            ->first();

        return new PaymentProviderConnectionResource($connection ?? new TenantPaymentConnection([
            'provider' => TenantPaymentConnection::PROVIDER_MERCADOPAGO,
            'status' => PaymentConnectionStatus::Disconnected,
        ]));
    }
}
