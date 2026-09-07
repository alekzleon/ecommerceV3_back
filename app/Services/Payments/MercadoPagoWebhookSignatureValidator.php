<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

class MercadoPagoWebhookSignatureValidator
{
    public function isValid(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        $signature = $request->header('x-signature');

        if (! is_string($secret) || $secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $parts = collect(explode(',', $signature))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return $key !== null && $value !== null ? [trim($key) => trim($value)] : [];
            });
        $timestamp = $parts->get('ts');
        $receivedHash = $parts->get('v1');
        $dataId = $request->query('data.id', $request->query('data_id'));
        $requestId = $request->header('x-request-id');

        if (! is_string($timestamp) || ! is_string($receivedHash)) {
            return false;
        }

        $segments = [];
        if (filled($dataId)) {
            $segments[] = 'id:'.strtolower((string) $dataId);
        }
        if (filled($requestId)) {
            $segments[] = 'request-id:'.$requestId;
        }
        $segments[] = 'ts:'.$timestamp;
        $manifest = implode(';', $segments).';';
        $expectedHash = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expectedHash, $receivedHash);
    }
}
