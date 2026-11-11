<?php

use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Livewire\Family\FamilyRoster;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\Notification;
use App\Models\User;
use App\Services\FamilyInvitationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('persists a generic family invitation notice for the registered recipient without granting access', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $recipient = User::factory()->create(['email' => 'Invitee@Example.com']);
    $family = Family::factory()->create(['creator_id' => $admin->id, 'name' => 'Private family sentinel']);

    Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family])
        ->set('inviteEmail', 'invitee@example.com')->call('inviteByEmail')->assertHasNoErrors();

    expect(Notification::count())->toBe(1);
    $notice = Notification::sole();
    expect($notice->user_id)->toBe($recipient->id)
        ->and($notice->kind)->toBe('family_invitation')
        ->and($notice->type)->toBe('family')
        ->and($notice->is_read)->toBeFalse()
        ->and($notice->action_url)->toBeNull()
        ->and($notice->related_entity_type)->toBe(FamilyInvitation::class)
        ->and($notice->related_entity_id)->toBe(FamilyInvitation::sole()->id)
        ->and($notice->title.' '.$notice->message)->not->toContain($family->name, $family->invite_code, 'signature=', 'generation=');
    $this->assertDatabaseCount('family_members', 0);
    $this->actingAs($recipient)->get(route('families.show', $family))->assertForbidden();
});

it('deduplicates a family generation and rolls back its notice with the invitation', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $recipient = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $service = app(FamilyInvitationService::class);
    $service->invite($family, $admin, $recipient->email);
    $invitation = FamilyInvitation::sole();
    NotificationService::familyInvited($invitation);
    expect(Notification::count())->toBe(1);
    $service->cancel($family, $admin, $invitation->id);
    $service->invite($family, $admin, $recipient->email);
    expect(Notification::count())->toBe(2);

    $another = User::factory()->create();
    DB::beginTransaction();
    $service->invite($family, $admin, $another->email);
    expect(Notification::count())->toBe(3);
    DB::rollBack();
    expect(Notification::count())->toBe(2)
        ->and(FamilyInvitation::where('email', $another->email)->exists())->toBeFalse();
});

it('persists a generic event invitation without leaking the join capability', function () {
    Queue::fake();
    $organizer = User::factory()->create();
    $recipient = User::factory()->create(['email' => 'Guest@Example.com', 'locale' => 'pt_BR']);
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Private event sentinel']);
    Livewire::actingAs($organizer)->test(ManageParticipants::class, ['event' => $event])
        ->set('inviteEmail', 'guest@example.com')->call('inviteByEmail')->assertHasNoErrors();

    expect(Notification::count())->toBe(1);
    $notice = Notification::sole();
    expect($notice->user_id)->toBe($recipient->id)
        ->and($notice->kind)->toBe('event_invitation')
        ->and($notice->related_entity_type)->toBe(AmigoSecretoParticipant::class)
        ->and($notice->action_url)->toBeNull()
        ->and($notice->title)->toBe(__('inbox.event_invitation_title', [], 'pt_BR'))
        ->and($notice->title.' '.$notice->message)->not->toContain($event->name, $event->event_code);
    $this->actingAs($recipient)->get(route('amigo-secreto.show', $event))->assertForbidden();
});
