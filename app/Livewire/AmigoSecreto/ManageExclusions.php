<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ManageExclusions extends Component
{
    public AmigoSecretoEvent $event;

    #[Rule(['required', 'integer', 'exists:users,id'])]
    public ?int $userAId = null;

    #[Rule(['required', 'integer', 'exists:users,id', 'different:userAId'])]
    public ?int $userBId = null;

    public function mount(AmigoSecretoEvent $event): void
    {
        $this->event = $event;
        $this->event = $this->authorizedEvent('viewManagement');
    }

    public function addExclusion(): void
    {
        $event = $this->authorizedEvent('update');
        $acceptedParticipant = ValidationRule::exists('amigo_secreto_participants', 'user_id')
            ->where('event_id', $event->id)
            ->where('status', 'ACCEPTED');
        $this->validate([
            'userAId' => ['required', 'integer', 'different:userBId', $acceptedParticipant],
            'userBId' => ['required', 'integer', 'different:userAId', $acceptedParticipant],
        ]);

        $exists = $event->exclusions()
            ->where(function ($query) {
                $query->where('user_a_id', $this->userAId)
                    ->where('user_b_id', $this->userBId);
            })
            ->orWhere(function ($query) {
                $query->where('event_id', $this->event->id)
                    ->where('user_a_id', $this->userBId)
                    ->where('user_b_id', $this->userAId);
            })
            ->exists();

        if ($exists) {
            $this->addError('userAId', 'This exclusion pair already exists.');

            return;
        }

        AmigoSecretoExclusion::create([
            'event_id' => $event->id,
            'user_a_id' => $this->userAId,
            'user_b_id' => $this->userBId,
        ]);

        $this->dispatch('notification', ['message' => 'Exclusion added.', 'type' => 'success']);
        $this->userAId = null;
        $this->userBId = null;
        $this->event = $event->refresh();
    }

    public function removeExclusion(int $exclusionId): void
    {
        $event = $this->authorizedEvent('update');

        $exclusion = $event->exclusions()->findOrFail($exclusionId);
        $exclusion->delete();
        $this->event = $event->refresh();
    }

    public function render(): View
    {
        $event = $this->authorizedEvent('viewManagement');
        $acceptedParticipants = $event->participants()
            ->where('status', 'ACCEPTED')
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        return view('livewire.amigo-secreto.manage-exclusions', [
            'event' => $event,
            'exclusions' => $event->exclusions()->with(['userA', 'userB'])->get(),
            'acceptedParticipants' => $acceptedParticipants,
        ]);
    }

    private function authorizedEvent(string $ability): AmigoSecretoEvent
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->event->getKey());
        Gate::authorize($ability, $event);

        return $event;
    }
}
