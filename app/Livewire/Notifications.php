<?php

namespace App\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Notifications extends Component {
    public $notifications = [];

    #[On('notification')]
    public function triggerNotification(...$params) {
        unset($this->notifications);
        $this->notifications = $params;
    }

    public function render(): View|Closure|string {
        return view('livewire.notifications');
    }
}
