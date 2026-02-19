<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white">{{ $family->name }}</h1>
            @if($family->description)
                <p class="text-[#C3C3D1] mt-2">{{ $family->description }}</p>
            @endif
        </div>
        <a href="{{ route('families.settings', $family) }}"
           class="px-4 py-2 text-sm font-medium text-[#C3C3D1] bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
            {{ __("messages.family.settings") }}
        </a>
    </div>

    {{-- Invite Code Section --}}
    <div class="mb-8 p-4 bg-gray-900 border border-gray-800 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-400">{{ __("messages.family.invite_code") }}</span>
                <p class="text-lg font-mono text-white mt-1" x-ref="inviteCode">{{ $family->invite_code }}</p>
            </div>
            <button x-data
                    x-on:click="navigator.clipboard.writeText($refs.inviteCode?.closest('[x-ref]')?.textContent.trim() ?? '{{ $family->invite_code }}'); $dispatch('notification', { message: 'Copied!', type: 'success' })"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Copy
            </button>
        </div>
    </div>

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
                                    wire:confirm="Promote this member to admin?"
                                    class="px-3 py-1.5 text-xs font-medium text-blue-400 bg-blue-900/30 border border-blue-800 rounded-lg hover:bg-blue-900/50 transition-colors">
                                Promote
                            </button>
                        @endif
                        <button wire:click="removeMember({{ $member->id }})"
                                wire:confirm="Remove this member from the family?"
                                class="px-3 py-1.5 text-xs font-medium text-red-400 bg-red-900/30 border border-red-800 rounded-lg hover:bg-red-900/50 transition-colors">
                            Remove
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
