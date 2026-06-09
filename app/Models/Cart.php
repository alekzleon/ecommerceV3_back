<?php

namespace App\Models;

use App\Enums\CartStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'currency',
        'items_count',
        'subtotal_snapshot',
        'discount_snapshot',
        'tax_snapshot',
        'total_snapshot',
        'source',
        'last_activity_at',
        'converted_at',
        'abandoned_at',
        'order_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_activity_at' => 'datetime',
        'converted_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'subtotal_snapshot' => 'decimal:2',
        'discount_snapshot' => 'decimal:2',
        'tax_snapshot' => 'decimal:2',
        'total_snapshot' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CartEvent::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CartStatus::ACTIVE->value);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function touchActivity(): void
    {
        $this->forceFill([
            'last_activity_at' => now(),
        ])->save();
    }
}
