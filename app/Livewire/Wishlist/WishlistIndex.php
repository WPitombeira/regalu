<?php

namespace App\Livewire\Wishlist;

use App\Models\Wishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class WishlistIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterType = '';

    public string $filterPrivacy = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filterType', 'filterPrivacy'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $query = Wishlist::where('user_id', Auth::id())
            ->where('is_archived', false);

        if ($this->search !== '') {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($this->filterType !== '') {
            $query->where('type', $this->filterType);
        }

        if ($this->filterPrivacy !== '') {
            $query->where('privacy', $this->filterPrivacy);
        }

        $wishlists = $query->withCount('items')->latest()->orderByDesc('id')->simplePaginate(12);

        return view('livewire.wishlist.wishlist-index', [
            'wishlists' => $wishlists,
        ]);
    }
}
