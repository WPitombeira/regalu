<?php

namespace App\Livewire\Family;

use App\Models\Family;
use App\Models\FamilyMember;
use Livewire\Component;

class FamilyRoster extends Component {
    public Family $family;

    public function getMembers() {
        return $this->family->members()->with('user')->get();
    }

    public function removeMember(int $memberId): void {
        $member = FamilyMember::findOrFail($memberId);

        $isAdmin = FamilyMember::where('family_id', $this->family->id)
            ->where('user_id', auth()->id())
            ->where('role', 'ADMIN')
            ->exists();

        if (! $isAdmin) {
            return;
        }

        if ($member->user_id === auth()->id()) {
            return;
        }

        $member->delete();

        $this->dispatch('notification', ["message" => __("messages.family.removed"), "type" => "success"]);
    }

    public function promoteMember(int $memberId): void {
        $member = FamilyMember::findOrFail($memberId);

        $isAdmin = FamilyMember::where('family_id', $this->family->id)
            ->where('user_id', auth()->id())
            ->where('role', 'ADMIN')
            ->exists();

        if (! $isAdmin) {
            return;
        }

        $member->update(['role' => 'ADMIN']);

        $this->dispatch('notification', ["message" => __("messages.family.role_admin"), "type" => "success"]);
    }

    public function render() {
        return view('livewire.family.family-roster', [
            'members' => $this->getMembers(),
        ]);
    }
}
