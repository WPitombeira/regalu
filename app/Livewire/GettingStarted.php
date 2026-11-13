<?php

namespace App\Livewire;

use App\Models\Wishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GettingStarted extends Component
{
    public function render(): View
    {
        abort_unless(Auth::check(), 403);

        $wishlist = Wishlist::query()->where('user_id', Auth::id())->where('is_archived', false)
            ->select(['id', 'name', 'privacy'])->withExists('items')
            ->orderByDesc('items_exists')->orderByDesc('id')->first();

        return view('livewire.getting-started', [
            'wishlist' => $wishlist,
            'step' => $wishlist ? ($wishlist->getAttribute('items_exists') ? 'privacy' : 'add') : 'create',
        ]);
    }
}
