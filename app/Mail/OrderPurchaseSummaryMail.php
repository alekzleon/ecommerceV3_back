<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderPurchaseSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }

    public function build(): self
    {
        $this->order->loadMissing(['user', 'items', 'payments']);

        return $this->subject('Detalle de tu compra ' . $this->order->number)
            ->view('emails.order-purchase-summary', [
                'order' => $this->order,
                'user' => $this->order->user,
            ]);
    }
}
