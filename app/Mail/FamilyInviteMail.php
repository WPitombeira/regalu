<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FamilyInviteMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public string $familyName,
        public string $joinUrl,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: "You've been invited to join {$this->familyName}!",
        );
    }

    public function content(): Content {
        return new Content(
            view: 'mail.family-invite',
            with: [
                'familyName' => $this->familyName,
                'joinUrl' => $this->joinUrl,
            ],
        );
    }

    public function attachments(): array {
        return [];
    }
}
