<?php

namespace App\Http\Resources\Cart;

use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Estas notas son para mí:
     * - Este resource debe regresar el carrito ya listo para pintar en frontend.
     * - Los totales salen de snapshots del carrito.
     * - Las promociones aplicadas las reconstruyo desde los items porque
     *   actualmente no guardo un arreglo consolidado en la tabla carts.
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user.defaultAddress', 'user.addresses']);

        $items = $this->whenLoaded('items');
        $shipping = $this->shippingPayload();

        $promotionsApplied = [];

        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                if (! $item->promotion_id) {
                    continue;
                }

                $promotionId = (int) $item->promotion_id;

                if (! isset($promotionsApplied[$promotionId])) {
                    $promotionsApplied[$promotionId] = [
                        'id' => $promotionId,
                        'type' => $item->promotion_type,
                        'name' => $item->promotion_name_snapshot,
                        'total_discount' => 0,
                        'items_count' => 0,
                        'gift_units' => 0,
                        'gift_item_units' => 0,
                        'gift_line_total' => 0,
                        'snapshot' => $item->promotion_snapshot,
                    ];
                }

                $promotionsApplied[$promotionId]['total_discount'] += (float) $item->line_discount_snapshot;
                $promotionsApplied[$promotionId]['items_count'] += 1;
                $promotionsApplied[$promotionId]['gift_units'] += (int) data_get($item->promotion_snapshot, 'gift_units', 0);
                $promotionsApplied[$promotionId]['gift_item_units'] += (int) data_get($item->promotion_snapshot, 'gift_item_units', 0);
                $promotionsApplied[$promotionId]['gift_line_total'] += (float) data_get($item->promotion_snapshot, 'gift_line_total', 0);
            }
        }

        $promotionsApplied = array_values(array_map(function ($promotion) {
            $promotion['total_discount'] = round((float) $promotion['total_discount'], 2);
            $promotion['gift_units'] = (int) $promotion['gift_units'];
            $promotion['gift_item_units'] = (int) $promotion['gift_item_units'];
            $promotion['gift_line_total'] = round((float) $promotion['gift_line_total'], 2);
            return $promotion;
        }, $promotionsApplied));

        return [
            'id' => $this->id,
            'status' => $this->status,
            'currency' => $this->currency,
            'items_count' => (float) $this->items_count,
            'subtotal' => (float) $this->subtotal_snapshot,
            'discount' => (float) $this->discount_snapshot,
            'tax' => (float) $this->tax_snapshot,
            'tax_breakdown' => data_get($this->metadata, 'taxes', [
                'total' => 0.0,
                'items' => [],
            ]),
            'total' => (float) $this->total_snapshot,
            'last_activity_at' => $this->last_activity_at,
            'shipping' => $shipping,
            'promotions_applied' => $promotionsApplied,
            'items' => CartItemResource::collection($items),
        ];
    }

    protected function shippingPayload(): array
    {
        $addresses = $this->user?->addresses
            ? $this->user->addresses->sortByDesc('is_default')->sortByDesc('id')->values()
            : collect();
        $selectedAddress = $this->user?->defaultAddress;

        return [
            'requires_address' => true,
            'has_selected_address' => (bool) $selectedAddress,
            'selected_address' => $selectedAddress ? $this->addressPayload($selectedAddress) : null,
            'addresses' => $addresses->map(fn (UserAddress $address) => $this->addressPayload($address))->values(),
            'can_choose_address' => $addresses->count() > 1,
        ];
    }

    protected function addressPayload(UserAddress $address): array
    {
        return [
            'id' => $address->id,
            'dir_cli_id' => $address->dir_cli_id,
            'cliente_id' => $address->cliente_id,
            'alias' => $address->alias,
            'street' => $address->street,
            'address_line_2' => $address->address_line_2,
            'zip_code' => $address->zip_code,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
            'delivery_note' => $address->references,
            'contact_name' => $address->contact_name,
            'phone' => $address->phone,
            'is_default' => (bool) $address->is_default,
            'es_dir_ppal' => $address->es_dir_ppal,
            'usar_para_envios' => $address->usar_para_envios,
            'usar_para_facturar' => $address->usar_para_facturar,
            'full_address' => $address->full_address,
        ];
    }
}
