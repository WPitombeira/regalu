<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationInbox;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component
{
    use WithPagination;

    /**
     * Mark an owned notification read and resolve its current authorized action.
     */
    public function markAsRead(int $notificationId): void
    {
        $user = $this->currentUser();

        $destination = app(NotificationInbox::class)->open($user, $notificationId);
        if ($destination !== null) {
            $this->redirect($destination);
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

        $notifications = app(NotificationInbox::class)->pageFor($user);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications,
        ]);
    }
}
