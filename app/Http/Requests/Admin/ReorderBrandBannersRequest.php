<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderBrandBannersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'banners' => ['required', 'array', 'min:1'],
            'banners.*.id' => ['required', 'integer', 'exists:brand_banners,id'],
            'banners.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
