<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white">{{ $family->name }}</h1>
            @if($family->description)
                <p class="text-[#C3C3D1] mt-2">{{ $family->description }}</p>
            @endif
        </div>
        @can('manage', $family)
            <a href="{{ route('families.settings', $family) }}"
               class="px-4 py-2 text-sm font-medium text-[#C3C3D1] bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                {{ __("messages.family.settings") }}
            </a>
        @endcan
    </div>

    {{-- Invite Code Section --}}
    <div class="mb-8 p-4 bg-gray-900 border border-gray-800 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-400">{{ __("messages.family.invite_code") }}</span>
                <p class="text-lg font-mono text-white mt-1" x-ref="inviteCode">{{ $family->invite_code }}</p>
            </div>
            <button x-data
                    x-on:click="navigator.clipboard.writeText($refs.inviteCode?.closest('[x-ref]')?.textContent.trim() ?? '{{ $family->invite_code }}'); $dispatch('notification', { message: @js(__('family_forms.copied')), type: 'success' })"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                {{ __('family_forms.copy') }}
            </button>
        </div>
    </div>

    @can('manage', $family)
        <section aria-labelledby="email-invitations-heading" class="mb-8 p-4 bg-gray-900 border border-gray-800 rounded-lg">
            <h2 id="email-invitations-heading" class="text-xl font-semibold text-white">{{ __('family_invitations.admin_heading') }}</h2>
            <p id="email-invitations-help" class="mt-2 text-sm text-gray-400">{{ __('family_invitations.help') }}</p>
            @can('update', $family)
                <form wire:submit="inviteByEmail" class="mt-4">
                    <label for="invite-email" class="block mb-2 text-sm font-medium text-white">{{ __('family_invitations.email') }}</label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input id="invite-email" type="email" wire:model="inviteEmail" required maxlength="255" autocomplete="email"
                               aria-describedby="email-invitations-help invite-email-error"
                               class="min-w-0 flex-1 rounded-lg bg-gray-800 border border-gray-700 p-2.5 text-white">
                        <button type="submit" wire:loading.attr="disabled" wire:target="inviteByEmail"
                                class="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">{{ __('family_invitations.send') }}</button>
                    </div>
                    <div id="invite-email-error" role="alert" class="mt-2 text-sm text-red-400">@error('inviteEmail') {{ $message }} @enderror</div>
                </form>
            @endcan
            <div class="mt-4 space-y-3">
                @forelse($invitations as $invitation)
                    <article wire:key="family-invitation-{{ $invitation->id }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-gray-800 pt-3">
                        <div class="min-w-0">
                            <p class="text-sm text-white break-all">{{ $invitation->email }}</p>
                            <p class="text-sm text-gray-400">
                                {{ $invitation->status === 'PENDING' && $invitation->expires_at->isPast() ? __('family_invitations.expired') : __('family_invitations.status.'.strtolower($invitation->status)) }}
                                @if($invitation->status === 'PENDING' && $invitation->expires_at->isFuture())
                                    <span class="block">{{ __('family_invitations.expires', ['date' => $invitation->expires_at->toDateString()]) }}</span>
                                @endif
                            </p>
                        </div>
                        @can('update', $family)
                            @if($invitation->status === 'PENDING')
                                <button type="button" wire:click="cancelInvitation({{ $invitation->id }})" wire:confirm="{{ __('family_invitations.cancel_confirm') }}"
                                        wire:loading.attr="disabled" wire:target="cancelInvitation"
                                        class="shrink-0 px-3 py-2 text-sm text-red-300 bg-red-900/30 border border-red-800 rounded-lg disabled:opacity-50">{{ __('family_invitations.cancel') }}</button>
                            @endif
                        @endcan
                    </article>
                @empty
                    <p class="text-sm text-gray-400">{{ __('family_invitations.empty') }}</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $invitations->links('livewire.family.invitation-pagination') }}</div>
        </section>
    @endcan

    {{-- Members List --}}
    <h2 class="text-xl font-semibold text-white mb-4">{{ __("messages.family.members") }} ({{ $members->count() }})</h2>

    <div class="space-y-3">
        @foreach($members as $member)
            <div class="flex items-center justify-between p-4 bg-gray-900 border border-gray-800 rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-700 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold text-sm">
                            {{ strtoupper(substr($member->user->name, 0, 1)) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-white font-medium">{{ $member->user->name }}</p>
                        <p class="text-sm text-gray-400">{{ $member->user->email }}</p>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $member->role === 'ADMIN' ? 'bg-blue-900 text-blue-300' : 'bg-gray-700 text-gray-300' }}">
                        {{ $member->role === 'ADMIN' ? __("messages.family.role_admin") : __("messages.family.role_member") }}
                    </span>
                </div>

                @php
                    $isAdmin = $members->where('user_id', auth()->id())->where('role', 'ADMIN')->isNotEmpty();
                @endphp

                @if($isAdmin && $member->user_id !== auth()->id())
                    <div class="flex items-center gap-2">
                        @if($member->role !== 'ADMIN')
                            <button wire:click="promoteMember({{ $member->id }})"
                                    wire:confirm="{{ __('family_forms.promote_confirm') }}"
                                    class="px-3 py-1.5 text-xs font-medium text-blue-400 bg-blue-900/30 border border-blue-800 rounded-lg hover:bg-blue-900/50 transition-colors">
                                {{ __('family_forms.promote') }}
                            </button>
                        @endif
                        @if($member->user_id !== $family->creator_id)
                            <button wire:click="removeMember({{ $member->id }})"
                                    wire:confirm="{{ __('family_forms.remove_confirm') }}"
                                    class="px-3 py-1.5 text-xs font-medium text-red-400 bg-red-900/30 border border-red-800 rounded-lg hover:bg-red-900/50 transition-colors">
                                {{ __('family_forms.remove') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
