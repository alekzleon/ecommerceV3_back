<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_title',
        'logo_disk',
        'logo_path',
        'favicon_disk',
        'favicon_path',
        'contact_numbers',
        'email',
        'address',
        'social_links',
        'forms_recipient_email',
        'meta',
        'google_analytics_pixel',
        'meta_pixel',
        'loyalty',
        'og_image_disk',
        'og_image_path',
    ];

    protected $casts = [
        'contact_numbers' => 'array',
        'social_links' => 'array',
        'meta' => 'array',
        'loyalty' => 'array',
    ];

    protected $appends = [
        'logo_url',
        'favicon_url',
        'og_image_url',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->publicUrl($this->logo_path, $this->logo_disk)
        );
    }

    protected function faviconUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->publicUrl($this->favicon_path, $this->favicon_disk)
        );
    }

    protected function ogImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->publicUrl($this->og_image_path, $this->og_image_disk)
        );
    }

    protected function publicUrl(?string $path, ?string $disk): ?string
    {
        return $path ? Storage::disk($disk ?: 'public')->url($path) : null;
    }
}
