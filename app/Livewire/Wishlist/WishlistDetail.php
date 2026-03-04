<?php

namespace App\Livewire\Wishlist;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WishlistDetail extends Component {
    public Wishlist $wishlist;

    public function mount(Wishlist $wishlist): void {
        $this->wishlist = $wishlist;
    }

    public function markAsBought(int $itemId): void {
        $item = WishlistItem::where('wishlist_id', $this->wishlist->id)
            ->where('id', $itemId)
            ->where('status', 'AVAILABLE')
            ->firstOrFail();

        // Only non-owners can mark items as bought
        if ($this->wishlist->user_id === Auth::id()) {
            return;
        }

        $item->markAsBought(Auth::user());

        $this->dispatch('notification', ["message" => __("messages.wishlist.item_bought"), "type" => "success"]);
    }

    public function render(): View {
        $isOwner = $this->wishlist->user_id === Auth::id();
        $items = $this->wishlist->items()->latest()->get();

        return view('livewire.wishlist.wishlist-detail', [
            'items' => $items,
            'isOwner' => $isOwner,
        ]);
    }
}
