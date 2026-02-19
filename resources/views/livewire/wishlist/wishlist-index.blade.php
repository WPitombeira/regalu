<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
        <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.wishlist.wishlists") }}</h1>
        <a href="{{ route('wishlists.create') }}"
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ __("messages.wishlist.create") }}
        </a>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search wishlists..."
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <select wire:model.live="filterType"
            class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Types</option>
            <option value="CHRISTMAS">Christmas</option>
            <option value="BIRTHDAY">Birthday</option>
            <option value="WEDDING">Wedding</option>
            <option value="GENERIC">Generic</option>
        </select>
        <select wire:model.live="filterPrivacy"
            class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">All Privacy</option>
            <option value="PRIVATE">{{ __("messages.wishlist.privacy_private") }}</option>
            <option value="FAMILY">{{ __("messages.wishlist.privacy_family") }}</option>
            <option value="SPECIFIC">{{ __("messages.wishlist.privacy_specific") }}</option>
        </select>
    </div>

    {{-- Wishlist Grid --}}
    @if ($wishlists->isEmpty())
        <div class="text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-gray-400 text-lg">{{ __("messages.wishlist.no_items") }}</p>
            <a href="{{ route('wishlists.create') }}"
                class="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                {{ __("messages.wishlist.create") }}
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($wishlists as $wishlist)
                <a href="{{ route('wishlists.show', $wishlist) }}"
                    class="block bg-gray-800/50 border border-gray-700 rounded-xl p-6 hover:bg-gray-800 hover:border-gray-600 transition-all duration-200 group">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-100 group-hover:text-blue-400 transition-colors">
                            {{ $wishlist->name }}
                        </h3>
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
                        <p class="text-gray-400 text-sm mb-4 line-clamp-2">{{ $wishlist->description }}</p>
                    @endif

                    <div class="flex items-center justify-between mt-auto">
                        <span class="text-sm text-gray-500">
                            {{ $wishlist->items_count }} {{ __("messages.wishlist.items") }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            @switch($wishlist->privacy)
                                @case('PRIVATE') bg-gray-700 text-gray-400 @break
                                @case('FAMILY') bg-green-900/50 text-green-300 @break
                                @case('SPECIFIC') bg-yellow-900/50 text-yellow-300 @break
                            @endswitch">
                            @switch($wishlist->privacy)
                                @case('PRIVATE') {{ __("messages.wishlist.privacy_private") }} @break
                                @case('FAMILY') {{ __("messages.wishlist.privacy_family") }} @break
                                @case('SPECIFIC') {{ __("messages.wishlist.privacy_specific") }} @break
                            @endswitch
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
