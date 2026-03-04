<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public string $userName,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: __("messages.auth.reset_password"),
        );
    }

    public function content(): Content {
        return new Content(
            view: 'mail.password-reset',
            with: [
                'url' => $this->url,
                'userName' => $this->userName,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array {
        return [];
    }
}
