<?php

use App\Livewire\AmigoSecreto\EventDetail;
use App\Livewire\AmigoSecreto\EventHistory;
use App\Livewire\AmigoSecreto\EventIndex;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('paginates history deterministically and resets the page when switching periods', function () {
    $user = User::factory()->create();
    $events = AmigoSecretoEvent::factory()->count(13)->create([
        'organizer_id' => $user->id, 'status' => 'COMPLETED', 'created_at' => now(),
    ]);
    $component = Livewire::withQueryParams(['period' => 'past'])->actingAs($user)->test(EventIndex::class)
        ->assertViewHas('events', fn ($page) => $page->count() === 12 && $page->first()->id === $events->last()->id)
        ->call('nextPage')->assertViewHas('events', fn ($page) => $page->count() === 1 && $page->currentPage() === 2);
    $events->first()->update(['organizer_id' => User::factory()->create()->id]);
    $component->call('$refresh')->assertViewHas('events', fn ($page) => $page->isEmpty())
        ->assertSee('previousPage', false)->set('period', 'active')
        ->assertViewHas('events', fn ($page) => $page->currentPage() === 1);
});

it('revalidates history access on an already mounted page', function (string $change) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $change === 'organizer' ? $user->id : User::factory(), 'status' => 'COMPLETED',
    ]);
    $member = $change === 'organizer' ? null : AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED',
    ]);
    $component = Livewire::actingAs($user)->test(EventHistory::class, ['eventId' => $event->id])
        ->assertViewHas('event', function ($record) {
            expect(array_keys($record->getAttributes()))->toEqualCanonicalizing([
                'id', 'name', 'description', 'event_type', 'status', 'is_archived',
                'event_date', 'budget_min', 'budget_max', 'accepted_count',
            ]);
            expect($record->getRelations())->toBe([]);

            return true;
        });
    match ($change) {
        'organizer' => $event->update(['organizer_id' => User::factory()->create()->id]),
        'participant' => $member->update(['status' => 'WITHDRAWN']),
        'event' => $event->update(['status' => 'PLANNING']),
        'deleted' => $event->delete(),
    };
    $component->call('$refresh')->assertForbidden();
})->with(['organizer', 'participant', 'event', 'deleted']);

it('locks the history resource identifier', function () {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'status' => 'COMPLETED']);
    Livewire::actingAs($user)->test(EventHistory::class, ['eventId' => $event->id])->set('eventId', $event->id + 1);
})->throws(CannotUpdateLockedPropertyException::class);

it('rejects missing principals and invalid event periods', function () {
    $this->get('/secret-santa/1/history')->assertRedirect(route('login'));
    Livewire::test(EventIndex::class)->assertForbidden();
    Livewire::test(EventHistory::class, ['eventId' => 1])->assertForbidden();
    Livewire::withQueryParams(['period' => 'everything'])->actingAs(User::factory()->create())->test(EventIndex::class)->assertNotFound();
});

it('checks current grants and password sessions on real HTTP history refreshes', function (string $change, int $status) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['status' => 'COMPLETED', 'is_archived' => true]);
    $member = AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    $page = $this->actingAs($user)->get(route('amigo-secreto.history', $event))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'amigo-secreto.event-history');
    expect($snapshot)->toBeString()->not->toContain('event_code', 'wishlist_id', 'target_user_id', 'organizer_id');
    $sessionHash = session('password_hash_web');
    if ($change === 'password') {
        $user->update(['password' => 'rotated-test-password']);
    } elseif ($change === 'membership') {
        $member->update(['status' => 'WITHDRAWN']);
    }
    $response = $this->actingAs($user->fresh())->withSession(['password_hash_web' => $sessionHash])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertStatus($status);
    $response->assertDontSee($event->event_code);
    if ($status !== 200) {
        $response->assertDontSee($event->name);
    }
})->with([['current', 200], ['password', 401], ['membership', 403]]);

it('checks current authority and password sessions on real HTTP completion', function (string $change, int $status) {
    $host = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $host->id, 'status' => 'REVEALED', 'event_date' => null]);
    $page = $this->actingAs($host)->get(route('amigo-secreto.show', $event))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'amigo-secreto.event-detail');
    $sessionHash = session('password_hash_web');
    if ($change === 'password') {
        $host->update(['password' => 'rotated-test-password']);
    } elseif ($change === 'organizer') {
        $event->update(['organizer_id' => User::factory()->create()->id]);
    } elseif ($change === 'archive') {
        $event->update(['is_archived' => true]);
    }
    $this->actingAs($host->fresh())->withSession(['password_hash_web' => $sessionHash])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'complete', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertStatus($status);
    expect($event->fresh()->status)->toBe($status === 200 ? 'COMPLETED' : 'REVEALED');
})->with([['current', 200], ['password', 401], ['organizer', 403], ['archive', 403]]);

it('rejects archive after current authority or lifecycle changes at the final write', function (string $change) {
    $host = User::factory()->create();
    $other = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $host->id, 'status' => 'COMPLETED']);
    $component = Livewire::actingAs($host)->test(EventDetail::class, ['event' => $event]);
    $interleaved = false;
    DB::connection()->beforeExecuting(function (string $sql) use ($event, $other, $change, &$interleaved): void {
        if (! $interleaved && str_starts_with($sql, 'update "amigo_secreto_events" set ')) {
            $interleaved = true;
            $event->update($change === 'organizer' ? ['organizer_id' => $other->id] : ['status' => 'REVEALED']);
        }
    });
    $component->call('archive')->assertForbidden();
    expect($interleaved)->toBeTrue();
    expect($event->fresh()->is_archived)->toBeFalse();
})->with(['organizer', 'status']);

it('does not let participants archive or organizers archive unfinished events', function (bool $host) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $host ? $user->id : User::factory(), 'status' => $host ? 'REVEALED' : 'COMPLETED',
    ]);
    if (! $host) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    Livewire::actingAs($user)->test(EventDetail::class, ['event' => $event])->call('archive')->assertForbidden();
    expect($event->fresh()->is_archived)->toBeFalse();
})->with([false, true]);

it('keeps lifecycle catalogs complete in both supported locales', function () {
    $en = require lang_path('en/event_history.php');
    $pt = require lang_path('pt_BR/event_history.php');
    expect(array_keys($en))->toBe(array_keys($pt));
    foreach (['en' => $en, 'pt_BR' => $pt] as $locale => $catalog) {
        app()->setLocale($locale);
        foreach ($catalog as $key => $copy) {
            expect($copy)->toBeString()->not->toBe('');
            expect(__('event_history.'.$key))->toBe($copy);
        }
    }
});
