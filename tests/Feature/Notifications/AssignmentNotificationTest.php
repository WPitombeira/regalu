<?php

use App\Livewire\AmigoSecreto\ExecuteDraw;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('notifies only accepted drawers once without persisting their recipient identity', function () {
    Queue::fake();
    $organizer = User::factory()->create();
    $buyers = User::factory()->count(3)->create();
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $organizer->id, 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
        'reveal_date' => today()->addWeek(),
    ]);
    foreach ($buyers as $buyer) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $buyer->id, 'status' => 'ACCEPTED']);
    }
    AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $organizer->id, 'status' => 'INVITED']);

    $component = Livewire::actingAs($organizer)->test(ExecuteDraw::class, ['event' => $event]);
    $component->call('executeDraw');
    expect($event->fresh()->status)->toBe('DRAWS_COMPLETE')
        ->and(Notification::count())->toBe(3);
    foreach (Notification::all() as $notice) {
        expect($buyers->pluck('id')->all())->toContain($notice->user_id);
        expect($notice->kind)->toBe('assignment_available')
            ->and($notice->related_entity_type)->toBe(AmigoSecretoEvent::class)
            ->and($notice->related_entity_id)->toBe($event->id)
            ->and($notice->action_url)->toBeNull();
        foreach ($buyers as $buyer) {
            expect($notice->title.' '.$notice->message)->not->toContain($buyer->name, $buyer->email);
        }
    }
    $component->call('executeDraw');
    expect(Notification::count())->toBe(3);
});

it('records one private reveal notice per eligible buyer only when a reveal becomes due', function () {
    $this->travelTo(now()->startOfDay());
    $event = AmigoSecretoEvent::factory()->create([
        'status' => 'DRAWS_COMPLETE', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
        'reveal_date' => today()->addDay(),
    ]);
    $buyer = User::factory()->create();
    $recipient = User::factory()->create(['name' => 'Hidden recipient sentinel']);
    foreach ([$buyer, $recipient] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $buyer->id, 'target_user_id' => $recipient->id, 'draw_date' => now()]);
    $this->artisan('secret-santa:reveal')->assertSuccessful();
    expect(Notification::count())->toBe(0);
    $this->travel(1)->days();
    $this->artisan('secret-santa:reveal')->assertSuccessful();
    expect(Notification::count())->toBe(1);
    $notice = Notification::sole();
    expect($notice->user_id)->toBe($buyer->id)
        ->and($notice->kind)->toBe('assignment_revealed')
        ->and($notice->related_entity_type)->toBe(AmigoSecretoEvent::class)
        ->and($notice->title.' '.$notice->message)->not->toContain($recipient->name, $recipient->email);
    $this->artisan('secret-santa:reveal')->assertSuccessful();
    expect(Notification::count())->toBe(1);
});

it('skips revoked drawers without preventing other due assignments from being revealed', function () {
    $event = AmigoSecretoEvent::factory()->create([
        'status' => 'DRAWS_COMPLETE', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
        'reveal_date' => today(),
    ]);
    $revoked = User::factory()->create();
    $buyer = User::factory()->create();
    $recipient = User::factory()->create();
    foreach ([$buyer, $recipient] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    foreach ([$revoked, $buyer] as $user) {
        AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $user->id, 'target_user_id' => $recipient->id, 'draw_date' => now()]);
    }

    $this->artisan('secret-santa:reveal')->assertSuccessful();

    expect($event->fresh()->status)->toBe('REVEALED')
        ->and(Notification::sole()->user_id)->toBe($buyer->id);
});
