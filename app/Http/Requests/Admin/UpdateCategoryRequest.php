<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : $this->input('name'),
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : $this->input('slug'),
            'code' => $this->filled('code') ? mb_strtoupper(trim((string) $this->input('code'))) : $this->input('code'),
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : $this->input('is_active'),
            'remove_image' => $this->has('remove_image')
                ? filter_var($this->input('remove_image'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : false,
        ]);
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'code' => ['sometimes', 'nullable', 'string', 'max:10', Rule::unique('categories', 'code')->ignore($categoryId)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
