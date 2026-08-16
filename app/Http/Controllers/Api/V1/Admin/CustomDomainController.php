<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomDomainRequest;
use App\Http\Resources\Admin\CustomDomainResource;
use App\Models\CustomDomain;
use App\Services\Domains\CloudflareCustomHostnameService;
use Illuminate\Http\JsonResponse;

class CustomDomainController extends Controller
{
    public function index(): JsonResponse
    {
        $this->ensurePlanCanUseCustomDomains();

        $domains = CustomDomain::query()
            ->where('tenant_id', tenant('id'))
            ->latest()
            ->get();

        return response()->json([
            'ok' => true,
            'message' => 'Dominios personalizados obtenidos correctamente.',
            'data' => CustomDomainResource::collection($domains)->resolve(),
        ]);
    }

    public function store(StoreCustomDomainRequest $request, CloudflareCustomHostnameService $cloudflare): JsonResponse
    {
        $this->ensurePlanCanUseCustomDomains();

        $tenant = tenant();
        $hostname = $cloudflare->normalizeHostname($request->validated('domain'));

        $cloudflare->assertHostnameCanBeUsed($hostname, $tenant);

        $currentDomain = CustomDomain::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($currentDomain && $currentDomain->hostname !== $hostname) {
            return response()->json([
                'ok' => false,
                'message' => 'Solo puedes conectar un dominio personalizado por tienda. Desconecta el dominio actual para agregar otro.',
                'data' => [
                    'current_domain' => (new CustomDomainResource($currentDomain))->resolve(),
                ],
            ], 422);
        }

        $domain = CustomDomain::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'hostname' => $hostname,
        ]);

        if ($domain->exists && $domain->is_ready) {
            return response()->json([
                'ok' => true,
                'message' => 'El dominio ya esta conectado a esta tienda.',
                'data' => (new CustomDomainResource($domain))->resolve(),
            ]);
        }

        $domain->fill([
            'cname_target' => $cloudflare->cnameTarget(),
            'app_status' => CustomDomain::STATUS_PENDING_DNS,
            'hostname_status' => CustomDomain::STATUS_PENDING_DNS,
        ])->save();

        $domain = $cloudflare->create($domain);

        return response()->json([
            'ok' => true,
            'message' => 'Dominio registrado. Indica al cliente que configure el registro DNS mostrado.',
            'data' => (new CustomDomainResource($domain))->resolve(),
        ], 201);
    }

    public function show(CustomDomain $customDomain): JsonResponse
    {
        $this->ensurePlanCanUseCustomDomains();
        $this->authorizeTenantDomain($customDomain);

        return response()->json([
            'ok' => true,
            'message' => 'Dominio personalizado obtenido correctamente.',
            'data' => (new CustomDomainResource($customDomain))->resolve(),
        ]);
    }

    public function refresh(CustomDomain $customDomain, CloudflareCustomHostnameService $cloudflare): JsonResponse
    {
        $this->ensurePlanCanUseCustomDomains();
        $this->authorizeTenantDomain($customDomain);

        $customDomain = $cloudflare->refresh($customDomain);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del dominio actualizado correctamente.',
            'data' => (new CustomDomainResource($customDomain))->resolve(),
        ]);
    }

    public function destroy(CustomDomain $customDomain, CloudflareCustomHostnameService $cloudflare): JsonResponse
    {
        $this->ensurePlanCanUseCustomDomains();
        $this->authorizeTenantDomain($customDomain);

        $cloudflare->delete($customDomain);

        return response()->json([
            'ok' => true,
            'message' => 'Dominio personalizado desconectado correctamente.',
        ]);
    }

    private function ensurePlanCanUseCustomDomains(): void
    {
        abort_unless(tenant()?->plan_key === 'shop_plus', 403, 'Los dominios personalizados estan disponibles en el plan Shop+.');
    }

    private function authorizeTenantDomain(CustomDomain $customDomain): void
    {
        abort_unless($customDomain->tenant_id === tenant('id'), 404);
    }
}
