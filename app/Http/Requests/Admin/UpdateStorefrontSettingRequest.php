<?php

namespace App\Http\Requests\Admin;

use App\Models\EcommerceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorefrontSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_published' => ['sometimes', 'boolean'],
            'construction_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'construction_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'active_template' => ['sometimes', 'string', Rule::in(EcommerceSetting::availableHomeTemplates())],
            'template' => ['sometimes', 'string', Rule::in(EcommerceSetting::availableHomeTemplates())],
        ];
    }

    public function messages(): array
    {
        return [
            'template.in' => 'La plantilla seleccionada no está disponible.',
            'active_template.in' => 'La plantilla seleccionada no está disponible.',
        ];
    }
}
