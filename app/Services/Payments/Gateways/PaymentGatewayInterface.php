<?php

namespace App\Services\Payments\Gateways;

interface PaymentGatewayInterface
{
    public function provider(): string;

    public function createCheckout(array $payload = []): array;

    public function getPayment(string $paymentId): array;

    public function refund(string $paymentId, array $payload = []): array;

    public function handleWebhook(array $payload = []): array;
}
