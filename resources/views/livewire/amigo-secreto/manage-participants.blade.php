<div>
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.amigo_secreto.participants") }}</h1>
            <a href="{{ route('amigo-secreto.show', $event) }}"
               class="text-sm text-gray-400 hover:text-gray-200 transition-colors">
                &larr; Back to event
            </a>
        </div>

        {{-- Invite by Email --}}
        @if ($event->organizer_id === auth()->id())
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-8">
                <h2 class="text-lg font-semibold text-gray-200 mb-4">Invite by Email</h2>
                <form wire:submit="inviteByEmail" class="flex gap-3">
                    <div class="flex-1">
                        <input type="email" wire:model="inviteEmail"
                               class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                               placeholder="friend@example.com">
                        @error('inviteEmail') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 transition-colors whitespace-nowrap">
                        Send Invite
                    </button>
                </form>

                <div class="mt-4 p-3 bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-400">
                        Or share this code:
                        <code class="ml-2 font-mono font-bold text-blue-400 tracking-wider">{{ $event->event_code }}</code>
                    </p>
                </div>
            </div>
        @endif

        {{-- Participants List --}}
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold text-gray-200">
                    Current Participants ({{ $participants->count() }})
                </h2>
            </div>

            @if ($participants->isEmpty())
                <div class="px-6 py-8 text-center text-gray-500">
                    No participants yet. Invite someone to get started!
                </div>
            @else
                <ul class="divide-y divide-gray-700">
                    @foreach ($participants as $participant)
                        <li class="px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-gray-300 font-medium text-sm">
                                    {{ $participant->user ? strtoupper(substr($participant->user->name, 0, 2)) : '??' }}
                                </div>
                                <div>
                                    <p class="text-gray-100 font-medium">
                                        {{ $participant->user ? $participant->user->name : $participant->invite_email }}
                                        @if ($participant->user_id === $event->organizer_id)
                                            <span class="ml-2 text-xs text-yellow-400">(Organizer)</span>
                                        @endif
                                    </p>
                                    @if ($participant->user && $participant->invite_email)
                                        <p class="text-sm text-gray-500">{{ $participant->invite_email }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($participant->status)
                                        @case('ACCEPTED') bg-green-900 text-green-300 @break
                                        @case('INVITED') bg-yellow-900 text-yellow-300 @break
                                        @case('DECLINED') bg-red-900 text-red-300 @break
                                        @case('WITHDRAWN') bg-gray-700 text-gray-400 @break
                                    @endswitch
                                ">
                                    {{ $participant->status }}
                                </span>

                                @if ($event->organizer_id === auth()->id() && $participant->user_id !== $event->organizer_id)
                                    <button wire:click="removeParticipant({{ $participant->id }})"
                                            wire:confirm="Are you sure you want to remove this participant?"
                                            class="text-red-400 hover:text-red-300 transition-colors">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
