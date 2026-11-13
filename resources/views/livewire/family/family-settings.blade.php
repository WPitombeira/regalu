<div>
    <form wire:submit="update" class="space-y-6 mb-8">
        <div>
            <label for="name" class="block mb-2 text-sm font-medium text-white">
                {{ __("messages.family.name") }}
            </label>
            <input type="text" wire:model="name" id="name"
                   class="bg-gray-800 border border-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 placeholder-gray-400"
                   placeholder="{{ __("messages.family.name") }}">
            @error('name')
                <div class="text-red-500 mt-1 text-sm">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="description" class="block mb-2 text-sm font-medium text-white">
                {{ __("messages.family.description") }}
            </label>
            <textarea wire:model="description" id="description" rows="3"
                      class="bg-gray-800 border border-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 placeholder-gray-400"
                      placeholder="{{ __("messages.family.description") }}"></textarea>
            @error('description')
                <div class="text-red-500 mt-1 text-sm">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit"
                class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-800 transition-colors">
            {{ __("messages.buttons.update") }}
        </button>
    </form>

    <hr class="border-gray-800 my-8">

    {{-- Invite Code Section --}}
    <div class="mb-8">
        <h3 class="text-lg font-semibold text-white mb-3">{{ __("messages.family.invite_code") }}</h3>
        <div class="flex items-center gap-3">
            <span class="px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white font-mono">
                {{ $family->invite_code }}
            </span>
            <button wire:click="regenerateCode"
                    wire:confirm="{{ __('family_forms.regenerate_confirm') }}"
                    class="px-4 py-2.5 text-sm font-medium text-yellow-400 bg-yellow-900/30 border border-yellow-800 rounded-lg hover:bg-yellow-900/50 transition-colors">
                {{ __('family_forms.regenerate') }}
            </button>
        </div>
    </div>

    <hr class="border-gray-800 my-8">

    {{-- Archive Section --}}
    <div>
        <h3 class="text-lg font-semibold text-white mb-3">{{ __('family_forms.danger_zone') }}</h3>
        <button wire:click="archive"
                wire:confirm="{{ __('family_forms.archive_confirm') }}"
                class="px-5 py-2.5 text-sm font-medium text-red-400 bg-red-900/30 border border-red-800 rounded-lg hover:bg-red-900/50 transition-colors">
            {{ __("messages.buttons.archive") }}
        </button>
    </div>
</div>
