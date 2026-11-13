<div>
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-100 mb-8">{{ __("messages.amigo_secreto.create") }}</h1>

        <form wire:submit="create" class="space-y-6 bg-gray-800 border border-gray-700 rounded-xl p-8">
            {{-- Event Name --}}
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ __("messages.amigo_secreto.name") }} *
                </label>
                <input type="text" id="name" wire:model="name"
                       class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                       placeholder="Christmas Gift Exchange 2026">
                @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ __("messages.amigo_secreto.description") }}
                </label>
                <textarea id="description" wire:model="description" rows="3"
                          class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                          placeholder="A fun gift exchange for the holidays!"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Event Type --}}
            <div>
                <label for="event_type" class="block text-sm font-medium text-gray-300 mb-1">
                    {{ __("messages.amigo_secreto.event_type") }} *
                </label>
                <select id="event_type" wire:model="event_type"
                        class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                    <option value="CASUAL">Casual</option>
                    <option value="CHRISTMAS">Christmas</option>
                    <option value="BIRTHDAY">Birthday</option>
                    <option value="WEDDING">Wedding</option>
                </select>
                @error('event_type') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Budget Range --}}
            <div data-event-budget class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="budget_min" class="block text-sm font-medium text-gray-300 mb-1">
                        {{ __('event_budget.minimum_label') }}
                    </label>
                    <input type="number" id="budget_min" wire:model="budget_min" step="0.01" min="0" max="99999999.99"
                           aria-invalid="{{ $errors->has('budget_min') ? 'true' : 'false' }}"
                           aria-describedby="event-budget-help{{ $errors->has('budget_min') ? ' budget_min-error' : '' }}"
                           class="min-h-11 w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                           placeholder="20.00">
                    @error('budget_min') <p id="budget_min-error" role="alert" class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="budget_max" class="block text-sm font-medium text-gray-300 mb-1">
                        {{ __('event_budget.maximum_label') }}
                    </label>
                    <input type="number" id="budget_max" wire:model="budget_max" step="0.01" min="0" max="99999999.99"
                           aria-invalid="{{ $errors->has('budget_max') ? 'true' : 'false' }}"
                           aria-describedby="event-budget-help{{ $errors->has('budget_max') ? ' budget_max-error' : '' }}"
                           class="min-h-11 w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                           placeholder="100.00">
                    @error('budget_max') <p id="budget_max-error" role="alert" class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <p id="event-budget-help" class="col-span-full text-sm text-gray-300">{{ __('event_budget.help') }}</p>
            </div>

            <div>
                <label for="recipient_visibility" class="block text-sm font-medium text-gray-300 mb-1">{{ __('assignments.visibility') }}</label>
                <select id="recipient_visibility" wire:model="recipient_visibility"
                        class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100">
                    <option value="NAMED">{{ __('assignments.named') }}</option>
                    <option value="ANONYMOUS_UNTIL_REVEAL">{{ __('assignments.anonymous') }}</option>
                </select>
                <p class="mt-2 text-sm text-gray-400">{{ __('assignments.visibility_help') }}</p>
                @error('recipient_visibility') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="event_date" class="block text-sm font-medium text-gray-300 mb-1">
                        {{ __("messages.amigo_secreto.event_date") }}
                    </label>
                    <input type="date" id="event_date" wire:model="event_date"
                           class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                    @error('event_date') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="reveal_date" class="block text-sm font-medium text-gray-300 mb-1">
                        {{ __("messages.amigo_secreto.reveal_date") }}
                    </label>
                    <input type="date" id="reveal_date" wire:model="reveal_date"
                           class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                    @error('reveal_date') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Family (optional) --}}
            @if ($families && $families->isNotEmpty())
                <div>
                    <label for="family_id" class="block text-sm font-medium text-gray-300 mb-1">
                        Family (optional)
                    </label>
                    <select id="family_id" wire:model="family_id"
                            class="w-full rounded-lg border-gray-600 bg-gray-700 text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">-- No family --</option>
                        @foreach ($families as $family)
                            <option value="{{ $family->id }}">{{ $family->name }}</option>
                        @endforeach
                    </select>
                    @error('family_id') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('amigo-secreto.index') }}"
                   class="px-4 py-2 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors">
                    {{ __("messages.buttons.cancel") }}
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                    {{ __("messages.buttons.create") }}
                </button>
            </div>
        </form>
    </div>
</div>
