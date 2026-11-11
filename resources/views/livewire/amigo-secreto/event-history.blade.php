<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('amigo-secreto.index', ['period' => 'past']) }}" class="text-sm text-blue-400 hover:underline">&larr; {{ __('event_history.back') }}</a>
    <header class="space-y-3">
        <p class="text-sm font-medium text-blue-400">{{ __('event_history.title') }}</p>
        <h1 class="text-3xl font-bold text-gray-100 break-words">{{ $event->name }}</h1>
        <div class="flex flex-wrap gap-2 text-sm text-gray-300">
            <span class="bg-gray-700 rounded-full px-3 py-1">{{ __('messages.amigo_secreto.status_'.strtolower($event->status)) }}</span>
            @if ($event->is_archived)
                <span class="bg-gray-700 rounded-full px-3 py-1">{{ __('event_history.archived') }}</span>
            @endif
        </div>
        @if ($event->description)
            <p class="text-gray-400 whitespace-pre-line break-words">{{ $event->description }}</p>
        @endif
    </header>
    <p class="bg-gray-800 border border-gray-700 rounded-xl p-6 text-gray-300">{{ __('event_history.privacy') }}</p>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <dt class="text-sm text-gray-400">{{ __('event_history.accepted') }}</dt>
            <dd class="mt-2 text-xl text-gray-100">{{ $event->accepted_count }}</dd>
        </div>
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <dt class="text-sm text-gray-400">{{ __('messages.amigo_secreto.event_date') }}</dt>
            <dd class="mt-2 text-xl text-gray-100">{{ $event->event_date?->locale(app()->getLocale())->isoFormat('LL') ?? __('event_history.undated') }}</dd>
        </div>
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
            <dt class="text-sm text-gray-400">{{ __('messages.amigo_secreto.event_type') }}</dt>
            <dd class="mt-2 text-xl text-gray-100">{{ $event->event_type }}</dd>
        </div>
        @if ($event->budget_min !== null || $event->budget_max !== null)
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                <dt class="text-sm text-gray-400">{{ __('messages.amigo_secreto.budget') }}</dt>
                <dd class="mt-2 text-xl text-gray-100">{{ __('assignments.price_range', ['min' => $event->budget_min ?? '—', 'max' => $event->budget_max ?? '—']) }}</dd>
            </div>
        @endif
    </dl>
</div>
