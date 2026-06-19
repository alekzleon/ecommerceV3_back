<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactMapUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('url')) {
            $this->merge([
                'url' => $this->filled('url') ? trim((string) $this->input('url')) : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.url' => 'El link del mapa debe ser una URL válida.',
            'url.max' => 'El link del mapa no puede tener más de 2048 caracteres.',
        ];
    }
}
