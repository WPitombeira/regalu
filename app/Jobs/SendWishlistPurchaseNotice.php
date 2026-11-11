<?php

namespace App\Jobs;

use App\Mail\WishlistPurchasedMail;
use App\Models\Wishlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(10, 60, 180)]
class SendWishlistPurchaseNotice implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $itemId,
        public readonly int $wishlistId,
        public readonly int $ownerId,
    ) {}

    public function handle(): void
    {
        // Never redirect a delayed notice to a different list or a new owner.
        $wishlist = Wishlist::query()->whereKey($this->wishlistId)
            ->where('user_id', $this->ownerId)->where('is_archived', false)
            ->whereHas('items', fn (Builder $items) => $items->whereKey($this->itemId)->whereIn('status', ['BOUGHT', 'RECEIVED']))
            ->with('user:id,email,locale')->first();
        $owner = $wishlist?->user;
        if (! $owner) {
            return;
        }

        $locale = in_array($owner->locale, ['en', 'pt_BR'], true) ? $owner->locale : 'en';
        Mail::to($owner->email)->locale($locale)->send(new WishlistPurchasedMail(
            route('wishlists.show', ['wishlist' => $this->wishlistId]),
        ));
    }
}
