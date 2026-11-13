<footer class="bg-white shadow-sm dark:bg-gray-900 p-6">
    <div class="w-full max-w-screen-xl mx-auto">
        <div class="sm:flex sm:items-center sm:justify-between sm:gap-6">
            <a href="{{ route('home') }}" aria-label="{{ __('footer_navigation.home', ['name' => config('app.name')]) }}" class="inline-flex min-h-11 min-w-11 items-center mb-4 sm:mb-0 space-x-3 rtl:space-x-reverse rounded-sm text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-700 dark:text-white dark:focus-visible:outline-blue-300">
                <img src="{{ asset('/media/logo.png') }}" class="h-8" alt="" />
                <span class="self-center text-2xl font-semibold whitespace-nowrap">{{ config('app.name') }}</span>
            </a>
            <p class="mb-6 text-sm font-medium text-gray-500 sm:mb-0 dark:text-gray-400">
                {{ __('footer_navigation.privacy_unavailable') }}
            </p>
        </div>
        <hr class="border-gray-200 sm:mx-auto dark:border-gray-700 mt-2" />
        <span class="block text-sm text-gray-500 sm:text-center dark:text-gray-400">© {{ \Carbon\Carbon::now()->year }} {{ config('app.name') }}™. {{ __('footer_navigation.rights') }}</span>
    </div>
</footer>