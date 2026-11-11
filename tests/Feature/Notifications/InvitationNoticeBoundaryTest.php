<?php

use App\Livewire\Dashboard;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\Notification;
use App\Models\User;
use App\Services\FamilyInvitationService;
use App\Services\NotificationInbox;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('withdraws family invitation hints when their current authority is lost', function (string $change) {
    Queue::fake();
    $recipient = User::factory()->create(['email' => 'invitee@example.test']);
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    app(FamilyInvitationService::class)->invite($family, $admin, $recipient->email);
    $invitation = FamilyInvitation::sole();
    $notice = Notification::sole();
    $dashboard = Livewire::actingAs($recipient)->test(Dashboard::class)->assertViewHas('familyInvitationCount', 1);
    expect(app(NotificationInbox::class)->recentFor($recipient)->sole()->available)->toBeTrue();

    match ($change) {
        'expiry' => $invitation->update(['expires_at' => now()->subMinute()]),
        'cancel' => $invitation->update(['status' => 'CANCELLED']),
        'archive' => $family->update(['is_archived' => true]),
        'inviter' => $family->update(['creator_id' => User::factory()->create()->id]),
        'email' => $recipient->update(['email' => 'changed@example.test']),
        'collision' => User::factory()->create(['email' => 'INVITEE@example.test']),
        'joined' => FamilyMember::create(['family_id' => $family->id, 'user_id' => $recipient->id, 'role' => 'MEMBER']),
        'delete' => $invitation->delete(),
    };
    $dashboard->call('$refresh')->assertViewHas('familyInvitationCount', 0);
    expect(app(NotificationInbox::class)->recentFor($recipient)->sole()->available)->toBeFalse()
        ->and(app(NotificationInbox::class)->open($recipient, $notice->id))->toBeNull();
})->with(['expiry', 'cancel', 'archive', 'inviter', 'email', 'collision', 'joined', 'delete']);

it('withdraws event invitation hints without granting event access', function (string $change) {
    $recipient = User::factory()->create(['email' => 'invitee@example.test']);
    $event = AmigoSecretoEvent::factory()->create();
    $participant = AmigoSecretoParticipant::create(['event_id' => $event->id, 'invite_email' => $recipient->email, 'status' => 'INVITED']);
    NotificationService::eventInvited($participant);
    $dashboard = Livewire::actingAs($recipient)->test(Dashboard::class)->assertViewHas('eventInvitationCount', 1);
    expect(app(NotificationInbox::class)->recentFor($recipient)->sole()->available)->toBeTrue();
    match ($change) {
        'closed' => $event->update(['status' => 'DRAWS_COMPLETE']),
        'archive' => $event->update(['is_archived' => true]),
        'withdrawn' => $participant->update(['status' => 'WITHDRAWN']),
        'email' => $recipient->update(['email' => 'changed@example.test']),
        'collision' => User::factory()->create(['email' => 'INVITEE@example.test']),
        'delete' => $participant->delete(),
    };
    $dashboard->call('$refresh')->assertViewHas('eventInvitationCount', 0);
    expect(app(NotificationInbox::class)->recentFor($recipient)->sole()->available)->toBeFalse();
    $this->actingAs($recipient)->get(route('amigo-secreto.show', $event))->assertForbidden();
})->with(['closed', 'archive', 'withdrawn', 'email', 'collision', 'delete']);

it('does not notify an absent or ambiguous registered email recipient', function (bool $ambiguous) {
    $event = AmigoSecretoEvent::factory()->create();
    $participant = AmigoSecretoParticipant::create(['event_id' => $event->id, 'invite_email' => 'invitee@example.test', 'status' => 'INVITED']);
    if ($ambiguous) {
        User::factory()->create(['email' => 'invitee@example.test']);
        User::factory()->create(['email' => 'INVITEE@example.test']);
    }
    NotificationService::eventInvited($participant);
    expect(Notification::count())->toBe(0);
})->with([false, true]);
