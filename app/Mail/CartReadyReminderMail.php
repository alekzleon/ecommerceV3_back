<?php

namespace App\Mail;

use App\Models\Cart;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CartReadyReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cart $cart,
        public string $cartUrl,
        public bool $createdFromLastPurchase = false
    ) {
    }

    public function build(): self
    {
        $this->cart->loadMissing(['user', 'items.product']);

        return $this->subject('Tu carrito está listo para tu compra')
            ->view('emails.cart-ready-reminder', [
                'cart' => $this->cart,
                'user' => $this->cart->user,
                'cartUrl' => $this->cartUrl,
                'createdFromLastPurchase' => $this->createdFromLastPurchase,
            ]);
    }
}
