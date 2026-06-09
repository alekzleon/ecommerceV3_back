<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactLeadCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{name: string, email: string} $lead
     */
    public function __construct(
        public array $lead
    ) {
    }

    public function build(): self
    {
        return $this->subject('Recibimos tus datos')
            ->view('emails.contact-lead-customer', [
                'lead' => $this->lead,
            ]);
    }
}
