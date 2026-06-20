<?php

namespace App\Console\Commands;

use App\Jobs\SendAbandonedCartEmailJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\EcommerceSetting;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectAbandonedCarts extends Command
{
    protected $signature = 'carts:detect-abandoned';
    protected $description = 'Detecta carritos abandonados y dispara el correo';

    public function handle(): int
    {
        $this->info('Iniciando detección de carritos abandonados...');
        $settings = EcommerceSetting::abandonedCartSettings();

        if (! (bool) data_get($settings, 'enabled', true)) {
            $this->info('La detección de carritos abandonados está desactivada.');

            return self::SUCCESS;
        }

        $abandonAfterMinutes = max(60, (int) data_get($settings, 'abandon_after_minutes', 60));
        $sendEmail = (bool) data_get($settings, 'send_email', true);
        $sendWhatsApp = (bool) data_get($settings, 'send_whatsapp', true);

        Cart::with(['items', 'user'])
            ->where('status', 'active')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', now()->subMinutes($abandonAfterMinutes))
            ->whereNull('abandoned_notified_at')
            ->whereHas('items')
            ->chunkById(100, function ($carts) use ($sendEmail, $sendWhatsApp) {
                foreach ($carts as $cart) {
                    $cart->update([
                        'status' => 'abandoned',
                        'abandoned_at' => now(),
                        'abandoned_notified_at' => now(),
                    ]);

                    if ($sendEmail) {
                        SendAbandonedCartEmailJob::dispatch($cart->id);
                    }

                    if ($sendWhatsApp) {
                        $link = rtrim(config('services.frontend.url'), '/') . '/carrito';
                        $message = "Hola, vimos que dejaste productos en tu carrito 🛒\n\n"
                        . "Aún están esperando por ti.\n"
                        . "Recupera tu carrito aquí: {$link}";

                        Log::info('SendWhatsAppMessageJob config debug', [
                            'base_url' => config('services.ultramsg.base_url'),
                            'instance_id' => config('services.ultramsg.instance_id'),
                            'token_exists' => !empty(config('services.ultramsg.token')),
                        ]);

                        SendWhatsAppMessageJob::dispatch('+523332244005', $message);
                        $cart->forceFill(['abandoned_whatsapp_sent_at' => now()])->save();
                    }

                    Log::info('Carrito marcado como abandonado y job enviado.', [
                        'cart_id' => $cart->id,
                        'user_id' => $cart->user_id,
                    ]);
                }
            });

        $this->info('Proceso finalizado.');

        return self::SUCCESS;
    }
}
