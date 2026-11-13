<?php

namespace App\Livewire;

use App\Http\Controllers\NewsletterController;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Home extends Component
{
    public function render(): View
    {
        return view('livewire.home.index');
    }

    #[Rule(['required', 'email'])]
    public string $email = '';

    public function registerNewsletter(): void
    {
        if (NewsletterController::register($this->email)) {
            $this->dispatch('notification', ['message' => __('messages.action.newsletter_success'), 'type' => 'success']);
        } else {
            $this->dispatch('notification', ['message' => __('messages.action.generic_error'), 'type' => 'error']);
        }
    }

    public string $feedback_email = '';

    public string $feedback_name = '';

    #[Rule(['required'])]
    public string $feedback_message = '';

    public function registerFeedback(): void
    {
        $this->addError('feedback', __('contact.unavailable'));
    }
}
