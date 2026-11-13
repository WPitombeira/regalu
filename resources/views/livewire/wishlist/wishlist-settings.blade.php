<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-100">{{ __('wishlist_forms.settings_title', ['name' => $wishlist->name]) }}</h1>
    </div>

    {{-- Edit Form --}}
    <form wire:submit="update" class="bg-gray-800/50 border border-gray-700 rounded-xl p-6 space-y-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-100">{{ __('wishlist_forms.edit_title') }}</h2>

        {{-- Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __('wishlist_forms.name') }}
            </label>
            <input wire:model="name" type="text" id="name"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Description --}}
        <div>
            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __('wishlist_forms.description') }}
            </label>
            <textarea wire:model="description" id="description" rows="3"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Type --}}
        <div>
            <label for="type" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __('wishlist_forms.type') }}
            </label>
            <select wire:model="type" id="type"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="GENERIC">{{ __('wishlist_forms.type_generic') }}</option>
                <option value="CHRISTMAS">{{ __('wishlist_forms.type_christmas') }}</option>
                <option value="BIRTHDAY">{{ __('wishlist_forms.type_birthday') }}</option>
                <option value="WEDDING">{{ __('wishlist_forms.type_wedding') }}</option>
            </select>
            @error('type')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Privacy --}}
        <div>
            <label for="privacy" class="block text-sm font-medium text-gray-300 mb-2">
                {{ __('wishlist_forms.privacy') }}
            </label>
            <select wire:model="privacy" id="privacy"
                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="PRIVATE">{{ __('wishlist_forms.privacy_private') }}</option>
                <option value="FAMILY">{{ __('wishlist_forms.privacy_family') }}</option>
                <option value="SPECIFIC">{{ __('wishlist_forms.privacy_specific') }}</option>
            </select>
            @error('privacy')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                {{ __('wishlist_forms.save') }}
            </button>
        </div>
    </form>

    {{-- Share Management --}}
    <div class="bg-gray-800/50 border border-gray-700 rounded-xl p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-100 mb-4">{{ __('wishlist_forms.sharing_title') }}</h2>

        {{-- Add Share --}}
        <div class="flex gap-3 mb-6">
            <div class="flex-1">
                <input wire:model="shareEmail" type="email" aria-label="{{ __('wishlist_forms.share_email') }}" placeholder="{{ __('wishlist_forms.share_placeholder') }}"
                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                @error('shareEmail')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button wire:click="addShare" type="button"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                {{ __('wishlist_forms.add_share') }}
            </button>
        </div>

        {{-- Shared Users List --}}
        @if ($shares->isEmpty())
            <p class="text-gray-500 text-sm">{{ __('wishlist_forms.no_shares') }}</p>
        @else
            <div class="space-y-3">
                @foreach ($shares as $share)
                    <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
                        <div>
                            <p class="text-gray-200 font-medium">{{ $share->sharedWithUser->name }}</p>
                            <p class="text-gray-500 text-sm">{{ $share->sharedWithUser->email }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400 uppercase">{{ match ($share->access_level) {
                                'VIEW' => __('wishlist_forms.access_view'),
                                'EDIT' => __('wishlist_forms.access_edit'),
                                default => $share->access_level,
                            } }}</span>
                            <button wire:click="removeShare({{ $share->id }})" type="button"
                                aria-label="{{ __('wishlist_forms.remove_share', ['name' => $share->sharedWithUser->name]) }}"
                                class="text-red-400 hover:text-red-300 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Danger Zone --}}
    <div class="bg-red-900/20 border border-red-800/50 rounded-xl p-6">
        <h2 class="text-xl font-semibold text-red-300 mb-4">{{ __('wishlist_forms.danger_title') }}</h2>
        <div class="flex flex-col sm:flex-row gap-3">
            <button wire:click="archive" wire:confirm="{{ __('wishlist_forms.confirm_archive') }}"
                class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                {{ __('wishlist_forms.archive') }}
            </button>
            <button wire:click="delete" wire:confirm="{{ __('wishlist_forms.confirm_delete') }}"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                {{ __('wishlist_forms.delete') }}
            </button>
        </div>
    </div>
</div>
