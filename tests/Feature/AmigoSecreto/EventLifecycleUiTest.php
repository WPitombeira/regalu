<?php

use App\Livewire\AmigoSecreto\EventDetail;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use Livewire\Livewire;

it('guides an eligible organizer from completion to archive', function () {
    $host = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $host->id, 'status' => 'DRAWS_COMPLETE', 'event_date' => null,
    ]);
    $component = Livewire::actingAs($host)->test(EventDetail::class, ['event' => $event])
        ->assertSee('wire:click="complete"', false)->assertDontSee('wire:click="archive"', false)
        ->call('complete')->assertHasNoErrors()->assertDispatched('notification')
        ->assertDontSee('wire:click="complete"', false)->assertSee('wire:click="archive"', false)
        ->assertSee(__('event_history.closed'))->assertDontSee($event->event_code)
        ->assertSee(route('amigo-secreto.history', $event), false);
    expect($event->fresh()->status)->toBe('COMPLETED');
    $component->call('archive')->assertRedirect(route('amigo-secreto.index', ['period' => 'past']));
    expect($event->fresh()->is_archived)->toBeTrue();
});

it('shows completion and archive controls only when policy allows them', function (string $role, string $status, int $days, bool $complete, bool $archive) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $role === 'host' ? $user->id : User::factory(),
        'status' => $status, 'event_date' => today()->addDays($days),
    ]);
    if ($role !== 'host') {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    $component = Livewire::actingAs($user)->test(EventDetail::class, ['event' => $event]);
    $complete ? $component->assertSee('wire:click="complete"', false) : $component->assertDontSee('wire:click="complete"', false);
    $archive ? $component->assertSee('wire:click="archive"', false) : $component->assertDontSee('wire:click="archive"', false);
})->with([
    ['host', 'PLANNING', -1, false, false],
    ['host', 'DRAWS_COMPLETE', 1, false, false],
    ['host', 'REVEALED', 0, true, false],
    ['host', 'COMPLETED', -1, false, true],
    ['member', 'REVEALED', -1, false, false],
    ['member', 'COMPLETED', -1, false, false],
]);

it('offers only consent revocation after completion', function () {
    $user = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);
    $event = AmigoSecretoEvent::factory()->create(['status' => 'COMPLETED']);
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED', 'wishlist_id' => $wishlist->id,
    ]);
    Livewire::actingAs($user)->test(EventDetail::class, ['event' => $event])
        ->assertDontSee('wire:submit="saveWishlist"', false)->assertDontSee('id="wishlist_id"', false)
        ->assertDontSee($wishlist->name)->assertSee('wire:click="revokeWishlist"', false)
        ->call('revokeWishlist')->assertHasNoErrors()->assertDispatched('notification')
        ->assertDontSee('wire:click="revokeWishlist"', false);
    expect($participant->fresh()->wishlist_id)->toBeNull();
});

it('explains completed assignment closure instead of promising future wishlist access', function () {
    $buyer = User::factory()->create();
    $recipient = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['status' => 'COMPLETED']);
    foreach ([$buyer, $recipient] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    AmigoSecretoDraw::create(['event_id' => $event->id, 'buyer_user_id' => $buyer->id, 'recipient_user_id' => $recipient->id]);
    $this->actingAs($buyer)->get(route('amigo-secreto.assignment', $event))->assertOk()
        ->assertSee(__('event_history.closed'))->assertDontSee(__('assignments.not_shared'));
    Livewire::actingAs($buyer)->test(EventDetail::class, ['event' => $event])
        ->assertSee($recipient->name)->assertDontSee(__('assignments.open'));
});

it('links unarchived history to the remaining event controls but never reopens archived details', function (bool $archived) {
    $host = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $host->id, 'status' => 'COMPLETED', 'is_archived' => $archived]);
    $response = $this->actingAs($host)->get(route('amigo-secreto.history', $event))->assertOk();
    $link = 'href="'.route('amigo-secreto.show', $event).'"';
    $archived ? $response->assertDontSee($link, false) : $response->assertSee($link, false);
})->with([false, true]);
