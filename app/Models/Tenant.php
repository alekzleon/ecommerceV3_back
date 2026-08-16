<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'id',
        'plan_key',
        'subscription_status',
        'subscription_started_at',
        'subscription_ends_at',
        'suspended_at',
        'payment_provider',
        'provider_customer_id',
        'provider_subscription_id',
        'last_payment_at',
        'data',
    ];

    protected $casts = [
        'subscription_started_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'data' => 'array',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'plan_key',
            'subscription_status',
            'subscription_started_at',
            'subscription_ends_at',
            'suspended_at',
            'payment_provider',
            'provider_customer_id',
            'provider_subscription_id',
            'last_payment_at',
            'created_at',
            'updated_at',
        ];
    }

    public function getPlanAttribute(): array
    {
        return config("plans.plans.{$this->plan_key}", config('plans.plans.free', []));
    }

    public function isSubscriptionUsable(): bool
    {
        if ($this->subscription_status !== self::STATUS_ACTIVE) {
            return false;
        }

        return ! $this->subscription_ends_at || $this->subscription_ends_at->isFuture();
    }

    public function hasPlanModule(string $moduleName): bool
    {
        $modules = $this->plan['modules'] ?? [];

        return in_array('*', $modules, true) || in_array($moduleName, $modules, true);
    }

    public function planLimit(string $key): ?int
    {
        $value = data_get($this->plan, "limits.{$key}");

        return $value === null ? null : (int) $value;
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function stripeAccount(): HasOne
    {
        return $this->hasOne(TenantStripeAccount::class);
    }

    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    public function activatePlan(string $planKey, mixed $endsAt = null, ?string $provider = null, ?string $providerSubscriptionId = null): void
    {
        if (! array_key_exists($planKey, config('plans.plans', []))) {
            throw new \InvalidArgumentException("El plan [{$planKey}] no existe.");
        }

        if ($endsAt === null && (int) config("plans.plans.{$planKey}.trial_days", 0) > 0) {
            $endsAt = now()->addDays((int) config("plans.plans.{$planKey}.trial_days"));
        }

        $this->forceFill([
            'plan_key' => $planKey,
            'subscription_status' => self::STATUS_ACTIVE,
            'subscription_started_at' => $this->subscription_started_at ?? now(),
            'subscription_ends_at' => $endsAt,
            'suspended_at' => null,
            'payment_provider' => $provider ?: $this->payment_provider,
            'provider_subscription_id' => $providerSubscriptionId ?: $this->provider_subscription_id,
            'last_payment_at' => now(),
        ])->save();
    }

    public function suspendSubscription(?string $status = self::STATUS_SUSPENDED): void
    {
        $this->forceFill([
            'subscription_status' => $status ?: self::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ])->save();
    }
}
