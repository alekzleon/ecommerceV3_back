<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('stripe_enabled')) {
            $this->merge([
                'stripe_enabled' => filter_var($this->input('stripe_enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'stripe_enabled' => ['sometimes', 'boolean'],
            'methods' => ['sometimes', 'array'],
            'methods.stripe' => ['sometimes', 'array'],
            'methods.stripe.enabled' => ['sometimes', 'boolean'],
            'default_method' => ['sometimes', 'nullable', Rule::in(['stripe'])],
        ];
    }
}
