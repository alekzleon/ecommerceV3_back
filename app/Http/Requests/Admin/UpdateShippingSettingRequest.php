<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['enabled', 'free_shipping_minimum_enabled'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }

        if ($this->has('default_cost')) {
            $this->merge([
                'default_cost' => $this->input('default_cost') === '' ? null : $this->input('default_cost'),
            ]);
        }

        if ($this->has('free_shipping_minimum')) {
            $this->merge([
                'free_shipping_minimum' => $this->input('free_shipping_minimum') === '' ? null : $this->input('free_shipping_minimum'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'default_cost' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'free_shipping_minimum_enabled' => ['sometimes', 'boolean'],
            'free_shipping_minimum' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $enabled = $this->boolean('free_shipping_minimum_enabled');

            if ($enabled && $this->input('free_shipping_minimum') === null) {
                $validator->errors()->add(
                    'free_shipping_minimum',
                    'Indica el monto mínimo para activar el envío gratis.'
                );
            }
        });
    }
}
