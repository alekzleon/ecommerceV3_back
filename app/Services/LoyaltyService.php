<?php

namespace App\Services;

use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;

class LoyaltyService
{
    public function settings(): array
    {
        $loyalty = SiteSetting::current()->loyalty ?? [];

        return [
            'first_purchase_discount_enabled' => (bool) data_get($loyalty, 'first_purchase_discount_enabled', false),
            'first_purchase_discount_percentage' => (float) data_get($loyalty, 'first_purchase_discount_percentage', 0),
            'cashback_enabled' => (bool) data_get($loyalty, 'cashback_enabled', false),
            'cashback_earn_percentage' => (float) data_get($loyalty, 'cashback_earn_percentage', 0),
            'cashback_redeem_enabled' => (bool) data_get($loyalty, 'cashback_redeem_enabled', false),
            'cashback_max_redeem_percentage' => (float) data_get($loyalty, 'cashback_max_redeem_percentage', 100),
        ];
    }

    public function hasCompletedPurchase(User $user): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('status', Order::STATUS_PAID)
                    ->orWhere('payment_status', Order::PAYMENT_PAID);
            })
            ->exists();
    }

    public function availableCashback(User $user): float
    {
        $credits = CashbackTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', CashbackTransaction::TYPE_CREDIT)
            ->where('status', CashbackTransaction::STATUS_AVAILABLE)
            ->sum('amount');

        $debits = CashbackTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', CashbackTransaction::TYPE_DEBIT)
            ->whereIn('status', [CashbackTransaction::STATUS_AVAILABLE, CashbackTransaction::STATUS_PENDING])
            ->sum('amount');

        return max(0, round((float) $credits - (float) $debits, 2));
    }

    public function firstPurchaseDiscount(User $user, float $baseAmount): array
    {
        $settings = $this->settings();
        $percentage = (float) $settings['first_purchase_discount_percentage'];
        $enabled = (bool) $settings['first_purchase_discount_enabled'];
        $eligible = $enabled && $percentage > 0 && ! $this->hasCompletedPurchase($user);
        $amount = $eligible ? round($baseAmount * ($percentage / 100), 2) : 0.0;

        return [
            'enabled' => $enabled,
            'eligible' => $eligible,
            'percentage' => $percentage,
            'amount' => $amount,
        ];
    }

    public function cashbackEarn(float $baseAmount): array
    {
        $settings = $this->settings();
        $percentage = (float) $settings['cashback_earn_percentage'];
        $enabled = (bool) $settings['cashback_enabled'];
        $amount = $enabled && $percentage > 0 ? round($baseAmount * ($percentage / 100), 2) : 0.0;

        return [
            'enabled' => $enabled,
            'percentage' => $percentage,
            'amount' => $amount,
        ];
    }

    public function maxRedeemable(User $user, float $cartAmount): float
    {
        $settings = $this->settings();

        if (! $settings['cashback_redeem_enabled']) {
            return 0.0;
        }

        $balance = $this->availableCashback($user);
        $maxPercentage = (float) $settings['cashback_max_redeem_percentage'];
        $maxByCart = $maxPercentage > 0 ? round($cartAmount * ($maxPercentage / 100), 2) : $cartAmount;

        return max(0, round(min($balance, $maxByCart, $cartAmount), 2));
    }
}
