<div>
    <div class="max-w-md mx-auto">
        <div class="text-center mb-8">
            <svg class="mx-auto h-16 w-16 text-blue-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
            </svg>
            <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.amigo_secreto.join") }}</h1>
            <p class="mt-2 text-gray-400">Enter the event code to join a Secret Santa event.</p>
        </div>

        <form wire:submit="join" class="bg-gray-800 border border-gray-700 rounded-xl p-8">
            <div class="mb-6">
                <label for="eventCode" class="block text-sm font-medium text-gray-300 mb-1">
                    Event Code
                </label>
                <input type="text" id="eventCode" wire:model="eventCode"
                       maxlength="12"
                       class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 text-center text-xl font-mono tracking-widest uppercase"
                       placeholder="ABCDEF123456">
                @error('eventCode') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full px-4 py-3 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                {{ __("messages.amigo_secreto.join") }}
            </button>
        </form>
    </div>
</div>
