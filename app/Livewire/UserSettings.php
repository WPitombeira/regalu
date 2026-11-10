<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class UserSettings extends Component
{
    public function render(): View
    {
        return view('livewire.user-settings');
    }
}
