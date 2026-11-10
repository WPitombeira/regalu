<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FamilyInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $familyName,
        public string $joinUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('family_invitations.mail_subject', ['family' => $this->familyName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.family-invite',
            with: [
                'familyName' => $this->familyName,
                'joinUrl' => $this->joinUrl,
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
