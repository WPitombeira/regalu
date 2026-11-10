<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class EventDetail extends Component
{
    public AmigoSecretoEvent $event;

    public function mount(AmigoSecretoEvent $event): void
    {
        $this->event = $event;
        $this->event = $this->authorizedEvent();
    }

    /** @return array<int, array{key: string, label: string, completed: bool, current: bool}> */
    private function getStatusSteps(): array
    {
        $statuses = ['PLANNING', 'INVITES_SENT', 'DRAW_PENDING', 'DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'];
        $currentIndex = array_search($this->event->status, $statuses);

        return collect($statuses)->map(function (string $status, int $index) use ($currentIndex) {
            return [
                'key' => $status,
                'label' => __('messages.amigo_secreto.status_'.strtolower($status)),
                'completed' => $index <= $currentIndex,
                'current' => $index === $currentIndex,
            ];
        })->all();
    }

    public function render(): View
    {
        $this->event = $this->authorizedEvent();
        $myAssignment = AmigoSecretoDraw::query()
            ->where('event_id', $this->event->getKey())
            ->where('drawer_user_id', Auth::id())
            ->with('target')
            ->first();

        $isOrganizer = $this->event->organizer_id === Auth::id();

        $isParticipant = $this->event->participants()
            ->where('user_id', Auth::id())
            ->where('status', 'ACCEPTED')
            ->exists();

        return view('livewire.amigo-secreto.event-detail', [
            'myAssignment' => $myAssignment,
            'isOrganizer' => $isOrganizer,
            'isParticipant' => $isParticipant,
            'statusSteps' => $this->getStatusSteps(),
        ]);
    }

    private function authorizedEvent(): AmigoSecretoEvent
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->event->getKey());
        Gate::authorize('view', $event);
        $event->setRelation('participants', $event->participants()
            ->get(['id', 'event_id', 'status']));

        return $event;
    }
}
