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
}
