<?php

namespace App\Http\Resources\Payments;

use App\Enums\PaymentConnectionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentProviderConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $connected = $this->status === PaymentConnectionStatus::Connected;

        return [
            'provider' => $this->provider,
            'connected' => $connected,
            'status' => $this->status?->value ?? PaymentConnectionStatus::Disconnected->value,
            'provider_account_id' => $this->provider_account_id,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'requires_reauthorization' => $this->status === PaymentConnectionStatus::ReauthorizationRequired,
        ];
    }
}
