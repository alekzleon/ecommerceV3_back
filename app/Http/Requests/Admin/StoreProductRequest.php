<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $slug = $this->input('slug');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'slug' => filled($slug)
                ? Str::slug(trim((string) $slug))
                : null,
            'brand' => $this->filled('brand') ? trim((string) $this->brand) : null,
            'keyword' => $this->filled('keyword') ? trim((string) $this->keyword) : null,
            'sku' => $this->filled('sku') ? trim((string) $this->sku) : null,
            'short_description' => $this->filled('short_description') ? trim((string) $this->short_description) : null,
            'description' => $this->filled('description') ? trim((string) $this->description) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
            'processed' => $this->has('processed')
                ? filter_var($this->input('processed'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : false,
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id'),
            ],

            'family_id' => [
                'nullable',
                'integer',
                Rule::exists('families', 'id'),
            ],

            'microsip_id' => [
                'nullable',
                'string',
                'max:100',
            ],

            'es_almacenable' => ['nullable', 'string', 'max:1'],
            'es_juego' => ['nullable', 'string', 'max:1'],
            'estatus' => ['nullable', 'string', 'max:1'],
            'causa_susp' => ['nullable', 'string', 'max:255'],
            'fecha_susp' => ['nullable', 'date'],
            'imprimir_comp' => ['nullable', 'string', 'max:1'],
            'permitir_agregar_comp' => ['nullable', 'string', 'max:1'],
            'linea_articulo_id' => ['nullable', 'integer', 'min:0'],
            'unidad_venta' => ['nullable', 'string', 'max:100'],
            'unidad_compra' => ['nullable', 'string', 'max:100'],
            'contenido_unidad_compra' => ['nullable', 'numeric'],
            'peso_unitario' => ['nullable', 'numeric'],
            'es_peso_variable' => ['nullable', 'string', 'max:1'],
            'seguimiento' => ['nullable', 'string', 'max:1'],
            'dias_garantia' => ['nullable', 'integer', 'min:0'],
            'es_importado' => ['nullable', 'string', 'max:1'],
            'es_siempre_importado' => ['nullable', 'string', 'max:1'],
            'pctje_arancel' => ['nullable', 'numeric'],
            'notas_compras' => ['nullable', 'string'],
            'imprimir_notas_compras' => ['nullable', 'string', 'max:1'],
            'notas_ventas' => ['nullable', 'string'],
            'imprimir_notas_ventas' => ['nullable', 'string', 'max:1'],
            'es_precio_variable' => ['nullable', 'string', 'max:1'],
            'cuenta_almacen' => ['nullable', 'string', 'max:100'],
            'cuenta_costo_venta' => ['nullable', 'string', 'max:100'],
            'cuenta_ventas' => ['nullable', 'string', 'max:100'],
            'cuenta_dscto_ventas' => ['nullable', 'string', 'max:100'],
            'cuenta_devol_ventas' => ['nullable', 'string', 'max:100'],
            'cuenta_compras' => ['nullable', 'string', 'max:100'],
            'cuenta_devol_compras' => ['nullable', 'string', 'max:100'],
            'aplicar_factor_venta' => ['nullable', 'string', 'max:1'],
            'factor_venta' => ['nullable', 'numeric'],
            'red_precio_con_impto' => ['nullable', 'string', 'max:1'],
            'factor_red_precio_con_impto' => ['nullable', 'numeric'],
            'usuario_creador' => ['nullable', 'string', 'max:100'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:100'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:100'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:100'],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug'),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

            'default_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'sku' => [
                'nullable',
                'string',
                'max:255',
            ],

            'brand' => [
                'nullable',
                'string',
                'max:255',
            ],

            'keyword' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'processed' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no existe.',

            'family_id.exists' => 'La familia seleccionada no existe.',

            'name.required' => 'El nombre del producto es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',

            'slug.unique' => 'El slug ya está en uso por otro producto.',

            'short_description.max' => 'La descripción corta no puede exceder 500 caracteres.',

            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe ser jpg, jpeg, png o webp.',
            'image.max' => 'La imagen no debe pesar más de 4 MB.',

            'default_price.required' => 'El precio es obligatorio.',
            'default_price.numeric' => 'El precio debe ser numérico.',
            'default_price.min' => 'El precio no puede ser menor a 0.',
        ];
    }
}
