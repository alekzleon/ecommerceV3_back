<?php

namespace App\Services\Orders;

use App\Models\DoctoVe;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DoctoVeService
{
    protected const DEFAULT_COND_PAGO_ID = 556;

    public function createFromPaidOrder(Order $order): DoctoVe
    {
        return $this->createFromOrder($order);
    }

    public function createFromOrder(Order $order): DoctoVe
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->with(['user.customerProfile', 'items.product'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $doctoVe = DoctoVe::query()
                ->where('order_id', $order->id)
                ->first();

            if ($doctoVe) {
                $folioMicrosip = $doctoVe->folio_microsip ?: $this->webFolio($doctoVe->id);
                $dirCliId = $this->orderDirCliId($order);

                if (
                    $doctoVe->orden_compra !== $order->orden_compra
                    || $doctoVe->folio_microsip !== $folioMicrosip
                    || $doctoVe->dir_cli_id !== $dirCliId
                    || $doctoVe->dir_consig_id !== $dirCliId
                    || (int) $doctoVe->cond_pago_id !== self::DEFAULT_COND_PAGO_ID
                ) {
                    $doctoVe->forceFill([
                        'folio' => $folioMicrosip,
                        'folio_microsip' => $folioMicrosip,
                        'dir_cli_id' => $dirCliId,
                        'dir_consig_id' => $dirCliId,
                        'orden_compra' => $order->orden_compra,
                        'fecha_orden_compra' => $order->created_at?->toDateString(),
                        'cond_pago_id' => self::DEFAULT_COND_PAGO_ID,
                    ])->save();
                }

                return $doctoVe->load('detalles');
            }

            $validationErrors = $this->validationErrors($order);
            $now = now();

            $doctoVe = DoctoVe::create([
                'order_id' => $order->id,
                'tipo_docto' => 'r',
                'subtipo_docto' => 'N',
                'folio' => $order->folio_microsip ?? $order->number,
                'fecha' => ($order->paid_at ?? $now)->toDateString(),
                'hora' => ($order->paid_at ?? $now)->format('H:i:s'),
                'clave_cliente' => $this->customerKey($order->user),
                'cliente_id' => $this->numericMicrosipId($order->user?->microsip_id),
                'dir_cli_id' => $this->orderDirCliId($order),
                'dir_consig_id' => $this->orderDirCliId($order),
                'moneda_id' => $this->monedaId($order->currency),
                'tipo_cambio' => 1,
                'tipo_dscto' => 'P',
                'dscto_pctje' => 0,
                'dscto_importe' => $order->discount,
                'estatus' => 'N',
                'aplicado' => 'S',
                'orden_compra' => $order->orden_compra,
                'fecha_orden_compra' => $order->created_at?->toDateString(),
                'descripcion' => 'Pedido ecommerce ' . $order->number,
                'importe_neto' => $order->subtotal,
                'fletes' => $order->shipping,
                'otros_cargos' => 0,
                'total_impuestos' => $order->tax,
                'total_retenciones' => 0,
                'total_anticipos' => 0,
                'peso_embarque' => 0,
                'forma_emitida' => 'N',
                'contabilizado' => 'N',
                'acreditar_cxc' => 'N',
                'sistema_origen' => 'VE',
                'cond_pago_id' => self::DEFAULT_COND_PAGO_ID,
                'importe_cobro' => $order->total,
                'usuario_creador' => 'ECOMMERCE',
                'es_cfd' => 'N',
                'enviado' => 'N',
                'cfd_envio_especial' => 'N',
                'cfdi_certificado' => 'N',
                'fecha_hora_creacion' => ($order->paid_at ?? $now)->format('H:i:s'),
                'usuario_ult_modif' => 'ECOMMERCE',
                'fecha_hora_ult_modif' => $now,
                'cargar_sun' => 'N',
                'ptl' => 'N',
                'sync_status' => empty($validationErrors) ? 'pending' : 'needs_review',
                'sincronizado' => false,
                'validation_errors' => $validationErrors ?: null,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'order_folio_microsip' => $order->folio_microsip,
                    'orden_compra' => $order->orden_compra,
                    'dir_cli_id' => $this->orderDirCliId($order),
                    'payment_method' => $order->payment_method,
                    'stripe_session_id' => $order->stripe_session_id,
                    'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                    'shipping_address' => $order->shipping_address_snapshot,
                ],
            ]);

            $folioMicrosip = $this->webFolio($doctoVe->id);
            $doctoVe->forceFill([
                'folio' => $folioMicrosip,
                'folio_microsip' => $folioMicrosip,
                'metadata' => array_merge($doctoVe->metadata ?? [], [
                    'folio_microsip' => $folioMicrosip,
                ]),
            ])->save();

            $order->items->values()->each(function (OrderItem $item, int $index) use ($doctoVe) {
                $doctoVe->detalles()->create([
                    'docto_ve_id' => $doctoVe->docto_ve_id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'clave_articulo' => substr((string) ($item->clave_articulo_snapshot ?: $item->sku_snapshot), 0, 20),
                    'articulo_id' => $this->numericMicrosipId($item->product?->microsip_id),
                    'unidades' => $item->quantity,
                    'unidades_compro' => $item->quantity,
                    'unidades_surt_de' => 0,
                    'unidades_a_surtir' => $item->quantity,
                    'precio_unitario' => $item->unit_price,
                    'pctje_dscto' => 0,
                    'dscto_art' => $item->discount,
                    'pctje_dscto_cli' => 0,
                    'dscto_extra' => 0,
                    'pctje_dscto_vol' => 0,
                    'pctje_dscto_prom' => 0,
                    'precio_total_neto' => $item->line_total,
                    'precio_modificado' => 'N',
                    'pctje_comis' => 0,
                    'rol' => 'N',
                    'posicion' => $index + 1,
                    'notas' => $item->promotion_name_snapshot,
                    'metadata' => [
                        'sku_snapshot' => $item->sku_snapshot,
                        'name_snapshot' => $item->name_snapshot,
                        'brand_snapshot' => $item->brand_snapshot,
                        'rol_clave_art_id_snapshot' => $item->rol_clave_art_id_snapshot,
                        'price_info' => data_get($item->metadata, 'price_info'),
                        'promotion_snapshot' => $item->promotion_snapshot,
                        'order_item_metadata' => $item->metadata,
                    ],
                ]);
            });

            return $doctoVe->load('detalles');
        });
    }

    protected function validationErrors(Order $order): array
    {
        $errors = [];

        if (! $this->numericMicrosipId($order->user?->microsip_id)) {
            $errors['cliente_id'][] = 'El cliente no tiene microsip_id numérico para CLIENTE_ID.';
        }

        if (! $this->orderDirCliId($order)) {
            $errors['dir_cli_id'][] = 'La orden no tiene DIR_CLI_ID de dirección de cliente.';
        }

        foreach ($order->items as $item) {
            if (! $item->product?->microsip_id) {
                $errors['items'][] = "La partida {$item->id} no tiene ARTICULO_ID del producto.";
            }

            if (! $item->clave_articulo_snapshot) {
                $errors['items'][] = "La partida {$item->id} no tiene CLAVE_ARTICULO con rol 17.";
            }
        }

        return $errors;
    }

    protected function customerKey(?User $user): ?string
    {
        return $user?->username
            ?? $user?->customerProfile?->id_microsip
            ?? $user?->microsip_id;
    }

    protected function numericMicrosipId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function orderDirCliId(Order $order): ?int
    {
        return $this->numericMicrosipId(data_get($order->shipping_address_snapshot, 'dir_cli_id'));
    }

    protected function webFolio(int $id): string
    {
        return 'W' . $id;
    }

    protected function monedaId(?string $currency): int
    {
        return strtoupper((string) $currency) === 'MXN' ? 1 : 1;
    }
}
