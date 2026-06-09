<?php

namespace App\Http\Requests\Admin;

use App\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->title) : null,
            'subtitle' => $this->filled('subtitle') ? trim((string) $this->subtitle) : null,
            'description' => $this->filled('description') ? trim((string) $this->description) : null,
            'link_url' => $this->filled('link_url') ? trim((string) $this->link_url) : null,
            'button_text' => $this->filled('button_text') ? trim((string) $this->button_text) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'media' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime',
                'max:51200',
            ],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'metadata' => ['nullable', 'array'],
            'media_type' => ['nullable', Rule::in([Banner::MEDIA_TYPE_IMAGE, Banner::MEDIA_TYPE_VIDEO])],
        ];
    }
}
