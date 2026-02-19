<div class="max-w-2xl mx-auto py-8">
    <h1 class="text-2xl font-bold text-white mb-8">{{ __("messages.profile.settings") }}</h1>

    {{-- Profile Information Section --}}
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __("messages.profile.name") }} & {{ __("messages.profile.phone") }}</h2>
        <form wire:submit="updateProfile" class="space-y-4">
            <div>
                <label for="name" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __("messages.profile.name") }}</label>
                <input type="text" wire:model="name" id="name"
                    class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 placeholder-gray-400">
                @error('name')
                    <div class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="phone" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __("messages.profile.phone") }}</label>
                <input type="text" wire:model="phone" id="phone"
                    class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 placeholder-gray-400">
                @error('phone')
                    <div class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="avatar" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __("messages.profile.avatar") }}</label>
                @if ($avatar)
                    <div class="mb-3">
                        <img src="{{ $avatar->temporaryUrl() }}" alt="Avatar preview" class="w-20 h-20 rounded-full object-cover border-2 border-gray-600">
                    </div>
                @elseif ($avatarPreview)
                    <div class="mb-3">
                        <img src="{{ $avatarPreview }}" alt="Current avatar" class="w-20 h-20 rounded-full object-cover border-2 border-gray-600">
                    </div>
                @endif
                <input type="file" wire:model="avatar" id="avatar" accept="image/*"
                    class="block w-full text-sm text-[#C3C3D1] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700">
                @error('avatar')
                    <div class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5">
                    {{ __("messages.buttons.save") }}
                </button>
            </div>
        </form>
    </div>

    {{-- Password Change Section --}}
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __("messages.profile.change_password") }}</h2>
        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <label for="currentPassword" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __("messages.profile.current_password") }}</label>
                <input type="password" wire:model="currentPassword" id="currentPassword" placeholder="••••••••"
                    class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 placeholder-gray-400">
                @error('currentPassword')
                    <div class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="newPassword" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __("messages.profile.new_password") }}</label>
                <input type="password" wire:model="newPassword" id="newPassword" placeholder="••••••••"
                    class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 placeholder-gray-400">
                @error('newPassword')
                    <div class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="newPasswordConfirm" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __("messages.profile.confirm_new_password") }}</label>
                <input type="password" wire:model="newPasswordConfirm" id="newPasswordConfirm" placeholder="••••••••"
                    class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 placeholder-gray-400">
                @error('newPasswordConfirm')
                    <div class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5">
                    {{ __("messages.buttons.update") }}
                </button>
            </div>
        </form>
    </div>
</div>
