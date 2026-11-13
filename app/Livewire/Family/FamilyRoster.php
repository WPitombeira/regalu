<?php

namespace App\Livewire\Family;

use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class FamilyRoster extends Component
{
    use WithPagination;

    public Family $family;

    public string $inviteEmail = '';

    public function mount(Family $family): void
    {
        $this->family = $family;
        $this->family = $this->authorizedFamily();
    }

    public function inviteByEmail(): void
    {
        $family = $this->authorizedFamily('update');
        $this->inviteEmail = strtolower(trim($this->inviteEmail));
        $this->validate(['inviteEmail' => ['required', 'email', 'max:255']]);

        $sender = Auth::user();
        abort_unless($sender instanceof User, 403);
        app(FamilyInvitationService::class)->invite($family, $sender, $this->inviteEmail);

        $this->inviteEmail = '';
        $this->resetPage('invitationsPage');
        $this->dispatch('notification', ['message' => __('family_invitations.sent'), 'type' => 'success']);
    }

    public function cancelInvitation(int $invitationId): void
    {
        $family = $this->authorizedFamily('update');
        $sender = Auth::user();
        abort_unless($sender instanceof User, 403);
        if (app(FamilyInvitationService::class)->cancel($family, $sender, $invitationId)) {
            $this->dispatch('notification', ['message' => __('family_invitations.status.cancelled'), 'type' => 'success']);
        }
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

        $changed = FamilyMember::query()->whereKey($member->id)
            ->where('family_id', $family->id)->where('user_id', $member->user_id)
            ->whereExists(Family::query()->selectRaw('1')->whereKey($family->id)->editableBy((int) Auth::id())
                ->whereColumn('families.creator_id', '!=', 'family_members.user_id')->toBase())
            ->delete();
        abort_unless($changed === 1, 409);

        $this->dispatch('notification', ['message' => __('messages.family.removed'), 'type' => 'success']);
    }

    public function promoteMember(int $memberId): void
    {
        $family = $this->authorizedFamily('update');
        $member = $family->members()->findOrFail($memberId);

        $eligible = FamilyMember::query()->whereKey($member->id)
            ->where('family_id', $family->id)->where('user_id', $member->user_id)
            ->whereExists(Family::query()->selectRaw('1')->whereKey($family->id)->editableBy((int) Auth::id())->toBase());
        $changed = $eligible->update(['role' => 'ADMIN']);

        // Confirm an already-admin membership using the same identity and grant.
        abort_unless($changed === 1 || ($changed === 0 && $eligible->useWritePdo()->where('role', 'ADMIN')->exists()), 409);

        $this->dispatch('notification', ['message' => __('messages.family.role_admin'), 'type' => 'success']);
    }

    public function render(): View
    {
        $this->family = $this->authorizedFamily();

        return view('livewire.family.family-roster', [
            'members' => $this->getMembers(),
            'invitations' => Gate::allows('manage', $this->family)
                ? FamilyInvitation::where('family_id', $this->family->id)->latest('updated_at')->latest('id')
                    ->paginate(10, ['id', 'email', 'status', 'expires_at'], 'invitationsPage')
                : null,
        ]);
    }

    private function authorizedFamily(string $ability = 'view'): Family
    {
        $family = Family::query()->findOrFail($this->family->getKey());
        Gate::authorize($ability, $family);

        return $family;
    }
}
