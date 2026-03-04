<?php

namespace App\Livewire;

use App\Http\Controllers\NewsletterController;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Home extends Component {
    public function render() {
        return view('livewire.home.index');
    }

    #[Rule(['required', 'email'])]
    public string $email = '';
    
    public function registerNewsletter(): void {
        if (NewsletterController::register($this->email))
            $this->dispatch('notification', ["message" => __("messages.action.newsletter_success"), "type" => "success"]);
        else
            $this->dispatch('notification', ["message" => __("messages.action.generic_error"),"type" => "error"]);
    }

    public string $feedback_email = 'Anonymous';
    public string $feedback_name = 'Anonymous';

    #[Rule(['required'])]
    public string $feedback_message = '';

    public function registerFeedback(): void {
        $this->validate();
        $this->dispatch('notification', ["message" => __("messages.action.newsletter_success"), "type" => "success"]);
    }
}
