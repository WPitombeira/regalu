<?php

namespace App\Livewire\Family;

use App\Models\Family;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;
use Livewire\Component;

class FamilySettings extends Component
{
    public Family $family;

    #[Rule(['required', 'min:2'])]
    public string $name = '';

    #[Rule(['nullable'])]
    public ?string $description = null;

    public function mount(Family $family): void
    {
        $this->family = $family;
        $family = $this->authorizedFamily();
        $this->name = $family->name;
        $this->description = $family->description;
    }

    public function update(): void
    {
        $family = $this->authorizedFamily('update');
        $this->validate();

        $editable = Family::query()->whereKey($family->id)->editableBy((int) Auth::id());
        $values = [
            'name' => $this->name,
            'description' => $this->description,
        ];
        $changed = $editable->update($values);

        // Some drivers report zero for an unchanged row; never retry the write.
        abort_unless($changed === 1 || ($changed === 0 && $editable->useWritePdo()->where($values)->exists()), 409);

        $this->dispatch('notification', ['message' => __('messages.family.created'), 'type' => 'success']);
    }

    public function archive(): void
    {
        $family = $this->authorizedFamily('archive');
        $changed = Family::query()->whereKey($family->id)->editableBy((int) Auth::id())
            ->update(['is_archived' => true]);
        abort_unless($changed === 1, 409);

        $this->dispatch('notification', ['message' => __('messages.family.archived'), 'type' => 'success']);
    }

    public function regenerateCode(): void
    {
        $family = $this->authorizedFamily('update');
        $changed = Family::query()->whereKey($family->id)->editableBy((int) Auth::id())->update([
            'invite_code' => Str::upper(Str::random(12)),
        ]);
        abort_unless($changed === 1, 409);

        $this->dispatch('notification', ['message' => __('messages.family.code_regenerated'), 'type' => 'success']);
    }

    public function render(): View
    {
        $this->family = $this->authorizedFamily();

        return view('livewire.family.family-settings');
    }

    private function authorizedFamily(string $ability = 'manage'): Family
    {
        $family = Family::query()->findOrFail($this->family->getKey());
        Gate::authorize($ability, $family);

        return $family;
    }
}
