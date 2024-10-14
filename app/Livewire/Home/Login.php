<?php

namespace App\Livewire\Home;

use Livewire\Component;

class Login extends Component {
    public bool $showLogin = true;

    public function render() {
        return view('livewire.home.login', ['showLogin' => $this->showLogin]);
    }
}
