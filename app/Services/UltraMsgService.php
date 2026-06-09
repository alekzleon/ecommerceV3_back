<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UltraMsgService
{
    public function sendChatMessage(string $to, string $body): array
    {
        $baseUrl = rtrim(config('services.ultramsg.base_url'), '/');
        $instanceId = config('services.ultramsg.instance_id');
        $token = config('services.ultramsg.token');
        $timeout = config('services.ultramsg.timeout', 30);

        $url = "{$baseUrl}/{$instanceId}/messages/chat";

        Log::info('URL ULTTRA', [
                        'url:: -  ' => $url,                        
                    ]);


        $payload = [
            'token' => $token,
            'to'    => $to,
            'body'  => $body,
        ];

        $response = Http::asForm()
            ->timeout($timeout)
            ->withoutVerifying() // solo si de verdad lo necesitas; idealmente quítalo en producción
            ->post($url, $payload);

        $data = [
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?: $response->body(),
        ];

        Log::info('UltraMsg sendChatMessage', [
            'url' => $url,
            'to' => $to,
            'status' => $response->status(),
            'response' => $data['body'],
        ]);

        if (! $response->successful()) {
            Log::error('UltraMsg error', [
                'to' => $to,
                'payload' => [
                    'to' => $to,
                    'body' => $body,
                ],
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        }

        return $data;
    }
}