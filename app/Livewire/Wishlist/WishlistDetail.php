<?php

namespace App\Livewire\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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

    #[On('item-added')]
    public function refreshItems(): void
    {
        $this->resetPage();
    }

    public function markAsBought(int $itemId): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->wishlist->refresh();
        Gate::authorize('view', $this->wishlist);
        // Reject owner probes before any status-dependent lookup.
        abort_if($this->wishlist->user_id === $user->id || $this->wishlist->is_archived, 403);

        $item = WishlistItem::where('wishlist_id', $this->wishlist->id)
            ->where('id', $itemId)
            ->where('status', 'AVAILABLE')
            ->firstOrFail();

        abort_unless($item->markAsBought($user), 409);

        $this->dispatch('notification', ['message' => __('messages.wishlist.item_bought'), 'type' => 'success']);
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->wishlist->refresh();
        $isOwner = $this->wishlist->user_id === $user->id;
        $items = $this->wishlist->visibleItemsFor($user)
            ->orderByRaw("CASE priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END")
            ->latest()->orderByDesc('id')->simplePaginate(20);

        return view('livewire.wishlist.wishlist-detail', [
            'items' => $items,
            'isOwner' => $isOwner,
            'purchasedCount' => $isOwner
                ? $this->wishlist->items()->whereIn('status', ['BOUGHT', 'RECEIVED'])->count()
                : null,
        ]);
    }
}
