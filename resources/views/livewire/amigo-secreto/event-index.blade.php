<div>
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.amigo_secreto.events") }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('amigo-secreto.join') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors">
                {{ __("messages.amigo_secreto.join") }}
            </a>
            <a href="{{ route('amigo-secreto.create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                {{ __("messages.amigo_secreto.create") }}
            </a>
        </div>
    </div>

    @if ($events->isEmpty())
        <div class="text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-300">No events yet</h3>
            <p class="mt-2 text-gray-500">Create your first Secret Santa event or join one with a code.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($events as $event)
                <a href="{{ route('amigo-secreto.show', $event) }}"
                   class="block bg-gray-800 border border-gray-700 rounded-xl p-6 hover:border-blue-500 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-100 truncate">{{ $event->name }}</h3>
                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @switch($event->status)
                                @case('PLANNING') bg-yellow-900 text-yellow-300 @break
                                @case('INVITES_SENT') bg-blue-900 text-blue-300 @break
                                @case('DRAW_PENDING') bg-purple-900 text-purple-300 @break
                                @case('DRAWS_COMPLETE') bg-green-900 text-green-300 @break
                                @case('REVEALED') bg-indigo-900 text-indigo-300 @break
                                @case('COMPLETED') bg-gray-700 text-gray-300 @break
                            @endswitch
                        ">
                            {{ __("messages.amigo_secreto.status_" . strtolower($event->status)) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-700 text-gray-300">
                            {{ $event->event_type }}
                        </span>
                    </div>

                    @if ($event->event_date)
                        <p class="text-sm text-gray-400 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ $event->event_date->format('M d, Y') }}
                        </p>
                    @endif

                    <p class="text-sm text-gray-500">
                        {{ $event->participants_count ?? $event->participants->count() }} {{ __("messages.amigo_secreto.participants") }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</div>
