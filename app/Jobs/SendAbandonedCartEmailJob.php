<?php

namespace App\Jobs;

use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendAbandonedCartEmailJob implements ShouldQueue
{
    use Queueable;

    public int $cartId;

    public function __construct(int $cartId)
    {
        $this->cartId = $cartId;
    }

    public function handle(): void
    {
        $cart = Cart::with(['user', 'items.product'])->find($this->cartId);

        if (!$cart) {
            Log::warning('Carrito no encontrado para email de abandono.', [
                'cart_id' => $this->cartId,
            ]);
            return;
        }

        if ($cart->status !== 'abandoned') {
            Log::info('El carrito ya no está abandonado, no se envía correo.', [
                'cart_id' => $cart->id,
                'status' => $cart->status,
            ]);
            return;
        }

        if (!$cart->user || !$cart->user->email) {
            Log::warning('El carrito abandonado no tiene email.', [
                'cart_id' => $cart->id,
            ]);
            return;
        }

        $recoverUrl = URL::temporarySignedRoute(
            'cart.recover',
            now()->addHours(48),
            ['cart' => $cart->id]
        );

        Mail::to($cart->user->email)->send(
            new AbandonedCartMail($cart, $recoverUrl)
        );

        Log::info('Correo de carrito abandonado enviado.', [
            'cart_id' => $cart->id,
            'email' => $cart->user->email,
        ]);
    }
}