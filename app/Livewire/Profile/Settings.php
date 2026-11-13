<?php

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Settings extends Component
{
    use WithFileUploads;

    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:20'])]
    public string $phone = '';

    #[Rule(['nullable', 'image', 'max:2048'])]
    public ?TemporaryUploadedFile $avatar = null;

    public string $avatarPreview = '';

    #[Rule(['required', 'string'])]
    public string $currentPassword = '';

    #[Rule(['required', 'string', 'min:8'])]
    public string $newPassword = '';

    #[Rule(['required', 'string', 'min:8'])]
    public string $newPasswordConfirm = '';

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('profile_settings.name'),
            'phone' => __('profile_settings.phone'),
            'avatar' => __('profile_settings.avatar'),
            'currentPassword' => __('profile_settings.current_password'),
            'newPassword' => __('profile_settings.new_password'),
            'newPasswordConfirm' => __('profile_settings.confirm_password'),
        ];
    }

    public function mount(): void
    {
        $user = $this->authenticatedUser();
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
        $this->avatarPreview = $user->avatar_url ?? '';
    }

    public function updateProfile(): void
    {
        $this->validateOnly('name');
        $this->validateOnly('phone');

        $user = $this->authenticatedUser();
        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
        ];

        if ($this->avatar) {
            $this->validateOnly('avatar');
            $path = $this->avatar->store('avatars', 'public');
            $data['avatar_url'] = '/storage/'.$path;
            $this->avatarPreview = $data['avatar_url'];
            $this->avatar = null;
        }

        $user->update($data);

        $this->dispatch('notification', ['message' => __('profile_settings.profile_updated'), 'type' => 'success']);
    }

    public function updatePassword(): void
    {
        $this->validateOnly('currentPassword');
        $this->validateOnly('newPassword');
        $this->validateOnly('newPasswordConfirm');

        if ($this->newPassword !== $this->newPasswordConfirm) {
            $this->addError('newPasswordConfirm', __('profile_settings.password_mismatch'));

            return;
        }

        $user = $this->authenticatedUser();

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', __('profile_settings.wrong_password'));

            return;
        }

        $user->forceFill([
            'password' => $this->newPassword,
            'remember_token' => Str::random(60),
        ])->save();

        // Livewire's persistent middleware finishes before component actions run.
        // Advance only this session; other sessions retain the revoked generation.
        session()->put('password_hash_'.Auth::getDefaultDriver(), Auth::hashPasswordForCookie($user->password));

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirm = '';

        $this->dispatch('notification', ['message' => __('profile_settings.password_updated'), 'type' => 'success']);
    }

    public function render(): View
    {
        $this->authenticatedUser();

        return view('livewire.profile.settings');
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
