<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EventHistory extends Component
{
    #[Locked]
    public int $eventId;

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        // Authorize current participation and project only historical metadata.
        $event = AmigoSecretoEvent::historyFor($user)->whereKey($this->eventId)
            ->select(['id', 'name', 'description', 'event_type', 'status', 'is_archived', 'event_date', 'budget_min', 'budget_max'])
            ->withCount(['participants as accepted_count' => fn ($query) => $query->where('status', 'ACCEPTED')])
            ->first();
        abort_if($event === null, 403);

        return view('livewire.amigo-secreto.event-history', ['event' => $event]);
    }
}
