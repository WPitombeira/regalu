<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EventIndex extends Component
{
    use WithPagination;

    #[Url(except: 'active')]
    public string $period = 'active';

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($this->period, ['active', 'past'], true), 404);

        $query = $this->period === 'past'
            ? AmigoSecretoEvent::historyFor($user)
            : AmigoSecretoEvent::visibleTo($user)->where('is_archived', false)->where('status', '!=', 'COMPLETED');
        $events = $query->select(['id', 'name', 'event_type', 'status', 'is_archived', 'event_date', 'created_at'])
            ->withCount('participants')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(12);

        return view('livewire.amigo-secreto.event-index', [
            'events' => $events,
        ]);
    }
}
