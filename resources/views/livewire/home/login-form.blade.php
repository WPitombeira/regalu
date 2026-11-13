@props(['showLogin', 'showRegister'])

<div>
    {{-- <x-toast type="info" message="This is a success message" /> --}}
    <section class="bg-gray-50 dark:bg-gray-900" x-data="{ showLogin: true, showRegister: false }">
        <div class="flex flex-col items-center px-4 py-8 sm:py-12">
            <div class="w-full max-w-md min-w-0">
                <noscript>
                    <p class="mb-4 rounded-lg border border-amber-400 bg-amber-50 p-4 text-sm text-amber-950">{{ __('auth_forms.javascript_required') }}</p>
                </noscript>
                {{-- // Login --}}
                <div x-show="showLogin"
                    class="transition-all ease-in-out delay-300 w-full bg-white rounded-lg shadow-sm dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-4 space-y-4 sm:p-8 sm:space-y-6">
                        <fieldset disabled x-bind:disabled="false" class="min-w-0 space-y-4 sm:space-y-6">
                        <h1
                            class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                            {{ __('auth_forms.login_heading') }}
                        </h1>
                        <form class="space-y-4 md:space-y-6" wire:submit="login">
                            <div>
                                <label for="login-email"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('auth_forms.fields.email') }}</label>
                                <input id="login-email" x-ref="loginEmail" type="email" wire:model="email" autocomplete="username"
                                    @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    placeholder="name@company.com" required="">
                                @error('email')
                                    <p id="login-email-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="login-password"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('auth_forms.fields.password') }}</label>
                                <input id="login-password" type="password" wire:model="password" autocomplete="current-password" placeholder="••••••••"
                                    @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    required="">
                                @error('password')
                                    <p id="login-password-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input wire:model="remember" id="remember" type="checkbox"
                                            class="w-4 h-4 border border-gray-300 rounded-sm bg-gray-50 focus:ring-3 focus:ring-primary-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-600 dark:ring-offset-gray-800">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="remember" class="text-gray-500 dark:text-gray-300">{{ __('auth_forms.remember') }}</label>
                                    </div>
                                </div>
                                <a href="{{ route('password.request') }}"
                                    class="text-sm font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300">{{ __('auth_forms.forgot_password') }}</a>
                            </div>
                            <button type="submit"
                                class="min-h-11 w-full rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">{{ __('auth_forms.login') }}</button>
                            
                            @error('loginError')
                                <p id="login-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            
                            <p class="text-sm font-light text-gray-500 dark:text-gray-400">
                                {{ __('auth_forms.new_account') }} <button x-on:click="showLogin = false; showRegister = true; $nextTick(() => $refs.registerName.focus())" type="button"
                                    class="font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300">{{ __('auth_forms.register') }}</button>
                            </p>


                            <div>
                                <p id="login-providers-unavailable" class="text-sm text-gray-600 dark:text-gray-300">{{ __('auth_forms.providers_unavailable') }}</p>
                                <div class="grid grid-cols-2 gap-2 mt-3">
                                    <x-ui.buttons.login provider="Apple" description-id="login-providers-unavailable" />
                                    <x-ui.buttons.login provider="Google" description-id="login-providers-unavailable" />
                                </div>
                            </div>
                        </form>
                        </fieldset>
                    </div>
                </div>
                
                {{-- // Register --}}
                <div x-show.important="showRegister" style="display: none" class="transition-all ease-in-out delay-300 w-full bg-white rounded-lg shadow-sm dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-4 space-y-4 sm:p-8 sm:space-y-6">
                        <fieldset disabled x-bind:disabled="false" class="min-w-0 space-y-4 sm:space-y-6">
                        <h1
                            class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                            {{ __('auth_forms.register_heading') }}
                        </h1>
                        <form class="space-y-4 md:space-y-6" wire:submit="register">
                            <div>
                                <label for="register-name" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __('auth_forms.fields.name') }}</label>
                                <input id="register-name" x-ref="registerName" type="text" wire:model="registerName" autocomplete="name" required minlength="2" maxlength="255"
                                    @error('registerName') aria-invalid="true" aria-describedby="register-name-error" @enderror
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                @error('registerName')
                                    <p id="register-name-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="register-email" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __('auth_forms.fields.email') }}</label>
                                <input id="register-email" type="email" wire:model="registerEmail" autocomplete="email" required maxlength="255"
                                    @error('registerEmail') aria-invalid="true" aria-describedby="register-email-error" @enderror
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                @error('registerEmail')
                                    <p id="register-email-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="register-password" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __('auth_forms.fields.password') }}</label>
                                <input id="register-password" type="password" wire:model="registerPassword" autocomplete="new-password" required minlength="8" placeholder="••••••••"
                                    @error('registerPassword') aria-invalid="true" aria-describedby="register-password-error" @else aria-describedby="register-password-hint" @enderror
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                <p id="register-password-hint" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('auth_forms.password_hint') }}</p>
                                @error('registerPassword')
                                    <p id="register-password-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="register-password-confirm" class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __('auth_forms.fields.confirmation') }}</label>
                                <input id="register-password-confirm" type="password" wire:model="registerPasswordConfirm" autocomplete="new-password" required minlength="8" placeholder="••••••••"
                                    @error('registerPasswordConfirm') aria-invalid="true" aria-describedby="register-password-confirm-error" @enderror
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                @error('registerPasswordConfirm')
                                    <p id="register-password-confirm-error" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                class="min-h-11 w-full rounded-lg bg-blue-700 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">{{ __('auth_forms.register') }}</button>
                        </form>
                        <p class="text-sm font-light text-gray-500 dark:text-gray-400">
                            {{ __('auth_forms.existing_account') }} <button x-on:click="showLogin = true; showRegister = false; $nextTick(() => $refs.loginEmail.focus())" type="button"
                            class="font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300">{{ __('auth_forms.login') }}</button></p>
                            
                        <div>
                            <p id="register-providers-unavailable" class="text-sm text-gray-600 dark:text-gray-300">{{ __('auth_forms.providers_unavailable') }}</p>
                            <div class="grid grid-cols-2 gap-2 mt-3">
                                <x-ui.buttons.login provider="Apple" description-id="register-providers-unavailable" />
                                <x-ui.buttons.login provider="Google" description-id="register-providers-unavailable" />
                            </div>
                        </div>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>