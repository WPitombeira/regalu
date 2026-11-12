<div class="mx-auto max-w-3xl space-y-6" data-testid="getting-started" data-step="{{ $step }}">
    <h1 class="text-3xl font-bold text-gray-100">{{ __('onboarding.title') }}</h1>
    <p class="text-gray-300">{{ __('onboarding.intro') }}</p>
    <p class="text-gray-300">{{ __('onboarding.private_first') }}</p>
    @if ($wishlist)
        <p class="break-words font-semibold text-gray-100" data-testid="onboarding-wishlist">{{ $wishlist->name }}</p>
    @endif
    @if ($step === 'privacy')
        <p class="rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-100">{{ __('onboarding.ready') }}</p>
    @endif
    <ol class="space-y-4">
        @foreach (['create', 'add', 'privacy'] as $stage)
            <li class="rounded-xl border border-gray-700 bg-gray-800 p-5" @if ($step === $stage) aria-current="step" @endif>
                <div class="flex flex-wrap items-center gap-3">
                    <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-700 text-gray-100">{{ $loop->iteration }}</span>
                    <h2 class="text-xl font-semibold text-gray-100">{{ __('onboarding.'.$stage) }}</h2>
                    @if ($step === $stage)<span class="text-sm text-blue-200">{{ __('onboarding.next') }}</span>@endif
                </div>
                <p class="mt-3 text-gray-300">{{ __('onboarding.'.$stage.'_help') }}</p>
                @if ($stage === 'create')
                    @if ($wishlist)
                        <p class="mt-3 text-emerald-300">{{ __('onboarding.created') }}</p>
                    @else
                        <a href="{{ route('wishlists.create') }}" class="mt-4 inline-flex rounded-lg bg-blue-600 px-4 py-3 font-medium text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-blue-300">{{ __('onboarding.create') }}</a>
                    @endif
                @elseif ($stage === 'add')
                    @if ($wishlist)
                        @if ($wishlist->items_exists)<p class="mt-3 text-emerald-300">{{ __('onboarding.added') }}</p>@endif
                        <a href="{{ route('wishlists.show', $wishlist) }}" class="mt-4 inline-flex rounded-lg bg-blue-600 px-4 py-3 font-medium text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-blue-300">{{ __('onboarding.open') }}</a>
                    @else
                        <p class="mt-3 text-sm text-gray-400">{{ __('onboarding.waiting') }}</p>
                    @endif
                @else
                    @if ($wishlist)
                        <p class="mt-3 font-medium text-gray-100" data-testid="onboarding-privacy">{{ __('onboarding.'.strtolower($wishlist->privacy)) }}</p>
                        <a href="{{ route('wishlists.settings', $wishlist) }}" class="mt-4 inline-flex rounded-lg border border-blue-400 px-4 py-3 font-medium text-blue-200 hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-blue-300">{{ __('onboarding.review') }}</a>
                    @else
                        <p class="mt-3 text-sm text-gray-400">{{ __('onboarding.waiting') }}</p>
                    @endif
                    <p class="mt-3 text-sm text-gray-300">{{ __('onboarding.sharing_help') }}</p>
                @endif
            </li>
        @endforeach
    </ol>
    <a href="{{ route('dashboard') }}" class="inline-flex rounded-lg px-4 py-3 text-blue-300 hover:underline focus-visible:outline-2 focus-visible:outline-blue-300">{{ __('onboarding.dashboard') }}</a>
</div>
