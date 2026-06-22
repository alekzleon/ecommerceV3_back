<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'mode' => ['nullable', 'string', 'in:create_only,create_or_update'],
            'import_images' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Debes enviar el archivo Excel.',
            'file.file' => 'El archivo enviado no es válido.',
            'file.mimes' => 'El archivo debe ser un Excel con extensión .xlsx.',
            'file.max' => 'El archivo excede el tamaño máximo permitido.',
            'mode.in' => 'El modo debe ser create_only o create_or_update.',
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
