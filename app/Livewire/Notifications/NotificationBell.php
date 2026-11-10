<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    /**
     * Get the count of unread notifications for the current user.
     */
    #[Computed]
    public function unreadCount(): int
    {
        $user = $this->currentUser();

        return NotificationService::unreadCount($user);
    }

    /**
     * Get the most recent notifications (max 5) for the dropdown preview.
     *
     * @return Collection<int, Notification>
     */
    #[Computed]
    public function recentNotifications(): Collection
    {
        $user = $this->currentUser();

        return Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
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
        $this->currentUser();

        return view('livewire.notifications.notification-bell');
    }
}
