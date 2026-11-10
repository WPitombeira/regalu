<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class EventIndex extends Component
{
    use WithPagination;

    public function render(): View
    {
        $userId = Auth::id();

        $events = AmigoSecretoEvent::where(function ($query) use ($userId) {
            $query->where('organizer_id', $userId)
                ->orWhereHas('participants', function ($participants) use ($userId) {
                    $participants->where('user_id', $userId)->where('status', 'ACCEPTED');
                });
        })
            ->where('is_archived', false)
            ->withCount('participants')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(12);

        return view('livewire.amigo-secreto.event-index', [
            'events' => $events,
        ]);
    }
}
