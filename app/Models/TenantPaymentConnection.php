<?php

namespace App\Models;

use App\Enums\PaymentConnectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantPaymentConnection extends Model
{
    use CentralConnection;

    public const PROVIDER_MERCADOPAGO = 'mercadopago';
    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_PAYPAL = 'paypal';
    public const PROVIDER_NETPAY = 'netpay';
    public const PROVIDER_OPENPAY = 'openpay';
    public const PROVIDER_CLOUDIPAY = 'cloudipay';

    public const SUPPORTED_PROVIDERS = [
        self::PROVIDER_MERCADOPAGO,
        self::PROVIDER_STRIPE,
        self::PROVIDER_PAYPAL,
        self::PROVIDER_NETPAY,
        self::PROVIDER_OPENPAY,
        self::PROVIDER_CLOUDIPAY,
    ];

    protected $fillable = [
        'tenant_id',
        'provider',
        'provider_account_id',
        'credentials',
        'metadata',
        'status',
        'connected_at',
        'expires_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'metadata' => 'array',
        'status' => PaymentConnectionStatus::class,
        'connected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isConnected(): bool
    {
        return $this->status === PaymentConnectionStatus::Connected;
    }
}
