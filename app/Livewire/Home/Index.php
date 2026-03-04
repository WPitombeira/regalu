<?php

namespace App\Livewire\Home;
use Livewire\Attributes\Rule;

use Livewire\Component;

class Index extends Component {
    public function render() {
        return view('livewire.home.index');
    }

    #[Rule(['required', 'email'])]
    public string $email = '';

    #[Rule(['required', 'min:5'])]
    public string $feedback_message = '';
    public string $feedback_email = '';
    public string $feedback_name = '';
}
