<?php

use App\Livewire\Dashboard;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\User;
use App\Services\FamilyInvitationService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('shows pending invitation counts but never turns an email match into a joining capability', function () {
    Queue::fake();
    $recipient = User::factory()->create(['email' => 'Invitee@Example.com']);
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id, 'name' => 'Private family sentinel']);
    app(FamilyInvitationService::class)->invite($family, $admin, 'invitee@example.com');
    $event = AmigoSecretoEvent::factory()->create(['name' => 'Private event sentinel']);
    $invite = AmigoSecretoParticipant::create(['event_id' => $event->id, 'invite_email' => 'invitee@example.com', 'status' => 'INVITED']);
    AmigoSecretoParticipant::create(['event_id' => $event->id, 'invite_email' => 'other@example.com', 'status' => 'INVITED']);

    $component = Livewire::actingAs($recipient)->test(Dashboard::class)
        ->assertViewHas('familyInvitationCount', 1)->assertViewHas('eventInvitationCount', 1)
        ->assertSee('Check your email')->assertDontSee($family->name)->assertDontSee($family->invite_code)
        ->assertDontSee($event->name)->assertDontSee($event->event_code)->assertDontSee('signature=')->assertDontSee('generation=');
    $this->assertDatabaseCount('family_members', 0);
    expect($invite->fresh()->status)->toBe('INVITED');

    FamilyInvitation::query()->update(['status' => 'CANCELLED']);
    $event->update(['status' => 'DRAWS_COMPLETE']);
    $component->call('$refresh')->assertViewHas('familyInvitationCount', 0)->assertViewHas('eventInvitationCount', 0);
});
