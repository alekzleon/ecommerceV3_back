<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $shopUrl
    ) {
    }

    public function build(): self
    {
        $this->order->loadMissing(['user', 'items']);

        return $this->subject('Ya puedes volver a surtir tu pedido')
            ->view('emails.purchase-reminder', [
                'order' => $this->order,
                'user' => $this->order->user,
                'shopUrl' => $this->shopUrl,
            ]);
    }
}
