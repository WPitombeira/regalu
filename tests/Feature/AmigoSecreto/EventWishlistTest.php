<?php

use App\Livewire\AmigoSecreto\EventDetail;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('lets an accepted participant opt in their own private wishlist and revoke it', function () {
    $recipient = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create();
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $recipient->id, 'status' => 'ACCEPTED',
    ]);
    $wishlist = Wishlist::factory()->create(['user_id' => $recipient->id, 'privacy' => 'PRIVATE']);

    $component = Livewire::actingAs($recipient)->test(EventDetail::class, ['event' => $event])
        ->set('wishlist_id', $wishlist->id)->call('saveWishlist')->assertHasNoErrors();

    expect($participant->fresh()->wishlist_id)->toBe($wishlist->id);
    expect($wishlist->fresh()->privacy)->toBe('PRIVATE');
    $this->assertDatabaseCount('wishlist_shares', 0);

    $component->set('wishlist_id', null)->call('saveWishlist')->assertHasNoErrors();
    expect($participant->fresh()->wishlist_id)->toBeNull();
});

it('rejects other people’s and archived wishlists', function (bool $archived) {
    $recipient = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create();
    $participant = AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $recipient->id, 'status' => 'ACCEPTED']);
    $wishlist = Wishlist::factory()->create([
        'user_id' => $archived ? $recipient->id : User::factory()->create()->id,
        'is_archived' => $archived,
    ]);
    Livewire::actingAs($recipient)->test(EventDetail::class, ['event' => $event])
        ->set('wishlist_id', $wishlist->id)->call('saveWishlist')->assertHasErrors('wishlist_id');
    expect($participant->fresh()->wishlist_id)->toBeNull();
})->with([false, true]);

it('lets recipients revoke consent after the reveal date', function () {
    $recipient = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'status' => 'REVEALED', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL', 'reveal_date' => today()->subWeek(),
    ]);
    $wishlist = Wishlist::factory()->create(['user_id' => $recipient->id]);
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $recipient->id, 'status' => 'ACCEPTED', 'wishlist_id' => $wishlist->id,
    ]);
    Livewire::actingAs($recipient)->test(EventDetail::class, ['event' => $event])
        ->set('wishlist_id', null)->call('saveWishlist')->assertHasNoErrors();
    expect($participant->fresh()->wishlist_id)->toBeNull();
});

it('allows only revocation of wishlist consent after completion', function (bool $revoke) {
    $recipient = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['status' => 'COMPLETED']);
    $original = Wishlist::factory()->create(['user_id' => $recipient->id]);
    $replacement = Wishlist::factory()->create(['user_id' => $recipient->id]);
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $recipient->id, 'status' => 'ACCEPTED', 'wishlist_id' => $original->id,
    ]);

    $component = Livewire::actingAs($recipient)->test(EventDetail::class, ['event' => $event])
        ->set('wishlist_id', $revoke ? null : $replacement->id)->call('saveWishlist');

    expect($participant->fresh()->wishlist_id)->toBe($revoke ? null : $original->id);
    $revoke ? $component->assertHasNoErrors() : $component->assertForbidden();
    $this->assertDatabaseCount('wishlist_shares', 0);
})->with([true, false]);

it('rechecks consent grants at the final write', function (string $change) {
    $recipient = User::factory()->create();
    $other = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['status' => 'REVEALED']);
    $otherEvent = AmigoSecretoEvent::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $recipient->id]);
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $recipient->id, 'status' => 'ACCEPTED',
    ]);
    $component = Livewire::actingAs($recipient)->test(EventDetail::class, ['event' => $event])
        ->set('wishlist_id', $wishlist->id);
    $interleaved = false;
    DB::connection()->beforeExecuting(function (string $sql) use ($change, $event, $otherEvent, $participant, $wishlist, $other, &$interleaved): void {
        if ($interleaved || ! str_starts_with($sql, 'update "amigo_secreto_participants" set ')) {
            return;
        }
        $interleaved = true;
        match ($change) {
            'complete' => $event->update(['status' => 'COMPLETED']),
            'archive-event' => $event->update(['is_archived' => true]),
            'withdraw' => $participant->update(['status' => 'DECLINED']),
            'move-user' => $participant->update(['user_id' => $other->id]),
            'move-event' => $participant->update(['event_id' => $otherEvent->id]),
            'archive-list' => $wishlist->update(['is_archived' => true]),
            'transfer-list' => $wishlist->update(['user_id' => $other->id]),
        };
    });

    $component->call('saveWishlist');
    expect($interleaved)->toBeTrue();
    expect($participant->fresh()->wishlist_id)->toBeNull();
    $component->assertForbidden();
})->with(['complete', 'archive-event', 'withdraw', 'move-user', 'move-event', 'archive-list', 'transfer-list']);
