<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDomain extends Model
{
    public const STATUS_PENDING_DNS = 'pending_dns';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_PENDING_SSL = 'pending_ssl';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'tenant_id',
        'hostname',
        'cloudflare_hostname_id',
        'app_status',
        'hostname_status',
        'ssl_status',
        'cname_target',
        'verification_errors',
        'cloudflare_response',
        'verified_at',
        'last_checked_at',
    ];

    protected $casts = [
        'verification_errors' => 'array',
        'cloudflare_response' => 'array',
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getIsReadyAttribute(): bool
    {
        return $this->hostname_status === self::STATUS_ACTIVE
            && $this->ssl_status === self::STATUS_ACTIVE;
    }
}
