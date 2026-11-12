<div class="flex items-center md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse">
    @if(Auth::check())
    <div type="button" x-data="{show: false}" x-on:click.outside="show = false">
        <button x-on:click="show = !show"
            class="flex text-sm bg-gray-800 rounded-full md:me-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
            id="user-menu-button" aria-expanded="false" aria-haspopup="true">
            <span class="sr-only">Open user menu</span>
            <img class="w-8 h-8 rounded-full" src="{{ auth()->user()->avatar_url ?? asset('media/default-avatar.png') }}"
                alt="user photo">
        </button>

        <!-- Dropdown menu -->
        <div x-show="show" class="z-10 absolute bg-white divide-y divide-gray-100 rounded-lg mt-2 shadow-sm w-44 dark:bg-gray-700" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
            <div class="px-4 py-3">
                <span class="block text-sm text-gray-900 dark:text-white">{{ auth()->user()->name }}</span>
                <span class="block text-sm  text-gray-500 truncate dark:text-gray-400">{{ auth()->user()->email }}</span>
            </div>
            <ul class="py-2">
                <li>
                    <a href="{{ route('dashboard') }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Dashboard</a>
                </li>
                <li>
                    <a href="{{ route('settings') }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Settings</a>
                </li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Sign out</button>
                    </form>
                </li>
            </ul>
        </div>

    </div>
    @else
        <a href="/login"
            class="{{ Request::is('login') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 font-medium text-gray-900 rounded-sm hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
            __("messages.navbar.login") }}</a>
    @endif
    <button @click="primaryOpen = !primaryOpen" :aria-expanded="primaryOpen" type="button"
        class="inline-flex items-center p-2 w-10 h-10 justify-center text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-hidden focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600"
        aria-controls="navbar-user" aria-expanded="false">
        <span class="sr-only">{{ __('navigation.menu') }}</span>
        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M1 1h15M1 7h15M1 13h15" />
        </svg>
    </button>
</div>