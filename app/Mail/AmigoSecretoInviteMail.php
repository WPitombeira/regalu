<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AmigoSecretoInviteMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public string $eventName,
        public string $joinUrl,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: "You've been invited to {$this->eventName} - Secret Santa!",
        );
    }

    public function content(): Content {
        return new Content(
            view: 'mail.amigo-secreto-invite',
            with: [
                'eventName' => $this->eventName,
                'joinUrl' => $this->joinUrl,
            ],
        );
    }

    public function attachments(): array {
        return [];
    }
}
