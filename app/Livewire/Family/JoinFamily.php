<?php

namespace App\Livewire\Family;

use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class JoinFamily extends Component
{
    #[Rule(['required', 'size:12'])]
    public string $inviteCode = '';

    public function join(): Redirector|RedirectResponse|null
    {
        abort_unless(auth()->check(), 403);
        $this->validate();

        $family = Family::where('invite_code', $this->inviteCode)->first();

        if (! $family) {
            $this->addError('inviteCode', __('messages.family.invalid_code'));

            return null;
        }

        Gate::authorize('join', $family);

        $alreadyMember = FamilyMember::where('family_id', $family->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyMember) {
            $this->addError('inviteCode', __('messages.family.already_member'));

            return null;
        }

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => auth()->id(),
            'role' => 'MEMBER',
            'joined_at' => now(),
        ]);

        $this->dispatch('notification', ['message' => __('messages.family.joined'), 'type' => 'success']);

        return redirect()->route('families.show', $family);
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        return view('livewire.family.join-family');
    }
}
