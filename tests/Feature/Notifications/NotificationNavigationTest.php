<?php

use App\Livewire\Notifications\NotificationList;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\NotificationService;
use Livewire\Livewire;

it('marks legacy notifications read without following unapproved destinations', function (string $url) {
    $user = User::factory()->create();
    $notice = NotificationService::send($user, 'system', 'Legacy notice', 'Message', actionUrl: $url);
    Livewire::actingAs($user)->test(NotificationList::class)
        ->call('markAsRead', $notice->id)->assertNoRedirect();
    expect($notice->fresh()->is_read)->toBeTrue();
})->with(['https://example.com/', '//example.com/', '/\\example.com/', 'javascript:alert(1)', '/families/1', '/join-event/SECRET', '/dashboard?next=https://example.com']);

it('revalidates purchase notice ownership and list availability when opened', function (string $change) {
    $owner = User::factory()->create();
    $list = Wishlist::factory()->create(['user_id' => $owner->id]);
    NotificationService::wishlistPurchased($list, 123);
    $notice = Notification::sole();
    Livewire::actingAs($owner)->test(NotificationList::class)->call('markAsRead', $notice->id)
        ->assertRedirect(route('wishlists.show', $list));
    $mounted = Livewire::actingAs($owner)->test(NotificationList::class);
    match ($change) {
        'archive' => $list->update(['is_archived' => true]),
        'owner' => $list->update(['user_id' => User::factory()->create()->id]),
        'delete' => $list->delete(),
    };
    $mounted->call('markAsRead', $notice->id)->assertNoRedirect();
})->with(['archive', 'owner', 'delete']);

it('revalidates assignment notices without granting hosts access to another pairing', function (string $change) {
    $buyer = User::factory()->create();
    $target = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['status' => 'DRAWS_COMPLETE', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL', 'reveal_date' => today()->addDay()]);
    foreach ([$buyer, $target] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    $draw = AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $buyer->id, 'target_user_id' => $target->id, 'draw_date' => now()]);
    NotificationService::assignmentsAvailable($event);
    $notice = Notification::sole();
    Livewire::actingAs($event->organizer)->test(NotificationList::class)->call('markAsRead', $notice->id)->assertNoRedirect();
    expect($notice->fresh()->is_read)->toBeFalse();
    Livewire::actingAs($buyer)->test(NotificationList::class)->call('markAsRead', $notice->id)
        ->assertRedirect(route('amigo-secreto.assignment', $event));
    $mounted = Livewire::actingAs($buyer)->test(NotificationList::class);
    match ($change) {
        'archive' => $event->update(['is_archived' => true]),
        'complete' => $event->update(['status' => 'COMPLETED']),
        'buyer' => $event->participants()->where('user_id', $buyer->id)->delete(),
        'target' => $event->participants()->where('user_id', $target->id)->delete(),
        'draw' => $draw->delete(),
    };
    $mounted->call('markAsRead', $notice->id)->assertNoRedirect();
})->with(['archive', 'complete', 'buyer', 'target', 'draw']);

it('withdraws a reveal notice when the reveal date is postponed', function () {
    $buyer = User::factory()->create();
    $target = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'status' => 'REVEALED', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL', 'reveal_date' => today(),
    ]);
    foreach ([$buyer, $target] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $buyer->id, 'target_user_id' => $target->id, 'draw_date' => now()]);
    NotificationService::assignmentsAvailable($event, revealed: true);
    $notice = Notification::sole();
    Livewire::actingAs($buyer)->test(NotificationList::class)->call('markAsRead', $notice->id)
        ->assertRedirect(route('amigo-secreto.assignment', $event));
    $mounted = Livewire::actingAs($buyer)->test(NotificationList::class);
    $event->update(['reveal_date' => today()->addDay()]);
    $mounted->call('markAsRead', $notice->id)->assertNoRedirect()->assertSee(__('inbox.unavailable'));
});

it('opens a scheduled named reveal notice using its current event date when no reveal date exists', function () {
    $buyer = User::factory()->create();
    $target = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'status' => 'DRAWS_COMPLETE', 'recipient_visibility' => 'NAMED',
        'reveal_date' => null, 'event_date' => today(),
    ]);
    foreach ([$buyer, $target] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $buyer->id, 'target_user_id' => $target->id, 'draw_date' => now()]);

    $this->artisan('secret-santa:reveal')->assertExitCode(0);
    expect($event->refresh()->status)->toBe('REVEALED');
    $notice = Notification::where('user_id', $buyer->id)->sole();
    expect($notice->kind)->toBe('assignment_revealed');
    Livewire::actingAs($buyer)->test(NotificationList::class)->call('markAsRead', $notice->id)
        ->assertRedirect(route('amigo-secreto.assignment', $event));

    $mounted = Livewire::actingAs($buyer)->test(NotificationList::class);
    $event->update(['event_date' => today()->addDay()]);
    $mounted->call('markAsRead', $notice->id)->assertNoRedirect()->assertSee(__('inbox.unavailable'));
});
