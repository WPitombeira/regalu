<div>
    <form wire:submit="join" class="space-y-6">
        <div>
            <label for="inviteCode" class="block mb-2 text-sm font-medium text-white">
                {{ __("messages.family.invite_code") }}
            </label>
            <input type="text" wire:model="inviteCode" id="inviteCode"
                   maxlength="12"
                   class="bg-gray-800 border border-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 placeholder-gray-400 font-mono uppercase tracking-wider"
                   placeholder="{{ __("messages.family.join_placeholder") }}">
            @error('inviteCode')
                <div class="text-red-500 mt-1 text-sm">{{ $message }}</div>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-800 transition-colors">
                {{ __("messages.family.join") }}
            </button>
            <a href="{{ route('families.index') }}"
               class="px-5 py-2.5 text-sm font-medium text-[#C3C3D1] bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                {{ __("messages.buttons.cancel") }}
            </a>
        </div>
    </form>
</div>
