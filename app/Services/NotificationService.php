<?php

namespace App\Services;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\FamilyInvitation;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class NotificationService
{
    public static function familyInvited(FamilyInvitation $invitation): void
    {
        $recipient = self::recipientForEmail($invitation->email);
        if ($recipient !== null) {
            self::record($recipient, 'family_invitation', 'family', $invitation, $invitation->generation);
        }
    }

    public static function eventInvited(AmigoSecretoParticipant $participant): void
    {
        $recipient = self::recipientForEmail($participant->invite_email ?? '');
        if ($recipient !== null) {
            self::record($recipient, 'event_invitation', 'secret_santa', $participant);
        }
    }

    public static function assignmentsAvailable(AmigoSecretoEvent $event, bool $revealed = false): void
    {
        foreach ($event->draws()->with('drawer')->lazyById(100) as $draw) {
            $buyer = $draw->drawer;
            if ($buyer !== null && Gate::forUser($buyer)->allows('view', $event)
                && app(AssignmentAccess::class)->summaryFor($event, $buyer) !== null) {
                self::record($buyer, $revealed ? 'assignment_revealed' : 'assignment_available', 'secret_santa', $event, (string) $draw->id);
            }
        }
    }

    public static function wishlistPurchased(Wishlist $wishlist, int $itemId): void
    {
        $owner = User::find($wishlist->user_id);
        if ($owner !== null) {
            self::record($owner, 'wishlist_purchase', 'wishlist', $wishlist, (string) $itemId);
        }
    }

    private static function recipientForEmail(string $email): ?User
    {
        $recipients = User::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])->limit(2)->get();

        return $recipients->count() === 1 ? $recipients->sole() : null;
    }

    private static function record(User $recipient, string $kind, string $type, Model $entity, string $version = ''): void
    {
        $locale = in_array($recipient->locale, ['en', 'pt_BR'], true) ? $recipient->locale : 'en';
        Notification::firstOrCreate([
            'deduplication_key' => hash('sha256', $kind.':'.$recipient->id.':'.$entity->getKey().':'.$version),
        ], [
            'user_id' => $recipient->id,
            'kind' => $kind,
            'type' => $type,
            'title' => __('inbox.'.$kind.'_title', [], $locale),
            'message' => __('inbox.'.$kind.'_message', [], $locale),
            'related_entity_type' => $entity->getMorphClass(),
            'related_entity_id' => $entity->getKey(),
            'action_url' => null,
            'is_read' => false,
        ]);
    }

    /**
     * Send a notification to a user.
     *
     * @param  User  $user  The recipient user
     * @param  string  $type  Notification type (e.g. 'wishlist', 'family', 'secret_santa', 'system')
     * @param  string  $title  Short notification title
     * @param  string  $message  Full notification message
     * @param  string|null  $actionUrl  Optional URL to navigate to when clicking
     * @param  Model|null  $relatedEntity  Optional related Eloquent model (morph)
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
    public static function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)->unread()->count();
    }
}
