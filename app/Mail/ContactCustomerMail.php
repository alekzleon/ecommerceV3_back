<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{name: string, email: string, message: string} $contact
     */
    public function __construct(
        public array $contact
    ) {
    }

    public function build(): self
    {
        return $this->subject('Recibimos tu mensaje')
            ->view('emails.contact-customer', [
                'contact' => $this->contact,
            ]);
    }
}
