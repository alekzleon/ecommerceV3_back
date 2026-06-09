<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    public const STATUS_ACTIVO = 'activo';
    public const STATUS_BAJA = 'baja';
    public const STATUS_SUSPENDIDO_CREDITO = 'suspendido_credito';

    public const ONBOARDING_INVITED = 'invited';
    public const ONBOARDING_IN_PROGRESS = 'in_progress';
    public const ONBOARDING_PROFILE_COMPLETED = 'profile_completed';

    protected $fillable = [
        'user_id',
        'commercial_name',
        'whatsapp',
        'id_microsip',
        'status',
        'onboarding_status',
        'credit_limit',
        'credit_days',
        'discount_percent',
        'assigned_seller_id',
        'route',
        'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'credit_days' => 'integer',
        'assigned_seller_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_seller_id');
    }
}
