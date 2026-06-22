<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Http\Request;

class SalesChannelService
{
    public const DEFAULT_CHANNEL = 'online_store';

    public const ALLOWED_CHANNELS = [
        'online_store',
        'whatsapp',
        'instagram',
        'facebook',
        'tiktok',
        'google',
        'email',
        'marketplace',
        'physical_store',
        'admin',
        'referral',
        'other',
    ];

    public function normalize(?string $channel): ?string
    {
        $channel = strtolower(trim((string) $channel));
        $channel = preg_replace('/[^a-z0-9]+/', '_', $channel);
        $channel = trim((string) $channel, '_');

        if ($channel === '') {
            return null;
        }

        return in_array($channel, self::ALLOWED_CHANNELS, true) ? $channel : 'other';
    }

    public function fromRequest(Request $request): ?string
    {
        return $this->normalize(
            $request->input('sales_channel')
                ?: $request->input('channel')
                ?: $request->query('sales_channel')
                ?: $request->query('channel')
                ?: $request->query('utm_source')
        );
    }

    public function trackingFromRequest(Request $request): array
    {
        return collect([
            'channel' => $request->input('channel') ?: $request->query('channel'),
            'sales_channel' => $request->input('sales_channel') ?: $request->query('sales_channel'),
            'utm_source' => $request->input('utm_source') ?: $request->query('utm_source'),
            'utm_medium' => $request->input('utm_medium') ?: $request->query('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign') ?: $request->query('utm_campaign'),
            'utm_content' => $request->input('utm_content') ?: $request->query('utm_content'),
            'utm_term' => $request->input('utm_term') ?: $request->query('utm_term'),
            'referrer' => $request->headers->get('referer'),
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }

    public function applyToCart(Cart $cart, ?string $channel, array $tracking = []): Cart
    {
        $channel = $this->normalize($channel);

        if (! $channel && empty($tracking)) {
            return $cart;
        }

        $metadata = $cart->metadata ?? [];
        $existingTracking = data_get($metadata, 'sales_channel_tracking', []);
        $mergedTracking = array_filter(array_merge($existingTracking, $tracking), fn ($value) => filled($value));

        if (! empty($mergedTracking)) {
            data_set($metadata, 'sales_channel_tracking', $mergedTracking);
        }

        $cart->forceFill([
            'sales_channel' => $channel ?: $cart->sales_channel ?: self::DEFAULT_CHANNEL,
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ])->save();

        return $cart->fresh([
            'user',
            'items.product.category',
            'items.product.family',
        ]);
    }

    public function label(?string $channel): string
    {
        return match ($channel ?: self::DEFAULT_CHANNEL) {
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'google' => 'Google',
            'email' => 'Email',
            'marketplace' => 'Marketplace',
            'physical_store' => 'Tienda fisica',
            'admin' => 'Administrador',
            'referral' => 'Referido',
            'other' => 'Otro',
            default => 'Tienda online',
        };
    }
}
