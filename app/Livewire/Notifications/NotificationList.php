<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component
{
    use WithPagination;

    /**
     * Mark a single notification as read. If it has an action_url, redirect to it.
     */
    public function markAsRead(int $notificationId): void
    {
        $user = $this->currentUser();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $notificationId)
            ->first();

        if (! $notification) {
            return;
        }

        $notification->update(['is_read' => true]);

        if ($notification->action_url) {
            $this->redirect($notification->action_url);
        }
    }

    /**
     * Mark all of the current user's notifications as read.
     */
    public function markAllRead(): void
    {
        $user = $this->currentUser();

        Notification::where('user_id', $user->id)
            ->unread()
            ->update(['is_read' => true]);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function render(): View
    {
        $user = $this->currentUser();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
        ]);
    }
}
