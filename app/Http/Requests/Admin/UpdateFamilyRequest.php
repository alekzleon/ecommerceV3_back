<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateFamilyRequest extends FormRequest
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
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : $this->input('is_active'),
        ]);
    }

    public function rules(): array
    {
        $family = $this->route('family');
        $categoryId = $this->filled('category_id')
            ? $this->integer('category_id')
            : (int) $family?->category_id;

        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('families', 'slug')
                    ->ignore($family?->id)
                    ->where(fn ($query) => $query->where('category_id', $categoryId)),
            ],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
