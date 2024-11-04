<?php

namespace App\Livewire\Home;

use App\Http\Controllers\UserController;
use Livewire\Component;
use Livewire\Attributes\Rule;

class LoginForm extends Component {
    protected UserController $userController;

    public function __construct(UserController $userController) {
        $this->userController = $userController;
    }

    public const VALID_PROVIDERS = ['google', 'apple'];
    public bool $showLogin = true;
    public bool $showRegister = false;

    // Toggles between the login and register forms
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

    public function login() {
        $this->validate();

        if ($this->userController->loginWithoutRedirect($this->email, $this->password, $this->remember)) {
            $this->dispatch('notification', ["message" => __("messages.authentication.success"), "type" => 'success']);
            return redirect()->intended('home');
        }

        $this->addError('loginError', __("messages.authentication.wrongcredentials"));
    }

    // Checks if the given provider is valid
    public function isValidProvider($provider = null): bool {
        if (in_array(strtolower($provider), self::VALID_PROVIDERS))
            return true;

        return false;
    }
}
