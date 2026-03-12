<?php

namespace App\Mail;

use App\Models\ProductInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProductInvitation $invitation
    ) {}

    public function envelope(): Envelope
    {
        $productName = $this->invitation->getShareableDisplayName();
        $typeLabel = $this->invitation->getShareableTypeLabel();

        return new Envelope(
            subject: 'Einladung: Zugriff auf '.$typeLabel.' "'.$productName.'"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.product-invitation',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
