@props(['showLogin', 'showRegister'])

<div>
    {{-- <x-toast type="info" message="This is a success message" /> --}}
    <section class="bg-gray-50 dark:bg-gray-900" x-data="{ showLogin: true, showRegister: false }">
        <div :class="{'h-[80vh]': ! showLogin}" class="h-[70vh] flex flex-col items-center justify-center grow lg:py-0 transition-all ease-in-out delay-200">
            <div class="flex flex-col items-center px-6 w-4/6">
                {{-- // Login --}}
                <div x-show="showLogin"
                    class="transition-all ease-in-out delay-300 w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                        <h1
                            class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                            {{ __("messages.login.login_headline") }}
                        </h1>
                        <form class="space-y-4 md:space-y-6" wire:submit="login">
                            <div>
                                <label for="email"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">{{ __("messages.login.email") }}</label>
                                <input type="email" wire:model="email"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    placeholder="name@company.com" required="">
                                    @error('email')
                                    <div class="text-red-600 mt-1 text-small">{{ $message }}</div>
                                    @enderror
                            </div>
                            <div>
                                <label for="password"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">{{ __("messages.login.password") }}</label>
                                <input type="password" wire:model="password" placeholder="••••••••"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    required="">
                                    @error('password')
                                    <div class="text-red-600 mt-1 text-small">{{ $message }}</div>
                                    @enderror
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input wire:model="remember" id="remember" aria-describedby="remember" type="checkbox"
                                            class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-primary-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-600 dark:ring-offset-gray-800">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="remember" class="text-gray-500 dark:text-gray-300">{{ __("messages.login.remember_me") }}</label>
                                    </div>
                                </div>
                                <a href="#"
                                    class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-500">{{ __("messages.login.forgot_password") }}</a>
                            </div>
                            <button type="submit"
                                class="w-full text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">{{ __("messages.login.login") }}</button>
                            
                            @error('loginError')
                            <div class="text-red-600 mt-1 text-small">{{ $message }}</div>
                            @enderror
                            
                            <p class="text-sm font-light text-gray-500 dark:text-gray-400">
                                {{ __("messages.login.not_registered_yet") }} <button x-on:click="showLogin = !showLogin; showRegister = !showRegister" type="button"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-500">{{ __("messages.login.register_cta") }}</button>
                            </p>


                            <div>
                                <span class="relative flex justify-between">
                                    <div aria-hidden="true" class="w-full inset-0 flex items-center">
                                        <div class="w-full border border-gray-300 dark:border-gray-600"></div>
                                    </div>
                                    <div class="w-full flex justify-center text-sm">
                                        <span class="px-2 text-gray-500 dark:text-gray-400">{{ __("messages.login.continue_with") }}</span>
                                    </div>
                                    <div aria-hidden="true"  class="w-full inset-0 flex items-center">
                                        <div class="w-full border border-gray-300 dark:border-gray-600"></div>
                                    </div>
                                </span>
                                <div class="grid grid-cols-2 gap-2 mt-4">
                                    <x-ui.buttons.login provider="Apple" />
                                    <x-ui.buttons.login provider="Google" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                {{-- // Register --}}
                <div x-show.important="showRegister" class="transition-all ease-in-out delay-300 w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                    <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                        <h1
                            class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                            {{ __("messages.login.register_headline") }}
                        </h1>
                        <form class="space-y-4 md:space-y-6" wire:submit="register">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __("messages.login.form_fields.name") }}</label>
                                <input type="text" wire:model="name" placeholder="{{ __("messages.login.form_fields.name_placeholder") }}" class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __("messages.login.form_fields.email") }}</label>
                                <input type="email" wire:model="registerEmail" placeholder="{{ __("messages.login.form_fields.email_placeholder") }}" class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __("messages.login.form_fields.password") }}</label>
                                <input type="password" wire:model="registerPassword" placeholder="••••••••" class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-900 dark:text-white">{{ __("messages.login.form_fields.confirm_password") }}</label>
                                <input type="password" wire:model="registerPasswordConfirm" placeholder="••••••••" class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            </div>
                            <button type="submit"
                                class="w-full text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">{{ __("messages.login.register") }}</button>
                        </form>
                        <p class="text-sm font-light text-gray-500 dark:text-gray-400">
                            Already have an account? <button x-on:click="showLogin = !showLogin; showRegister = !showRegister" type="button"
                            class="font-medium text-primary-600 hover:underline dark:text-primary-500">Login</button></p>
                            
                        <div>
                            <span class="relative flex justify-between">
                                <div aria-hidden="true" class="w-full inset-0 flex items-center">
                                    <div class="w-full border border-gray-300 dark:border-gray-600"></div>
                                </div>
                                <div class="w-full flex justify-center text-sm">
                                    <span class="px-2 text-gray-500 dark:text-gray-400">{{ __("messages.login.register_with") }}</span>
                                </div>
                                <div aria-hidden="true"  class="w-full inset-0 flex items-center">
                                    <div class="w-full border border-gray-300 dark:border-gray-600"></div>
                                </div>
                            </span>
                            <div class="grid grid-cols-2 gap-2 mt-4">
                                <x-ui.buttons.login provider="Apple" />
                                <x-ui.buttons.login provider="Google" />
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </section>
</div>