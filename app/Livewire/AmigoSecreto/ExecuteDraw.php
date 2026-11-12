<?php

namespace App\Livewire\AmigoSecreto;

use App\Jobs\SendAssignment;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Services\AssignmentAccess;
use App\Services\DrawAlgorithmService;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use RuntimeException;

class ExecuteDraw extends Component
{
    public AmigoSecretoEvent $event;

    public function mount(AmigoSecretoEvent $event): void
    {
        $this->event = $event;
        $this->event = $this->authorizedEvent('view');
    }

    public function executeDraw(): void
    {
        $event = $this->authorizedEvent('draw');

        if (in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'], true)) {
            $this->dispatch('notification', ['message' => __('messages.amigo_secreto.already_drawn'), 'type' => 'error']);

            return;
        }

        $acceptedParticipants = $event->participants()
            ->where('status', 'ACCEPTED')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        $exclusions = $event->exclusions()
            ->get()
            ->map(fn (Model $exclusion): array => [
                (int) $exclusion->getAttribute('user_a_id'),
                (int) $exclusion->getAttribute('user_b_id'),
            ])
            ->all();

        try {
            $service = new DrawAlgorithmService;
            $results = $service->execute($acceptedParticipants, $exclusions);

            DB::transaction(function () use ($results, $event) {
                foreach ($results as $drawerId => $targetId) {
                    $draw = AmigoSecretoDraw::create([
                        'event_id' => $event->id,
                        'drawer_user_id' => $drawerId,
                        'target_user_id' => $targetId,
                        'draw_date' => now(),
                    ]);
                    SendAssignment::dispatch($draw->id)->afterCommit();
                }

                $event->update(['status' => 'DRAWS_COMPLETE']);
                NotificationService::assignmentsAvailable($event);
            });

            $this->event = $event->refresh();

            $this->dispatch('notification', ['message' => __('messages.amigo_secreto.draw_complete'), 'type' => 'success']);
        } catch (RuntimeException $e) {
            $this->dispatch('notification', ['message' => __('messages.amigo_secreto.draw_impossible'), 'type' => 'error']);
        }
    }

    public function render(): View
    {
        $this->event = $this->authorizedEvent('view');
        $isOrganizer = $this->event->organizer_id === Auth::id();
        $myDraw = app(AssignmentAccess::class)->summaryFor($this->event, Auth::user());

        return view('livewire.amigo-secreto.execute-draw', [
            'isOrganizer' => $isOrganizer,
            'myDraw' => $myDraw,
        ]);
    }

    private function authorizedEvent(string $ability): AmigoSecretoEvent
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->event->getKey());
        Gate::authorize($ability, $event);

        return $event;
    }
}
