<?php

namespace App\Http\Resources\Account;

use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'family_id' => $this->family_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'name' => $this->category?->name,
                    'slug' => $this->category?->slug,
                ];
            }),
            'family' => $this->whenLoaded('family', function () {
                return [
                    'id' => $this->family?->id,
                    'category_id' => $this->family?->category_id,
                    'name' => $this->family?->name,
                    'slug' => $this->family?->slug,
                ];
            }),
            'microsip_id' => $this->microsip_id,
            'es_almacenable' => $this->es_almacenable,
            'es_juego' => $this->es_juego,
            'estatus' => $this->estatus,
            'causa_susp' => $this->causa_susp,
            'fecha_susp' => $this->fecha_susp?->toDateString(),
            'imprimir_comp' => $this->imprimir_comp,
            'permitir_agregar_comp' => $this->permitir_agregar_comp,
            'linea_articulo_id' => $this->linea_articulo_id,
            'unidad_venta' => $this->unidad_venta,
            'unidad_compra' => $this->unidad_compra,
            'contenido_unidad_compra' => $this->contenido_unidad_compra !== null ? (float) $this->contenido_unidad_compra : null,
            'peso_unitario' => $this->peso_unitario !== null ? (float) $this->peso_unitario : null,
            'es_peso_variable' => $this->es_peso_variable,
            'seguimiento' => $this->seguimiento,
            'dias_garantia' => $this->dias_garantia,
            'es_importado' => $this->es_importado,
            'es_siempre_importado' => $this->es_siempre_importado,
            'pctje_arancel' => $this->pctje_arancel !== null ? (float) $this->pctje_arancel : null,
            'notas_compras' => $this->notas_compras,
            'imprimir_notas_compras' => $this->imprimir_notas_compras,
            'notas_ventas' => $this->notas_ventas,
            'imprimir_notas_ventas' => $this->imprimir_notas_ventas,
            'es_precio_variable' => $this->es_precio_variable,
            'cuenta_almacen' => $this->cuenta_almacen,
            'cuenta_costo_venta' => $this->cuenta_costo_venta,
            'cuenta_ventas' => $this->cuenta_ventas,
            'cuenta_dscto_ventas' => $this->cuenta_dscto_ventas,
            'cuenta_devol_ventas' => $this->cuenta_devol_ventas,
            'cuenta_compras' => $this->cuenta_compras,
            'cuenta_devol_compras' => $this->cuenta_devol_compras,
            'aplicar_factor_venta' => $this->aplicar_factor_venta,
            'factor_venta' => $this->factor_venta !== null ? (float) $this->factor_venta : null,
            'red_precio_con_impto' => $this->red_precio_con_impto,
            'factor_red_precio_con_impto' => $this->factor_red_precio_con_impto !== null ? (float) $this->factor_red_precio_con_impto : null,
            'usuario_creador' => $this->usuario_creador,
            'fecha_hora_creacion' => $this->fecha_hora_creacion?->toJSON(),
            'usuario_aut_creacion' => $this->usuario_aut_creacion,
            'usuario_ult_modif' => $this->usuario_ult_modif,
            'fecha_hora_ult_modif' => $this->fecha_hora_ult_modif?->toJSON(),
            'usuario_aut_modif' => $this->usuario_aut_modif,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'default_price' => $this->getAttribute('current_price') !== null
                ? (float) $this->getAttribute('current_price')
                : ($this->default_price !== null ? (float) $this->default_price : null),
            'base_default_price' => $this->default_price !== null ? (float) $this->default_price : null,
            'price_info' => [
                'precio_empresa_id' => $this->getAttribute('price_company_id') ?? ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'requested_precio_empresa_id' => $this->getAttribute('requested_price_company_id') ?? ProductPriceService::DEFAULT_PRICE_COMPANY_ID,
                'is_default_price_list' => (bool) ($this->getAttribute('is_default_price_list') ?? true),
                'source' => $this->getAttribute('price_source') ?? 'precios_articulos_default_missing',
            ],
            'sku' => $this->sku,
            'is_active' => (bool) $this->is_active,
            'brand' => $this->brand,
            'keyword' => $this->keyword,
            'processed' => (bool) $this->processed,
            'is_favorite' => (bool) ($this->is_favorite_for_current_user ?? true),
            'favorited_at' => $this->favorite_created_at
                ?? $this->pivot?->created_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
