<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductGalleryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductGalleryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->title) : null,
            'description' => $this->filled('description') ? trim((string) $this->description) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'media' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,video/mp4,video/webm,video/quicktime',
                'max:51200',
            ],
            'media_type' => ['nullable', Rule::in([
                ProductGalleryItem::MEDIA_TYPE_IMAGE,
                ProductGalleryItem::MEDIA_TYPE_VIDEO,
            ])],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
