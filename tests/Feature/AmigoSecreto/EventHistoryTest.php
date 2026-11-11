<?php

use App\Livewire\AmigoSecreto\EventIndex;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('separates current events from authorized past events without exposing invitation data', function () {
    $user = User::factory()->create();
    $active = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id]);
    $completed = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'status' => 'COMPLETED']);
    $archived = AmigoSecretoEvent::factory()->create(['is_archived' => true]);
    $unrelated = AmigoSecretoEvent::factory()->create(['status' => 'COMPLETED']);
    AmigoSecretoParticipant::create(['event_id' => $archived->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    AmigoSecretoParticipant::create(['event_id' => $unrelated->id, 'user_id' => $user->id, 'status' => 'INVITED']);

    Livewire::actingAs($user)->test(EventIndex::class)
        ->assertSee($active->name)->assertDontSee($completed->name)->assertDontSee($archived->name);

    Livewire::withQueryParams(['period' => 'past'])->actingAs($user)->test(EventIndex::class)
        ->assertSee($completed->name)->assertSee($archived->name)
        ->assertDontSee($active->name)->assertDontSee($unrelated->name)
        ->assertDontSee($completed->event_code)->assertDontSee($archived->event_code)
        ->assertViewHas('events', function ($events) {
            foreach ($events as $event) {
                expect(array_keys($event->getAttributes()))->toEqualCanonicalizing([
                    'id', 'name', 'event_type', 'status', 'is_archived', 'event_date', 'created_at', 'participants_count',
                ]);
                expect($event->getRelations())->toBe([]);
            }

            return $events->count() === 2;
        });
});

it('serves past event metadata to the organizer and accepted participants only', function (string $role, bool $archived, int $expected) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $role === 'organizer' ? $user->id : User::factory(),
        'status' => 'COMPLETED', 'is_archived' => $archived,
        'description' => 'A shared celebration to remember.',
    ]);
    if (in_array($role, ['ACCEPTED', 'INVITED', 'DECLINED', 'WITHDRAWN'], true)) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => $role]);
    }

    $response = $this->actingAs($user)->get('/secret-santa/'.$event->id.'/history')->assertStatus($expected);
    if ($expected === 200) {
        $response->assertSee($event->name)->assertSee($event->description)
            ->assertDontSee($event->event_code)->assertDontSee('wire:click="complete"', false)
            ->assertDontSee('wire:click="archive"', false)->assertDontSee('saveWishlist', false);
    }
    if ($archived) {
        $this->get(route('amigo-secreto.show', $event))->assertForbidden();
    }
})->with([
    ['organizer', false, 200], ['organizer', true, 200],
    ['ACCEPTED', false, 200], ['ACCEPTED', true, 200],
    ['INVITED', true, 403], ['DECLINED', true, 403], ['WITHDRAWN', true, 403], ['outsider', true, 403],
]);

it('does not expose an active event through history', function () {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id]);
    $this->actingAs($user)->get('/secret-santa/'.$event->id.'/history')->assertForbidden();
});
