<?php

use App\Livewire\AmigoSecreto\EventDetail;
use App\Livewire\AmigoSecreto\ExecuteDraw;
use App\Livewire\AmigoSecreto\JoinEvent;
use App\Livewire\AmigoSecreto\ManageExclusions;
use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('locks the event before reading the roster for a mutation', function (string $action) {
    Queue::fake();
    $event = AmigoSecretoEvent::factory()->create();
    $guest = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $event->organizer_id]);
    $members = User::factory()->count(3)->create()->prepend($event->organizer);
    foreach ($members as $member) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED']);
    }
    $excluded = $event->exclusions()->create(['user_a_id' => $members[1]->id, 'user_b_id' => $members[2]->id]);
    [$class, $method, $arguments] = match ($action) {
        'join' => [JoinEvent::class, 'join', []],
        'remove participant' => [ManageParticipants::class, 'removeParticipant', [$event->participants()->where('user_id', $members[3]->id)->sole()->id]],
        'draw' => [ExecuteDraw::class, 'executeDraw', []],
        'add exclusion' => [ManageExclusions::class, 'addExclusion', []],
        'remove exclusion' => [ManageExclusions::class, 'removeExclusion', [$excluded->id]],
        'wishlist consent' => [EventDetail::class, 'saveWishlist', []],
    };
    $component = Livewire::actingAs($action === 'join' ? $guest : $event->organizer)
        ->test($class, $action === 'join' ? ['code' => $event->event_code] : ['event' => $event]);
    if ($action === 'add exclusion') {
        $component->set('userAId', $members[0]->id)->set('userBId', $members[3]->id);
    }
    if ($action === 'wishlist consent') {
        $component->set('wishlist_id', $wishlist->id);
    }
    $statements = [];
    DB::listen(function (QueryExecuted $query) use ($class, $method, &$statements): void {
        if (array_any(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), fn (array $frame): bool => ($frame['class'] ?? null) === $class && ($frame['function'] ?? null) === $method)) {
            $statements[] = [$query->sql, $query->connection->transactionLevel()];
        }
    });

    $component->call($method, ...$arguments)->assertHasNoErrors();

    $lock = array_find_key($statements, fn (array $entry): bool => str_starts_with($entry[0], 'update "amigo_secreto_events" set "id" = id'));
    $roster = array_find_key($statements, fn (array $entry): bool => str_starts_with($entry[0], 'select')
        && (str_contains($entry[0], '"amigo_secreto_participants"') || str_contains($entry[0], '"amigo_secreto_exclusions"')));
    expect($lock)->not->toBeNull()
        ->and($roster)->not->toBeNull()
        ->and($lock)->toBeLessThan($roster)
        ->and($statements[$lock][1])->toBeGreaterThan(1)
        ->and($statements[$roster][1])->toBe($statements[$lock][1]);
    match ($action) {
        'join' => expect($event->participants()->where('user_id', $guest->id)->sole()->status)->toBe('ACCEPTED'),
        'remove participant' => expect($event->participants()->count())->toBe(3),
        'draw' => expect($event->draws()->count())->toBe(4)->and($event->fresh()->status)->toBe('DRAWS_COMPLETE'),
        'add exclusion' => expect($event->exclusions()->count())->toBe(2),
        'remove exclusion' => expect($event->exclusions()->count())->toBe(0),
        'wishlist consent' => expect($event->participants()->where('user_id', $event->organizer_id)->sole()->wishlist_id)->toBe($wishlist->id),
    };
})->with(['join', 'remove participant', 'draw', 'add exclusion', 'remove exclusion', 'wishlist consent']);
