<?php

namespace App\Http\Resources\Cart;

use App\Services\SalesChannelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_channel' => $this->sales_channel ?: SalesChannelService::DEFAULT_CHANNEL,
            'sales_channel_label' => app(SalesChannelService::class)->label($this->sales_channel),
            'items_count' => $this->items_count,
            'subtotal' => (float) $this->subtotal_snapshot,
            'discount' => (float) $this->discount_snapshot,
            'tax' => (float) $this->tax_snapshot,
            'total' => (float) $this->total_snapshot,
            'tax_breakdown' => data_get($this->metadata, 'taxes', [
                'total' => 0.0,
                'items' => [],
            ]),
            'coupon' => data_get($this->metadata, 'coupon'),
            'loyalty' => data_get($this->metadata, 'loyalty', [
                'first_purchase_discount' => null,
                'cashback' => null,
            ]),
        ];
    }
}
