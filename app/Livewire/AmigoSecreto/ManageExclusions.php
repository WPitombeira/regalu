<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use App\Services\EventRoster;
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
        $created = EventRoster::locked($event->id, function (AmigoSecretoEvent $event): bool {
            Gate::authorize('update', $event);
            $acceptedIds = $event->participants()->where('status', 'ACCEPTED')->whereNotNull('user_id')
                ->lockForUpdate()->pluck('user_id')->all();
            $this->validate([
                'userAId' => ['required', 'integer', 'different:userBId', ValidationRule::in($acceptedIds)],
                'userBId' => ['required', 'integer', 'different:userAId', ValidationRule::in($acceptedIds)],
            ]);

            $existing = $event->exclusions()->where(function ($query) {
                $query->where(fn ($pair) => $pair->where('user_a_id', $this->userAId)->where('user_b_id', $this->userBId))
                    ->orWhere(fn ($pair) => $pair->where('user_a_id', $this->userBId)->where('user_b_id', $this->userAId));
            })->lockForUpdate()->first();

            if ($existing) {
                $this->addError('userAId', 'This exclusion pair already exists.');

                return false;
            }

            AmigoSecretoExclusion::create([
                'event_id' => $event->id,
                'user_a_id' => $this->userAId,
                'user_b_id' => $this->userBId,
            ]);

            return true;
        });
        if (! $created) {
            return;
        }

        $this->dispatch('notification', ['message' => 'Exclusion added.', 'type' => 'success']);
        $this->userAId = null;
        $this->userBId = null;
        $this->event = $event->refresh();
    }

    public function removeExclusion(int $exclusionId): void
    {
        $event = $this->authorizedEvent('update');

        EventRoster::locked($event->id, function (AmigoSecretoEvent $event) use ($exclusionId): void {
            Gate::authorize('update', $event);
            $event->exclusions()->lockForUpdate()->findOrFail($exclusionId)->delete();
        });
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
