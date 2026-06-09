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
            'alias' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:150'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'zip_code' => ['required', 'string', 'max:20'],
            'neighborhood' => ['required', 'string', 'max:150'],
            'state' => ['required', 'string', 'max:150'],
            'delivery_note' => ['nullable', 'string'],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'alias.required' => 'El nombre de la dirección es obligatorio.',
            'street.required' => 'La calle o dirección es obligatoria.',
            'zip_code.required' => 'El código postal es obligatorio.',
            'neighborhood.required' => 'La colonia es obligatoria.',
            'state.required' => 'El estado es obligatorio.',
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
            'zip_code',
            'neighborhood',
            'state',
            'delivery_note',
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
