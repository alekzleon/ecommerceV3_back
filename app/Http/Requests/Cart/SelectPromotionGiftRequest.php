<?php

namespace App\Http\Requests\Cart;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SelectPromotionGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'gift_item_id' => ['required', 'integer', 'exists:gift_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'gift_item_id.required' => 'Debes enviar el artículo de regalo.',
            'gift_item_id.integer' => 'El identificador del artículo de regalo no es válido.',
            'gift_item_id.exists' => 'El artículo de regalo no existe.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
