<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreVariantAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'value' => $this->filled('value') ? trim((string) $this->value) : null,
            'slug' => $this->filled('slug') ? Str::slug(trim((string) $this->slug)) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    public function rules(): array
    {
        $attributeId = $this->route('variantAttribute')?->id;

        return [
            'value' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('variant_attribute_values', 'slug')->where('variant_attribute_id', $attributeId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $attribute = $this->route('variantAttribute');

                if ($this->hasFile('image') && $attribute?->slug !== 'color') {
                    $validator->errors()->add('image', 'La imagen solo está permitida para valores del atributo Color.');
                }
            },
        ];
    }
}
