<?php

namespace App\Http\Requests\Account;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        return [
            'alias' => ['sometimes', 'required', 'string', 'max:100'],
            'street' => ['sometimes', 'required', 'string', 'max:150'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:190'],
            'zip_code' => ['sometimes', 'required', 'string', 'max:20'],
            'neighborhood' => ['sometimes', 'required', 'string', 'max:150'],
            'state' => ['sometimes', 'required', 'string', 'max:150'],
            'delivery_note' => ['sometimes', 'nullable', 'string'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'is_default' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
