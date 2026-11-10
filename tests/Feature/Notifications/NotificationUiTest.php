<?php

use App\Livewire\Notifications;
use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Notifications\NotificationList;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('paginates the actual notification component and never mutates another users notifications', function () {
    $user = User::factory()->create();
    $other = NotificationService::send(User::factory()->create(), 'system', 'Private notice', 'Private message');
    for ($i = 0; $i < 17; $i++) {
        NotificationService::send($user, 'system', "Notice {$i}", 'Message');
    }
    $component = Livewire::actingAs($user)->test(NotificationList::class)
        ->assertDontSee('Private notice')
        ->assertViewHas('notifications', fn ($page) => $page->count() === 15)
        ->call('nextPage')->assertViewHas('notifications', fn ($page) => $page->count() === 2)
        ->call('markAsRead', $other->id)->assertNoRedirect()
        ->call('markAllRead');
    expect(NotificationService::unreadCount($user))->toBe(0)
        ->and($other->fresh()->is_read)->toBeFalse();
});

it('marks owned notifications read and follows only the selected owned action', function () {
    $user = User::factory()->create();
    $plain = NotificationService::send($user, 'system', 'Plain', 'No link');
    $linked = NotificationService::send($user, 'system', 'Linked', 'Has link', actionUrl: '/dashboard');
    Livewire::actingAs($user)->test(NotificationList::class)->call('markAsRead', $plain->id)->assertNoRedirect();
    Livewire::actingAs($user)->test(NotificationList::class)->call('markAsRead', $linked->id)->assertRedirect('/dashboard');
    expect($plain->fresh()->is_read)->toBeTrue()->and($linked->fresh()->is_read)->toBeTrue();
});

it('bounds the bell preview and clears only the signed in users unread notifications', function () {
    $user = User::factory()->create();
    $other = NotificationService::send(User::factory()->create(), 'system', 'Other', 'Other');
    for ($i = 0; $i < 7; $i++) {
        NotificationService::send($user, 'system', "Bell {$i}", 'Message');
    }
    Livewire::actingAs($user)->test(NotificationBell::class)
        ->assertSet('unreadCount', 7)
        ->assertSet('recentNotifications', fn ($items) => $items->count() === 5)
        ->call('markAllRead')->assertSet('unreadCount', 0);
    expect($other->fresh()->is_read)->toBeFalse();
});

it('denies notification actions when the session has expired', function (string $component) {
    $user = User::factory()->create();
    $mounted = Livewire::actingAs($user)->test($component);
    Auth::logout();
    $mounted->call('markAllRead')->assertForbidden();
})->with([NotificationBell::class, NotificationList::class]);

it('renders and clears typed transient toast notifications', function () {
    Livewire::test(Notifications::class)
        ->call('triggerNotification', ['message' => 'Saved', 'type' => 'success'])
        ->assertSet('notifications.0.message', 'Saved')
        ->assertSet('notifications.0.type', 'success')
        ->call('triggerNotification', [])
        ->assertSet('notifications.1.type', 'info')
        ->call('clearNotifications')->assertSet('notifications', []);
});
