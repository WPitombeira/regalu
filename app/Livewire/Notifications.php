<?php

namespace App\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Notifications extends Component
{
    /** @var list<array{message: string, type: string, wasSeen: bool, time: int}> */
    public array $notifications = [];

    /** @param array{message?: string, type?: string} ...$params */
    #[On('notification')]
    public function triggerNotification(array ...$params): void
    {
        foreach ($params as $param) {
            $this->addNotification($param['message'] ?? '', $param['type'] ?? 'info');
        }
    }

    private function addNotification(string $Message = '', string $Type = 'info'): void
    {
        $Notification = [
            'message' => $Message,
            'type' => $Type,
            'wasSeen' => false,
            'time' => now()->timestamp,
        ];

        array_push($this->notifications, $Notification);
    }

    public function clearNotifications(): void
    {
        $this->notifications = [];
    }

    public function render(): View|Closure|string
    {
        return view('livewire.notifications');
    }
}
