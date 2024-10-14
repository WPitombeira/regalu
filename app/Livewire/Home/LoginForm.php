<?php

namespace App\Livewire\Home;

use Livewire\Component;

class LoginForm extends Component {
    public bool $showLogin = true;
    public bool $showRegister = false;

    public function toggleForm() {
        $this->showLogin = !$this->showLogin;
        $this->showRegister = !$this->showRegister;
    }

    public function render() {
        return view('livewire.home.login-form');
    }
}
