<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Cart;
use App\Models\SiteSetting;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public Cart $cart;
    public string $recoverUrl;
    /**
     * Create a new message instance.
     */
    public function __construct(Cart $cart, string $recoverUrl)
    {
        $this->cart = $cart;
        $this->recoverUrl = $recoverUrl;
    }

    public function build(): self
    {
        return $this->subject('Tu carrito sigue esperándote')
            ->view('emails.abandoned-cart', [
                'cart' => $this->cart,
                'user' => $this->cart->user,
                'recoverUrl' => $this->recoverUrl,
                'settings' => SiteSetting::current(),
            ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Abandoned Cart Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned-cart',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
