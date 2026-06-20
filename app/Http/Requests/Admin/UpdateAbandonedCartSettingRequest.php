<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAbandonedCartSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['enabled', 'send_email', 'send_whatsapp'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'abandon_after_minutes' => ['required', 'integer', 'min:60', 'max:10080'],
            'recovery_link_expires_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'send_email' => ['required', 'boolean'],
            'send_whatsapp' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'abandon_after_minutes.min' => 'El carrito solo puede marcarse como abandonado después de al menos 60 minutos.',
            'abandon_after_minutes.max' => 'El tiempo máximo permitido es de 10080 minutos.',
            'recovery_link_expires_hours.min' => 'El link de recuperación debe durar al menos 1 hora.',
            'recovery_link_expires_hours.max' => 'El link de recuperación no puede durar más de 720 horas.',
        ];
    }
}
