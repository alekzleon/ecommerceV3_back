<?php

namespace App\Http\Requests\Admin;

use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->filled('sku') ? trim((string) $this->sku) : null,
            'name' => $this->filled('name') ? trim((string) $this->name) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
            'applies_promotions' => $this->has('applies_promotions')
                ? filter_var($this->input('applies_promotions'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255', Rule::unique('product_variants', 'sku')],
            'name' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'applies_promotions' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:variant_attribute_values,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = collect($this->input('attribute_value_ids', []))->filter()->unique()->values();

            if ($ids->isEmpty()) {
                return;
            }

            $product = $this->route('product');
            $productId = is_object($product) ? $product->id : $product;

            $values = VariantAttributeValue::query()
                ->with('attribute')
                ->whereIn('id', $ids)
                ->whereHas('attribute', fn ($query) => $query->where('product_id', $productId))
                ->get();

            if ($values->count() !== $ids->count()) {
                $validator->errors()->add(
                    'attribute_value_ids',
                    'Todos los valores de atributo deben pertenecer al producto de la variante.'
                );

                return;
            }

            $attributeIds = $values->pluck('variant_attribute_id');

            if ($attributeIds->count() !== $attributeIds->unique()->count()) {
                $validator->errors()->add(
                    'attribute_value_ids',
                    'No puedes seleccionar más de un valor para el mismo atributo en una variante.'
                );
            }
        });
    }
}
