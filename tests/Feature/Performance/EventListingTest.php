<?php

use App\Livewire\AmigoSecreto\EventIndex;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('bounds event listings and counts participants without per-event queries', function () {
    $user = User::factory()->create();
    $events = AmigoSecretoEvent::factory()->count(16)->create(['organizer_id' => $user->id]);
    foreach ($events as $event) {
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'ACCEPTED',
        ]);
    }

    DB::enableQueryLog();
    $component = Livewire::actingAs($user)->test(EventIndex::class)
        ->assertViewHas('events', fn ($page) => $page->count() === 12
            && $page->first()->id === $events->last()->id
            && $page->first()->participants_count === 1);
    $participantReads = collect(DB::getQueryLog())->filter(
        fn (array $query) => str_starts_with($query['query'], 'select * from "amigo_secreto_participants"')
    );
    DB::disableQueryLog();
    expect($participantReads)->toHaveCount(0);

    $component->call('nextPage')->assertViewHas('events', fn ($page) => $page->count() === 4);
});

it('excludes archived events for both organizers and participants', function () {
    $user = User::factory()->create();
    $archived = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'is_archived' => true]);
    $accepted = AmigoSecretoEvent::factory()->create();
    $invited = AmigoSecretoEvent::factory()->create();

    AmigoSecretoParticipant::create(['event_id' => $accepted->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    AmigoSecretoParticipant::create(['event_id' => $invited->id, 'user_id' => $user->id, 'status' => 'INVITED']);

    Livewire::actingAs($user)->test(EventIndex::class)
        ->assertSee($accepted->name)
        ->assertDontSee($archived->name)
        ->assertDontSee($invited->name);
});
