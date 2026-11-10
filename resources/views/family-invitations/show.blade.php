<x-layouts.app>
    <section class="bg-[#070715] px-4 py-8">
        <div class="max-w-xl mx-auto rounded-lg border border-gray-800 bg-gray-900 p-6 text-white">
            <h1 class="text-2xl font-semibold mb-4">{{ __('family_invitations.heading') }}</h1>
            @if($invitation)
                <p class="mb-4">{{ __('family_invitations.summary', ['family' => $invitation->family->name]) }}</p>
                @if($invitation->status === 'PENDING')
                    <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col sm:flex-row gap-3">
                        @csrf
                        <button type="submit" name="decision" value="accept" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700">{{ __('family_invitations.accept') }}</button>
                        <button type="submit" name="decision" value="decline" class="px-4 py-2 rounded-lg border border-gray-600 hover:bg-gray-800">{{ __('family_invitations.decline') }}</button>
                    </form>
                @else
                    <p role="status">{{ __('family_invitations.status.'.strtolower($invitation->status)) }}</p>
                @endif
            @else
                <p role="alert">{{ __('family_invitations.unavailable') }}</p>
            @endif
            <a href="{{ route('families.index') }}" class="mt-6 inline-block text-blue-400 hover:underline">{{ __('family_invitations.back') }}</a>
        </div>
    </section>
</x-layouts.app>
