<?php

namespace App\Console\Commands;

use App\Jobs\SendAbandonedCartEmailJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectAbandonedCarts extends Command
{
    protected $signature = 'carts:detect-abandoned';
    protected $description = 'Detecta carritos abandonados y dispara el correo';

    public function handle(): int
    {
        $link = "http://localhost:5173/carrito";
        $this->info('Iniciando detección de carritos abandonados...');

        Cart::with(['items', 'user'])
            ->where('status', 'active')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', now()->subMinutes(1))
            ->whereHas('items')
            ->chunkById(100, function ($carts) {
                foreach ($carts as $cart) {
                    $cart->update([
                        'status' => 'abandoned',
                        'abandoned_at' => now(),
                    ]);

                    SendAbandonedCartEmailJob::dispatch($cart->id);

                    $link = "http://localhost:5173/carrito";
                    $message = "Hola, vimos que dejaste productos en tu carrito 🛒\n\n"
                    . "Aún están esperando por ti.\n"
                    . "Recupera tu carrito aquí: {$link}";

                    Log::info('SendWhatsAppMessageJob config debug', [
                        'base_url' => config('services.ultramsg.base_url'),
                        'instance_id' => config('services.ultramsg.instance_id'),
                        'token_exists' => !empty(config('services.ultramsg.token')),
                    ]);
                    
                    SendWhatsAppMessageJob::dispatch('+523332244005', $message);

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
