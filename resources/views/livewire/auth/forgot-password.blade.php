<x-layouts.app>
    <section class="bg-gray-50 dark:bg-gray-900">
        <div class="h-[70vh] flex flex-col items-center justify-center grow lg:py-0">
            <div class="w-full bg-white rounded-lg shadow dark:border sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                    <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                        {{ __("messages.auth.forgot_password") }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __("messages.auth.forgot_password_instructions") }}
                    </p>

                    @if (session('status'))
                        <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-4 md:space-y-6">
                        @csrf
                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                {{ __("messages.login.email") }}
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                placeholder="name@company.com" required>
                            @error('email')
                                <div class="text-red-600 mt-1 text-sm">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit"
                            class="w-full text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                            {{ __("messages.auth.send_reset_link") }}
                        </button>

                        <p class="text-sm font-light text-gray-500 dark:text-gray-400">
                            <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:underline dark:text-primary-500">
                                {{ __("messages.login.login") }}
                            </a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
