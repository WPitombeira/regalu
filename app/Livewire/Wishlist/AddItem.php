<?php

namespace App\Livewire\Wishlist;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\UrlMetaFetchService;
use App\Support\WishlistPrice;
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

    #[Rule(['nullable', 'numeric', 'regex:'.WishlistPrice::AMOUNT_PATTERN])]
    public ?string $price_min = null;

    #[Rule(['nullable', 'numeric', 'regex:'.WishlistPrice::AMOUNT_PATTERN])]
    public ?string $price_max = null;

    #[Rule(['required', 'in:BRL,USD,EUR'])]
    public string $currency = 'BRL';

    #[Locked]
    public ?string $suggestedPrice = null;

    #[Locked]
    public ?string $suggestedCurrency = null;

    #[Locked]
    public ?string $metadataSourceUrl = null;

    #[Rule(['required', 'in:LOW,MEDIUM,HIGH'])]
    public string $priority = 'MEDIUM';

    public function mount(int $wishlistId): void
    {
        $this->wishlist_id = $wishlistId;
        $this->authorizeWishlist();
    }

    public function fetchMeta(): void
    {
        $this->updatedUrl();

        if ($this->url === '') {
            return;
        }

        $this->validateOnly('url');

        $rateLimitKey = 'wishlist-meta-fetch:'.Auth::id();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->addError('url', __('price_import.rate_limited'));

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

        $this->suggestedPrice = $meta['price'] ?? null;
        $this->suggestedCurrency = $meta['currency'] ?? null;
        $this->metadataSourceUrl = $this->url;
    }

    public function updatedUrl(): void
    {
        $this->authorizeWishlist();
        $this->reset(['image_url', 'suggestedPrice', 'suggestedCurrency', 'metadataSourceUrl']);
        $this->resetValidation(['url', 'suggestedPrice']);
    }

    public function applySuggestedPrice(): void
    {
        $this->authorizeWishlist();
        $this->resetValidation('suggestedPrice');

        if (filled($this->price_min) || filled($this->price_max)) {
            $this->addError('suggestedPrice', __('price_import.manual_price_preserved'));

            return;
        }

        if ($this->metadataSourceUrl === $this->url && $this->suggestedPrice !== null && $this->suggestedCurrency !== null) {
            $this->price_min = $this->suggestedPrice;
            $this->price_max = $this->suggestedPrice;
            $this->currency = $this->suggestedCurrency;
        } else {
            $this->addError('suggestedPrice', __('price_import.fetch_again'));
        }
    }

    public function save(): void
    {
        $this->authorizeWishlist();
        $this->validate();

        if (filled($this->price_min) && filled($this->price_max) && $this->price_min > $this->price_max) {
            $this->addError('price_max', __('price_import.invalid_range'));

            return;
        }

        WishlistItem::create([
            'wishlist_id' => $this->wishlist_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'url' => $this->url ?: null,
            'image_url' => $this->image_url ?: null,
            'price_min' => WishlistPrice::normalize($this->price_min),
            'price_max' => WishlistPrice::normalize($this->price_max),
            'currency' => filled($this->price_min) || filled($this->price_max) ? $this->currency : null,
            'priority' => $this->priority,
            'status' => 'AVAILABLE',
        ]);

        $this->dispatch('notification', ['message' => __('price_import.item_added'), 'type' => 'success']);
        $this->dispatch('item-added');

        $this->reset(['name', 'description', 'url', 'image_url', 'price_min', 'price_max', 'currency', 'suggestedPrice', 'suggestedCurrency', 'metadataSourceUrl', 'priority']);
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
