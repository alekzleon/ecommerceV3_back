<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AddWishlistProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('list_name')) {
            $this->merge([
                'list_name' => trim((string) $this->input('list_name')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'wishlist_id' => ['nullable', 'integer', 'exists:wishlists,id'],
            'list_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('wishlist_id') && ! $this->filled('list_name')) {
                $validator->errors()->add('wishlist_id', 'Debes seleccionar una lista o enviar el nombre de una nueva.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes enviar el producto.',
            'product_id.exists' => 'El producto no existe o no está activo.',
            'wishlist_id.exists' => 'La lista seleccionada no existe.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'ok' => false,
            'message' => 'Error de validación.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
