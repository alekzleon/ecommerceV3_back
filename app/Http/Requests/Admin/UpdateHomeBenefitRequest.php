<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateHomeBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('titulo') && ! $this->has('title')) {
            $this->merge(['title' => $this->input('titulo')]);
        }

        if ($this->has('texto') && ! $this->has('text')) {
            $this->merge(['text' => $this->input('texto')]);
        }

        if ($this->has('eliminar_icono') && ! $this->has('remove_icon')) {
            $this->merge(['remove_icon' => $this->input('eliminar_icono')]);
        }

        foreach (['title', 'text'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => $this->filled($field) ? trim((string) $this->input($field)) : null,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'text' => ['sometimes', 'nullable', 'string', 'max:300'],
            'icon' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif,svg', 'max:5120'],
            'icono' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif,svg', 'max:5120'],
            'remove_icon' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'El título no puede tener más de 120 caracteres.',
            'text.max' => 'El texto no puede tener más de 300 caracteres.',
            'icon.image' => 'El icono debe ser una imagen.',
            'icon.mimes' => 'El icono debe ser jpeg, png, webp, gif o svg.',
            'icon.max' => 'El icono no puede pesar más de 5 MB.',
            'icono.image' => 'El icono debe ser una imagen.',
            'icono.mimes' => 'El icono debe ser jpeg, png, webp, gif o svg.',
            'icono.max' => 'El icono no puede pesar más de 5 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasInput = $this->hasAny([
                'title',
                'titulo',
                'text',
                'texto',
                'remove_icon',
                'eliminar_icono',
            ]);

            if (! $hasInput && ! $this->hasFile('icon') && ! $this->hasFile('icono')) {
                $validator->errors()->add('benefit', 'Debes enviar al menos un campo para actualizar el beneficio.');
            }
        });
    }
}
