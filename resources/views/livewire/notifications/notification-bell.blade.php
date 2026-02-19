<div
    wire:poll.30s
    x-data="{ open: false }"
    @click.outside="open = false"
    class="relative"
>
    {{-- Bell button --}}
    <button
        @click="open = !open"
        class="relative p-2 text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg transition-colors"
        aria-label="{{ __('messages.navbar.notifications') }}"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        {{-- Unread badge --}}
        @if ($this->unreadCount > 0)
            <span class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full transform translate-x-1 -translate-y-1">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-80 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50"
        style="display: none;"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h3 class="text-sm font-semibold text-white">
                {{ __('messages.notifications.title') }}
            </h3>
            @if ($this->unreadCount > 0)
                <button
                    wire:click="markAllRead"
                    class="text-xs text-blue-400 hover:text-blue-300 transition-colors"
                >
                    {{ __('messages.notifications.mark_all_read') }}
                </button>
            @endif
        </div>

        {{-- Notification items --}}
        <div class="max-h-80 overflow-y-auto">
            @forelse ($this->recentNotifications as $notification)
                <div
                    class="flex items-start gap-3 px-4 py-3 border-b border-gray-700/50 hover:bg-gray-700/50 transition-colors {{ !$notification->is_read ? 'bg-gray-700/30' : '' }}"
                >
                    {{-- Type icon --}}
                    <div class="flex-shrink-0 mt-0.5">
                        @switch($notification->type)
                            @case('wishlist')
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-purple-500/20 text-purple-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                                </span>
                                @break
                            @case('family')
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-500/20 text-green-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                </span>
                                @break
                            @case('secret_santa')
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-500/20 text-red-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </span>
                                @break
                            @default
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-500/20 text-blue-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </span>
                        @endswitch
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">
                            {{ $notification->title }}
                        </p>
                        <p class="text-xs text-gray-400 truncate mt-0.5">
                            {{ $notification->message }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </div>

                    {{-- Unread indicator --}}
                    @if (!$notification->is_read)
                        <span class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-500 text-sm">
                    {{ __('messages.notifications.no_notifications') }}
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="px-4 py-3 border-t border-gray-700">
            <a
                href="{{ route('notifications.index') }}"
                class="block text-center text-sm text-blue-400 hover:text-blue-300 transition-colors"
                @click="open = false"
            >
                {{ __('messages.notifications.title') }} &rarr;
            </a>
        </div>
    </div>
</div>
