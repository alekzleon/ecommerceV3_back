<?php

namespace App\Mail;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl
    ) {
    }

    public function build(): self
    {
        return $this->subject('Recupera tu contraseña')
            ->view('emails.password-reset-link', [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'settings' => SiteSetting::current(),
            ]);
    }
}
