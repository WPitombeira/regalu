<?php

namespace App\Livewire\Home;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Login extends Component
{
    public bool $showLogin = true;

    public function render(): View
    {
        return view('livewire.home.login', ['showLogin' => $this->showLogin]);
    }
}
