<?php

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('enforces current session and event eligibility through the signed join snapshot', function (string $state, bool $invited) {
    $user = User::factory()->create(['email' => 'guest@example.test', 'password' => 'original-fixture-password']);
    $event = AmigoSecretoEvent::factory()->create();
    $invite = $invited ? AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => ' Guest@Example.Test ', 'status' => 'INVITED',
    ]) : null;
    $before = $invite?->fresh()->getAttributes();
    $page = $this->actingAs($user)->get(route('amigo-secreto.public-join', $event->event_code))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshots = array_filter(array_map(fn (string $encoded) => html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches[1]),
        fn (string $snapshot) => json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'amigo-secreto.join-event');
    expect($snapshots)->toHaveCount(1);
    $sessionHash = session('password_hash_web');
    expect($sessionHash)->toBeString()->not->toBeEmpty();

    if ($state === 'revoked') {
        $user->update(['password' => 'replacement-fixture-password']);
    } elseif ($state === 'closed') {
        $event->update(['status' => 'COMPLETED']);
    }

    $response = $this->actingAs($user->fresh())->withSession(['password_hash_web' => $sessionHash])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => array_values($snapshots)[0], 'updates' => [],
            'calls' => [['path' => '', 'method' => 'join', 'params' => []]],
        ]]], ['X-Livewire' => 'true']);

    if ($state === 'current') {
        $response->assertOk()->assertJsonPath('components.0.effects.redirect', route('amigo-secreto.show', $event));
        $participant = $event->participants()->sole();
        expect($participant->user_id)->toBe($user->id)->and($participant->status)->toBe('ACCEPTED')
            ->and($participant->accepted_at)->not->toBeNull();
        if ($invite !== null) {
            expect($participant->id)->toBe($invite->id);
        }
    } else {
        $response->assertStatus($state === 'revoked' ? 401 : 403);
        expect($event->participants()->where('user_id', $user->id)->exists())->toBeFalse()
            ->and($invite?->fresh()->getAttributes())->toBe($before);
        if ($state === 'revoked') {
            $this->assertGuest();
        }
    }
})->with(['current', 'revoked', 'closed'])->with(['code' => false, 'invitation' => true]);
