<?php

namespace App\Livewire\Family;

use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FamilyRoster extends Component
{
    public Family $family;

    public function mount(Family $family): void
    {
        $this->family = $family;
        $this->family = $this->authorizedFamily();
    }

    /** @return Collection<int, FamilyMember> */
    public function getMembers(): Collection
    {
        $family = $this->authorizedFamily();

        return FamilyMember::query()
            ->where('family_id', $family->getKey())
            ->with('user')
            ->get();
    }

    public function removeMember(int $memberId): void
    {
        $family = $this->authorizedFamily('update');
        $member = $family->members()->findOrFail($memberId);

        abort_if($member->getAttribute('user_id') === $family->getAttribute('creator_id'), 403);

        if ($member->getAttribute('user_id') === Auth::id()) {
            return;
        }

        $member->delete();

        $this->dispatch('notification', ['message' => __('messages.family.removed'), 'type' => 'success']);
    }

    public function promoteMember(int $memberId): void
    {
        $family = $this->authorizedFamily('update');
        $member = $family->members()->findOrFail($memberId);

        $member->update(['role' => 'ADMIN']);

        $this->dispatch('notification', ['message' => __('messages.family.role_admin'), 'type' => 'success']);
    }

    public function render(): View
    {
        $this->family = $this->authorizedFamily();

        return view('livewire.family.family-roster', [
            'members' => $this->getMembers(),
        ]);
    }

    private function authorizedFamily(string $ability = 'view'): Family
    {
        $family = Family::query()->findOrFail($this->family->getKey());
        Gate::authorize($ability, $family);

        return $family;
    }
}
