<footer class="bg-white shadow dark:bg-gray-900 p-6">
    <div class="w-full max-w-screen-xl mx-auto">
        <div class="sm:flex sm:items-center sm:justify-between">
            <a href="{{ config("app.url") }}" class="flex items-center mb-4 sm:mb-0 space-x-3 rtl:space-x-reverse">
                <img src="{{ asset('/media/logo.png')}}" class="h-8" alt="{{ config("app.name") }} Logo" />
                <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">{{ config("app.name") }}</span>
            </a>
            <ul class="flex flex-wrap items-center mb-6 text-sm font-medium text-gray-500 sm:mb-0 dark:text-gray-400">
                <li>
                    <a href="#" class="hover:underline me-4 md:me-6">{{ __("messages.navbar.about") }}</a>
                </li>
                <li>
                    <a href="#" class="hover:underline me-4 md:me-6">{{ __("messages.navbar.privacy_policy") }}</a>
                </li>
                <li>
                    <a href="#" class="hover:underline">{{ __("messages.navbar.contact") }}</a>
                </li>
            </ul>
        </div>
        <hr class="border-gray-200 sm:mx-auto dark:border-gray-700 mt-2" />
        <span class="block text-sm text-gray-500 sm:text-center dark:text-gray-400">© {{ \Carbon\Carbon::now()->year }} <a
                href="{{ config("app.url") }}" class="hover:underline">{{ config("app.name") }}™</a>. {{ __("messages.footer.rights") }}.</span>
    </div>
</footer>