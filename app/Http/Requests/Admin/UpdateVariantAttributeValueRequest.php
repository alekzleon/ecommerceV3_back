<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateVariantAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('value')) {
            $data['value'] = $this->filled('value') ? trim((string) $this->value) : null;
        }

        if ($this->has('slug')) {
            $data['slug'] = $this->filled('slug') ? Str::slug(trim((string) $this->slug)) : null;
        }

        if ($this->has('is_active')) {
            $data['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('remove_image')) {
            $data['remove_image'] = filter_var($this->input('remove_image'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $attributeId = $this->route('variantAttribute')?->id;
        $valueId = $this->route('attributeValue')?->id;

        return [
            'value' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('variant_attribute_values', 'slug')
                    ->where('variant_attribute_id', $attributeId)
                    ->ignore($valueId),
            ],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $attribute = $this->route('variantAttribute');

                if (($this->hasFile('image') || $this->boolean('remove_image')) && $attribute?->slug !== 'color') {
                    $validator->errors()->add('image', 'La imagen solo está permitida para valores del atributo Color.');
                }
            },
        ];
    }
}
