<div class="max-w-3xl mx-auto">
    {{-- Page header --}}
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-white">
            {{ __('messages.notifications.title') }}
        </h1>
        @if ($notifications->total() > 0)
            <button
                wire:click="markAllRead"
                class="text-sm text-blue-400 hover:text-blue-300 transition-colors"
            >
                {{ __('messages.notifications.mark_all_read') }}
            </button>
        @endif
    </div>

    {{-- Notification list --}}
    <div class="space-y-2">
        @forelse ($notifications as $notification)
            <div
                wire:click="markAsRead({{ $notification->id }})"
                class="flex items-start gap-4 p-4 rounded-lg border transition-colors cursor-pointer
                    {{ !$notification->is_read
                        ? 'bg-gray-800/80 border-gray-700 hover:bg-gray-700/80'
                        : 'bg-gray-800/30 border-gray-800 hover:bg-gray-800/50' }}"
            >
                {{-- Type icon --}}
                <div class="flex-shrink-0 mt-0.5">
                    @switch($notification->type)
                        @case('wishlist')
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-purple-500/20 text-purple-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                            </span>
                            @break
                        @case('family')
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-green-500/20 text-green-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </span>
                            @break
                        @case('secret_santa')
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-red-500/20 text-red-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            @break
                        @default
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-500/20 text-blue-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                    @endswitch
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-white">
                            {{ $notification->title }}
                        </p>
                        @if (!$notification->is_read)
                            <span class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full"></span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ $notification->message }}
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        {{ $notification->created_at->diffForHumans() }}
                    </p>
                </div>

                {{-- Action arrow --}}
                @if ($notification->action_url)
                    <div class="flex-shrink-0 self-center text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-gray-500">
                <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <p class="text-lg font-medium">{{ __('messages.notifications.no_notifications') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($notifications->hasPages())
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
