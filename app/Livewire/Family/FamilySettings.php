<?php

namespace App\Livewire\Family;

use App\Models\Family;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;
use Livewire\Component;

class FamilySettings extends Component {
    public Family $family;

    #[Rule(['required', 'min:2'])]
    public string $name = '';

    #[Rule(['nullable'])]
    public ?string $description = null;

    public function mount(Family $family): void {
        $this->family = $family;
        $this->name = $family->name;
        $this->description = $family->description;
    }

    public function update(): void {
        $this->validate();

        $this->family->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->dispatch('notification', ["message" => __("messages.family.created"), "type" => "success"]);
    }

    public function archive(): void {
        $this->family->update(['is_archived' => true]);

        $this->dispatch('notification', ["message" => __("messages.family.archived"), "type" => "success"]);
    }

    public function regenerateCode(): void {
        $this->family->update([
            'invite_code' => Str::upper(Str::random(12)),
        ]);

        $this->dispatch('notification', ["message" => __("messages.family.code_regenerated"), "type" => "success"]);
    }

    public function render() {
        return view('livewire.family.family-settings');
    }
}
