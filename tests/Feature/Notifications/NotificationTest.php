<?php

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;

describe('notifications', function () {
    it('can create a notification via service', function () {
        $user = User::factory()->create();

        $notification = NotificationService::send(
            user: $user,
            type: 'system',
            title: 'Welcome',
            message: 'Welcome to Regalu!',
            actionUrl: '/dashboard',
        );

        expect($notification)->toBeInstanceOf(Notification::class);
        expect($notification->user_id)->toBe($user->id);
        expect($notification->type)->toBe('system');
        expect($notification->title)->toBe('Welcome');
        expect($notification->message)->toBe('Welcome to Regalu!');
        expect($notification->action_url)->toBe('/dashboard');
        expect($notification->is_read)->toBeFalse();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Welcome',
        ]);
    });

    it('shows correct unread count', function () {
        $user = User::factory()->create();

        // Create 3 unread notifications
        NotificationService::send($user, 'system', 'Test 1', 'Message 1');
        NotificationService::send($user, 'system', 'Test 2', 'Message 2');
        NotificationService::send($user, 'system', 'Test 3', 'Message 3');

        expect(NotificationService::unreadCount($user))->toBe(3);

        // Mark one as read
        Notification::where('user_id', $user->id)->first()->update(['is_read' => true]);

        expect(NotificationService::unreadCount($user))->toBe(2);
    });

    it('can mark notification as read', function () {
        $user = User::factory()->create();

        $notification = NotificationService::send($user, 'system', 'Test', 'Message');

        expect($notification->is_read)->toBeFalse();

        $notification->update(['is_read' => true]);
        $notification->refresh();

        expect($notification->is_read)->toBeTrue();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    });

    it('can mark all notifications as read', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create notifications for our user
        NotificationService::send($user, 'system', 'Test 1', 'Message 1');
        NotificationService::send($user, 'system', 'Test 2', 'Message 2');
        NotificationService::send($user, 'system', 'Test 3', 'Message 3');

        // Create a notification for another user (should NOT be affected)
        NotificationService::send($otherUser, 'system', 'Other', 'Other message');

        expect(NotificationService::unreadCount($user))->toBe(3);
        expect(NotificationService::unreadCount($otherUser))->toBe(1);

        // Mark all as read for our user
        Notification::where('user_id', $user->id)->unread()->update(['is_read' => true]);

        expect(NotificationService::unreadCount($user))->toBe(0);
        // Other user's notifications should remain unread
        expect(NotificationService::unreadCount($otherUser))->toBe(1);
    });

    it('notification list is paginated', function () {
        $user = User::factory()->create();

        // Create 20 notifications
        for ($i = 1; $i <= 20; $i++) {
            NotificationService::send($user, 'system', "Notification {$i}", "Message {$i}");
        }

        $this->actingAs($user);

        // The NotificationList component paginates at 15 per page
        $page1 = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        expect($page1)->toHaveCount(15);
        expect($page1->total())->toBe(20);
        expect($page1->lastPage())->toBe(2);

        // Page 2 should have the remaining 5
        $page2 = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'page', 2);

        expect($page2)->toHaveCount(5);
    });

    it('does not count other users notifications in unread count', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        NotificationService::send($userA, 'system', 'For A', 'Message A');
        NotificationService::send($userB, 'system', 'For B', 'Message B');
        NotificationService::send($userB, 'system', 'For B 2', 'Message B 2');

        expect(NotificationService::unreadCount($userA))->toBe(1);
        expect(NotificationService::unreadCount($userB))->toBe(2);
    });

    it('can store notification with related entity morph', function () {
        $user = User::factory()->create();
        $relatedUser = User::factory()->create();

        $notification = NotificationService::send(
            user: $user,
            type: 'family',
            title: 'New member',
            message: 'A new member joined your family.',
            relatedEntity: $relatedUser,
        );

        expect($notification->related_entity_type)->not->toBeNull();
        expect($notification->related_entity_id)->toBe($relatedUser->id);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'related_entity_id' => $relatedUser->id,
        ]);
    });

    it('scopes unread notifications correctly', function () {
        $user = User::factory()->create();

        $unread = NotificationService::send($user, 'system', 'Unread', 'Unread message');
        $read = NotificationService::send($user, 'system', 'Read', 'Read message');
        $read->update(['is_read' => true]);

        $unreadNotifications = Notification::where('user_id', $user->id)->unread()->get();

        expect($unreadNotifications)->toHaveCount(1);
        expect($unreadNotifications->first()->id)->toBe($unread->id);
    });
});
