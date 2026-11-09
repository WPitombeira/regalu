<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class WishlistController extends Controller
{
    public function index(): View
    {
        return view('livewire.wishlist.index');
    }

    public function create(): View
    {
        return view('livewire.wishlist.create');
    }

    #[Authorize('view', 'wishlist')]
    public function show(Wishlist $wishlist): View
    {
        return view('livewire.wishlist.detail', ['wishlist' => $wishlist]);
    }

    #[Authorize('update', 'wishlist')]
    public function settings(Wishlist $wishlist): View
    {
        return view('livewire.wishlist.settings', ['wishlist' => $wishlist]);
    }
}
