<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.wishlist.create") }}</h1>
    </div>

    <form wire:submit="create" class="bg-gray-800/50 border border-gray-700 rounded-xl p-6 space-y-6">
        {{-- Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __("messages.wishlist.name") }}
            </label>
            <input wire:model="name" type="text" id="name"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="My Birthday Wishlist" />
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Description --}}
        <div>
            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __("messages.wishlist.description") }}
            </label>
            <textarea wire:model="description" id="description" rows="3"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="What is this wishlist for?"></textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Type --}}
        <div>
            <label for="type" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __("messages.wishlist.type") }}
            </label>
            <select wire:model="type" id="type"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="GENERIC">Generic</option>
                <option value="CHRISTMAS">Christmas</option>
                <option value="BIRTHDAY">Birthday</option>
                <option value="WEDDING">Wedding</option>
            </select>
            @error('type')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Privacy --}}
        <div>
            <label for="privacy" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __("messages.wishlist.privacy") }}
            </label>
            <select wire:model="privacy" id="privacy"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="PRIVATE">{{ __("messages.wishlist.privacy_private") }}</option>
                <option value="FAMILY">{{ __("messages.wishlist.privacy_family") }}</option>
                <option value="SPECIFIC">{{ __("messages.wishlist.privacy_specific") }}</option>
            </select>
            @error('privacy')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Family (optional) --}}
        @if ($families->isNotEmpty())
            <div>
                <label for="family_id" class="block text-sm font-medium text-gray-300 mb-2">
                    Family (optional)
                </label>
                <select wire:model="family_id" id="family_id"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">None</option>
                    @foreach ($families as $family)
                        <option value="{{ $family->id }}">{{ $family->name }}</option>
                    @endforeach
                </select>
                @error('family_id')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
            <a href="{{ route('wishlists.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-200 transition-colors">
                {{ __("messages.buttons.cancel") }}
            </a>
            <button type="submit"
                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                {{ __("messages.buttons.create") }}
            </button>
        </div>
    </form>
</div>
