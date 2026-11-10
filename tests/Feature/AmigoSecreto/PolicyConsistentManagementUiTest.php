<?php

use App\Livewire\AmigoSecreto\ManageExclusions;
use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('shows participant and exclusion mutation controls while the organizer may update an event', function () {
    $organizer = User::factory()->create();
    $first = User::factory()->create(['name' => 'First Participant']);
    $second = User::factory()->create(['name' => 'Second Participant']);
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

    foreach ([$organizer, $first, $second] as $user) {
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'ACCEPTED',
        ]);
    }
    $exclusion = AmigoSecretoExclusion::create([
        'event_id' => $event->id,
        'user_a_id' => $first->id,
        'user_b_id' => $second->id,
    ]);

    Livewire::actingAs($organizer)
        ->test(ManageParticipants::class, ['event' => $event])
        ->assertSee('First Participant')
        ->assertSee('wire:submit="inviteByEmail"', false)
        ->assertSee('wire:click="removeParticipant(', false);

    Livewire::actingAs($organizer)
        ->test(ManageExclusions::class, ['event' => $event])
        ->assertSee('First Participant')
        ->assertSee('wire:submit="addExclusion"', false)
        ->assertSee('wire:click="removeExclusion('.$exclusion->id.')"', false);
});

it('keeps participant and exclusion lists readable but hides mutation controls after a draw', function () {
    $organizer = User::factory()->create();
    $first = User::factory()->create(['name' => 'Drawn First']);
    $second = User::factory()->create(['name' => 'Drawn Second']);
    $event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => 'DRAWS_COMPLETE',
    ]);

    foreach ([$organizer, $first, $second] as $user) {
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'ACCEPTED',
        ]);
    }
    $exclusion = AmigoSecretoExclusion::create([
        'event_id' => $event->id,
        'user_a_id' => $first->id,
        'user_b_id' => $second->id,
    ]);

    Livewire::actingAs($organizer)
        ->test(ManageParticipants::class, ['event' => $event])
        ->assertSee('Drawn First')
        ->assertDontSee('wire:submit="inviteByEmail"', false)
        ->assertDontSee('wire:click="removeParticipant(', false);

    Livewire::actingAs($organizer)
        ->test(ManageExclusions::class, ['event' => $event])
        ->assertSee('Drawn First')
        ->assertSee('Drawn Second')
        ->assertDontSee('wire:submit="addExclusion"', false)
        ->assertDontSee('wire:click="removeExclusion(', false);

    Livewire::actingAs($organizer)
        ->test(ManageParticipants::class, ['event' => $event])
        ->call('removeParticipant', $event->participants()->where('user_id', $first->id)->sole()->id)
        ->assertForbidden();
});

it('keeps lists readable and refreshes them without mutation controls when an event is archived', function () {
    $organizer = User::factory()->create();
    $first = User::factory()->create(['name' => 'Archived First']);
    $second = User::factory()->create(['name' => 'Archived Second']);
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

    foreach ([$organizer, $first, $second] as $user) {
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'ACCEPTED',
        ]);
    }
    $exclusion = AmigoSecretoExclusion::create([
        'event_id' => $event->id,
        'user_a_id' => $first->id,
        'user_b_id' => $second->id,
    ]);

    $participants = Livewire::actingAs($organizer)->test(ManageParticipants::class, ['event' => $event]);
    $exclusions = Livewire::actingAs($organizer)->test(ManageExclusions::class, ['event' => $event]);
    $event->update(['is_archived' => true]);

    $participants->call('$refresh')
        ->assertSee('Archived First')
        ->assertDontSee('wire:submit="inviteByEmail"', false)
        ->assertDontSee('wire:click="removeParticipant(', false)
        ->call('inviteByEmail')
        ->assertForbidden();

    $exclusions->call('$refresh')
        ->assertSee('Archived First')
        ->assertSee('Archived Second')
        ->assertDontSee('wire:submit="addExclusion"', false)
        ->assertDontSee('wire:click="removeExclusion(', false)
        ->call('removeExclusion', $exclusion->id)
        ->assertForbidden();
});
