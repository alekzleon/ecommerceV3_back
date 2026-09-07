<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlatformPaymentFeeSetting extends Model
{
    use CentralConnection;

    public const TYPE_NONE = 'none';
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'provider',
        'plan_key',
        'fee_type',
        'fee_value',
        'is_active',
    ];

    protected $casts = [
        'fee_value' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
