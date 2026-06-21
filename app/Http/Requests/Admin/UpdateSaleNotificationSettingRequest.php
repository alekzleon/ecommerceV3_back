<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleNotificationSettingRequest extends FormRequest
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

        if ($this->has('admin_email')) {
            $this->merge([
                'admin_email' => $this->filled('admin_email')
                    ? strtolower(trim((string) $this->input('admin_email')))
                    : null,
            ]);
        }

        if ($this->has('admin_whatsapp')) {
            $this->merge([
                'admin_whatsapp' => $this->filled('admin_whatsapp')
                    ? preg_replace('/\D+/', '', (string) $this->input('admin_whatsapp'))
                    : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'send_email' => ['required', 'boolean'],
            'send_whatsapp' => ['required', 'boolean'],
            'admin_email' => ['nullable', 'email', 'max:255', 'required_if:send_email,true'],
            'admin_whatsapp' => ['nullable', 'string', 'min:10', 'max:15', 'required_if:send_whatsapp,true'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_email.required_if' => 'El correo administrador es obligatorio si las notificaciones por correo están activas.',
            'admin_email.email' => 'El correo administrador debe tener un formato válido.',
            'admin_whatsapp.required_if' => 'El WhatsApp administrador es obligatorio si las notificaciones por WhatsApp están activas.',
            'admin_whatsapp.min' => 'El WhatsApp administrador debe tener al menos 10 dígitos.',
            'admin_whatsapp.max' => 'El WhatsApp administrador no puede tener más de 15 dígitos.',
        ];
    }
}
