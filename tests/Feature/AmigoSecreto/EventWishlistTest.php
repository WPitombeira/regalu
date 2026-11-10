<?php

use App\Livewire\AmigoSecreto\EventDetail;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
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
