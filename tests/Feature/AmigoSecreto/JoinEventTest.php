<?php

use App\Livewire\AmigoSecreto\JoinEvent;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('joins a planning event and reuses a pending email invitation when present', function (bool $invited) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create();
    $otherEvent = AmigoSecretoEvent::factory()->create();
    $unrelatedInvite = AmigoSecretoParticipant::create([
        'event_id' => $otherEvent->id,
        'invite_email' => $user->email,
        'status' => 'INVITED',
    ]);
    $invite = $invited ? AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => $user->email,
        'status' => 'INVITED',
    ]) : null;

    Livewire::actingAs($user)->test(JoinEvent::class, ['code' => strtolower($event->event_code)])
        ->call('join')->assertHasNoErrors()->assertRedirect(route('amigo-secreto.show', $event));

    $participant = $event->participants()->sole();
    expect($participant->user_id)->toBe($user->id)
        ->and($participant->status)->toBe('ACCEPTED')
        ->and($participant->accepted_at)->not->toBeNull()
        ->and($unrelatedInvite->fresh()->user_id)->toBeNull();
    if ($invite) {
        expect($participant->id)->toBe($invite->id);
    }

    Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code])
        ->call('join')->assertRedirect(route('amigo-secreto.show', $event));
    expect($event->participants()->count())->toBe(1);
})->with(['new participant' => false, 'pending email invitation' => true]);
