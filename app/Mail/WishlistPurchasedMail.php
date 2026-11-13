<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WishlistPurchasedMail extends Mailable
{
    public function __construct(public readonly string $wishlistUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('wishlist_purchase.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.wishlist-purchased');
    }
}
