<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'sku_snapshot',
        'name_snapshot',
        'brand_snapshot',
        'image_snapshot',
        'category_snapshot',
        'family_snapshot',
        'base_unit_price_snapshot',
        'final_unit_price_snapshot',
        'price_snapshot',
        'quantity',
        'discount_snapshot',
        'line_discount_snapshot',
        'line_subtotal_snapshot',
        'promotion_id',
        'promotion_type',
        'promotion_name_snapshot',
        'promotion_snapshot',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'promotion_snapshot' => 'array',
        'price_snapshot' => 'decimal:2',
        'base_unit_price_snapshot' => 'decimal:2',
        'final_unit_price_snapshot' => 'decimal:2',
        'discount_snapshot' => 'decimal:2',
        'line_discount_snapshot' => 'decimal:2',
        'line_subtotal_snapshot' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}