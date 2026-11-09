<?php

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoExclusion;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;

it('keeps archived management pages read-only for organizers through the real routes', function (string $page) {
    $organizer = User::factory()->create();
    $member = User::factory()->create(['name' => 'Archived participant']);
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id, 'is_archived' => true]);
    AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED']);
    AmigoSecretoExclusion::create(['event_id' => $event->id, 'user_a_id' => $organizer->id, 'user_b_id' => $member->id]);

    $this->actingAs($organizer)->get(route('amigo-secreto.'.$page, $event))
        ->assertOk()->assertSee('Archived participant')
        ->assertSee('href="'.route('amigo-secreto.index').'"', false)
        ->assertDontSee('wire:submit="inviteByEmail"', false)
        ->assertDontSee('wire:submit="addExclusion"', false)
        ->assertDontSee('wire:click="removeParticipant(', false)
        ->assertDontSee('wire:click="removeExclusion(', false);
    $this->actingAs($member)->get(route('amigo-secreto.'.$page, $event))->assertForbidden();
})->with(['participants', 'exclusions']);
