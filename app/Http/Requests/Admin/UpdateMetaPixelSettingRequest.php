<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaPixelSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('pixel_id')) {
            $this->merge([
                'pixel_id' => $this->filled('pixel_id') ? trim((string) $this->input('pixel_id')) : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'pixel_id' => ['nullable', 'string', 'regex:/^[0-9]{5,30}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'pixel_id.regex' => 'El Meta Pixel ID debe contener solo números.',
        ];
    }
}
