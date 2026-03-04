<?php

use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

describe('amigo secreto draw', function () {
    it('can execute draw with valid participants', function () {
        $organizer = User::factory()->create();
        $users = User::factory()->count(3)->create();

        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'PLANNING',
        ]);

        // Add organizer and 3 users as accepted participants
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $organizer->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        foreach ($users as $user) {
            AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => 'ACCEPTED',
                'accepted_at' => now(),
            ]);
        }

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ExecuteDraw::class, ['event' => $event])
            ->call('executeDraw')
            ->assertDispatched('notification');

        $event->refresh();
        expect($event->status)->toBe('DRAWS_COMPLETE');

        $drawCount = AmigoSecretoDraw::where('event_id', $event->id)->count();
        expect($drawCount)->toBe(4); // organizer + 3 users

        // Verify no one drew themselves
        $draws = AmigoSecretoDraw::where('event_id', $event->id)->get();
        foreach ($draws as $draw) {
            expect($draw->drawer_user_id)->not->toBe($draw->target_user_id);
        }
    });

    it('draw respects exclusions', function () {
        $organizer = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'PLANNING',
        ]);

        $allUsers = [$organizer, $userA, $userB, $userC];
        foreach ($allUsers as $user) {
            AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => 'ACCEPTED',
                'accepted_at' => now(),
            ]);
        }

        // Create exclusion: userA and userB cannot be matched
        AmigoSecretoExclusion::create([
            'event_id' => $event->id,
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
        ]);

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ExecuteDraw::class, ['event' => $event])
            ->call('executeDraw')
            ->assertDispatched('notification');

        // Verify the exclusion is respected
        $draws = AmigoSecretoDraw::where('event_id', $event->id)->get();

        $aDrewB = $draws->where('drawer_user_id', $userA->id)->where('target_user_id', $userB->id)->isNotEmpty();
        $bDrewA = $draws->where('drawer_user_id', $userB->id)->where('target_user_id', $userA->id)->isNotEmpty();

        expect($aDrewB)->toBeFalse();
        expect($bDrewA)->toBeFalse();
    });

    it('handles impossible draw gracefully', function () {
        $organizer = User::factory()->create();
        $userA = User::factory()->create();

        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'PLANNING',
        ]);

        // Only 2 participants with mutual exclusion = impossible
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $organizer->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userA->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        AmigoSecretoExclusion::create([
            'event_id' => $event->id,
            'user_a_id' => $organizer->id,
            'user_b_id' => $userA->id,
        ]);

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ExecuteDraw::class, ['event' => $event])
            ->call('executeDraw')
            ->assertDispatched('notification');

        $event->refresh();
        expect($event->status)->toBe('PLANNING'); // Status should not change

        $drawCount = AmigoSecretoDraw::where('event_id', $event->id)->count();
        expect($drawCount)->toBe(0);
    });

    it('cannot draw twice', function () {
        $organizer = User::factory()->create();
        $users = User::factory()->count(2)->create();

        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'DRAWS_COMPLETE',
        ]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $organizer->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        foreach ($users as $user) {
            AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => 'ACCEPTED',
                'accepted_at' => now(),
            ]);
        }

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ExecuteDraw::class, ['event' => $event])
            ->call('executeDraw')
            ->assertDispatched('notification');

        // No draws should have been created since status was already DRAWS_COMPLETE
        $drawCount = AmigoSecretoDraw::where('event_id', $event->id)->count();
        expect($drawCount)->toBe(0);
    });
});
