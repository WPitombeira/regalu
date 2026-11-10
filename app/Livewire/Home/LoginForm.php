<?php

namespace App\Livewire\Home;

use App\Models\User;
use App\Services\LoginAttemptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;

class LoginForm extends Component
{
    public const VALID_PROVIDERS = ['google', 'apple'];

    public bool $showLogin = true;

    public bool $showRegister = false;

    // Toggles between the login and register forms
    public function toggleForm(): void
    {
        $this->showLogin = ! $this->showLogin;
        $this->showRegister = ! $this->showRegister;
    }

    public function render(): View
    {
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

    public function login(): ?RedirectResponse
    {
        $this->validateOnly('email');
        $this->validateOnly('password');

        if (app(LoginAttemptService::class)->attempt($this->email, $this->password, $this->remember, request()->ip())) {
            session()->regenerate();
            $this->dispatch('notification', ['message' => __('messages.action.login_success'), 'type' => 'success']);

            return redirect()->intended(route('dashboard'));
        }

        $this->addError('loginError', __('messages.form_messages.wrongcredentials'));

        return null;
    }

    public function register(): ?RedirectResponse
    {
        $this->validateOnly('registerName');
        $this->validateOnly('registerEmail');
        $this->validateOnly('registerPassword');
        $this->validateOnly('registerPasswordConfirm');

        if ($this->registerPassword !== $this->registerPasswordConfirm) {
            $this->addError('registerPasswordConfirm', __('messages.auth.password_mismatch'));

            return null;
        }

        if (User::where('email', $this->registerEmail)->exists()) {
            $this->addError('registerEmail', __('messages.auth.email_taken'));

            return null;
        }

        $user = User::create([
            'name' => $this->registerName,
            'email' => $this->registerEmail,
            'password' => $this->registerPassword,
        ]);

        Auth::login($user);

        $this->dispatch('notification', ['message' => __('messages.auth.register_success'), 'type' => 'success']);

        return redirect()->intended(route('dashboard'));
    }

    // Checks if the given provider is valid
    public function isValidProvider(?string $provider = null): bool
    {
        return is_string($provider) && in_array(strtolower($provider), self::VALID_PROVIDERS, true);
    }
}
