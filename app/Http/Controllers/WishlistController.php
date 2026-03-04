<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WishlistController extends Controller {
    use AuthorizesRequests;

    public function index(): View {
        return view('livewire.wishlist.index');
    }

    public function create(): View {
        return view('livewire.wishlist.create');
    }

    public function show(Wishlist $wishlist): View {
        $this->authorize('view', $wishlist);

        return view('livewire.wishlist.detail', ['wishlist' => $wishlist]);
    }

    public function settings(Wishlist $wishlist): View {
        $this->authorize('update', $wishlist);

        return view('livewire.wishlist.settings', ['wishlist' => $wishlist]);
    }
}
