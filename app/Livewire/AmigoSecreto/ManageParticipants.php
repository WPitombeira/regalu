<?php

namespace App\Livewire\AmigoSecreto;

use App\Jobs\SendEventInvitation;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ManageParticipants extends Component
{
    public AmigoSecretoEvent $event;

    #[Rule(['required', 'email'])]
    public string $inviteEmail = '';

    public function mount(AmigoSecretoEvent $event): void
    {
        $this->event = $event;
        $this->event = $this->authorizedEvent('viewManagement');
    }

    public function inviteByEmail(): void
    {
        $event = $this->authorizedEvent('update');
        $this->validate();

        $existing = $event->participants()
            ->where('invite_email', $this->inviteEmail)
            ->first();

        if ($existing) {
            $this->addError('inviteEmail', 'This email has already been invited.');

            return;
        }

        DB::transaction(function () use ($event): void {
            $participant = AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'invite_email' => $this->inviteEmail,
                'status' => 'INVITED',
                'invited_at' => now(),
            ]);

            NotificationService::eventInvited($participant);
            SendEventInvitation::dispatch($participant->id)->afterCommit();
        });

        $this->dispatch('notification', ['message' => __('messages.amigo_secreto.invite_queued'), 'type' => 'success']);
        $this->inviteEmail = '';
        $this->event = $event->refresh();
    }

    public function removeParticipant(int $participantId): void
    {
        $event = $this->authorizedEvent('update');

        $participant = $event->participants()->find($participantId);

        if ($participant && $participant->getAttribute('user_id') !== $event->organizer_id) {
            $participant->delete();
            $this->event = $event->refresh();
        }
    }

    public function render(): View
    {
        $event = $this->authorizedEvent('viewManagement');

        return view('livewire.amigo-secreto.manage-participants', [
            'event' => $event,
            'participants' => $event->participants()->with('user')->get(),
        ]);
    }

    private function authorizedEvent(string $ability): AmigoSecretoEvent
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->event->getKey());
        Gate::authorize($ability, $event);

        return $event;
    }
}
