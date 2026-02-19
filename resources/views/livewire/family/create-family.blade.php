<div>
    <form wire:submit="create" class="space-y-6">
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

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-800 transition-colors">
                {{ __("messages.buttons.create") }}
            </button>
            <a href="{{ route('families.index') }}"
               class="px-5 py-2.5 text-sm font-medium text-[#C3C3D1] bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                {{ __("messages.buttons.cancel") }}
            </a>
        </div>
    </form>
</div>
