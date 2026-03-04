<div class="max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <h1 class="text-3xl font-bold text-gray-100">{{ $wishlist->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @switch($wishlist->type)
                        @case('CHRISTMAS') bg-red-900/50 text-red-300 @break
                        @case('BIRTHDAY') bg-purple-900/50 text-purple-300 @break
                        @case('WEDDING') bg-pink-900/50 text-pink-300 @break
                        @default bg-gray-700 text-gray-300
                    @endswitch">
                    {{ ucfirst(strtolower($wishlist->type)) }}
                </span>
            </div>
            @if ($wishlist->description)
                <p class="text-gray-400">{{ $wishlist->description }}</p>
            @endif
        </div>

        @if ($isOwner)
            <div class="flex items-center gap-3">
                <a href="{{ route('wishlists.settings', $wishlist) }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </a>
            </div>
        @endif
    </div>

    {{-- Add Item (owner only) --}}
    @if ($isOwner)
        <div class="mb-8">
            <livewire:wishlist.add-item :wishlistId="$wishlist->id" />
        </div>
    @endif

    {{-- Items List --}}
    @if ($items->isEmpty())
        <div class="text-center py-16 bg-gray-800/30 border border-gray-700 rounded-xl">
            <svg class="mx-auto h-12 w-12 text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="text-gray-400">{{ __("messages.wishlist.no_items") }}</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($items as $item)
                <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4
                    {{ $item->status === 'BOUGHT' ? 'opacity-60' : '' }}">

                    {{-- Item Image --}}
                    @if ($item->image_url)
                        <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-700 flex-shrink-0">
                            <img src="{{ $item->image_url }}" alt="{{ $item->name }}"
                                class="w-full h-full object-cover" />
                        </div>
                    @endif

                    {{-- Item Details --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-lg font-medium text-gray-100 truncate">{{ $item->name }}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @switch($item->priority)
                                    @case('HIGH') bg-red-900/50 text-red-300 @break
                                    @case('MEDIUM') bg-yellow-900/50 text-yellow-300 @break
                                    @case('LOW') bg-green-900/50 text-green-300 @break
                                @endswitch">
                                {{ ucfirst(strtolower($item->priority)) }}
                            </span>
                        </div>

                        @if ($item->description)
                            <p class="text-gray-400 text-sm mb-1">{{ $item->description }}</p>
                        @endif

                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            @if ($item->price_min || $item->price_max)
                                <span>
                                    @if ($item->price_min && $item->price_max)
                                        ${{ number_format($item->price_min, 2) }} - ${{ number_format($item->price_max, 2) }}
                                    @elseif ($item->price_min)
                                        From ${{ number_format($item->price_min, 2) }}
                                    @else
                                        Up to ${{ number_format($item->price_max, 2) }}
                                    @endif
                                </span>
                            @endif

                            @if ($item->url)
                                <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer"
                                    class="text-blue-400 hover:text-blue-300 inline-flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    Link
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Status / Actions --}}
                    <div class="flex-shrink-0">
                        @if ($item->status === 'BOUGHT')
                            @if ($isOwner)
                                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-green-900/30 text-green-300 text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __("messages.wishlist.buyer_hidden") }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-green-900/30 text-green-300 text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Bought
                                </span>
                            @endif
                        @elseif ($item->status === 'AVAILABLE' && !$isOwner)
                            <button wire:click="markAsBought({{ $item->id }})"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                </svg>
                                {{ __("messages.wishlist.mark_bought") }}
                            </button>
                        @elseif ($item->status === 'AVAILABLE')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gray-700 text-gray-400 text-sm font-medium">
                                Available
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
