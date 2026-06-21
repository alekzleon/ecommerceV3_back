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
        public Order $order,
        public bool $adminNotification = false
    ) {
    }

    public function build(): self
    {
        $this->order->loadMissing(['user', 'items', 'payments']);

        $subject = $this->adminNotification
            ? 'Nueva compra en Cloudi Shop ' . $this->order->number
            : 'Detalle de tu compra ' . $this->order->number;

        return $this->subject($subject)
            ->view('emails.order-purchase-summary', [
                'order' => $this->order,
                'user' => $this->order->user,
                'isAdminNotification' => $this->adminNotification,
            ]);
    }
}
