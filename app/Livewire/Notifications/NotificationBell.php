<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component {
    /**
     * Get the count of unread notifications for the current user.
     */
    #[Computed]
    public function unreadCount(): int {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return NotificationService::unreadCount($user);
    }

    /**
     * Get the most recent notifications (max 5) for the dropdown preview.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Notification>
     */
    #[Computed]
    public function recentNotifications(): \Illuminate\Database\Eloquent\Collection {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Mark all of the current user's notifications as read.
     */
    public function markAllRead(): void {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        Notification::where('user_id', $user->id)
            ->unread()
            ->update(['is_read' => true]);
    }

    public function render() {
        return view('livewire.notifications.notification-bell');
    }
}
