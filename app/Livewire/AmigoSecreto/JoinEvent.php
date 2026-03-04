<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;

class JoinEvent extends Component {
    #[Rule(['required', 'string', 'size:12'])]
    public string $eventCode = '';

    public function mount(?string $code = null): void {
        if ($code) {
            $this->eventCode = $code;
        }
    }

    public function join() {
        $this->validate();

        $event = AmigoSecretoEvent::where('event_code', strtoupper($this->eventCode))->first();

        if (!$event) {
            $this->addError('eventCode', 'Event not found. Please check the code and try again.');
            return;
        }

        $alreadyParticipant = $event->participants()
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyParticipant) {
            return redirect()->route('amigo-secreto.show', $event);
        }

        // Check if user was invited by email and link the account
        $pendingInvite = $event->participants()
            ->where('invite_email', Auth::user()->email)
            ->whereNull('user_id')
            ->first();

        if ($pendingInvite) {
            $pendingInvite->update([
                'user_id' => Auth::id(),
                'status' => 'ACCEPTED',
                'accepted_at' => now(),
            ]);
        } else {
            AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'user_id' => Auth::id(),
                'status' => 'ACCEPTED',
                'accepted_at' => now(),
            ]);
        }

        $this->dispatch('notification', ["message" => "You have joined the event!", "type" => "success"]);

        return redirect()->route('amigo-secreto.show', $event);
    }

    public function render() {
        return view('livewire.amigo-secreto.join-event');
    }
}
