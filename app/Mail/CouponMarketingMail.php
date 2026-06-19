<?php

namespace App\Mail;

use App\Models\Coupon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CouponMarketingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Coupon $coupon,
        public ?string $customMessage = null,
        public ?string $customSubject = null
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->customSubject ?: "Cupón {$this->coupon->code} disponible")
            ->view('emails.coupon-marketing');
    }
}
