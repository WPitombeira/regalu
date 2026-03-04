<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationService {
    /**
     * Send a notification to a user.
     *
     * @param User $user The recipient user
     * @param string $type Notification type (e.g. 'wishlist', 'family', 'secret_santa', 'system')
     * @param string $title Short notification title
     * @param string $message Full notification message
     * @param string|null $actionUrl Optional URL to navigate to when clicking
     * @param Model|null $relatedEntity Optional related Eloquent model (morph)
     * @return Notification
     */
    public static function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?Model $relatedEntity = null,
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'related_entity_type' => $relatedEntity?->getMorphClass(),
            'related_entity_id' => $relatedEntity?->getKey(),
        ]);
    }

    /**
     * Get the unread notification count for a user.
     */
    public static function unreadCount(User $user): int {
        return Notification::where('user_id', $user->id)->unread()->count();
    }
}
