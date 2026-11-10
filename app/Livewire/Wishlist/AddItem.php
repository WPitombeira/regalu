<?php

namespace App\Livewire\Wishlist;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\UrlMetaFetchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class AddItem extends Component
{
    #[Locked]
    public int $wishlist_id;

    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    #[Rule(['nullable', 'string', 'url', 'max:2048'])]
    public string $url = '';

    #[Locked]
    #[Rule(['nullable', 'string', 'url:http,https', 'max:2048'])]
    public string $image_url = '';

    #[Rule(['nullable', 'numeric', 'min:0'])]
    public ?float $price_min = null;

    #[Rule(['nullable', 'numeric', 'min:0'])]
    public ?float $price_max = null;

    #[Rule(['required', 'in:LOW,MEDIUM,HIGH'])]
    public string $priority = 'MEDIUM';

    public function mount(int $wishlistId): void
    {
        $this->wishlist_id = $wishlistId;
        $this->authorizeWishlist();
    }

    public function fetchMeta(): void
    {
        $this->authorizeWishlist();

        if ($this->url === '') {
            return;
        }

        $this->validateOnly('url');

        $rateLimitKey = 'wishlist-meta-fetch:'.Auth::id();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->addError('url', __('Too many metadata requests. Please try again shortly.'));

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $service = app(UrlMetaFetchService::class);
        $meta = $service->fetch($this->url);

        if (isset($meta['title']) && $this->name === '') {
            $this->name = $meta['title'];
        }

        if (isset($meta['image'])) {
            $this->image_url = $meta['image'];
        }
    }

    public function save(): void
    {
        $this->authorizeWishlist();
        $this->validate();

        WishlistItem::create([
            'wishlist_id' => $this->wishlist_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'url' => $this->url ?: null,
            'image_url' => $this->image_url ?: null,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
            'priority' => $this->priority,
            'status' => 'AVAILABLE',
        ]);

        $this->dispatch('notification', ['message' => __('messages.wishlist.item_added'), 'type' => 'success']);
        $this->dispatch('item-added');

        $this->reset(['name', 'description', 'url', 'image_url', 'price_min', 'price_max', 'priority']);
        $this->priority = 'MEDIUM';
    }

    public function render(): View
    {
        $this->authorizeWishlist();

        return view('livewire.wishlist.add-item');
    }

    private function authorizeWishlist(): Wishlist
    {
        $wishlist = Wishlist::query()->findOrFail($this->wishlist_id);
        Gate::authorize('update', $wishlist);

        return $wishlist;
    }
}
