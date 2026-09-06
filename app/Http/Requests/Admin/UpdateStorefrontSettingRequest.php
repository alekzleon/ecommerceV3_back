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

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['requires_login_to_purchase', 'hide_prices_for_guests'] as $field) {
            $value = $this->input($field, data_get($this->input('access_rules', []), $field));

            if ($value !== null) {
                $data[$field] = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'is_published' => ['sometimes', 'boolean'],
            'construction_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'construction_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'requires_login_to_purchase' => ['sometimes', 'boolean'],
            'hide_prices_for_guests' => ['sometimes', 'boolean'],
            'access_rules' => ['sometimes', 'array'],
            'access_rules.requires_login_to_purchase' => ['sometimes'],
            'access_rules.hide_prices_for_guests' => ['sometimes'],
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
