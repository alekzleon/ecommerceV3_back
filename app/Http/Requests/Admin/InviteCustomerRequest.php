<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InviteCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commercial_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:users,username'],
            'whatsapp' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'commercial_name.required' => 'El nombre comercial es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Debes ingresar un correo válido.',
            'email.unique' => 'El correo ya está registrado.',
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
        ];
    }
}
