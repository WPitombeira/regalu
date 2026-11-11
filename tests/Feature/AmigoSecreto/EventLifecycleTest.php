<?php

use App\Livewire\AmigoSecreto\EventDetail;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('lets the organizer complete a drawn named event once its date arrives', function () {
    $organizer = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $organizer->id, 'status' => 'DRAWS_COMPLETE',
        'recipient_visibility' => 'NAMED', 'event_date' => today(),
    ]);

    Livewire::actingAs($organizer)->test(EventDetail::class, ['event' => $event])
        ->call('complete')->assertHasNoErrors();

    expect($event->fresh()->status)->toBe('COMPLETED');
    expect($event->fresh()->is_archived)->toBeFalse();
});

it('enforces completion eligibility without changing anonymous reveal policy', function (string $status, string $visibility, ?int $eventDays, ?int $revealDays, bool $allowed) {
    $organizer = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $organizer->id, 'status' => $status, 'recipient_visibility' => $visibility,
        'event_date' => $eventDays === null ? null : today()->addDays($eventDays),
        'reveal_date' => $revealDays === null ? null : today()->addDays($revealDays),
    ]);
    $component = Livewire::actingAs($organizer)->test(EventDetail::class, ['event' => $event])->call('complete');
    $allowed ? $component->assertHasNoErrors() : $component->assertForbidden();
    expect($event->fresh()->status)->toBe($allowed ? 'COMPLETED' : $status);
    expect($event->fresh()->recipient_visibility)->toBe($visibility);
})->with([
    ['PLANNING', 'NAMED', -1, null, false],
    ['INVITES_SENT', 'NAMED', -1, null, false],
    ['DRAW_PENDING', 'NAMED', -1, null, false],
    ['COMPLETED', 'NAMED', -1, null, false],
    ['DRAWS_COMPLETE', 'NAMED', 1, null, false],
    ['DRAWS_COMPLETE', 'NAMED', null, null, true],
    ['REVEALED', 'NAMED', -1, null, true],
    ['DRAWS_COMPLETE', 'ANONYMOUS_UNTIL_REVEAL', -1, -1, false],
    ['REVEALED', 'ANONYMOUS_UNTIL_REVEAL', -1, null, false],
    ['REVEALED', 'ANONYMOUS_UNTIL_REVEAL', -1, 1, false],
    ['REVEALED', 'ANONYMOUS_UNTIL_REVEAL', 0, 0, true],
]);

it('does not let an accepted participant complete the event', function () {
    $participant = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['status' => 'REVEALED', 'event_date' => null]);
    AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $participant->id, 'status' => 'ACCEPTED']);
    Livewire::actingAs($participant)->test(EventDetail::class, ['event' => $event])->call('complete')->assertForbidden();
    expect($event->fresh()->status)->toBe('REVEALED');
});

it('rechecks completion authority and dates in the final write', function (string $change) {
    $organizer = User::factory()->create();
    $other = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $organizer->id, 'status' => 'REVEALED', 'event_date' => today(),
        'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL', 'reveal_date' => today(),
    ]);
    $component = Livewire::actingAs($organizer)->test(EventDetail::class, ['event' => $event]);
    $interleaved = false;
    DB::connection()->beforeExecuting(function (string $sql) use ($change, $event, $other, &$interleaved): void {
        if ($interleaved || ! str_starts_with($sql, 'update "amigo_secreto_events" set ')) {
            return;
        }
        $interleaved = true;
        $event->update(match ($change) {
            'organizer' => ['organizer_id' => $other->id],
            'archive' => ['is_archived' => true],
            'status' => ['status' => 'DRAWS_COMPLETE'],
            'event-date' => ['event_date' => today()->addDay()],
            'reveal-date' => ['reveal_date' => today()->addDay()],
        });
    });
    $component->call('complete')->assertForbidden();
    expect($interleaved)->toBeTrue();
    expect($event->fresh()->status)->not->toBe('COMPLETED');
})->with(['organizer', 'archive', 'status', 'event-date', 'reveal-date']);

it('lets the organizer archive a completed event without deleting its history', function () {
    $organizer = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id, 'status' => 'COMPLETED']);
    Livewire::actingAs($organizer)->test(EventDetail::class, ['event' => $event])
        ->call('archive')->assertRedirect(route('amigo-secreto.index', ['period' => 'past']));
    expect($event->fresh()->status)->toBe('COMPLETED');
    expect($event->fresh()->is_archived)->toBeTrue();
});
