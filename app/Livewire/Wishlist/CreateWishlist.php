<?php

namespace App\Livewire\Wishlist;

use App\Models\Wishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class CreateWishlist extends Component
{
    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    #[Rule(['required', 'in:CHRISTMAS,BIRTHDAY,WEDDING,GENERIC'])]
    public string $type = 'GENERIC';

    #[Rule(['required', 'in:PRIVATE,FAMILY,SPECIFIC'])]
    public string $privacy = 'PRIVATE';

    #[Rule(['nullable', 'exists:families,id'])]
    public ?int $family_id = null;

    public function create(): Redirector|RedirectResponse
    {
        abort_unless(Auth::check(), 403);
        $this->validate();

        if ($this->family_id !== null) {
            abort_unless(Auth::user()->families()->whereKey($this->family_id)->where('is_archived', false)->exists(), 403);
        }

        $wishlist = Wishlist::create([
            'user_id' => Auth::id(),
            'family_id' => $this->family_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'type' => $this->type,
            'privacy' => $this->privacy,
            'is_archived' => false,
        ]);

        $this->dispatch('notification', ['message' => __('messages.wishlist.created'), 'type' => 'success']);

        return redirect()->route('wishlists.show', $wishlist);
    }

    public function render(): View
    {
        abort_unless(Auth::check(), 403);
        $families = Auth::user()->families()->where('is_archived', false)->get();

        return view('livewire.wishlist.create-wishlist', [
            'families' => $families,
        ]);
    }
}
