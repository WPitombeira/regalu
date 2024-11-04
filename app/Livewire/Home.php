<?php

namespace App\Livewire;

use App\Http\Controllers\NewsletterController;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Home extends Component {
    public function render() {
        return view('home');
    }

    #[Rule(['required', 'email'])]
    public string $email = '';
    
    public function registerNewsletter(): void {
        if (NewsletterController::register($this->email))
            $this->dispatch('notification', ["message" => __("messages.action.success"), "type" => 'success']);
        else
            $this->dispatch('notification', ["message" => __("messages.action.error"), "type" => 'error']);
    }
}
