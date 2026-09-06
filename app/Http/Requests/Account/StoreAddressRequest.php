<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedData());
    }

    public function rules(): array
    {
        return [
            'alias' => ['nullable', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'external_number' => ['nullable', 'string', 'max:50'],
            'internal_number' => ['nullable', 'string', 'max:50'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:150'],
            'delivery_note' => ['nullable', 'string'],
            'references' => ['nullable', 'string'],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'street.required' => 'La calle o dirección es obligatoria.',
            'street.max' => 'La dirección no puede superar 500 caracteres.',
            'contact_name.required' => 'El contacto de entrega es obligatorio.',
            'phone.required' => 'El teléfono de entrega es obligatorio.',
        ];
    }

    protected function normalizedData(): array
    {
        $data = [];

        foreach ([
            'alias',
            'street',
            'address_line_2',
            'external_number',
            'internal_number',
            'zip_code',
            'neighborhood',
            'city',
            'state',
            'delivery_note',
            'references',
            'contact_name',
            'phone',
        ] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        if ($this->has('is_default')) {
            $data['is_default'] = filter_var($this->input('is_default'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        return $data;
    }
}
