<?php

namespace App\Livewire\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistShare;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class WishlistSettings extends Component
{
    #[Locked]
    public Wishlist $wishlist;

    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    #[Rule(['required', 'in:PRIVATE,FAMILY,SPECIFIC'])]
    public string $privacy = 'PRIVATE';

    #[Rule(['required', 'in:CHRISTMAS,BIRTHDAY,WEDDING,GENERIC'])]
    public string $type = 'GENERIC';

    #[Rule(['nullable', 'email'])]
    public string $shareEmail = '';

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('wishlist_forms.attribute_name'),
            'description' => __('wishlist_forms.attribute_description'),
            'type' => __('wishlist_forms.attribute_type'),
            'privacy' => __('wishlist_forms.attribute_privacy'),
            'shareEmail' => __('wishlist_forms.attribute_email'),
        ];
    }

    public function mount(Wishlist $wishlist): void
    {
        Gate::authorize('update', $wishlist);
        $this->wishlist = $wishlist;
        $this->name = $wishlist->name;
        $this->description = $wishlist->description ?? '';
        $this->privacy = $wishlist->privacy;
        $this->type = $wishlist->type;
    }

    public function update(): void
    {
        $this->authorizeUpdate();
        $this->validate();

        $this->wishlist->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'privacy' => $this->privacy,
            'type' => $this->type,
        ]);

        $this->dispatch('notification', ['message' => __('wishlist_forms.updated'), 'type' => 'success']);
    }

    public function addShare(): void
    {
        $this->authorizeUpdate();
        $this->validateOnly('shareEmail');

        $user = User::where('email', $this->shareEmail)->first();

        if (! $user) {
            $this->addError('shareEmail', __('wishlist_forms.share_not_found'));

            return;
        }

        if ($user->id === Auth::id()) {
            $this->addError('shareEmail', __('wishlist_forms.share_self'));

            return;
        }

        $exists = WishlistShare::where('wishlist_id', $this->wishlist->id)
            ->where('shared_with_user_id', $user->id)
            ->exists();

        if ($exists) {
            $this->addError('shareEmail', __('wishlist_forms.share_exists'));

            return;
        }

        WishlistShare::create([
            'wishlist_id' => $this->wishlist->id,
            'shared_with_user_id' => $user->id,
            'access_level' => 'VIEW',
        ]);

        $this->shareEmail = '';
        $this->wishlist->refresh();

        $this->dispatch('notification', ['message' => __('wishlist_forms.share_added'), 'type' => 'success']);
    }

    public function removeShare(int $shareId): void
    {
        $this->authorizeUpdate();
        WishlistShare::where('id', $shareId)
            ->where('wishlist_id', $this->wishlist->id)
            ->delete();

        $this->wishlist->refresh();

        $this->dispatch('notification', ['message' => __('wishlist_forms.share_removed'), 'type' => 'success']);
    }

    public function archive(): void
    {
        $this->authorizeUpdate();
        $this->wishlist->update(['is_archived' => true]);

        $this->dispatch('notification', ['message' => __('wishlist_forms.updated'), 'type' => 'success']);

        redirect()->route('wishlists.index');
    }

    public function delete(): void
    {
        $this->authorizeUpdate();
        $this->wishlist->delete();

        $this->dispatch('notification', ['message' => __('wishlist_forms.deleted'), 'type' => 'success']);

        redirect()->route('wishlists.index');
    }

    public function render(): View
    {
        $this->authorizeUpdate();
        $shares = $this->wishlist->shares()->with('sharedWithUser')->get();

        return view('livewire.wishlist.wishlist-settings', [
            'shares' => $shares,
        ]);
    }

    private function authorizeUpdate(): void
    {
        $this->wishlist->refresh();
        Gate::authorize('update', $this->wishlist);
    }
}
