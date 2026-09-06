<?php

namespace App\Http\Requests\Account;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        return [
            'alias' => ['sometimes', 'nullable', 'string', 'max:100'],
            'street' => ['sometimes', 'required', 'string', 'max:500'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:190'],
            'external_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'internal_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:150'],
            'state' => ['sometimes', 'nullable', 'string', 'max:150'],
            'delivery_note' => ['sometimes', 'nullable', 'string'],
            'references' => ['sometimes', 'nullable', 'string'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'is_default' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
