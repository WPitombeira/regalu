<div class="max-w-4xl mx-auto">
    <a href="{{ route('amigo-secreto.show', $event) }}" class="text-sm text-gray-400 hover:text-gray-200">&larr; {{ __('assignments.back') }}</a>
    <h1 class="text-3xl font-bold text-gray-100 mt-4 mb-3">{{ __('assignments.title') }}</h1>
    <p class="text-lg text-green-300 mb-6">{{ $assignment['recipient_name'] === null ? __('assignments.hidden_recipient') : __('assignments.you_drew', ['name' => $assignment['recipient_name']]) }}</p>
    @if ($assignment['recipient_name'] === null && $event->reveal_date)
        <p class="text-sm text-gray-400 mb-6">{{ __('messages.amigo_secreto.reveal_date') }}: {{ $event->reveal_date->format('M d, Y') }}</p>
    @endif
    @if ($items === null)
        <p class="bg-gray-800 border border-gray-700 rounded-xl p-6 text-gray-300">{{ $event->status === 'COMPLETED' ? __('event_history.closed') : __('assignments.not_shared') }}</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            @forelse ($items as $item)
                <article class="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-3">
                    @if ($item['image_url'] && preg_match('/^https?:\/\//i', $item['image_url']))
                        <img src="{{ $item['image_url'] }}" alt="" referrerpolicy="no-referrer" loading="lazy" class="w-full h-40 object-contain rounded-lg">
                    @endif
                    <h2 class="text-xl font-semibold text-gray-100">{{ $item['name'] }}</h2>
                    <p class="text-sm text-gray-400">{{ __('assignments.priority_'.strtolower($item['priority'])) }}</p>
                    <x-wishlist.price :minimum="$item['price_min']" :maximum="$item['price_max']" :currency="$item['currency']" class="block text-gray-300" />
                    @if ($item['url'] && preg_match('/^https?:\/\//i', $item['url']))
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="block text-blue-400 underline">{{ __('assignments.store') }}</a>
                    @endif
                    @if ($item['status'] === 'AVAILABLE')
                        <button type="button" wire:click="markAsBought('{{ $item['token'] }}')" wire:loading.attr="disabled" class="px-4 py-2 bg-green-700 rounded-lg text-white">{{ __('assignments.buy') }}</button>
                    @else
                        <p class="text-sm text-gray-300">{{ __('assignments.bought') }}</p>
                    @endif
                </article>
            @empty
                <p class="text-gray-400">{{ __('assignments.empty') }}</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    @endif
</div>
