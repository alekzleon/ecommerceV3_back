<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderBannersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'banners' => ['required', 'array', 'min:1'],
            'banners.*.id' => ['required', 'integer', 'exists:banners,id'],
            'banners.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
