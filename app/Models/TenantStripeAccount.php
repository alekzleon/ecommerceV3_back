<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantStripeAccount extends Model
{
    use CentralConnection;

    public const STATUS_NOT_CONNECTED = 'not_connected';
    public const STATUS_ONBOARDING_PENDING = 'onboarding_pending';
    public const STATUS_RESTRICTED = 'restricted';
    public const STATUS_ENABLED = 'enabled';

    protected $fillable = [
        'tenant_id',
        'stripe_account_id',
        'account_type',
        'connect_status',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'disabled_reason',
        'country',
        'default_currency',
        'requirements_currently_due',
        'requirements_eventually_due',
        'requirements_past_due',
        'provider_payload',
        'onboarding_started_at',
        'onboarding_completed_at',
        'last_synced_at',
    ];

    protected $casts = [
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'requirements_currently_due' => 'array',
        'requirements_eventually_due' => 'array',
        'requirements_past_due' => 'array',
        'provider_payload' => 'array',
        'onboarding_started_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isReadyForCharges(): bool
    {
        return $this->connect_status === self::STATUS_ENABLED
            && $this->charges_enabled
            && $this->payouts_enabled;
    }
}
