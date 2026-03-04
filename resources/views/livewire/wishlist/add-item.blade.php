<div x-data="{ open: false }">
    <button @click="open = !open" type="button"
        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ __("messages.wishlist.add_item") }}
    </button>

    <div x-show="open" x-transition x-cloak class="mt-4 bg-gray-800/50 border border-gray-700 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-gray-100 mb-4">{{ __("messages.wishlist.add_item") }}</h3>

        <form wire:submit="save" class="space-y-4">
            {{-- URL with Fetch --}}
            <div>
                <label for="item-url" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __("messages.wishlist.item_url") }}
                </label>
                <div class="flex gap-2">
                    <input wire:model="url" type="url" id="item-url"
                        class="flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="https://example.com/product" />
                    <button wire:click="fetchMeta" type="button"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg transition-colors duration-200">
                        Fetch
                    </button>
                </div>
                @error('url')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Name --}}
            <div>
                <label for="item-name" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __("messages.wishlist.item_name") }}
                </label>
                <input wire:model="name" type="text" id="item-name"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Item name" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="item-description" class="block text-sm font-medium text-gray-300 mb-2">
                    Description
                </label>
                <textarea wire:model="description" id="item-description" rows="2"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Optional description"></textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Price Range --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="item-price-min" class="block text-sm font-medium text-gray-300 mb-2">
                        {{ __("messages.wishlist.item_price") }} (Min)
                    </label>
                    <input wire:model="price_min" type="number" step="0.01" min="0" id="item-price-min"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0.00" />
                    @error('price_min')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="item-price-max" class="block text-sm font-medium text-gray-300 mb-2">
                        {{ __("messages.wishlist.item_price") }} (Max)
                    </label>
                    <input wire:model="price_max" type="number" step="0.01" min="0" id="item-price-max"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0.00" />
                    @error('price_max')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Priority --}}
            <div>
                <label for="item-priority" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __("messages.wishlist.item_priority") }}
                </label>
                <select wire:model="priority" id="item-priority"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="LOW">Low</option>
                    <option value="MEDIUM">Medium</option>
                    <option value="HIGH">High</option>
                </select>
                @error('priority')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Image Preview --}}
            @if ($image_url)
                <div class="flex items-center gap-3">
                    <img src="{{ $image_url }}" alt="Preview" class="w-16 h-16 rounded-lg object-cover" />
                    <span class="text-sm text-gray-400">Image fetched from URL</span>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
                <button @click="open = false" type="button"
                    class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-200 transition-colors">
                    {{ __("messages.buttons.cancel") }}
                </button>
                <button type="submit"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                    {{ __("messages.buttons.save") }}
                </button>
            </div>
        </form>
    </div>
</div>
