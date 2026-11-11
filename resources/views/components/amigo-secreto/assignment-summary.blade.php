@props(['assignment', 'event'])
<div class="bg-green-900/30 border border-green-700 rounded-xl p-6 mb-8">
    <h2 class="text-lg font-semibold text-green-300 mb-2">{{ __('assignments.title') }}</h2>
    <p class="text-xl font-bold text-gray-100">
        {{ $assignment['recipient_name'] === null ? __('assignments.hidden_recipient') : __('assignments.you_drew', ['name' => $assignment['recipient_name']]) }}
    </p>
    <a href="{{ route('amigo-secreto.assignment', $event) }}" class="inline-flex mt-4 px-4 py-2 bg-blue-600 rounded-lg text-white">{{ __('assignments.open') }}</a>
</div>
