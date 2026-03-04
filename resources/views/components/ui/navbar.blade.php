<nav class="bg-white border-gray-200 dark:bg-gray-900">
  <div class="w-full p-4">
    <div class="px-12 flex items-center justify-between">
      <a 
      @if (Auth::check())
      href="/home"  
      @else
      href="/"  
      @endif class="flex items-center space-x-3 rtl:space-x-reverse">
        <img src="{{ asset('media/logo.png') }}" class="h-8" alt="{{ config(" app.name") }} Logo" />
        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">{{ config("app.name")
          }}</span>
      </a>
      @if (Auth::check())
        <livewire:notifications.notification-bell />
      @endif
      <x-ui.user.card />
      <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-user">
        <ul
          class="flex flex-col font-medium p-4 md:p-0 mt-4 border border-gray-100 rounded-lg bg-gray-50 md:space-x-8 rtl:space-x-reverse md:flex-row md:mt-0 md:border-0 md:bg-white dark:bg-gray-800 md:dark:bg-gray-900 dark:border-gray-700">
          @if (Auth::check())
          <li>
            <a href="/home"
              class="{{ Request::is('/home') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.dashboard") }}</a>
          </li>
          <li>
            <a href="/families"
              class="{{ Request::is('families*') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.families") }}</a>
          </li>
          <li>
            <a href="{{ route('wishlists.index') }}"
              class="{{ Request::is('wishlists*') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.wishlists") }}</a>
          </li>
          <li>
            <a href="{{ route('amigo-secreto.index') }}"
              class="{{ Request::is('secret-santa*') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.secret_santa") }}</a>
          </li>
          @else
          <li>
            <a href="/"
              class="{{ Request::is('/') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.home") }}</a>
          </li>
          <li>
            <a href="/"
              class="{{ Request::is('about') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.about") }}</a>
          </li>
          <li>
            <a href="/"
              class="{{ Request::is('features') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.features") }}</a>
          </li>
          <li>
            <a href="#contact"
              class="{{ Request::is('contact') ? 'md:text-blue-700 md:dark:text-blue-500' : '' }} block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">{{
              __("messages.navbar.contact") }}</a>
          </li>
          @endif
        </ul>
      </div>
    </div>
  </div>
</nav>