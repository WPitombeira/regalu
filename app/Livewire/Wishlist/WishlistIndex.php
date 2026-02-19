<?php

namespace App\Livewire\Wishlist;

use App\Models\Wishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WishlistIndex extends Component {
    public string $search = '';
    public string $filterType = '';
    public string $filterPrivacy = '';

    public function render(): View {
        $query = Wishlist::where('user_id', Auth::id())
            ->where('is_archived', false);

        if ($this->search !== '') {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->filterType !== '') {
            $query->where('type', $this->filterType);
        }

        if ($this->filterPrivacy !== '') {
            $query->where('privacy', $this->filterPrivacy);
        }

        $wishlists = $query->withCount('items')->latest()->get();

        return view('livewire.wishlist.wishlist-index', [
            'wishlists' => $wishlists,
        ]);
    }
}
