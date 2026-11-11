<?php

use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;

it('automatically reveals only due active drawn events and is idempotent', function () {
    $this->travelTo(now()->startOfDay()->addHours(9));
    $due = AmigoSecretoEvent::factory()->create([
        'status' => 'DRAWS_COMPLETE', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
        'reveal_date' => today(),
    ]);
    $draw = AmigoSecretoDraw::create([
        'event_id' => $due->id, 'drawer_user_id' => User::factory()->create()->id,
        'target_user_id' => User::factory()->create()->id, 'draw_date' => now()->subDay(),
    ]);
    $future = AmigoSecretoEvent::factory()->create(['status' => 'DRAWS_COMPLETE', 'reveal_date' => today()->addDay()]);
    $archived = AmigoSecretoEvent::factory()->create(['status' => 'DRAWS_COMPLETE', 'reveal_date' => today(), 'is_archived' => true]);
    $planning = AmigoSecretoEvent::factory()->create(['status' => 'PLANNING', 'reveal_date' => today()]);
    $undated = AmigoSecretoEvent::factory()->create(['status' => 'DRAWS_COMPLETE', 'reveal_date' => null, 'event_date' => null]);
    $legacy = AmigoSecretoEvent::factory()->create(['status' => 'DRAWS_COMPLETE', 'reveal_date' => null, 'event_date' => today()->subDay()]);

    $this->artisan('secret-santa:reveal')->assertSuccessful();
    expect($due->fresh()->status)->toBe('REVEALED');
    expect($draw->fresh()->revealed_at)->not->toBeNull();
    $revealedAt = $draw->fresh()->revealed_at->toDateTimeString();
    expect($future->fresh()->status)->toBe('DRAWS_COMPLETE');
    expect($archived->fresh()->status)->toBe('DRAWS_COMPLETE');
    expect($planning->fresh()->status)->toBe('PLANNING');
    expect($undated->fresh()->status)->toBe('DRAWS_COMPLETE');
    expect($legacy->fresh()->status)->toBe('REVEALED');

    $this->travel(1)->hours();
    $this->artisan('secret-santa:reveal')->assertSuccessful();
    expect($draw->fresh()->revealed_at->toDateTimeString())->toBe($revealedAt);
});

it('schedules automatic reveals without overlap', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'secret-santa:reveal'));

    expect($scheduled)->not->toBeNull();
    expect($scheduled->expression)->toBe('* * * * *');
    expect($scheduled->withoutOverlapping)->toBeTrue();
});
