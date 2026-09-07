<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'status',
        'payment_method',
        'provider_reference',
        'provider_payment_id',
        'provider_status',
        'external_reference',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'amount',
        'platform_fee',
        'currency',
        'paid_at',
        'provider_payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'paid_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
