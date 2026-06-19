<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderContactFaqsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'faqs' => ['required', 'array', 'min:1'],
            'faqs.*.id' => ['required', 'integer', 'exists:contact_faqs,id'],
            'faqs.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
