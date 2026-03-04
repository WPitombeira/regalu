<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ManageExclusions extends Component {
    public AmigoSecretoEvent $event;

    #[Rule(['required', 'integer', 'exists:users,id'])]
    public ?int $userAId = null;

    #[Rule(['required', 'integer', 'exists:users,id', 'different:userAId'])]
    public ?int $userBId = null;

    public function mount(AmigoSecretoEvent $event): void {
        $this->event = $event;
    }

    public function addExclusion(): void {
        $this->validate();

        $exists = $this->event->exclusions()
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
            'event_id' => $this->event->id,
            'user_a_id' => $this->userAId,
            'user_b_id' => $this->userBId,
        ]);

        $this->dispatch('notification', ["message" => "Exclusion added.", "type" => "success"]);
        $this->userAId = null;
        $this->userBId = null;
        $this->event->refresh();
    }

    public function removeExclusion(int $exclusionId): void {
        if ($this->event->organizer_id !== Auth::id()) {
            return;
        }

        $exclusion = $this->event->exclusions()->find($exclusionId);

        if ($exclusion) {
            $exclusion->delete();
            $this->event->refresh();
        }
    }

    public function render() {
        $acceptedParticipants = $this->event->participants()
            ->where('status', 'ACCEPTED')
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        return view('livewire.amigo-secreto.manage-exclusions', [
            'exclusions' => $this->event->exclusions()->with(['userA', 'userB'])->get(),
            'acceptedParticipants' => $acceptedParticipants,
        ]);
    }
}
