<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\AssignmentAccess;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AssignmentWishlist extends Component
{
    use WithPagination;

    #[Locked]
    public int $eventId;

    public function mount(AmigoSecretoEvent $event): void
    {
        $this->eventId = $event->id;
    }

    public function render(): View
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->eventId);
        Gate::authorize('view', $event);
        $buyer = Auth::user();
        abort_unless($buyer instanceof User, 403);
        $access = app(AssignmentAccess::class);
        $assignment = $access->summaryFor($event, $buyer);
        abort_if($assignment === null, 404);
        $wishlist = $access->wishlistFor($event, $buyer);
        $items = $wishlist?->items()->select([
            'id', 'name', 'url', 'image_url', 'price_min', 'price_max', 'priority', 'status',
        ])->orderByRaw("case priority when 'HIGH' then 0 when 'MEDIUM' then 1 else 2 end")
            ->orderByDesc('id')->paginate(12)->through(fn (WishlistItem $item): array => [
                ...$item->only(['name', 'url', 'image_url', 'price_min', 'price_max', 'priority', 'status']),
                // No correlatable item/list/recipient identifiers leave this page.
                'token' => Crypt::encryptString($event->id.':'.$buyer->id.':'.$item->id),
            ]);

        return view('livewire.amigo-secreto.assignment-wishlist', compact('event', 'assignment', 'items'));
    }

    public function markAsBought(string $token): void
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->eventId);
        Gate::authorize('view', $event);
        $buyer = Auth::user();
        abort_unless($buyer instanceof User, 403);
        $wishlist = app(AssignmentAccess::class)->wishlistFor($event, $buyer);
        abort_if($wishlist === null || strlen($token) > 4096, 404);

        try {
            $parts = explode(':', Crypt::decryptString($token));
        } catch (DecryptException) {
            abort(404);
        }
        abort_unless(count($parts) === 3 && $parts[0] === (string) $event->id
            && $parts[1] === (string) $buyer->id && ctype_digit($parts[2]), 404);
        $item = $wishlist->items()->whereKey($parts[2])->firstOrFail();
        $bought = app(AssignmentAccess::class)->markAsBoughtFor($event, $buyer, $item);
        $this->dispatch('notification', [
            'message' => $bought ? __('messages.wishlist.item_bought') : __('assignments.bought'),
            'type' => $bought ? 'success' : 'info',
        ]);
    }
}
