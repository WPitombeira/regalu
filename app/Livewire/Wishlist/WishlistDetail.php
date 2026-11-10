<?php

namespace App\Livewire\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class WishlistDetail extends Component
{
    use WithPagination;

    #[Locked]
    public Wishlist $wishlist;

    public function mount(Wishlist $wishlist): void
    {
        Gate::authorize('view', $wishlist);
        $this->wishlist = $wishlist;
    }

    public function markAsBought(int $itemId): void
    {
        $this->wishlist->refresh();
        Gate::authorize('view', $this->wishlist);

        $item = WishlistItem::where('wishlist_id', $this->wishlist->id)
            ->where('id', $itemId)
            ->where('status', 'AVAILABLE')
            ->firstOrFail();

        // Only non-owners can mark items as bought
        if ($this->wishlist->user_id === Auth::id()) {
            return;
        }

        abort_unless($item->markAsBought(Auth::user()), 409);

        $this->dispatch('notification', ['message' => __('messages.wishlist.item_bought'), 'type' => 'success']);
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->wishlist->refresh();
        $isOwner = $this->wishlist->user_id === $user->id;
        $items = $this->wishlist->visibleItemsFor($user)->latest()->orderByDesc('id')->simplePaginate(20);

        return view('livewire.wishlist.wishlist-detail', [
            'items' => $items,
            'isOwner' => $isOwner,
        ]);
    }
}
