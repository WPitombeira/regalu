<?php

namespace App\Livewire\Family;

use App\Models\Family;
use App\Models\FamilyMember;
use Livewire\Attributes\Rule;
use Livewire\Component;

class JoinFamily extends Component {
    #[Rule(['required', 'size:12'])]
    public string $inviteCode = '';

    public function join() {
        $this->validate();

        $family = Family::where('invite_code', $this->inviteCode)->first();

        if (! $family) {
            $this->addError('inviteCode', __("messages.family.invalid_code"));
            return;
        }

        $alreadyMember = FamilyMember::where('family_id', $family->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyMember) {
            $this->addError('inviteCode', __("messages.family.already_member"));
            return;
        }

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => auth()->id(),
            'role' => 'MEMBER',
            'joined_at' => now(),
        ]);

        $this->dispatch('notification', ["message" => __("messages.family.joined"), "type" => "success"]);

        return redirect()->route('families.show', $family);
    }

    public function render() {
        return view('livewire.family.join-family');
    }
}
