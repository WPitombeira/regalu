<?php

namespace App\Livewire\Wishlist;

use App\Models\WishlistItem;
use App\Services\UrlMetaFetchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;

class AddItem extends Component {
    public int $wishlist_id;

    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    #[Rule(['nullable', 'url', 'max:2048'])]
    public string $url = '';

    public string $image_url = '';

    #[Rule(['nullable', 'numeric', 'min:0'])]
    public ?float $price_min = null;

    #[Rule(['nullable', 'numeric', 'min:0'])]
    public ?float $price_max = null;

    #[Rule(['required', 'in:LOW,MEDIUM,HIGH'])]
    public string $priority = 'MEDIUM';

    public function mount(int $wishlistId): void {
        $this->wishlist_id = $wishlistId;
    }

    public function fetchMeta(): void {
        if ($this->url === '') {
            return;
        }

        $service = app(UrlMetaFetchService::class);
        $meta = $service->fetch($this->url);

        if (isset($meta['title']) && $this->name === '') {
            $this->name = $meta['title'];
        }

        if (isset($meta['image'])) {
            $this->image_url = $meta['image'];
        }
    }

    public function save(): void {
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

        $this->dispatch('notification', ["message" => __("messages.wishlist.item_added"), "type" => "success"]);
        $this->dispatch('item-added');

        $this->reset(['name', 'description', 'url', 'image_url', 'price_min', 'price_max', 'priority']);
        $this->priority = 'MEDIUM';
    }

    public function render(): View {
        return view('livewire.wishlist.add-item');
    }
}
