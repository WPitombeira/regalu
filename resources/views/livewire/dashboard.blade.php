<div class="mx-auto max-w-6xl space-y-8" data-testid="dashboard">
    <h1 class="text-3xl font-bold text-gray-100">{{ __('dashboard.title') }}</h1>
    @if ($assignments->isNotEmpty())
        <section aria-labelledby="dashboard-assignments" class="rounded-xl border border-blue-700 bg-gray-800 p-6">
            <h2 id="dashboard-assignments" class="text-xl font-semibold text-gray-100">{{ __('dashboard.assignments') }}</h2>
            <p class="mt-2 text-gray-300">{{ __('dashboard.assignment_help') }}</p>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($assignments as $assignment)
                    <li class="min-w-0"><a href="{{ route('amigo-secreto.assignment', $assignment) }}" class="block rounded-lg bg-blue-600 p-4 text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-blue-300">
                        <span class="block break-words font-semibold">{{ $assignment->name }}</span>
                        <span class="mt-1 block text-sm">{{ __('dashboard.open_assignment') }}</span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif
    @if ($familyInvitationCount || $eventInvitationCount)
        <section class="rounded-xl border border-blue-800 bg-blue-950 p-6" aria-labelledby="dashboard-invitations">
            <h2 id="dashboard-invitations" class="text-xl font-semibold text-blue-100">{{ __('dashboard.check_email') }}</h2>
            <p class="mt-2 text-blue-100">{{ __('dashboard.invitation_help') }}</p>
            <ul class="mt-3 space-y-1 text-blue-200">
                @if ($familyInvitationCount)
                    <li>{{ trans_choice('dashboard.family_invitations', $familyInvitationCount, ['count' => $familyInvitationCount]) }}</li>
                @endif
                @if ($eventInvitationCount)
                    <li>{{ trans_choice('dashboard.event_invitations', $eventInvitationCount, ['count' => $eventInvitationCount]) }}</li>
                @endif
            </ul>
        </section>
    @endif
    <section class="rounded-xl border border-gray-700 bg-gray-800 p-6" aria-labelledby="dashboard-events">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="dashboard-events" class="text-xl font-semibold text-gray-100">{{ __('dashboard.events') }}</h2>
            <a href="{{ route('amigo-secreto.index') }}" class="rounded-lg p-2 text-sm text-blue-300 hover:underline">{{ __('dashboard.all_events') }}</a>
        </div>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            @forelse ($events as $event)
                <li class="min-w-0">
                    <a href="{{ route('amigo-secreto.show', $event) }}" class="block h-full rounded-lg border border-gray-700 p-4 hover:border-blue-400 focus-visible:outline-2 focus-visible:outline-blue-400">
                        <h3 class="break-words font-semibold text-gray-100">{{ $event->name }}</h3>
                        <p class="mt-2 text-sm text-gray-300">{{ $event->event_date?->locale(app()->getLocale())->isoFormat('LL') ?? __('dashboard.date_pending') }}</p>
                        <p class="mt-1 text-sm text-gray-400">{{ __('messages.amigo_secreto.status_'.strtolower($event->status)) }}</p>
                    </a>
                </li>
            @empty
                <li class="py-2 sm:col-span-2">
                    <p class="text-gray-300">{{ __('dashboard.empty_events') }}</p>
                    <a href="{{ route('amigo-secreto.create') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700">{{ __('dashboard.create_event') }}</a>
                </li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-xl border border-gray-700 bg-gray-800 p-6" aria-labelledby="dashboard-notices">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="dashboard-notices" class="text-xl font-semibold text-gray-100">{{ __('dashboard.notifications') }}</h2>
            <a href="{{ route('notifications.index') }}" class="rounded-lg p-2 text-sm text-blue-300 hover:underline">{{ __('dashboard.all_notifications') }}</a>
        </div>
        <ul class="mt-4 divide-y divide-gray-700">
            @forelse ($notifications as $notification)
                <li class="py-3" wire:key="dashboard-notice-{{ $notification->id }}">
                    <button type="button" wire:click="markAsRead({{ $notification->id }})" wire:loading.attr="disabled"
                            class="rounded-lg py-2 text-left font-semibold text-blue-300 hover:underline focus-visible:outline-2 focus-visible:outline-blue-400">
                        {{ $notification->title }}
                        <span class="sr-only">{{ $notification->action_url ? __('inbox.open') : __('inbox.mark_read') }}</span>
                    </button>
                    @if (! $notification->is_read)<span class="ml-2 text-xs text-blue-200">{{ __('inbox.unread') }}</span>@endif
                    <p class="text-sm text-gray-300">{{ $notification->available ? $notification->message : __('inbox.unavailable') }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->locale(app()->getLocale())->diffForHumans() }}</p>
                </li>
            @empty
                <li class="py-2 text-gray-300">{{ __('dashboard.empty_notifications') }}</li>
            @endforelse
        </ul>
    </section>
    <div class="grid gap-6 md:grid-cols-2">
        @foreach (['wishlists' => $wishlists, 'families' => $families] as $section => $resources)
            <section class="min-w-0 rounded-xl border border-gray-700 bg-gray-800 p-6" aria-labelledby="dashboard-{{ $section }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="dashboard-{{ $section }}" class="text-xl font-semibold text-gray-100">{{ __('dashboard.'.$section) }}</h2>
                    <a href="{{ route($section.'.index') }}" class="rounded-lg p-2 text-sm text-blue-300 hover:underline">{{ __('dashboard.view_all') }}</a>
                </div>
                <ul class="mt-4 space-y-3">
                    @forelse ($resources as $resource)
                        <li><a href="{{ route($section.'.show', $resource) }}" class="block break-words rounded-lg p-2 text-blue-300 hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-blue-400">{{ $resource->name }}</a></li>
                    @empty
                        <li>
                            <p class="text-gray-300">{{ __('dashboard.empty_'.$section) }}</p>
                            <a href="{{ route($section === 'wishlists' ? 'wishlists.create' : 'families.join') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700">{{ __('dashboard.start_'.$section) }}</a>
                        </li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</div>