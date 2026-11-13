<x-layouts.app>
    <section class="bg-gray-50 dark:bg-gray-900">
        <div class="min-h-[70vh] flex flex-col items-center justify-center py-6">
            <div class="w-full bg-white rounded-lg shadow-sm dark:border sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                    <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                        {{ __('password_recovery.reset_title') }}
                    </h1>

                    @error('token')
                        <p id="token-error" role="alert" class="p-4 text-sm text-red-800 bg-red-100 rounded-lg dark:bg-red-900 dark:text-red-200">{{ $message }}</p>
                    @enderror

                    <form method="POST" action="{{ route('password.update') }}" class="space-y-4 md:space-y-6">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('password_recovery.email') }}
                            </label>
                            @php($emailValue = old('email', $email))
                            <input type="email" name="email" id="email" value="{{ is_string($emailValue) ? $emailValue : '' }}"
                                autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                @error('email') aria-describedby="email-error" @enderror
                                class="min-h-11 text-base bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-400 dark:focus:border-blue-400"
                                required>
                            @error('email')
                                <p id="email-error" role="alert" class="text-red-700 dark:text-red-300 mt-1 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('password_recovery.password') }}
                            </label>
                            <input type="password" name="password" id="password" autocomplete="new-password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                aria-describedby="password-hint{{ $errors->has('password') ? ' password-error' : '' }}"
                                class="min-h-11 text-base bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-400 dark:focus:border-blue-400"
                                minlength="8" required>
                            <p id="password-hint" class="text-gray-700 dark:text-gray-300 mt-1 text-sm">{{ __('password_recovery.password_hint') }}</p>
                            @error('password')
                                <p id="password-error" role="alert" class="text-red-700 dark:text-red-300 mt-1 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('password_recovery.confirmation') }}
                            </label>
                            <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                @error('password') aria-describedby="password-error" @enderror
                                class="min-h-11 text-base bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-400 dark:focus:border-blue-400"
                                minlength="8" required>
                        </div>

                        <button type="submit"
                            class="min-h-11 w-full text-white bg-blue-700 hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus-visible:outline-blue-300">
                            {{ __('password_recovery.reset') }}
                        </button>
                        <nav class="flex flex-col text-sm" aria-label="{{ __('password_recovery.forgot_title') }}">
                            <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300">{{ __('password_recovery.another') }}</a>
                            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300">{{ __('password_recovery.back') }}</a>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
