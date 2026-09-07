<?php

namespace App\Services\Payments;

use App\Models\Order;

class MercadoPagoPaymentStatusMapper
{
    public function map(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'approved' => Order::PAYMENT_PAID,
            'pending', 'in_process', 'authorized' => Order::PAYMENT_PENDING,
            'rejected' => Order::PAYMENT_FAILED,
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'charged_back' => 'chargeback',
            default => Order::PAYMENT_PENDING,
        };
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, [Order::PAYMENT_PAID, Order::PAYMENT_FAILED, 'cancelled', 'refunded', 'chargeback'], true);
    }
}
