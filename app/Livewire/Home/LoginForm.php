<?php

namespace App\Livewire\Home;

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Rule;
use Livewire\Features\SupportRedirects\Redirector;

class LoginForm extends Component {
    protected UserController $userController;

    public function __construct() {
        $this->userController = new UserController();
    }

    public const VALID_PROVIDERS = ['google', 'apple'];
    public bool $showLogin = true;
    public bool $showRegister = false;

    // Toggles between the login and register forms
    public function toggleForm(): void {
        $this->showLogin = !$this->showLogin;
        $this->showRegister = !$this->showRegister;
    }

    public function render() {
        return view('livewire.home.login-form');
    }

    // --- Login properties ---

    #[Rule(['required', 'email'])]
    public string $email = '';

    #[Rule(['required'])]
    public string $password = '';

    public bool $remember = false;

    // --- Registration properties ---

    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $registerName = '';

    #[Rule(['required', 'email', 'max:255'])]
    public string $registerEmail = '';

    #[Rule(['required', 'string', 'min:8'])]
    public string $registerPassword = '';

    #[Rule(['required', 'string', 'min:8'])]
    public string $registerPasswordConfirm = '';

    public function login() {
        $this->validate();

        if ($this->userController->loginWithoutRedirect($this->email, $this->password, $this->remember)) {
            $this->dispatch('notification', ["message" => __("messages.action.login_success"), "type" => 'success']);
            return redirect()->intended('home');
        }

        $this->addError('loginError', __("messages.form_messages.wrongcredentials"));
    }

    public function register() {
        $this->validateOnly('registerName');
        $this->validateOnly('registerEmail');
        $this->validateOnly('registerPassword');
        $this->validateOnly('registerPasswordConfirm');

        if ($this->registerPassword !== $this->registerPasswordConfirm) {
            $this->addError('registerPasswordConfirm', __("messages.auth.password_mismatch"));
            return;
        }

        if (User::where('email', $this->registerEmail)->exists()) {
            $this->addError('registerEmail', __("messages.auth.email_taken"));
            return;
        }

        $user = User::create([
            'name' => $this->registerName,
            'email' => $this->registerEmail,
            'password' => $this->registerPassword,
        ]);

        Auth::login($user);

        $this->dispatch('notification', ["message" => __("messages.auth.register_success"), "type" => 'success']);

        return redirect()->route('dashboard');
    }

    // Checks if the given provider is valid
    public function isValidProvider(?string $provider = null): bool {
        if (in_array(strtolower($provider), self::VALID_PROVIDERS))
            return true;

        return false;
    }
}
