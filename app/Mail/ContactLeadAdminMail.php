<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactLeadAdminMail extends Mailable
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
        return $this->subject('Nuevo registro de contacto')
            ->replyTo($this->lead['email'], $this->lead['name'])
            ->view('emails.contact-lead-admin', [
                'lead' => $this->lead,
            ]);
    }
}
