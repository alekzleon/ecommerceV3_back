<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductGalleryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductGalleryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['title', 'description'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        if ($this->has('is_active')) {
            $data['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'media' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,video/mp4,video/webm,video/quicktime',
                'max:51200',
            ],
            'media_type' => ['sometimes', 'nullable', Rule::in([
                ProductGalleryItem::MEDIA_TYPE_IMAGE,
                ProductGalleryItem::MEDIA_TYPE_VIDEO,
            ])],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
