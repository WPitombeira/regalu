@props(['provider' => 'apple', 'descriptionId'])

@if ($this->isValidProvider($provider))
    <button type="button" disabled aria-describedby="{{ $descriptionId }}"
        class="min-h-11 min-w-0 flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-600 cursor-not-allowed dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">

        @switch(strtolower($provider))
        @case('apple')
            <x-ui.icons.apple class="h-5 w-5 shrink-0 fill-current" aria-hidden="true" /> <span>Apple</span>
        @break
        @case('google')
            <x-ui.icons.google class="h-5 w-5 shrink-0" aria-hidden="true" /> <span>Google</span>
        @break
        @default

        @endswitch
    </button>
@endif