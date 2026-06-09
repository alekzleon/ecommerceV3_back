<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactAdminMail extends Mailable
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
        return $this->subject('Nuevo mensaje de contacto')
            ->replyTo($this->contact['email'], $this->contact['name'])
            ->view('emails.contact-admin', [
                'contact' => $this->contact,
            ]);
    }
}
