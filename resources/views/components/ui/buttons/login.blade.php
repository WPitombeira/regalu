@props(['provider' => 'apple'])

@if ($this->isValidProvider($provider))
    <button type="button"
        class="max-h-[50px] text-black bg-white hover:bg-slate-300 focus:ring-4 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-white-800 dark:hover:bg-white-900 dark:focus:ring-slate-800 flex align-middle">

        @switch(strtolower($provider))
        @case('apple')
            <x-ui.icons.apple height="100%" /> <span class="inline-block pl-2 self-center">Apple</span>
        @break
        @case('google')
            <x-ui.icons.google height="100%" /> <span class="inline-block pl-2 self-center">Google</span>
        @break
        @default

        @endswitch
    </button>
@endif