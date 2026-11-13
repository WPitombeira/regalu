<x-layouts.app>
    <section class="bg-gray-50 dark:bg-gray-900">
        <div class="min-h-[70vh] flex flex-col items-center justify-center py-6">
            <div class="w-full bg-white rounded-lg shadow-sm dark:border sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                    <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                        {{ __('password_recovery.forgot_title') }}
                    </h1>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        {{ __('password_recovery.instructions') }}
                    </p>

                    @if (session('status'))
                        <div role="status" class="p-4 text-sm text-green-800 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-200">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-4 md:space-y-6">
                        @csrf
                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('password_recovery.email') }}
                            </label>
                            @php($emailValue = old('email'))
                            <input type="email" name="email" id="email" value="{{ is_string($emailValue) ? $emailValue : '' }}"
                                autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                @error('email') aria-describedby="email-error" @enderror
                                class="min-h-11 text-base bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-400 dark:focus:border-blue-400"
                                required>
                            @error('email')
                                <p id="email-error" role="alert" class="text-red-700 dark:text-red-300 mt-1 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                            class="min-h-11 w-full text-white bg-blue-700 hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus-visible:outline-blue-300">
                            {{ __('password_recovery.send') }}
                        </button>

                        <p class="text-sm">
                            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300">
                                {{ __('password_recovery.back') }}
                            </a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
