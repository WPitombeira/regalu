<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AmigoSecretoAssignmentMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public string $drawerName,
        public string $targetName,
        public ?string $wishlistUrl = null,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: "You drew {$this->targetName}! - Secret Santa Assignment",
        );
    }

    public function content(): Content {
        return new Content(
            view: 'mail.amigo-secreto-assignment',
            with: [
                'drawerName' => $this->drawerName,
                'targetName' => $this->targetName,
                'wishlistUrl' => $this->wishlistUrl,
            ],
        );
    }

    public function attachments(): array {
        return [];
    }
}
