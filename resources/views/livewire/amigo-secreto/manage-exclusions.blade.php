<div>
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-100">{{ __("messages.amigo_secreto.exclusions") }}</h1>
            <a href="{{ route('amigo-secreto.show', $event) }}"
               class="text-sm text-gray-400 hover:text-gray-200 transition-colors">
                &larr; Back to event
            </a>
        </div>

        {{-- Add Exclusion --}}
        @if ($event->organizer_id === auth()->id())
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-8">
                <h2 class="text-lg font-semibold text-gray-200 mb-2">Add Exclusion Pair</h2>
                <p class="text-sm text-gray-400 mb-4">
                    Select two participants who should not be matched with each other.
                </p>

                @if ($acceptedParticipants->count() < 2)
                    <p class="text-sm text-yellow-400">
                        You need at least 2 accepted participants to create exclusions.
                    </p>
                @else
                    <form wire:submit="addExclusion" class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <select wire:model="userAId"
                                    class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Select person A --</option>
                                @foreach ($acceptedParticipants as $participant)
                                    @if ($participant->user)
                                        <option value="{{ $participant->user->id }}">{{ $participant->user->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('userAId') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center justify-center text-gray-500">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>

                        <div class="flex-1">
                            <select wire:model="userBId"
                                    class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Select person B --</option>
                                @foreach ($acceptedParticipants as $participant)
                                    @if ($participant->user)
                                        <option value="{{ $participant->user->id }}">{{ $participant->user->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('userBId') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 transition-colors whitespace-nowrap">
                            Add Exclusion
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Exclusions List --}}
        <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold text-gray-200">
                    Current Exclusions ({{ $exclusions->count() }})
                </h2>
            </div>

            @if ($exclusions->isEmpty())
                <div class="px-6 py-8 text-center text-gray-500">
                    No exclusions set. Everyone can potentially be matched with anyone.
                </div>
            @else
                <ul class="divide-y divide-gray-700">
                    @foreach ($exclusions as $exclusion)
                        <li class="px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-gray-100 font-medium">{{ $exclusion->userA->name }}</span>
                                <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <span class="text-gray-100 font-medium">{{ $exclusion->userB->name }}</span>
                                @if ($exclusion->reason)
                                    <span class="text-sm text-gray-500 italic">({{ $exclusion->reason }})</span>
                                @endif
                            </div>

                            @if ($event->organizer_id === auth()->id())
                                <button wire:click="removeExclusion({{ $exclusion->id }})"
                                        wire:confirm="Remove this exclusion?"
                                        class="text-red-400 hover:text-red-300 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
