<?php

use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('rechecks membership authorization in the final insert', function (string $revocation) {
    $family = Family::factory()->create();
    $recipient = User::factory()->create(['email' => 'race@example.com']);
    $invitation = FamilyInvitation::create([
        'family_id' => $family->id, 'inviter_id' => $family->creator_id,
        'email' => $recipient->email, 'generation' => (string) Str::uuid(),
        'status' => 'PENDING', 'expires_at' => now()->addWeek(),
    ]);
    $url = URL::temporarySignedRoute('family-invitations.show', $invitation->expires_at, ['invitation' => $invitation->id, 'generation' => $invitation->generation]);
    $fired = false;
    DB::connection()->beforeExecuting(function (string $sql, array $bindings, Connection $connection) use (&$fired, $family, $recipient, $invitation, $revocation): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'insert') || ! str_contains($sql, '"family_members"')) {
            return;
        }
        $fired = true;
        match ($revocation) {
            'archive' => $family->update(['is_archived' => true]),
            'sender' => $invitation->fresh()->update(['inviter_id' => $recipient->id]),
            'expiry' => $invitation->fresh()->update(['expires_at' => now()->subSecond()]),
            'cancel' => $invitation->fresh()->update(['status' => 'CANCELLED']),
            'generation' => $invitation->fresh()->update(['generation' => (string) Str::uuid()]),
            'email' => $recipient->update(['email' => 'changed@example.com']),
            'collision' => User::factory()->create(['email' => 'RACE@example.com']),
        };
    });
    $this->actingAs($recipient)->post($url, ['decision' => 'accept'])->assertConflict();
    expect($fired)->toBeTrue();
    $this->assertDatabaseCount('family_members', 0);
    expect($invitation->fresh()->status)->toBe('PENDING');
})->with(['archive', 'sender', 'expiry', 'cancel', 'generation', 'email', 'collision']);

it('rechecks the sending admin at the invitation write', function (string $change) {
    Queue::fake();
    $family = Family::factory()->create();
    $admin = User::factory()->create();
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family])->set('inviteEmail', 'race@example.com');
    $fired = false;
    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, $family, $member, $change): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'insert') || ! str_contains($sql, '"family_invitations"')) {
            return;
        }
        $fired = true;
        $change === 'archive' ? $family->update(['is_archived' => true]) : $member->update(['role' => 'MEMBER']);
    });
    $panel->call('inviteByEmail')->assertForbidden();
    expect($fired)->toBeTrue();
    $this->assertDatabaseCount('family_invitations', 0);
    Queue::assertNothingPushed();
})->with(['archive', 'demotion']);
