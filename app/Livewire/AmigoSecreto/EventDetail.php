<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoDraw;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventDetail extends Component {
    public AmigoSecretoEvent $event;

    public function mount(AmigoSecretoEvent $event): void {
        $this->event = $event->load(['organizer', 'participants.user', 'draws']);
    }

    /** @return array<int, array{key: string, label: string, completed: bool, current: bool}> */
    private function getStatusSteps(): array {
        $statuses = ['PLANNING', 'INVITES_SENT', 'DRAW_PENDING', 'DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'];
        $currentIndex = array_search($this->event->status, $statuses);

        return collect($statuses)->map(function (string $status, int $index) use ($currentIndex) {
            return [
                'key' => $status,
                'label' => __("messages.amigo_secreto.status_" . strtolower($status)),
                'completed' => $index <= $currentIndex,
                'current' => $index === $currentIndex,
            ];
        })->all();
    }

    public function render(): View {
        /** @var ?AmigoSecretoDraw $myAssignment */
        $myAssignment = $this->event->draws
            ->where('drawer_user_id', Auth::id())
            ->first();

        $isOrganizer = $this->event->organizer_id === Auth::id();

        $isParticipant = $this->event->participants
            ->where('user_id', Auth::id())
            ->where('status', 'ACCEPTED')
            ->isNotEmpty();

        return view('livewire.amigo-secreto.event-detail', [
            'myAssignment' => $myAssignment,
            'isOrganizer' => $isOrganizer,
            'isParticipant' => $isParticipant,
            'statusSteps' => $this->getStatusSteps(),
        ]);
    }
}
