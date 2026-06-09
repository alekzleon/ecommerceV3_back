<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerPfrProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        $customerId = is_object($customer) ? $customer->id : $customer;

        return [
            // Usuario
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($customerId),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($customerId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],

            // Perfil comercial
            'profile.id_microsip' => ['nullable', 'string', 'max:100'],
            'profile.status' => ['required', Rule::in([
                'activo',
                'baja',
                'suspendido_credito',
            ])],
            'profile.credit_limit' => ['nullable', 'numeric', 'min:0'],
            'profile.credit_days' => ['nullable', 'integer', 'min:0'],
            'profile.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profile.assigned_seller_id' => ['nullable', 'integer'],
            'profile.route' => ['nullable', 'string', 'max:100'],
            'profile.notes' => ['nullable', 'string'],

            // Perfil PFR editable desde admin por ahora
            'customer_pfr_profile.price_list' => ['sometimes', 'nullable', Rule::in([
                'Lista 1',
                'Lista 3',
                'Lista 5',
                'Lista 20',
            ])],

            // Dirección default
            'address.alias' => ['nullable', 'string', 'max:100'],
            'address.contact_name' => ['nullable', 'string', 'max:150'],
            'address.street' => ['nullable', 'string', 'max:150'],
            'address.external_number' => ['nullable', 'string', 'max:50'],
            'address.internal_number' => ['nullable', 'string', 'max:50'],
            'address.neighborhood' => ['nullable', 'string', 'max:150'],
            'address.zip_code' => ['nullable', 'string', 'max:20'],
            'address.city' => ['nullable', 'string', 'max:150'],
            'address.state' => ['nullable', 'string', 'max:150'],
            'address.references' => ['nullable', 'string'],
            'address.phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'username.required' => 'El username es obligatorio.',
            'username.unique' => 'El username ya está en uso.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Debes ingresar un correo válido.',
            'email.unique' => 'El correo ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'profile.status.required' => 'El status del cliente es obligatorio.',
            'profile.status.in' => 'El status seleccionado no es válido.',
            'profile.credit_limit.numeric' => 'El límite de crédito debe ser numérico.',
            'profile.credit_days.integer' => 'Los días de crédito deben ser un número entero.',
            'profile.discount_percent.numeric' => 'El descuento debe ser numérico.',
            'profile.discount_percent.max' => 'El descuento no puede ser mayor a 100.',
            'customer_pfr_profile.price_list.in' => 'La lista seleccionada no es válida.',
        ];
    }
}
