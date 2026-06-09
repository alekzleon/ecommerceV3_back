<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGiftItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->name) : null,
            'code' => $this->filled('code') ? trim((string) $this->code) : null,
            'description' => $this->filled('description') ? trim((string) $this->description) : null,
            'unit_label' => $this->filled('unit_label') ? trim((string) $this->unit_label) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:120', Rule::unique('gift_items', 'code')],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del artículo de regalo es obligatorio.',
            'code.unique' => 'El código del artículo de regalo ya existe.',
            'image.image' => 'El archivo debe ser una imagen.',
        ];
    }
}
