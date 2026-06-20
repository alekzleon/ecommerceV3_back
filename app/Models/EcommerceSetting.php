<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceSetting extends Model
{
    public const KEY_NAV_TITLE = 'nav_title';
    public const KEY_CONTACT_FAQ_IMAGE = 'contact_faq_image';
    public const KEY_CONTACT_MAP_URL = 'contact_map_url';
    public const KEY_META_PIXEL = 'meta_pixel';
    public const KEY_ABANDONED_CART = 'abandoned_cart';
    public const KEY_HOME_BENEFIT_PREFIX = 'home_benefit_';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, array $default = []): array
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, array $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function abandonedCartSettings(): array
    {
        $settings = array_merge([
            'enabled' => true,
            'abandon_after_minutes' => (int) env('CART_ABANDONED_AFTER_MINUTES', 60),
            'recovery_link_expires_hours' => 48,
            'send_email' => true,
            'send_whatsapp' => true,
        ], static::getValue(static::KEY_ABANDONED_CART, []));

        $settings['abandon_after_minutes'] = max(60, (int) $settings['abandon_after_minutes']);

        return $settings;
    }

    public static function homeBenefitKey(int $benefit): string
    {
        return self::KEY_HOME_BENEFIT_PREFIX . $benefit;
    }

    public static function homeBenefitValue(int $benefit): array
    {
        return array_merge([
            'benefit' => $benefit,
            'title' => null,
            'text' => null,
            'icon_disk' => 'public',
            'icon_path' => null,
        ], static::getValue(static::homeBenefitKey($benefit), []));
    }

    public static function homeBenefits(): array
    {
        return collect([1, 2, 3])
            ->map(fn (int $benefit) => static::homeBenefitValue($benefit))
            ->values()
            ->all();
    }
}
