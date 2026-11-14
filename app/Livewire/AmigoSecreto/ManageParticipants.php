<?php

namespace App\Livewire\AmigoSecreto;

use App\Jobs\SendEventInvitation;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Services\EventRoster;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
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

        $created = EventRoster::locked($event->id, function (AmigoSecretoEvent $event): bool {
            Gate::authorize('update', $event);
            $existing = $event->participants()
                ->where(function ($query) {
                    $query->whereRaw('LOWER(TRIM(invite_email)) = LOWER(TRIM(?))', [$this->inviteEmail])
                        ->orWhereHas('user', fn ($user) => $user
                            ->whereRaw('LOWER(TRIM(email)) = LOWER(TRIM(?))', [$this->inviteEmail]));
                })
                ->lockForUpdate()->first();

            if ($existing) {
                $this->addError('inviteEmail', 'This email has already been invited.');

                return false;
            }

            $participant = AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'invite_email' => $this->inviteEmail,
                'status' => 'INVITED',
                'invited_at' => now(),
            ]);

            NotificationService::eventInvited($participant);
            SendEventInvitation::dispatch($participant->id)->afterCommit();

            return true;
        });

        if (! $created) {
            return;
        }

        $this->dispatch('notification', ['message' => __('messages.amigo_secreto.invite_queued'), 'type' => 'success']);
        $this->inviteEmail = '';
        $this->event = $event->refresh();
    }

    public function removeParticipant(int $participantId): void
    {
        $event = $this->authorizedEvent('update');

        EventRoster::locked($event->id, function (AmigoSecretoEvent $event) use ($participantId): void {
            Gate::authorize('update', $event);
            $participant = $event->participants()->lockForUpdate()->find($participantId);

            if ($participant && $participant->getAttribute('user_id') !== $event->organizer_id) {
                $participant->delete();
            }
        });
        $this->event = $event->refresh();
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
