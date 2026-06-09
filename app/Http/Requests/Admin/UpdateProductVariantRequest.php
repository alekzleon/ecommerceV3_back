<?php

namespace App\Http\Requests\Admin;

use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['sku', 'name'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        foreach (['is_active', 'applies_promotions'] as $field) {
            if ($this->has($field)) {
                $data[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $variantId = $this->route('variant')?->id;

        return [
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'compare_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'applies_promotions' => ['sometimes', 'nullable', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'attribute_value_ids' => ['sometimes', 'nullable', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:variant_attribute_values,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('attribute_value_ids')) {
                return;
            }

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
