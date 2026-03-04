<?php

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

describe('amigo secreto events', function () {
    it('can create an event', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\AmigoSecreto\CreateEvent::class)
            ->set('name', 'Christmas 2026')
            ->set('event_type', 'CHRISTMAS')
            ->set('description', 'Holiday gift exchange')
            ->call('create')
            ->assertRedirect();

        $this->assertDatabaseHas('amigo_secreto_events', [
            'organizer_id' => $user->id,
            'name' => 'Christmas 2026',
            'event_type' => 'CHRISTMAS',
            'status' => 'PLANNING',
        ]);
    });

    it('organizer is auto-added as participant', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\AmigoSecreto\CreateEvent::class)
            ->set('name', 'Birthday Party')
            ->set('event_type', 'BIRTHDAY')
            ->call('create');

        $event = AmigoSecretoEvent::where('organizer_id', $user->id)->first();

        $this->assertDatabaseHas('amigo_secreto_participants', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'ACCEPTED',
        ]);
    });

    it('can invite participant by email', function () {
        $organizer = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ManageParticipants::class, ['event' => $event])
            ->set('inviteEmail', 'friend@example.com')
            ->call('inviteByEmail');

        $this->assertDatabaseHas('amigo_secreto_participants', [
            'event_id' => $event->id,
            'invite_email' => 'friend@example.com',
            'status' => 'INVITED',
        ]);
    });

    it('can add exclusion pair', function () {
        $organizer = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userA->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userB->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ManageExclusions::class, ['event' => $event])
            ->set('userAId', $userA->id)
            ->set('userBId', $userB->id)
            ->call('addExclusion');

        $this->assertDatabaseHas('amigo_secreto_exclusions', [
            'event_id' => $event->id,
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
        ]);
    });

    it('can remove exclusion', function () {
        $organizer = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        $exclusion = AmigoSecretoExclusion::create([
            'event_id' => $event->id,
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
        ]);

        Livewire::actingAs($organizer)
            ->test(\App\Livewire\AmigoSecreto\ManageExclusions::class, ['event' => $event])
            ->call('removeExclusion', $exclusion->id);

        $this->assertDatabaseMissing('amigo_secreto_exclusions', [
            'id' => $exclusion->id,
        ]);
    });
});
