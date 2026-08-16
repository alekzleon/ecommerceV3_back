<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $title,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public array $details = [],
        public string $brandName = 'Cloudi Shop',
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.account-notification', [
                'subjectLine' => $this->subjectLine,
                'title' => $this->title,
                'message' => $this->message,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'details' => $this->details,
                'brandName' => $this->brandName,
            ]);
    }
}
