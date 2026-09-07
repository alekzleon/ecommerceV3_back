<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PlatformPaymentFeeSetting;
use App\Models\Tenant;

class MarketplaceFeeCalculator
{
    public function calculateCents(Tenant $tenant, Order $order, string $provider): int
    {
        $totalCents = $this->toCents($order->total);
        if ($totalCents <= 0) {
            return 0;
        }

        $setting = PlatformPaymentFeeSetting::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->whereIn('plan_key', [(string) $tenant->plan_key, '*'])
            ->orderByRaw('plan_key = ? desc', [(string) $tenant->plan_key])
            ->first();

        if (! $setting || $setting->fee_type === PlatformPaymentFeeSetting::TYPE_NONE) {
            return 0;
        }

        $feeCents = match ($setting->fee_type) {
            PlatformPaymentFeeSetting::TYPE_PERCENTAGE => $this->percentageFeeCents($totalCents, (string) $setting->fee_value),
            PlatformPaymentFeeSetting::TYPE_FIXED => $this->toCents($setting->fee_value),
            default => 0,
        };

        return min($totalCents, max(0, $feeCents));
    }

    public function calculate(Tenant $tenant, Order $order, string $provider): string
    {
        return number_format($this->calculateCents($tenant, $order, $provider) / 100, 2, '.', '');
    }

    private function percentageFeeCents(int $totalCents, string $percentage): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $percentage)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');
        $percentageScale = ((int) $whole * 10000) + (int) str_pad($fraction, 4, '0');

        return intdiv(($totalCents * $percentageScale) + 500000, 1000000);
    }

    private function toCents(mixed $amount): int
    {
        $value = rtrim(rtrim((string) $amount, '0'), '.');
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
