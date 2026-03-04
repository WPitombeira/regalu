<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Settings extends Component {
    use WithFileUploads;

    #[Rule(['required', 'string', 'min:2', 'max:255'])]
    public string $name = '';

    #[Rule(['nullable', 'string', 'max:20'])]
    public string $phone = '';

    #[Rule(['nullable', 'image', 'max:2048'])]
    public TemporaryUploadedFile|null $avatar = null;

    public string $avatarPreview = '';

    #[Rule(['required', 'string'])]
    public string $currentPassword = '';

    #[Rule(['required', 'string', 'min:8'])]
    public string $newPassword = '';

    #[Rule(['required', 'string', 'min:8'])]
    public string $newPasswordConfirm = '';

    public function mount(): void {
        $user = Auth::user();
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
        $this->avatarPreview = $user->avatar_url ?? '';
    }

    public function updateProfile(): void {
        $this->validateOnly('name');
        $this->validateOnly('phone');

        $user = Auth::user();
        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
        ];

        if ($this->avatar) {
            $this->validateOnly('avatar');
            $path = $this->avatar->store('avatars', 'public');
            $data['avatar_url'] = '/storage/' . $path;
            $this->avatarPreview = $data['avatar_url'];
            $this->avatar = null;
        }

        $user->update($data);

        $this->dispatch('notification', ["message" => __("messages.profile.updated"), "type" => 'success']);
    }

    public function updatePassword(): void {
        $this->validateOnly('currentPassword');
        $this->validateOnly('newPassword');
        $this->validateOnly('newPasswordConfirm');

        if ($this->newPassword !== $this->newPasswordConfirm) {
            $this->addError('newPasswordConfirm', __("messages.auth.password_mismatch"));
            return;
        }

        $user = Auth::user();

        if (!Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', __("messages.profile.wrong_password"));
            return;
        }

        $user->update([
            'password' => $this->newPassword,
        ]);

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirm = '';

        $this->dispatch('notification', ["message" => __("messages.profile.password_updated"), "type" => 'success']);
    }

    public function render() {
        return view('livewire.profile.settings');
    }
}
