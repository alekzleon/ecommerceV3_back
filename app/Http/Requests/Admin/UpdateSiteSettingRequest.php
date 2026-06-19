<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateSiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        $aliases = [
            'site_title' => ['site_title', 'siteTitle', 'title', 'name', 'identity.site_title', 'identity.siteTitle', 'identity.title', 'identity.name'],
            'email' => ['email', 'contact.email'],
            'address' => ['address', 'contact.address'],
            'forms_recipient_email' => ['forms_recipient_email', 'formsRecipientEmail', 'forms.recipient_email', 'forms.recipientEmail'],
            'google_analytics_pixel' => ['google_analytics_pixel', 'googleAnalyticsPixel', 'pixels.google_analytics', 'pixels.googleAnalytics'],
            'meta_pixel' => ['meta_pixel', 'metaPixel', 'pixels.meta'],
        ];

        foreach ($aliases as $field => $paths) {
            if ($this->hasAnyPath($paths)) {
                $value = $this->firstPathValue($paths);
                $data[$field] = filled($value) ? trim((string) $value) : null;
            }
        }

        if ($this->hasAnyPath(['contact_numbers', 'contactNumbers', 'contact.numbers'])) {
            $data['contact_numbers'] = $this->normalizeArrayValue(
                $this->firstPathValue(['contact_numbers', 'contactNumbers', 'contact.numbers'])
            );
        }

        if ($this->hasAnyPath(['social_links', 'socialLinks', 'social'])) {
            $data['social_links'] = $this->normalizeArrayValue(
                $this->firstPathValue(['social_links', 'socialLinks', 'social'])
            );
        }

        if ($this->hasAnyPath(['meta', 'seo'])) {
            $data['meta'] = $this->normalizeArrayValue(
                $this->firstPathValue(['meta', 'seo'])
            );
        }

        if ($this->hasAnyPath(['loyalty', 'fidelidad'])) {
            $data['loyalty'] = $this->normalizeArrayValue(
                $this->firstPathValue(['loyalty', 'fidelidad'])
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'site_title' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif,svg', 'max:10240'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg,webp', 'max:2048'],
            'contact_numbers' => ['nullable', 'array', 'max:2'],
            'contact_numbers.*' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'social_links' => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'url', 'max:2048'],
            'social_links.facebook' => ['nullable', 'url', 'max:2048'],
            'social_links.tiktok' => ['nullable', 'url', 'max:2048'],
            'forms_recipient_email' => ['nullable', 'email', 'max:255'],
            'meta' => ['nullable', 'array'],
            'meta.title' => ['nullable', 'string', 'max:255'],
            'meta.description' => ['nullable', 'string', 'max:500'],
            'meta.keywords' => ['nullable', 'array'],
            'meta.keywords.*' => ['nullable', 'string', 'max:80'],
            'google_analytics_pixel' => ['nullable', 'string', 'max:20000'],
            'meta_pixel' => ['nullable', 'string', 'max:20000'],
            'loyalty' => ['nullable', 'array'],
            'loyalty.first_purchase_discount_enabled' => ['nullable', 'boolean'],
            'loyalty.first_purchase_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'loyalty.cashback_enabled' => ['nullable', 'boolean'],
            'loyalty.cashback_earn_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'loyalty.cashback_redeem_enabled' => ['nullable', 'boolean'],
            'loyalty.cashback_max_redeem_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'og_image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'identity.logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif,svg', 'max:10240'],
            'identity.favicon' => ['nullable', 'file', 'mimes:ico,png,svg,webp', 'max:2048'],
            'seo.og_image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_numbers.max' => 'Solo puedes agregar hasta 2 números de contacto.',
            'email.email' => 'El email debe tener un formato válido.',
            'forms_recipient_email.email' => 'El email de recepción debe tener un formato válido.',
            'social_links.*.url' => 'Las redes sociales deben ser URLs válidas.',
        ];
    }

    protected function hasAnyPath(array $paths): bool
    {
        return collect($paths)->contains(fn (string $path) => $this->hasPath($path));
    }

    protected function hasPath(string $path): bool
    {
        if ($this->has($path)) {
            return true;
        }

        return Arr::has($this->all(), $path);
    }

    protected function firstPathValue(array $paths): mixed
    {
        foreach ($paths as $path) {
            if ($this->has($path)) {
                return $this->input($path);
            }

            if (Arr::has($this->all(), $path)) {
                return data_get($this->all(), $path);
            }
        }

        return null;
    }

    protected function normalizeArrayValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
