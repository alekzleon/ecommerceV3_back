<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantStripeConnectController extends Controller
{
    public function __construct(
        protected StripeConnectService $stripeConnectService
    ) {
    }

    public function status(): JsonResponse
    {
        $connectAccount = tenant()->stripeAccount;

        if ($connectAccount?->stripe_account_id) {
            $connectAccount = $this->stripeConnectService->syncAccount($connectAccount);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Estado de Stripe Connect obtenido correctamente.',
            'data' => $this->stripeConnectService->payload($connectAccount),
        ]);
    }

    public function account(Request $request): JsonResponse
    {
        $connectAccount = $this->stripeConnectService->createOrRetrieveAccount(
            tenant(),
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Cuenta Stripe Connect preparada correctamente.',
            'data' => $this->stripeConnectService->payload($connectAccount),
        ]);
    }

    public function onboardingLink(Request $request): JsonResponse
    {
        $connectAccount = $this->stripeConnectService->createOrRetrieveAccount(
            tenant(),
            $request->user()
        );

        $link = $this->stripeConnectService->createOnboardingLink(
            $connectAccount,
            $this->connectUrl($request, 'return'),
            $this->connectUrl($request, 'refresh')
        );

        return response()->json([
            'ok' => true,
            'message' => 'Enlace de onboarding de Stripe Connect generado correctamente.',
            'data' => [
                ...$this->stripeConnectService->payload($connectAccount->fresh()),
                'onboarding' => $link,
            ],
        ]);
    }

    protected function connectUrl(Request $request, string $type): string
    {
        $origin = $this->storefrontOrigin($request);
        $path = $type === 'refresh'
            ? config('services.stripe.connect.refresh_path', '/admin/payments/stripe/refresh')
            : config('services.stripe.connect.return_path', '/admin/payments/stripe/return');

        return rtrim($origin, '/') . '/' . ltrim((string) $path, '/');
    }

    protected function storefrontOrigin(Request $request): string
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('X-Store-Origin');
        $tenantHost = $request->attributes->get('tenant_host');

        if (! is_string($origin) || trim($origin) === '') {
            return "https://{$tenantHost}";
        }

        $origin = trim($origin);
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        abort_unless(
            in_array($scheme, ['http', 'https'], true) && $host === $tenantHost,
            422,
            'El origen de la tienda no coincide con el tenant.'
        );

        return $scheme . '://' . $host . ($port ? ':' . $port : '');
    }
}
