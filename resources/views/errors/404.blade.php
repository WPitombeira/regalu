<x-layouts.app>
    <div class="flex items-center justify-center h-screen">
        <div class="text-center">
            <h1 class="text-9xl font-bold text-gray-900 dark:text-white">404</h1>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Page not found</h2>
            <p class="text-gray-700 dark:text-gray-300">The page you are looking for might have been removed had its name changed or is temporarily unavailable.</p>
            <a href="{{ route('home') }}"
                class="text-primary-600 hover:underline dark:text-primary-500">Go back home</a>
        </div>
    </div>
</x-layouts.app>