<div x-data="{ open: false }">
    <button @click="open = !open" type="button"
        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ __('price_import.add_item') }}
    </button>

    <div x-show="open" x-transition x-cloak class="mt-4 bg-gray-800/50 border border-gray-700 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-gray-100 mb-4">{{ __('price_import.add_item') }}</h3>

        <form wire:submit="save" class="space-y-4">
            {{-- URL with Fetch --}}
            <div>
                <label for="item-url" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __('price_import.product_url') }}
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input wire:model.live.blur="url" type="url" id="item-url" wire:loading.attr="disabled" wire:target="fetchMeta"
                        class="min-w-0 flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="https://example.com/product" />
                    <button wire:click="fetchMeta" type="button" wire:loading.attr="disabled" wire:target="fetchMeta,applySuggestedPrice"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg transition-colors duration-200 disabled:opacity-50">
                        <span wire:loading.remove wire:target="fetchMeta">{{ __('price_import.fetch') }}</span>
                        <span wire:loading wire:target="fetchMeta">{{ __('price_import.fetching') }}</span>
                    </button>
                </div>
                @error('url')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div role="status" aria-live="polite" aria-atomic="true">
                @if ($metadataSourceUrl !== null && $metadataSourceUrl === $url)
                    @if ($suggestedPrice !== null && $suggestedCurrency !== null)
                        <div class="rounded-lg border border-blue-800 bg-blue-950/30 p-4 space-y-3">
                            <p class="text-sm text-gray-300">{{ __('price_import.suggested_price') }}:
                                <x-wishlist.price :minimum="$suggestedPrice" :maximum="$suggestedPrice" :currency="$suggestedCurrency" class="font-semibold text-gray-100" />
                            </p>
                            <p class="text-sm text-gray-300">{{ __('price_import.review_notice') }}</p>
                            <button type="button" wire:click="applySuggestedPrice" wire:loading.attr="disabled"
                                @disabled(filled($price_min) || filled($price_max))
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg disabled:opacity-50">
                                {{ __('price_import.apply') }}
                            </button>
                            @if (filled($price_min) || filled($price_max))
                                <p class="text-sm text-gray-300">{{ __('price_import.manual_price_preserved') }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-300">{{ __('price_import.no_price') }}</p>
                    @endif
                @endif
            </div>
            @error('suggestedPrice')
                <p role="alert" class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            {{-- Name --}}
            <div>
                <label for="item-name" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __('price_import.name') }}
                </label>
                <input wire:model="name" type="text" id="item-name"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="{{ __('price_import.name') }}" />
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="item-description" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __('price_import.description') }}
                </label>
                <textarea wire:model="description" id="item-description" rows="2"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="{{ __('price_import.description_placeholder') }}"></textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Price Range --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="item-price-min" class="block text-sm font-medium text-gray-300 mb-2">
                        {{ __('price_import.minimum') }}
                    </label>
                    <input wire:model.live.blur="price_min" type="number" step="0.01" min="0" max="99999999.99" id="item-price-min"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0.00" />
                    @error('price_min')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="item-price-max" class="block text-sm font-medium text-gray-300 mb-2">
                        {{ __('price_import.maximum') }}
                    </label>
                    <input wire:model.live.blur="price_max" type="number" step="0.01" min="0" max="99999999.99" id="item-price-max"
                        class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0.00" />
                    @error('price_max')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Priority --}}
            <div>
                <label for="item-currency" class="block text-sm font-medium text-gray-300 mb-2">{{ __('price_import.currency') }}</label>
                <select wire:model="currency" id="item-currency" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-hidden focus:ring-2 focus:ring-blue-500">
                    @foreach (\App\Support\WishlistPrice::CURRENCIES as $code)
                        <option value="{{ $code }}">{{ __('price_import.currency_'.strtolower($code)) }}</option>
                    @endforeach
                </select>
                @error('currency')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="item-priority" class="block text-sm font-medium text-gray-300 mb-2">
                    {{ __('price_import.priority') }}
                </label>
                <select wire:model="priority" id="item-priority"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="LOW">{{ __('price_import.low') }}</option>
                    <option value="MEDIUM">{{ __('price_import.medium') }}</option>
                    <option value="HIGH">{{ __('price_import.high') }}</option>
                </select>
                @error('priority')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Image Preview --}}
            @if ($image_url)
                <div class="flex items-center gap-3">
                    <img src="{{ $image_url }}" alt="{{ __('price_import.image_preview') }}" referrerpolicy="no-referrer" class="w-16 h-16 rounded-lg object-cover" />
                    <span class="text-sm text-gray-400">{{ __('price_import.image_fetched') }}</span>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
                <button @click="open = false" type="button"
                    class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-200 transition-colors">
                    {{ __('price_import.cancel') }}
                </button>
                <button type="submit" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                    {{ __('price_import.save') }}
                </button>
            </div>
        </form>
    </div>
</div>
