<x-layouts.app>
    <section class="bg-[#070715]">
        <div class="max-w-5xl mx-auto py-8">
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-bold text-white">{{ __("messages.family.families") }}</h1>
                <div class="flex gap-3">
                    <a href="{{ route('families.join') }}"
                       class="px-4 py-2 text-sm font-medium text-[#C3C3D1] bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                        {{ __("messages.family.join") }}
                    </a>
                    <a href="{{ route('families.create') }}"
                       class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        {{ __("messages.family.create") }}
                    </a>
                </div>
            </div>

            @php
                $families = auth()->user()->families()->withCount('members')->where('is_archived', false)->get();
            @endphp

            @if($families->isEmpty())
                <div class="flex flex-col items-center justify-center py-16">
                    <p class="text-[#C3C3D1] text-lg mb-4">You don't belong to any family yet.</p>
                    <div class="flex gap-3">
                        <a href="{{ route('families.create') }}"
                           class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                            {{ __("messages.family.create") }}
                        </a>
                        <a href="{{ route('families.join') }}"
                           class="px-4 py-2 text-sm font-medium text-[#C3C3D1] bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                            {{ __("messages.family.join") }}
                        </a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($families as $family)
                        <a href="{{ route('families.show', $family) }}"
                           class="block p-6 bg-gray-900 border border-gray-800 rounded-lg hover:border-gray-600 transition-colors">
                            <h3 class="text-xl font-semibold text-white mb-2">{{ $family->name }}</h3>
                            @if($family->description)
                                <p class="text-[#C3C3D1] text-sm mb-4 line-clamp-2">{{ $family->description }}</p>
                            @endif
                            <div class="flex items-center text-sm text-gray-400">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $family->members_count }} {{ __("messages.family.members") }}
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
