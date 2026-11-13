<div x-data class="max-w-2xl mx-auto py-8">
    <h1 class="text-2xl font-bold text-white mb-8">{{ __('profile_settings.title') }}</h1>
    <noscript><p role="alert" class="mb-6 rounded-lg border border-amber-400 bg-gray-800 p-4 text-amber-200">{{ __('profile_settings.javascript_required') }}</p></noscript>

    {{-- Profile Information Section --}}
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __('profile_settings.information') }}</h2>
        <form wire:submit="updateProfile">
            <fieldset disabled x-bind:disabled="false" class="space-y-4">
                <div>
                    <label for="name" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __('profile_settings.name') }}</label>
                    <input type="text" wire:model="name" id="name" autocomplete="name" required minlength="2" maxlength="255"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror
                        class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-300 focus:border-blue-300 block w-full p-2.5 placeholder-gray-400">
                    @error('name')
                        <div id="name-error" role="alert" class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __('profile_settings.phone') }}</label>
                    <input type="tel" wire:model="phone" id="phone" autocomplete="tel" maxlength="20"
                        aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @error('phone') aria-describedby="phone-error" @enderror
                        class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-300 focus:border-blue-300 block w-full p-2.5 placeholder-gray-400">
                    @error('phone')
                        <div id="phone-error" role="alert" class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="avatar" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __('profile_settings.avatar') }}</label>
                    @if ($avatar && ! $errors->has('avatar') && $avatar->isPreviewable())
                        <div class="mb-3">
                            <img src="{{ $avatar->temporaryUrl() }}" alt="{{ __('profile_settings.avatar_preview') }}" class="w-20 h-20 rounded-full object-cover border-2 border-gray-600">
                        </div>
                    @elseif ($avatarPreview)
                        <div class="mb-3">
                            <img src="{{ $avatarPreview }}" alt="{{ __('profile_settings.avatar_current') }}" class="w-20 h-20 rounded-full object-cover border-2 border-gray-600">
                        </div>
                    @endif
                    <input type="file" wire:model="avatar" id="avatar" accept="image/*"
                        aria-invalid="{{ $errors->has('avatar') ? 'true' : 'false' }}" aria-describedby="avatar-hint{{ $errors->has('avatar') ? ' avatar-error' : '' }}"
                        class="block w-full min-h-11 text-sm text-[#C3C3D1] file:min-h-11 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-700 file:text-white hover:file:bg-blue-800">
                    <p id="avatar-hint" class="mt-2 text-sm text-[#C3C3D1]">{{ __('profile_settings.avatar_hint') }}</p>
                    @error('avatar')
                        <div id="avatar-error" role="alert" class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="min-h-11 text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-hidden focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
                        {{ __('profile_settings.save') }}
                    </button>
                </div>
            </fieldset>
        </form>
    </div>

    {{-- Password Change Section --}}
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __('profile_settings.change_password') }}</h2>
        <form wire:submit="updatePassword">
            <fieldset disabled x-bind:disabled="false" class="space-y-4">
                <div>
                    <label for="currentPassword" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __('profile_settings.current_password') }}</label>
                    <input type="password" wire:model="currentPassword" id="currentPassword" autocomplete="current-password" required
                        aria-invalid="{{ $errors->has('currentPassword') ? 'true' : 'false' }}" @error('currentPassword') aria-describedby="currentPassword-error" @enderror
                        class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-300 focus:border-blue-300 block w-full p-2.5 placeholder-gray-400">
                    @error('currentPassword')
                        <div id="currentPassword-error" role="alert" class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="newPassword" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __('profile_settings.new_password') }}</label>
                    <input type="password" wire:model="newPassword" id="newPassword" autocomplete="new-password" required minlength="8"
                        aria-invalid="{{ $errors->has('newPassword') ? 'true' : 'false' }}" aria-describedby="newPassword-hint{{ $errors->has('newPassword') ? ' newPassword-error' : '' }}"
                        class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-300 focus:border-blue-300 block w-full p-2.5 placeholder-gray-400">
                    <p id="newPassword-hint" class="mt-2 text-sm text-[#C3C3D1]">{{ __('profile_settings.password_hint') }}</p>
                    @error('newPassword')
                        <div id="newPassword-error" role="alert" class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="newPasswordConfirm" class="block mb-2 text-sm font-medium text-[#C3C3D1]">{{ __('profile_settings.confirm_password') }}</label>
                    <input type="password" wire:model="newPasswordConfirm" id="newPasswordConfirm" autocomplete="new-password" required minlength="8"
                        aria-invalid="{{ $errors->has('newPasswordConfirm') ? 'true' : 'false' }}" @error('newPasswordConfirm') aria-describedby="newPasswordConfirm-error" @enderror
                        class="bg-gray-700 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-300 focus:border-blue-300 block w-full p-2.5 placeholder-gray-400">
                    @error('newPasswordConfirm')
                        <div id="newPasswordConfirm-error" role="alert" class="text-red-400 mt-1 text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="min-h-11 text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-hidden focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
                        {{ __('profile_settings.update_password') }}
                    </button>
                </div>
            </fieldset>
        </form>
    </div>
</div>
