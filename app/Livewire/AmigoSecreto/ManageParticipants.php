<?php

namespace App\Livewire\AmigoSecreto;

use App\Mail\AmigoSecretoInviteMail;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ManageParticipants extends Component {
    public AmigoSecretoEvent $event;

    #[Rule(['required', 'email'])]
    public string $inviteEmail = '';

    public function mount(AmigoSecretoEvent $event): void {
        $this->event = $event;
    }

    public function inviteByEmail(): void {
        $this->validate();

        $existing = $this->event->participants()
            ->where('invite_email', $this->inviteEmail)
            ->first();

        if ($existing) {
            $this->addError('inviteEmail', 'This email has already been invited.');
            return;
        }

        AmigoSecretoParticipant::create([
            'event_id' => $this->event->id,
            'invite_email' => $this->inviteEmail,
            'status' => 'INVITED',
            'invited_at' => now(),
        ]);

        $joinUrl = route('amigo-secreto.public-join', ['code' => $this->event->event_code]);

        Mail::to($this->inviteEmail)->send(
            new AmigoSecretoInviteMail($this->event->name, $joinUrl)
        );

        $this->dispatch('notification', ["message" => __("messages.amigo_secreto.invite_sent"), "type" => "success"]);
        $this->inviteEmail = '';
        $this->event->refresh();
    }

    public function removeParticipant(int $participantId): void {
        if ($this->event->organizer_id !== Auth::id()) {
            return;
        }

        $participant = $this->event->participants()->find($participantId);

        if ($participant && $participant->user_id !== $this->event->organizer_id) {
            $participant->delete();
            $this->event->refresh();
        }
    }

    public function render() {
        return view('livewire.amigo-secreto.manage-participants', [
            'participants' => $this->event->participants()->with('user')->get(),
        ]);
    }
}
