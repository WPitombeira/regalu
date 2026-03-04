<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;

class CreateEvent extends Component {
    #[Rule(['required', 'string', 'min:3', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    #[Rule(['required', 'in:CHRISTMAS,BIRTHDAY,WEDDING,CASUAL'])]
    public string $event_type = 'CASUAL';

    #[Rule(['nullable', 'numeric', 'min:0'])]
    public ?string $budget_min = null;

    #[Rule(['nullable', 'numeric', 'min:0'])]
    public ?string $budget_max = null;

    #[Rule(['nullable', 'date', 'after_or_equal:today'])]
    public ?string $event_date = null;

    #[Rule(['nullable', 'date', 'after_or_equal:today'])]
    public ?string $reveal_date = null;

    #[Rule(['nullable', 'exists:families,id'])]
    public ?int $family_id = null;

    public function create() {
        $this->validate();

        $event = AmigoSecretoEvent::create([
            'organizer_id' => Auth::id(),
            'family_id' => $this->family_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'event_type' => $this->event_type,
            'budget_min' => $this->budget_min ?: null,
            'budget_max' => $this->budget_max ?: null,
            'event_date' => $this->event_date ?: null,
            'reveal_date' => $this->reveal_date ?: null,
            'status' => 'PLANNING',
        ]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        $this->dispatch('notification', ["message" => __("messages.amigo_secreto.created"), "type" => "success"]);

        return redirect()->route('amigo-secreto.show', $event);
    }

    public function render() {
        $families = Auth::user()->families;

        return view('livewire.amigo-secreto.create-event', [
            'families' => $families,
        ]);
    }
}
