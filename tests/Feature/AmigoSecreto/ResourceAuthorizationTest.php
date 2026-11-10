<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Livewire\AmigoSecreto\EventDetail;
use App\Livewire\AmigoSecreto\ExecuteDraw;
use App\Livewire\AmigoSecreto\JoinEvent;
use App\Livewire\AmigoSecreto\ManageExclusions;
use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Livewire\Livewire;

describe('Secret Santa resource authorization', function () {
    it('allows accepted participants and organizers to view an event but denies outsiders', function () {
        $organizer = User::factory()->create();
        $participant = User::factory()->create();
        $outsider = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        $this->actingAs($organizer)->get(route('amigo-secreto.show', $event))->assertOk();
        $this->actingAs($participant)->get(route('amigo-secreto.show', $event))->assertOk();
        $this->actingAs($outsider)->get(route('amigo-secreto.show', $event))->assertForbidden();
        $this->app['auth']->logout();
        $this->get(route('amigo-secreto.show', $event))->assertRedirect(route('login'));
    });

    it('restricts participant management pages to the organizer', function () {
        $organizer = User::factory()->create();
        $participant = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'ACCEPTED',
        ]);

        $this->actingAs($organizer)->get(route('amigo-secreto.participants', $event))->assertOk();
        $this->actingAs($participant)->get(route('amigo-secreto.participants', $event))->assertForbidden();
    });

    it('rechecks organizer authority after an event is transferred', function () {
        $organizer = User::factory()->create();
        $newOrganizer = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

        $component = Livewire::actingAs($organizer)
            ->test(ManageParticipants::class, ['event' => $event])
            ->set('inviteEmail', 'friend@example.com');

        $event->update(['organizer_id' => $newOrganizer->id]);

        $component->call('inviteByEmail')->assertForbidden();
        $this->assertDatabaseMissing('amigo_secreto_participants', [
            'event_id' => $event->id,
            'invite_email' => 'friend@example.com',
        ]);
    });

    it('rechecks event visibility after a participant is removed', function () {
        $organizer = User::factory()->create();
        $participant = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);
        $membership = AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'ACCEPTED',
        ]);

        $component = Livewire::actingAs($participant)->test(EventDetail::class, ['event' => $event]);
        $membership->delete();

        $component->call('$refresh')->assertForbidden();
    });

    it('does not allow invitations after a draw has completed', function () {
        $organizer = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'DRAWS_COMPLETE',
        ]);

        Livewire::actingAs($organizer)
            ->test(ManageParticipants::class, ['event' => $event])
            ->set('inviteEmail', 'late@example.com')
            ->call('inviteByEmail')
            ->assertForbidden();

        $this->assertDatabaseMissing('amigo_secreto_participants', [
            'event_id' => $event->id,
            'invite_email' => 'late@example.com',
        ]);
    });

    it('does not accept new event participants after a draw has completed', function () {
        $user = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['status' => 'DRAWS_COMPLETE']);

        Livewire::actingAs($user)
            ->test(JoinEvent::class)
            ->set('eventCode', $event->event_code)
            ->call('join')
            ->assertForbidden();

        $this->assertDatabaseMissing('amigo_secreto_participants', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    });

    it('does not allow event mutations after archive', function () {
        $organizer = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

        $component = Livewire::actingAs($organizer)
            ->test(ManageParticipants::class, ['event' => $event])
            ->set('inviteEmail', 'archived@example.com');

        $event->update(['is_archived' => true]);

        $component->call('inviteByEmail')->assertForbidden();
        $this->assertDatabaseMissing('amigo_secreto_participants', [
            'event_id' => $event->id,
            'invite_email' => 'archived@example.com',
        ]);
    });

    it('does not let an event creator attach an event to a family they cannot access', function () {
        $creator = User::factory()->create();
        $otherOwner = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $otherOwner->id]);

        Livewire::actingAs($creator)
            ->test(CreateEvent::class)
            ->set('name', 'Cross-family event')
            ->set('family_id', $family->id)
            ->call('create')
            ->assertForbidden();

        $this->assertDatabaseMissing('amigo_secreto_events', [
            'organizer_id' => $creator->id,
            'name' => 'Cross-family event',
        ]);
    });

    it('allows a family member to attach a new event to that family', function () {
        $member = User::factory()->create();
        $family = Family::factory()->create();
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);

        Livewire::actingAs($member)
            ->test(CreateEvent::class)
            ->set('name', 'Family event')
            ->set('family_id', $family->id)
            ->call('create')
            ->assertRedirect();

        $this->assertDatabaseHas('amigo_secreto_events', [
            'organizer_id' => $member->id,
            'family_id' => $family->id,
            'name' => 'Family event',
        ]);
    });

    it('rejects an unauthenticated Livewire event join action', function () {
        $event = AmigoSecretoEvent::factory()->create();

        Livewire::test(JoinEvent::class)
            ->set('eventCode', $event->event_code)
            ->call('join')
            ->assertForbidden();

        $this->assertDatabaseMissing('amigo_secreto_participants', [
            'event_id' => $event->id,
            'user_id' => null,
        ]);
    });

    it('only permits exclusions between accepted participants of this event', function () {
        $organizer = User::factory()->create();
        $acceptedA = User::factory()->create();
        $acceptedB = User::factory()->create();
        $invited = User::factory()->create();
        $outsider = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

        foreach ([$acceptedA, $acceptedB] as $user) {
            AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => 'ACCEPTED',
            ]);
        }
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $invited->id,
            'status' => 'INVITED',
        ]);

        Livewire::actingAs($organizer)
            ->test(ManageExclusions::class, ['event' => $event])
            ->set('userAId', $acceptedA->id)
            ->set('userBId', $acceptedB->id)
            ->call('addExclusion')
            ->assertHasNoErrors();

        Livewire::actingAs($organizer)
            ->test(ManageExclusions::class, ['event' => $event])
            ->set('userAId', $acceptedA->id)
            ->set('userBId', $invited->id)
            ->call('addExclusion')
            ->assertHasErrors(['userBId']);

        Livewire::actingAs($organizer)
            ->test(ManageExclusions::class, ['event' => $event])
            ->set('userAId', $acceptedA->id)
            ->set('userBId', $outsider->id)
            ->call('addExclusion')
            ->assertHasErrors(['userBId']);

        $this->assertDatabaseCount('amigo_secreto_exclusions', 1);
    });

    it('does not expose other participants\' pairings through event detail or draw views', function () {
        $organizer = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();
        $event = AmigoSecretoEvent::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'DRAWS_COMPLETE',
        ]);

        foreach ([$organizer, $first, $second, $third] as $user) {
            AmigoSecretoParticipant::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => 'ACCEPTED',
            ]);
        }

        AmigoSecretoDraw::create([
            'event_id' => $event->id,
            'drawer_user_id' => $first->id,
            'target_user_id' => $second->id,
            'draw_date' => now(),
        ]);
        AmigoSecretoDraw::create([
            'event_id' => $event->id,
            'drawer_user_id' => $second->id,
            'target_user_id' => $third->id,
            'draw_date' => now(),
        ]);
        AmigoSecretoDraw::create([
            'event_id' => $event->id,
            'drawer_user_id' => $organizer->id,
            'target_user_id' => $first->id,
            'draw_date' => now(),
        ]);

        $detail = Livewire::actingAs($first)->test(EventDetail::class, ['event' => $event]);
        expect($detail->instance()->event->relationLoaded('draws'))->toBeFalse();
        $detail->assertSee($second->name)->assertDontSee($third->name);

        $drawPage = Livewire::actingAs($organizer)->test(ExecuteDraw::class, ['event' => $event]);
        expect($drawPage->instance()->event->relationLoaded('draws'))->toBeFalse();
        $drawPage->assertSee($first->name)->assertDontSee($third->name);
    });
});
