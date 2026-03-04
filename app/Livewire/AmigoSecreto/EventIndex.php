<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventIndex extends Component {
    public function render(): View {
        $userId = Auth::id();

        $events = AmigoSecretoEvent::where('organizer_id', $userId)
            ->orWhereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'ACCEPTED');
            })
            ->where('is_archived', false)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.amigo-secreto.event-index', [
            'events' => $events,
        ]);
    }
}
