<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpeg,png,webp,gif,svg', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'El logo es obligatorio.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser jpeg, png, webp, gif o svg.',
            'logo.max' => 'El logo no puede pesar más de 10 MB.',
        ];
    }
}
