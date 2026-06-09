<?php

namespace App\Http\Requests\Account;

use App\Models\CustomerPfrProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPfrProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('cliente') === true;
    }

    public function rules(): array
    {
        return [
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'purchasing_contact_name' => ['nullable', 'string', 'max:255'],
            'quote_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:30'],
            'secondary_contact_name' => ['nullable', 'string', 'max:255'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'business_activity' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in([
                CustomerPfrProfile::PAYMENT_EFECTIVO,
                CustomerPfrProfile::PAYMENT_TRANSFERENCIA,
                CustomerPfrProfile::PAYMENT_CHEQUE,
                CustomerPfrProfile::PAYMENT_EFECTIVO_TRANSFERENCIA,
                CustomerPfrProfile::PAYMENT_OTRO,
            ])],
            'price_list' => ['nullable', 'string', 'max:50'],
            'requires_invoice' => ['nullable', 'boolean'],

            'fiscal_name' => ['nullable', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'fiscal_street' => ['nullable', 'string', 'max:255'],
            'fiscal_external_number' => ['nullable', 'string', 'max:50'],
            'fiscal_internal_number' => ['nullable', 'string', 'max:50'],
            'fiscal_zip_code' => ['nullable', 'string', 'max:20'],
            'fiscal_neighborhood' => ['nullable', 'string', 'max:255'],
            'fiscal_city' => ['nullable', 'string', 'max:255'],
            'fiscal_state' => ['nullable', 'string', 'max:255'],
            'xml_email' => ['nullable', 'email', 'max:255'],
            'cfdi_use' => ['nullable', 'string', 'max:255'],
            'tax_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],

            'delivery_same_as_fiscal' => ['nullable', 'boolean'],
            'delivery_address' => ['nullable', 'string'],
            'delivery_schedule' => ['nullable', 'string', 'max:255'],
            'delivery_observations' => ['nullable', 'string'],
            'distintivo_h' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.in' => 'El método de pago seleccionado no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'email' => 'El campo :attribute debe ser un correo válido.',
            'file' => 'El campo :attribute debe ser un archivo válido.',
            'max' => 'El campo :attribute no debe ser mayor a :max caracteres.',
            'string' => 'El campo :attribute debe ser texto.',
            'tax_certificate.mimes' => 'La constancia de situación fiscal debe ser un PDF.',
            'tax_certificate.max' => 'La constancia de situación fiscal no puede pesar más de 10 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'commercial_name' => 'nombre comercial',
            'purchasing_contact_name' => 'contacto de compras',
            'quote_email' => 'email de contacto',
            'business_phone' => 'teléfono del comercio',
            'secondary_contact_name' => 'segundo contacto',
            'secondary_phone' => 'teléfono 2',
            'business_activity' => 'giro comercial',
            'payment_method' => 'método de pago',
            'price_list' => 'lista',
            'requires_invoice' => 'facturación',
            'fiscal_name' => 'nombre fiscal',
            'rfc' => 'RFC',
            'fiscal_street' => 'calle fiscal',
            'fiscal_external_number' => 'número exterior fiscal',
            'fiscal_internal_number' => 'número interior fiscal',
            'fiscal_zip_code' => 'código postal fiscal',
            'fiscal_neighborhood' => 'colonia fiscal',
            'fiscal_city' => 'ciudad fiscal',
            'fiscal_state' => 'estado fiscal',
            'xml_email' => 'correo XML',
            'cfdi_use' => 'uso de CFDI',
            'tax_certificate' => 'constancia de situación fiscal',
            'delivery_same_as_fiscal' => 'entrega en dirección fiscal',
            'delivery_address' => 'dirección de entrega',
            'delivery_schedule' => 'horario de entrega',
            'delivery_observations' => 'observaciones de entrega',
            'distintivo_h' => 'distintivo H',
        ];
    }
}
