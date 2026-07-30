<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTenantController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $statusCounts = Tenant::query()
            ->selectRaw('subscription_status, count(*) as total')
            ->groupBy('subscription_status')
            ->pluck('total', 'subscription_status');

        $planCounts = Tenant::query()
            ->selectRaw('plan_key, count(*) as total')
            ->groupBy('plan_key')
            ->pluck('total', 'plan_key');

        $monthlyRevenue = SubscriptionPayment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return response()->json([
            'ok' => true,
            'message' => 'Dashboard central obtenido correctamente.',
            'data' => [
                'totals' => [
                    'tenants' => Tenant::query()->count(),
                    'active' => (int) ($statusCounts[Tenant::STATUS_ACTIVE] ?? 0),
                    'past_due' => (int) ($statusCounts[Tenant::STATUS_PAST_DUE] ?? 0),
                    'suspended' => (int) ($statusCounts[Tenant::STATUS_SUSPENDED] ?? 0),
                    'canceled' => (int) ($statusCounts[Tenant::STATUS_CANCELED] ?? 0),
                    'monthly_revenue' => round((float) $monthlyRevenue, 2),
                    'currency' => 'MXN',
                ],
                'by_plan' => $this->countsPayload($planCounts),
                'by_status' => $this->countsPayload($statusCounts),
                'recent_tenants' => Tenant::query()
                    ->with('domains')
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (Tenant $tenant) => $this->tenantSummaryPayload($tenant))
                    ->values(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'plan_key' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenants = Tenant::query()
            ->with('domains')
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('data->store_name', 'like', "%{$search}%")
                        ->orWhere('data->owner_email', 'like', "%{$search}%");
                });
            })
            ->when($validated['plan_key'] ?? null, fn ($query, string $planKey) => $query->where('plan_key', $planKey))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('subscription_status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'ok' => true,
            'message' => 'Tenants obtenidos correctamente.',
            'data' => $tenants->through(fn (Tenant $tenant) => $this->tenantSummaryPayload($tenant)),
        ]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('domains');

        return response()->json([
            'ok' => true,
            'message' => 'Tenant obtenido correctamente.',
            'data' => [
                ...$this->tenantSummaryPayload($tenant),
                'subscription' => $this->subscriptionPayload($tenant),
                'payments' => SubscriptionPayment::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn (SubscriptionPayment $payment) => $this->paymentPayload($payment))
                    ->values(),
            ],
        ]);
    }

    public function updateSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'plan_key' => ['sometimes', 'string', Rule::in(array_keys(config('plans.plans', [])))],
            'status' => ['required', Rule::in([
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_PAST_DUE,
                Tenant::STATUS_SUSPENDED,
                Tenant::STATUS_CANCELED,
            ])],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        $planKey = $validated['plan_key'] ?? $tenant->plan_key;
        $endsAt = array_key_exists('subscription_ends_at', $validated)
            ? $validated['subscription_ends_at']
            : $this->defaultEndsAtForPlan($tenant, $planKey);

        if ($validated['status'] === Tenant::STATUS_ACTIVE) {
            $tenant->activatePlan($planKey, $endsAt, $tenant->payment_provider, $tenant->provider_subscription_id);
        } else {
            $tenant->forceFill([
                'plan_key' => $planKey,
                'subscription_ends_at' => $endsAt,
            ])->save();
            $tenant->suspendSubscription($validated['status']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Suscripción del tenant actualizada correctamente.',
            'data' => $this->subscriptionPayload($tenant->fresh('domains')),
        ]);
    }

    protected function tenantSummaryPayload(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'store_name' => data_get($tenant->data, 'store_name'),
            'owner_email' => data_get($tenant->data, 'owner_email'),
            'domains' => $tenant->domains->pluck('domain')->values(),
            'plan_key' => $tenant->plan_key,
            'plan_name' => $tenant->plan['name'] ?? $tenant->plan_key,
            'subscription_status' => $tenant->subscription_status,
            'is_usable' => $tenant->isSubscriptionUsable(),
            'subscription_started_at' => $tenant->subscription_started_at?->toISOString(),
            'subscription_ends_at' => $tenant->subscription_ends_at?->toISOString(),
            'suspended_at' => $tenant->suspended_at?->toISOString(),
            'payment_provider' => $tenant->payment_provider,
            'provider_customer_id' => $tenant->provider_customer_id,
            'provider_subscription_id' => $tenant->provider_subscription_id,
            'last_payment_at' => $tenant->last_payment_at?->toISOString(),
            'created_at' => $tenant->created_at?->toISOString(),
        ];
    }

    protected function subscriptionPayload(Tenant $tenant): array
    {
        return [
            'tenant_id' => $tenant->id,
            'plan_key' => $tenant->plan_key,
            'plan' => [
                'name' => $tenant->plan['name'] ?? $tenant->plan_key,
                'label' => $tenant->plan['label'] ?? null,
                'price' => $tenant->plan['price'] ?? null,
                'currency' => $tenant->plan['currency'] ?? null,
                'interval' => $tenant->plan['interval'] ?? null,
            ],
            'status' => $tenant->subscription_status,
            'is_usable' => $tenant->isSubscriptionUsable(),
            'started_at' => $tenant->subscription_started_at?->toISOString(),
            'ends_at' => $tenant->subscription_ends_at?->toISOString(),
            'suspended_at' => $tenant->suspended_at?->toISOString(),
            'provider' => $tenant->payment_provider,
            'provider_customer_id' => $tenant->provider_customer_id,
            'provider_subscription_id' => $tenant->provider_subscription_id,
            'last_payment_at' => $tenant->last_payment_at?->toISOString(),
        ];
    }

    protected function paymentPayload(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'provider' => $payment->provider,
            'provider_invoice_id' => $payment->provider_invoice_id,
            'provider_subscription_id' => $payment->provider_subscription_id,
            'provider_customer_id' => $payment->provider_customer_id,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'period_start' => $payment->period_start?->toISOString(),
            'period_end' => $payment->period_end?->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
            'hosted_invoice_url' => $payment->hosted_invoice_url,
            'invoice_pdf' => $payment->invoice_pdf,
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }

    protected function countsPayload($counts): array
    {
        return collect($counts)
            ->map(fn ($total, $key) => [
                'key' => $key,
                'total' => (int) $total,
            ])
            ->values()
            ->all();
    }

    protected function defaultEndsAtForPlan(Tenant $tenant, string $planKey): mixed
    {
        if ($tenant->plan_key === $planKey && $tenant->subscription_ends_at) {
            return $tenant->subscription_ends_at;
        }

        $trialDays = (int) config("plans.plans.{$planKey}.trial_days", 0);

        return $trialDays > 0 ? now()->addDays($trialDays) : $tenant->subscription_ends_at;
    }
}
