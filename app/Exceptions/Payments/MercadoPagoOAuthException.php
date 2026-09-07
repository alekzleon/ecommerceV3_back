<?php

namespace App\Exceptions\Payments;

class MercadoPagoOAuthException extends PaymentConnectionException
{
    public function __construct(
        protected string $reason = 'oauth_failed',
        string $message = 'No fue posible conectar Mercado Pago.'
    ) {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
