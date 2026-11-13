<?php

use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->recipient = User::factory()->create(['email' => 'Recipient@Example.com']);
    $this->family = Family::factory()->create();
    $this->invitation = FamilyInvitation::create([
        'family_id' => $this->family->id,
        'inviter_id' => $this->family->creator_id,
        'email' => 'recipient@example.com',
        'generation' => (string) Str::uuid(),
        'status' => 'PENDING',
        'expires_at' => now()->addDays(7),
    ]);
    $this->invitationUrl = URL::temporarySignedRoute('family-invitations.show', $this->invitation->expires_at, [
        'invitation' => $this->invitation->id, 'generation' => $this->invitation->generation,
    ]);
});

it('requires an explicit recipient decision and never joins on GET', function (string $decision, string $status) {
    $this->actingAs($this->recipient)->get($this->invitationUrl)->assertOk()
        ->assertSee('Accept invitation')->assertSee('Decline invitation');
    $this->assertDatabaseCount('family_members', 0);
    expect($this->invitation->fresh()->status)->toBe('PENDING');

    $this->post($this->invitationUrl, ['decision' => $decision])->assertRedirect();
    expect($this->invitation->fresh()->status)->toBe($status)
        ->and($this->invitation->fresh()->responded_at)->not->toBeNull();
    if ($decision === 'accept') {
        $member = FamilyMember::sole();
        expect($member->user_id)->toBe($this->recipient->id)
            ->and($member->family_id)->toBe($this->family->id)
            ->and($member->role)->toBe('MEMBER');
    } else {
        $this->assertDatabaseCount('family_members', 0);
    }
})->with([['accept', 'ACCEPTED'], ['decline', 'DECLINED']]);

it('requires the signed email link and the matching authenticated recipient', function (string $invalid) {
    $user = $invalid === 'wrong-user' ? User::factory()->create() : $this->recipient;
    $url = $invalid === 'unsigned' ? route('family-invitations.show', $this->invitation) : $this->invitationUrl;
    $this->actingAs($user)->get($url)->assertForbidden()->assertDontSee($this->family->name);
    $this->post($url, ['decision' => 'accept'])->assertForbidden();
    $this->assertDatabaseCount('family_members', 0);
    expect($this->invitation->fresh()->status)->toBe('PENDING');
})->with(['wrong-user', 'unsigned']);

it('rejects stale invitation authority on both reads and decisions', function (string $change) {
    match ($change) {
        'record-expired' => $this->invitation->update(['expires_at' => now()->subSecond()]),
        'family-archived' => $this->family->update(['is_archived' => true]),
        'sender-removed' => $this->invitation->update(['inviter_id' => User::factory()->create()->id]),
        'generation-replaced' => $this->invitation->update(['generation' => (string) Str::uuid()]),
        'email-changed' => $this->recipient->update(['email' => 'changed@example.com']),
        'ambiguous-account' => User::factory()->create(['email' => 'recipient@example.com']),
        'link-expired' => $this->travel(8)->days(),
    };
    $this->actingAs($this->recipient)->get($this->invitationUrl)->assertForbidden()->assertDontSee($this->family->name);
    $this->post($this->invitationUrl, ['decision' => 'accept'])->assertForbidden();
    $this->assertDatabaseCount('family_members', 0);
    expect($this->invitation->fresh()->status)->toBe('PENDING');
})->with(['record-expired', 'family-archived', 'sender-removed', 'generation-replaced', 'email-changed', 'ambiguous-account', 'link-expired']);

it('never replays a terminal invitation or changes existing membership roles', function (string $decision) {
    FamilyMember::create(['family_id' => $this->family->id, 'user_id' => $this->recipient->id, 'role' => 'ADMIN']);
    $this->actingAs($this->recipient)->post($this->invitationUrl, ['decision' => $decision])->assertRedirect();
    $this->post($this->invitationUrl, ['decision' => 'accept'])->assertConflict();
    expect(FamilyMember::sole()->role)->toBe('ADMIN');
    $this->get($this->invitationUrl)->assertOk()->assertDontSee('name="decision"', false);
})->with(['accept', 'decline']);

it('returns not found for nonnumeric invitation identifiers', function () {
    $this->actingAs($this->recipient)->get('/family-invitations/not-an-id')->assertNotFound();
    $this->post('/family-invitations/not-an-id', ['decision' => 'accept'])->assertNotFound();
});

it('hides cancelled invitations and rejects their decisions', function () {
    $this->invitation->update(['status' => 'CANCELLED', 'responded_at' => now()]);
    $this->actingAs($this->recipient)->get($this->invitationUrl)
        ->assertForbidden()->assertDontSee($this->family->name);
    $this->post($this->invitationUrl, ['decision' => 'accept'])->assertForbidden();
    $this->assertDatabaseCount('family_members', 0);
    expect($this->invitation->fresh()->status)->toBe('CANCELLED');
});
