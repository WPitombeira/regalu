<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Models\AmigoSecretoEvent;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Livewire\Livewire;

it('offers only active family memberships when creating an event', function () {
    $member = User::factory()->create();
    $active = Family::factory()->create();
    $archived = Family::factory()->create(['is_archived' => true]);
    $other = Family::factory()->create();
    foreach ([$active, $archived] as $family) {
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
    }

    Livewire::actingAs($member)->test(CreateEvent::class)
        ->assertViewHas('families', fn ($families) => $families->modelKeys() === [$active->id])
        ->assertSee($active->name)->assertDontSee($archived->name)->assertDontSee($other->name);
});

it('rechecks the selected family archive state when an ordinary member submits', function (bool $archiveBeforeSubmit) {
    $member = User::factory()->create();
    $family = Family::factory()->create();
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
    $component = Livewire::actingAs($member)->test(CreateEvent::class)
        ->set('name', 'Family gift exchange')->set('family_id', $family->id);

    if ($archiveBeforeSubmit) {
        $family->update(['is_archived' => true]);
        $component->call('create')->assertForbidden()->assertNotDispatched('notification');
        $this->assertDatabaseCount('amigo_secreto_events', 0);
        $this->assertDatabaseCount('amigo_secreto_participants', 0);
    } else {
        $component->call('create')->assertRedirect();
        $event = AmigoSecretoEvent::sole();
        expect($event->family_id)->toBe($family->id)->and($event->organizer_id)->toBe($member->id);
        $this->assertDatabaseHas('amigo_secreto_participants', [
            'event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED',
        ]);
    }
})->with(['archived in another tab' => true, 'still active' => false]);
