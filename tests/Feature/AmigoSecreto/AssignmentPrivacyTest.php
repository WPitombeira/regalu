<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Livewire\AmigoSecreto\EventDetail;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('lets the host choose recipient visibility when creating an event', function (string $visibility) {
    $host = User::factory()->create();

    Livewire::actingAs($host)->test(CreateEvent::class)
        ->set('name', 'Secret gift exchange')
        ->set('recipient_visibility', $visibility)
        ->set('reveal_date', now()->addWeek()->toDateString())
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(AmigoSecretoEvent::sole()->recipient_visibility)->toBe($visibility);
})->with(['NAMED', 'ANONYMOUS_UNTIL_REVEAL']);

it('requires a reveal date for anonymous events', function () {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Secret gift exchange')
        ->set('recipient_visibility', 'ANONYMOUS_UNTIL_REVEAL')
        ->call('create')
        ->assertHasErrors(['reveal_date' => 'required_if']);

    expect(AmigoSecretoEvent::count())->toBe(0);
});

it('lets only the host change secrecy before the draw', function () {
    $host = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $host->id]);

    $component = Livewire::actingAs($host)->test(EventDetail::class, ['event' => $event])
        ->set('recipient_visibility', 'ANONYMOUS_UNTIL_REVEAL')
        ->set('reveal_date', now()->addWeek()->toDateString())
        ->call('savePrivacy')
        ->assertHasNoErrors();

    expect($event->fresh()->recipient_visibility)->toBe('ANONYMOUS_UNTIL_REVEAL');

    $event->update(['status' => 'DRAWS_COMPLETE']);
    $component->set('recipient_visibility', 'NAMED')->call('savePrivacy')->assertForbidden();
    expect($event->fresh()->recipient_visibility)->toBe('ANONYMOUS_UNTIL_REVEAL');
});

it('does not allow a participant to change the host’s secrecy rules', function () {
    $member = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create();
    AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED']);

    Livewire::actingAs($member)->test(EventDetail::class, ['event' => $event])
        ->assertDontSee('wire:submit="savePrivacy"', false)
        ->set('recipient_visibility', 'ANONYMOUS_UNTIL_REVEAL')
        ->set('reveal_date', today()->addWeek()->toDateString())
        ->call('savePrivacy')->assertForbidden();
    expect($event->fresh()->recipient_visibility)->toBe('NAMED');
});

it('rejects invalid secrecy settings and past reveal dates', function (string $visibility, ?string $date, string $field) {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Validated exchange')->set('recipient_visibility', $visibility)
        ->set('reveal_date', $date)->call('create')->assertHasErrors($field);
    expect(AmigoSecretoEvent::count())->toBe(0);
})->with([
    ['PUBLIC', null, 'recipient_visibility'],
    ['ANONYMOUS_UNTIL_REVEAL', '2000-01-01', 'reveal_date'],
    ['ANONYMOUS_UNTIL_REVEAL', 'not-a-date', 'reveal_date'],
]);

it('keeps named assignments as the backward-compatible default', function () {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Traditional exchange')->call('create')->assertHasNoErrors();
    expect(AmigoSecretoEvent::sole()->recipient_visibility)->toBe('NAMED');
});
