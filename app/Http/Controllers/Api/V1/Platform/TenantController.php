<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantProvisioningService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class TenantController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Planes obtenidos correctamente.',
            'data' => collect(config('plans.plans', []))
                ->map(fn (array $plan, string $key) => $this->planPayload($key, $plan))
                ->values()
                ->all(),
        ]);
    }

    public function tenantPlans(): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'ok' => true,
            'message' => 'Planes del tenant obtenidos correctamente.',
            'data' => [
                'current_plan_key' => $tenant->plan_key,
                'subscription_status' => $tenant->subscription_status,
                'is_usable' => $tenant->isSubscriptionUsable(),
                'plans' => collect(config('plans.plans', []))
                    ->map(function (array $plan, string $key) use ($tenant) {
                        return [
                            ...$this->planPayload($key, $plan),
                            'is_current' => $tenant->plan_key === $key,
                            'can_checkout' => $key !== 'free' && $tenant->plan_key !== $key,
                        ];
                    })
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function subscription(): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'ok' => true,
            'message' => 'Suscripción obtenida correctamente.',
            'data' => $this->subscriptionPayload($tenant),
        ]);
    }

    public function createSubscriptionCheckout(Request $request, StripePaymentService $stripePaymentService): JsonResponse
    {
        $validated = $request->validate([
            'plan_key' => ['required', 'string', 'max:50'],
        ]);

        $session = $stripePaymentService->createSubscriptionCheckoutSession(
            tenant(),
            $validated['plan_key'],
            $this->storefrontOrigin($request),
            $request->user()?->email
        );

        return response()->json([
            'ok' => true,
            'message' => 'Sesión de suscripción creada correctamente.',
            'data' => $session,
        ]);
    }

    public function confirmSubscriptionCheckout(Request $request, StripePaymentService $stripePaymentService): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $tenant = $stripePaymentService->syncSubscriptionCheckoutSession(
            tenant(),
            $validated['session_id']
        );

        return response()->json([
            'ok' => true,
            'message' => 'Suscripción actualizada correctamente.',
            'data' => $this->subscriptionPayload($tenant),
        ]);
    }

    public function store(Request $request, TenantProvisioningService $provisioningService): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'subdomain' => ['required', 'string', 'max:63'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'publish' => ['sometimes', 'boolean'],
        ]);

        try {
            $tenant = $provisioningService->createStore($validated);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Tenant provisioning failed', [
                'subdomain' => $validated['subdomain'] ?? null,
                'owner_email' => $validated['owner_email'] ?? null,
                'exception' => $exception,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No pudimos crear la tienda en este momento. Intenta de nuevo.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Tienda creada correctamente.',
            'data' => $this->tenantPayload($tenant),
        ], 201);
    }

    public function checkSubdomain(Request $request, TenantProvisioningService $provisioningService): JsonResponse
    {
        $validated = $request->validate([
            'subdomain' => ['required', 'string', 'max:63'],
        ]);

        $availability = $provisioningService->checkSubdomain($validated['subdomain']);

        return response()->json([
            'ok' => true,
            'message' => $availability['available']
                ? 'Subdominio disponible.'
                : 'Subdominio no disponible.',
            'data' => [
                ...$availability,
                'urls' => $this->domainUrls($availability['domains']),
            ],
        ]);
    }

    protected function tenantPayload(array $tenant): array
    {
        return [
            'tenant_id' => $tenant['tenant_id'],
            'database' => $tenant['database'],
            'domains' => $tenant['domains'],
            'urls' => $this->domainUrls($tenant['domains']),
            'admin' => $tenant['admin'],
            'storefront' => $tenant['storefront'],
            'subscription' => $tenant['subscription'],
        ];
    }

    protected function domainUrls(array $domains): array
    {
        $localDomain = $domains['local'] ?? null;
        $productionDomain = $domains['production'] ?? null;

        return [
            'local' => $localDomain ? "http://{$localDomain}:5173" : null,
            'local_login' => $localDomain ? "http://{$localDomain}:5173/login" : null,
            'production' => $productionDomain ? "https://{$productionDomain}" : null,
            'production_login' => $productionDomain ? "https://{$productionDomain}/login" : null,
        ];
    }

    protected function planPayload(string $key, array $plan): array
    {
        return [
            'key' => $key,
            'name' => $plan['name'],
            'label' => $plan['label'],
            'price' => $plan['price'],
            'currency' => $plan['currency'],
            'interval' => $plan['interval'],
            'description' => $plan['description'],
            'features' => $plan['features'],
            'included_modules' => $plan['modules'],
            'limits' => $plan['limits'] ?? [],
        ];
    }

    protected function subscriptionPayload($tenant): array
    {
        return [
            'tenant_id' => $tenant->id,
            'plan_key' => $tenant->plan_key,
            'plan' => [
                'name' => $tenant->plan['name'] ?? 'Free',
                'label' => $tenant->plan['label'] ?? 'Gratis',
                'price' => $tenant->plan['price'] ?? 0,
                'currency' => $tenant->plan['currency'] ?? 'MXN',
                'interval' => $tenant->plan['interval'] ?? null,
                'features' => $tenant->plan['features'] ?? [],
                'limits' => $tenant->plan['limits'] ?? [],
            ],
            'status' => $tenant->subscription_status,
            'is_usable' => $tenant->isSubscriptionUsable(),
            'started_at' => $tenant->subscription_started_at?->toISOString(),
            'ends_at' => $tenant->subscription_ends_at?->toISOString(),
            'suspended_at' => $tenant->suspended_at?->toISOString(),
        ];
    }

    protected function storefrontOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('X-Store-Origin');

        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $origin = trim($origin);
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $tenantHost = $request->attributes->get('tenant_host');

        if (! in_array($scheme, ['http', 'https'], true) || ! $host || $host !== $tenantHost) {
            return null;
        }

        return $scheme . '://' . $host . ($port ? ':' . $port : '');
    }
}
