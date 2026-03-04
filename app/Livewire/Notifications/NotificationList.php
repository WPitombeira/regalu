<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component {
    use WithPagination;

    /**
     * Mark a single notification as read. If it has an action_url, redirect to it.
     */
    public function markAsRead(int $notificationId): mixed {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $notificationId)
            ->first();

        if (! $notification) {
            return null;
        }

        $notification->update(['is_read' => true]);

        if ($notification->action_url) {
            return $this->redirect($notification->action_url);
        }

        return null;
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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
        ]);
    }
}
