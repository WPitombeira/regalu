<div class="flex items-center md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse">
    @if(Auth::check())
    <div class="relative" x-data="{show: false}"
        x-on:click.outside="show = false"
        x-on:keydown.escape="if (show) { $event.stopPropagation(); show = false; $refs.trigger.focus() }"
        x-on:focusout="if (!$refs.trigger.contains($event.relatedTarget) && !$refs.panel.contains($event.relatedTarget)) show = false">
        <button type="button" x-ref="trigger" x-on:click="show = !show"
            class="flex text-sm bg-gray-800 rounded-full md:me-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
            id="user-menu-button" aria-expanded="false" x-bind:aria-expanded="show" aria-controls="user-menu-panel">
            <span class="sr-only">{{ __('account_menu.trigger') }}</span>
            <span class="relative block w-8 h-8" x-data="{ loaded: false }">
                <svg x-show="!loaded" class="w-8 h-8 rounded-full text-gray-300" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="8" r="4" stroke-width="2" />
                    <path d="M4 22v-2a8 8 0 0 1 16 0v2" stroke-width="2" />
                </svg>
                @if (filled(auth()->user()->avatar_url))
                    <img x-show="loaded" style="display: none;"
                        x-init="loaded = $el.complete && $el.naturalWidth > 0"
                        x-on:load="loaded = true" x-on:error="loaded = false"
                        class="absolute inset-0 w-8 h-8 rounded-full object-cover" src="{{ auth()->user()->avatar_url }}" alt="">
                @endif
            </span>
        </button>

        <!-- Dropdown menu -->
        <div id="user-menu-panel" x-ref="panel" x-show="show" style="display: none;" class="z-50 absolute right-0 bg-white divide-y divide-gray-100 rounded-lg mt-2 shadow-sm w-44 dark:bg-gray-700">
            <div class="px-4 py-3">
                <span class="block truncate text-sm text-gray-900 dark:text-white">{{ auth()->user()->name }}</span>
                <span class="block text-sm  text-gray-500 truncate dark:text-gray-400">{{ auth()->user()->email }}</span>
            </div>
            <ul class="py-2">
                <li>
                    <a href="{{ route('dashboard') }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">{{ __('messages.navbar.dashboard') }}</a>
                </li>
                <li>
                    <a href="{{ route('settings') }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">{{ __('account_menu.settings') }}</a>
                </li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">{{ __('account_menu.logout') }}</button>
                    </form>
                </li>
            </ul>
        </div>
        <button data-collapse-toggle="navbar-user" type="button"
            class="inline-flex items-center p-2 w-10 h-10 justify-center text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-hidden focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600"
            aria-controls="navbar-user" aria-expanded="false">
            <span class="sr-only">{{ __('account_menu.main_menu') }}</span>
            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M1 1h15M1 7h15M1 13h15" />
            </svg>
        </button>
    </div>
    @else
    <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-user">
      <ul
        class="flex flex-col font-medium p-4 md:p-0 mt-4 border border-gray-100 rounded-lg bg-gray-50 md:space-x-8 rtl:space-x-reverse md:flex-row md:mt-0 md:border-0 md:bg-white dark:bg-gray-800 md:dark:bg-gray-900 dark:border-gray-700">
        <li>
        <a href="/login"
            class="{{ Request::is('login') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded-sm hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
            __("messages.navbar.login") }}</a>
        </li>
      </ul>
    </div>
    @endif
</div>