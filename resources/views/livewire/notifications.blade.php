@props(['notifications'])

@php
    $classes = [
        'success' => 'dark:bg-green-800 dark:text-green-200 bg-green-500 border-green-700',
        'error' => 'dark:bg-red-500 dark:text-red-200 bg-red-500 border-red-700',
        'warning' => 'dark:bg-orange-700 dark:text-orange-200 bg-yellow-500 border-yellow-700',
    ];
@endphp
<div>
    @foreach ($notifications as $notification)
    @php
    $class = $classes[$notification["type"]] ?? 'dark:bg-blue-800 dark:text-blue-200 bg-blue-500 border-blue-700';    
    @endphp
    <div id="toast-parent" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="top-4 right-4 fixed" 
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90"
        >
        <div id="toast-default" class="flex items-center w-full max-w-xs p-4 text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-gray-800" role="alert"
        
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-90"
        x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-blue-500 bg-blue-100 rounded-lg {{ $class }}">
                @switch($notification["type"])
                    @case('success')
                        <x-ui.icons.success />
                        @break
                    @case('warning')
                        <x-ui.icons.warning />
                        @break
                    @case('error')
                        <x-ui.icons.error />
                        @break
                    @default
                        <x-ui.icons.info />
                        
                @endswitch
                <span class="sr-only">{{ $notification["type"] }} icon</span>
            </div>
            <div class="ms-3 text-sm font-normal">{{ $notification["message"] }}</div>
            <button type="button" class="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700" data-dismiss-target="#toast-default" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                </svg>
            </button>
        </div>
    </div>
    @endforeach
</div>