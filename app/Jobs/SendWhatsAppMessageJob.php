<?php

namespace App\Jobs;

use App\Services\UltraMsgService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $to,
        public string $body
    ) {}

    public function handle(UltraMsgService $ultraMsgService): void
    {
        $result = $ultraMsgService->sendChatMessage($this->to, $this->body);

        if (! $result['success']) {
            throw new \Exception('No se pudo enviar el WhatsApp con UltraMsg.');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendWhatsAppMessageJob failed', [
            'to' => $this->to,
            'body' => $this->body,
            'error' => $exception->getMessage(),
        ]);
    }
}