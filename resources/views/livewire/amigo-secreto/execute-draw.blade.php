<div>
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.amigo_secreto.draw") }}</h1>
            <a href="{{ route('amigo-secreto.show', $event) }}"
               class="text-sm text-gray-400 hover:text-gray-200 transition-colors">
                &larr; Back to event
            </a>
        </div>

        @if ($event->status !== 'DRAWS_COMPLETE' && $event->status !== 'REVEALED' && $event->status !== 'COMPLETED')
            {{-- Execute Draw Section --}}
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 mb-8 text-center">
                <svg class="mx-auto h-20 w-20 text-blue-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                </svg>

                <h2 class="text-xl font-semibold text-gray-100 mb-2">Ready to draw?</h2>
                <p class="text-gray-400 mb-6">
                    This will randomly assign each participant a person to give a gift to.
                    The draw cannot be undone.
                </p>

                <p class="text-sm text-gray-500 mb-6">
                    {{ $event->participants()->where('status', 'ACCEPTED')->count() }} accepted participants
                    @if ($event->exclusions()->count() > 0)
                        &middot; {{ $event->exclusions()->count() }} exclusion(s) set
                    @endif
                </p>

                @if ($isOrganizer)
                    <button wire:click="executeDraw"
                            wire:confirm="Are you sure? This will assign Secret Santa pairs and cannot be undone."
                            class="px-8 py-3 bg-blue-600 rounded-xl text-lg font-semibold text-white hover:bg-blue-700 transition-colors shadow-lg shadow-blue-600/25">
                        {{ __("messages.amigo_secreto.draw") }}
                    </button>
                @else
                    <p class="text-gray-500 italic">Only the organizer can execute the draw.</p>
                @endif
            </div>
        @else
            {{-- Draw Results --}}
            <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-200">Draw Results</h2>
                </div>

                @if ($isOrganizer)
                    {{-- Organizer sees all draws --}}
                    <ul class="divide-y divide-gray-700">
                        @foreach ($draws as $draw)
                            <li class="px-6 py-4 flex items-center gap-3">
                                <div class="flex-1">
                                    <span class="text-gray-100 font-medium">{{ $draw->drawer->name }}</span>
                                </div>
                                <svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                                <div class="flex-1 text-right">
                                    <span class="text-green-400 font-medium">{{ $draw->target->name }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    {{-- Participant sees only their assignment --}}
                    @if ($myDraw)
                        <div class="px-6 py-8 text-center">
                            <p class="text-gray-400 mb-2">You drew:</p>
                            <p class="text-3xl font-bold text-green-400">{{ $myDraw->target->name }}</p>
                        </div>
                    @else
                        <div class="px-6 py-8 text-center text-gray-500">
                            You are not part of this draw.
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>
