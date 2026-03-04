<?php

namespace App\Livewire\Family;

use App\Models\Family;
use App\Models\FamilyMember;
use Livewire\Attributes\Rule;
use Livewire\Component;

class CreateFamily extends Component {
    #[Rule(['required', 'min:2'])]
    public string $name = '';

    #[Rule(['nullable'])]
    public ?string $description = null;

    public function create() {
        $this->validate();

        $family = Family::create([
            'name' => $this->name,
            'description' => $this->description,
            'creator_id' => auth()->id(),
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => auth()->id(),
            'role' => 'ADMIN',
            'joined_at' => now(),
        ]);

        $this->dispatch('notification', ["message" => __("messages.family.created"), "type" => "success"]);

        return redirect()->route('families.show', $family);
    }

    public function render() {
        return view('livewire.family.create-family');
    }
}
