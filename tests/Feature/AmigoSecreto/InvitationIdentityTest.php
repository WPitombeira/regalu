<?php

use App\Jobs\SendEventInvitation;
use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('refuses a normalized duplicate invitation without enqueueing another delivery', function () {
    Queue::fake();
    $event = AmigoSecretoEvent::factory()->create();
    $invitation = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => ' Guest@Example.Test ',
        'status' => 'INVITED',
        'invited_at' => now()->subDay(),
    ]);
    $before = $invitation->fresh()->getAttributes();

    Livewire::actingAs($event->organizer)->test(ManageParticipants::class, ['event' => $event])
        ->set('inviteEmail', 'guest@example.test')->call('inviteByEmail')
        ->assertHasErrors('inviteEmail')->assertNotDispatched('notification')
        ->assertSet('inviteEmail', 'guest@example.test');

    expect($event->participants()->count())->toBe(1)
        ->and($invitation->fresh()->getAttributes())->toBe($before);
    Queue::assertNotPushed(SendEventInvitation::class);
});

it('refuses an event that becomes closed during authorization', function () {
    Queue::fake();
    $event = AmigoSecretoEvent::factory()->create();
    $component = Livewire::actingAs($event->organizer)->test(ManageParticipants::class, ['event' => $event])
        ->set('inviteEmail', 'guest@example.test');
    $fired = false;
    DB::listen(function (QueryExecuted $query) use ($event, &$fired): void {
        if ($fired || ! str_starts_with($query->sql, 'select * from "amigo_secreto_events"')
            || ! array_any(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), fn (array $frame): bool => ($frame['class'] ?? null) === ManageParticipants::class && ($frame['function'] ?? null) === 'inviteByEmail')) {
            return;
        }
        $fired = true;
        AmigoSecretoEvent::whereKey($event->id)->update(['status' => 'DRAWS_COMPLETE']);
    });

    $component->call('inviteByEmail')->assertForbidden()->assertNotDispatched('notification');

    expect($fired)->toBeTrue()
        ->and($event->fresh()->status)->toBe('DRAWS_COMPLETE')
        ->and($event->participants()->count())->toBe(0);
    Queue::assertNotPushed(SendEventInvitation::class);
});

it('does not invite an account that already joined by code', function () {
    Queue::fake();
    $event = AmigoSecretoEvent::factory()->create();
    $guest = User::factory()->create(['email' => 'guest@example.test']);
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $guest->id, 'status' => 'ACCEPTED',
    ]);
    $before = $participant->fresh()->getAttributes();

    Livewire::actingAs($event->organizer)->test(ManageParticipants::class, ['event' => $event])
        ->set('inviteEmail', 'Guest@Example.Test')->call('inviteByEmail')
        ->assertHasErrors('inviteEmail')->assertNotDispatched('notification');

    expect($event->participants()->count())->toBe(1)
        ->and($participant->fresh()->getAttributes())->toBe($before);
    Queue::assertNotPushed(SendEventInvitation::class);
});

it('holds the event write lock before checking an invitation identity', function () {
    Queue::fake();
    $event = AmigoSecretoEvent::factory()->create();
    $component = Livewire::actingAs($event->organizer)->test(ManageParticipants::class, ['event' => $event])
        ->set('inviteEmail', 'guest@example.test');
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = [$query->sql, $query->connection->transactionLevel()];
    });

    $component->call('inviteByEmail')->assertHasNoErrors()->assertDispatched('notification');

    $lock = array_find_key($statements, fn (array $entry): bool => str_starts_with($entry[0], 'update "amigo_secreto_events" set "id" = id'));
    $identity = array_find_key($statements, fn (array $entry): bool => str_starts_with($entry[0], 'select * from "amigo_secreto_participants"'));
    expect($lock)->not->toBeNull()
        ->and($identity)->not->toBeNull()
        ->and($lock)->toBeLessThan($identity)
        ->and($statements[$lock][1])->toBeGreaterThan(1)
        ->and($statements[$identity][1])->toBe($statements[$lock][1]);
    expect($event->participants()->sole()->status)->toBe('INVITED');
    Queue::assertPushed(SendEventInvitation::class, 1);
});
