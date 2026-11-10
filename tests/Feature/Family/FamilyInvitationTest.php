<?php

use App\Jobs\SendFamilyInvitation;
use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

it('lets an admin queue an email invitation without creating a membership', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);

    Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family])
        ->set('inviteEmail', '  Invitee@Example.com  ')
        ->call('inviteByEmail')
        ->assertHasNoErrors()
        ->assertSet('inviteEmail', '');

    $invitation = FamilyInvitation::sole();
    expect($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->family_id)->toBe($family->id)
        ->and($invitation->inviter_id)->toBe($admin->id)
        ->and($invitation->status)->toBe('PENDING')
        ->and($invitation->expires_at->isFuture())->toBeTrue();
    $this->assertDatabaseCount('family_members', 0);
    Queue::assertPushed(SendFamilyInvitation::class, fn ($job) => $job->invitationId === $invitation->id
        && $job->generation === $invitation->generation && $job->afterCommit);
    Queue::assertPushed(SendFamilyInvitation::class, 1);
});

it('rejects repeat invitations and emails already in the family', function (string $existing) {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    if ($existing === 'pending') {
        $panel->set('inviteEmail', 'member@example.com')->call('inviteByEmail')->assertHasNoErrors();
    } else {
        $member = User::factory()->create(['email' => 'Member@Example.com']);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
    }
    $panel->set('inviteEmail', ' MEMBER@example.com ')->call('inviteByEmail')->assertHasErrors('inviteEmail');
    Queue::assertPushed(SendFamilyInvitation::class, $existing === 'pending' ? 1 : 0);
    expect(FamilyInvitation::count())->toBe($existing === 'pending' ? 1 : 0);
})->with(['pending', 'member']);

it('denies invitation sends after authority or family activity is revoked', function (string $change) {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create();
    $membership = FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family])->set('inviteEmail', 'new@example.com');
    match ($change) {
        'archived' => $family->update(['is_archived' => true]),
        'demoted' => $membership->update(['role' => 'MEMBER']),
        'removed' => $membership->delete(),
    };
    $panel->call('inviteByEmail')->assertForbidden();
    Queue::assertNothingPushed();
    $this->assertDatabaseCount('family_invitations', 0);
})->with(['archived', 'demoted', 'removed']);

it('lets an admin cancel and reissue without reviving the old link or queued job', function () {
    Queue::fake();
    Mail::fake();
    $admin = User::factory()->create();
    $recipient = User::factory()->create(['email' => 'reissue@example.com']);
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    $panel->set('inviteEmail', $recipient->email)->call('inviteByEmail')->assertHasNoErrors();
    $old = FamilyInvitation::sole();
    $url = URL::temporarySignedRoute('family-invitations.show', $old->expires_at, ['invitation' => $old->id, 'generation' => $old->generation]);
    $panel->call('cancelInvitation', $old->id)->assertHasNoErrors();
    expect($old->fresh()->status)->toBe('CANCELLED');
    $panel->set('inviteEmail', $recipient->email)->call('inviteByEmail')->assertHasNoErrors();
    $new = FamilyInvitation::sole();
    expect($new->generation)->not->toBe($old->generation)
        ->and($new->status)->toBe('PENDING')->and($new->responded_at)->toBeNull();
    (new SendFamilyInvitation($old->id, $old->generation))->handle();
    Mail::assertNothingSent();
    Queue::assertPushed(SendFamilyInvitation::class, 2);
    $this->actingAs($recipient)->post($url, ['decision' => 'accept'])->assertForbidden();
    $this->assertDatabaseCount('family_members', 0);
});

it('allows a fresh invitation after expiry or decline', function (string $state) {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    $panel->set('inviteEmail', 'retry@example.com')->call('inviteByEmail');
    $old = FamilyInvitation::sole();
    $old->update($state === 'expired' ? ['expires_at' => now()->subSecond()] : ['status' => 'DECLINED', 'responded_at' => now()]);
    $panel->set('inviteEmail', 'retry@example.com')->call('inviteByEmail')->assertHasNoErrors();
    expect(FamilyInvitation::sole()->generation)->not->toBe($old->generation);
    Queue::assertPushed(SendFamilyInvitation::class, 2);
})->with(['expired', 'declined']);

it('bounds invitation sends per admin across families and releases the budget after an hour', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    foreach (range(1, 20) as $number) {
        $panel->set('inviteEmail', "person{$number}@example.com")->call('inviteByEmail')->assertHasNoErrors();
    }
    $other = Family::factory()->create(['creator_id' => $admin->id]);
    $otherPanel = Livewire::test(FamilyRoster::class, ['family' => $other]);
    $otherPanel->set('inviteEmail', 'overflow@example.com')->call('inviteByEmail')->assertHasErrors('inviteEmail');
    Queue::assertPushed(SendFamilyInvitation::class, 20);
    $this->travel(61)->minutes();
    $otherPanel->set('inviteEmail', 'after@example.com')->call('inviteByEmail')->assertHasNoErrors();
    Queue::assertPushed(SendFamilyInvitation::class, 21);
});
