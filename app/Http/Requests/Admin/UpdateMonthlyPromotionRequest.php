<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMonthlyPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['title', 'description', 'link_url', 'button_text'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        if ($this->has('is_active')) {
            $data['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'link_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'button_text' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'image.image' => 'El archivo debe ser una imagen.',
            'link_url.url' => 'El enlace debe ser una URL válida.',
        ];
    }
}
