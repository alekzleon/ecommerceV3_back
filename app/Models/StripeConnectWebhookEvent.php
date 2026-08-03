<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class StripeConnectWebhookEvent extends Model
{
    use CentralConnection;

    protected $fillable = [
        'stripe_event_id',
        'stripe_account_id',
        'tenant_id',
        'type',
        'status',
        'processed_at',
        'payload',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
