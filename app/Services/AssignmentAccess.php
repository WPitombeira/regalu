<?php

namespace App\Services;

use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

class AssignmentAccess
{
    /** @return array{recipient_name: ?string}|null */
    public function summaryFor(AmigoSecretoEvent $event, User $buyer): ?array
    {
        $draw = $this->drawFor($event, $buyer);
        if ($draw === null) {
            return null;
        }

        $visible = $event->recipient_visibility === 'NAMED'
            || ($event->recipient_visibility === 'ANONYMOUS_UNTIL_REVEAL'
                && in_array($event->status, ['REVEALED', 'COMPLETED'], true)
                && $event->reveal_date !== null && $event->reveal_date->lte(today()));

        return ['recipient_name' => $visible ? $draw->target()->value('name') : null];
    }

    public function wishlistFor(AmigoSecretoEvent $event, User $buyer): ?Wishlist
    {
        $draw = $this->drawFor($event, $buyer);
        abort_if($draw === null, 404);
        $wishlistId = $event->participants()->where('user_id', $draw->target_user_id)
            ->where('status', 'ACCEPTED')->value('wishlist_id');

        return Wishlist::query()->whereKey($wishlistId)->where('user_id', $draw->target_user_id)
            ->where('is_archived', false)->first(['id']);
    }

    public function markAsBoughtFor(AmigoSecretoEvent $event, User $buyer, WishlistItem $item): bool
    {
        // Re-evaluate every access condition inside the final write, not against
        // the models loaded while rendering or validating the action reference.
        $authorizedItem = WishlistItem::query()->whereKey($item->id)
            ->whereExists(function (Builder $query) use ($event, $buyer): void {
                $query->selectRaw('1')->from('amigo_secreto_draws as assignment')
                    ->join('amigo_secreto_events as event', 'event.id', '=', 'assignment.event_id')
                    ->join('amigo_secreto_participants as buyer', 'buyer.event_id', '=', 'event.id')
                    ->join('amigo_secreto_participants as recipient', 'recipient.event_id', '=', 'event.id')
                    ->join('wishlists as wishlist', 'wishlist.id', '=', 'wishlist_items.wishlist_id')
                    ->whereColumn('buyer.user_id', 'assignment.drawer_user_id')
                    ->whereColumn('recipient.user_id', 'assignment.target_user_id')
                    ->whereColumn('recipient.wishlist_id', 'wishlist.id')
                    ->whereColumn('wishlist.user_id', 'recipient.user_id')
                    ->where('assignment.event_id', $event->id)
                    ->where('assignment.drawer_user_id', $buyer->id)
                    ->where('assignment.target_user_id', '!=', $buyer->id)
                    ->where('event.is_archived', false)
                    ->whereIn('event.status', ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'])
                    ->where('buyer.status', 'ACCEPTED')
                    ->where('recipient.status', 'ACCEPTED')
                    ->where('wishlist.is_archived', false);
            });

        $updated = (clone $authorizedItem)->where('status', 'AVAILABLE')->update([
            'status' => 'BOUGHT',
            'bought_by_user_id' => $buyer->id,
            'bought_at' => now(),
        ]);
        if ($updated === 1) {
            return true;
        }

        // Repeated purchases remain harmless; a revoked grant is not reported
        // as an already-bought item and must not disclose its current state.
        abort_unless($authorizedItem->exists(), 404);

        return false;
    }

    private function drawFor(AmigoSecretoEvent $event, User $buyer): ?AmigoSecretoDraw
    {
        Gate::forUser($buyer)->authorize('view', $event);
        if (! in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'], true)
            || ! $event->participants()->where('user_id', $buyer->id)->where('status', 'ACCEPTED')->exists()) {
            return null;
        }

        $draw = $event->draws()->where('drawer_user_id', $buyer->id)
            ->where('target_user_id', '!=', $buyer->id)->first();
        if ($draw === null || ! $event->participants()->where('user_id', $draw->target_user_id)
            ->where('status', 'ACCEPTED')->exists()) {
            return null;
        }

        return $draw;
    }
}
