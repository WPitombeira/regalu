<?php

namespace App\Livewire\Home;

use App\Http\Controllers\UserController;
use App\View\Components\Notifications;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
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

    #[Rule(['required', 'email'])]
    public string $email = '';

    #[Rule(['required'])]
    public string $password = '';

    public bool $remember = false;

    public function testToast() {
        $this->dispatch('notification', 'This is a test toast', 'success');
    }

    public function login() {
        $this->validate();

        if ((new UserController())->loginWithoutRedirect($this->email, $this->password, $this->remember)) {
            $this->dispatch('notification', ["message" => __("messages.authentication.success"), "type" => 'success']);
            redirect()->intended('dashboard');
        }

        $this->addError('loginError', __("messages.authentication.wrongcredentials"));
    }
}
