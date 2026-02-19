<div>
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="flex items-start justify-between mb-8">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-3xl font-bold text-gray-100">{{ $event->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
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
                @if ($event->description)
                    <p class="text-gray-400">{{ $event->description }}</p>
                @endif
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-gray-700 text-gray-300">
                {{ $event->event_type }}
            </span>
        </div>

        {{-- Status Progression --}}
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-between">
                @foreach ($statusSteps as $index => $step)
                    <div class="flex flex-col items-center flex-1">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold mb-2
                            {{ $step['completed'] ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-500' }}
                            {{ $step['current'] ? 'ring-2 ring-blue-400' : '' }}
                        ">
                            @if ($step['completed'] && !$step['current'])
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </div>
                        <span class="text-xs text-center {{ $step['current'] ? 'text-blue-400 font-medium' : 'text-gray-500' }}">
                            {{ $step['label'] }}
                        </span>
                    </div>
                    @if (!$loop->last)
                        <div class="flex-1 h-0.5 mx-2 mt-[-20px] {{ $step['completed'] ? 'bg-blue-600' : 'bg-gray-700' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Event Details --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                <h3 class="text-sm font-medium text-gray-400 mb-1">{{ __("messages.amigo_secreto.participants") }}</h3>
                <p class="text-2xl font-bold text-gray-100">{{ $event->participants->count() }}</p>
            </div>
            @if ($event->event_date)
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                    <h3 class="text-sm font-medium text-gray-400 mb-1">{{ __("messages.amigo_secreto.event_date") }}</h3>
                    <p class="text-2xl font-bold text-gray-100">{{ $event->event_date->format('M d, Y') }}</p>
                </div>
            @endif
            @if ($event->budget_min || $event->budget_max)
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                    <h3 class="text-sm font-medium text-gray-400 mb-1">{{ __("messages.amigo_secreto.budget") }}</h3>
                    <p class="text-2xl font-bold text-gray-100">
                        @if ($event->budget_min && $event->budget_max)
                            ${{ $event->budget_min }} - ${{ $event->budget_max }}
                        @elseif ($event->budget_min)
                            From ${{ $event->budget_min }}
                        @else
                            Up to ${{ $event->budget_max }}
                        @endif
                    </p>
                </div>
            @endif
        </div>

        {{-- Event Code --}}
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-8">
            <h3 class="text-sm font-medium text-gray-400 mb-2">Share this code to invite people</h3>
            <div class="flex items-center gap-3">
                <code class="text-2xl font-mono font-bold text-blue-400 tracking-widest bg-gray-900 px-4 py-2 rounded-lg">
                    {{ $event->event_code }}
                </code>
            </div>
        </div>

        {{-- Assignment (if draws complete and user is participant) --}}
        @if ($myAssignment && in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED']))
            <div class="bg-gradient-to-r from-green-900/50 to-emerald-900/50 border border-green-700 rounded-xl p-6 mb-8">
                <h3 class="text-lg font-semibold text-green-300 mb-2">Your Assignment</h3>
                <p class="text-2xl font-bold text-gray-100">
                    You drew: <span class="text-green-400">{{ $myAssignment->target->name }}</span>
                </p>
            </div>
        @endif

        {{-- Action Buttons --}}
        @if ($isOrganizer)
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('amigo-secreto.participants', $event) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm font-medium text-gray-200 hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    {{ __("messages.amigo_secreto.participants") }}
                </a>
                <a href="{{ route('amigo-secreto.exclusions', $event) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm font-medium text-gray-200 hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    {{ __("messages.amigo_secreto.exclusions") }}
                </a>
                @if (!in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED']))
                    <a href="{{ route('amigo-secreto.draw', $event) }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __("messages.amigo_secreto.draw") }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
