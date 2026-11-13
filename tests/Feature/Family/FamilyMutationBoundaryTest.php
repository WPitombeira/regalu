<?php

use App\Livewire\Family\FamilyRoster;
use App\Livewire\Family\FamilySettings;
use App\Livewire\Family\JoinFamily;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('refuses a code join when eligibility changes immediately before the membership insert', function (string $change) {
    $family = Family::factory()->create(['invite_code' => 'ABCDEFGHIJKL']);
    $user = User::factory()->create();
    $panel = Livewire::actingAs($user)->test(JoinFamily::class)->set('inviteCode', $family->invite_code);
    $fired = false;

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, $family, $user, $change): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'insert') || ! str_contains($sql, '"family_members"')) {
            return;
        }
        $fired = true;
        match ($change) {
            'code' => $family->update(['invite_code' => 'MNOPQRSTUVWX']),
            'archive' => $family->update(['is_archived' => true]),
            'deleted user' => $user->delete(),
            'deleted family' => $family->delete(),
        };
    });

    $panel->call('join');

    expect($fired)->toBeTrue();
    expect(FamilyMember::where('family_id', $family->id)->where('user_id', $user->id)->exists())->toBeFalse();
    $panel->assertStatus(409)->assertNotDispatched('notification');
})->with(['code', 'archive', 'deleted user', 'deleted family']);

it('does not replace a membership inserted by another admission before code join', function () {
    $family = Family::factory()->create();
    $user = User::factory()->create();
    $panel = Livewire::actingAs($user)->test(JoinFamily::class)->set('inviteCode', $family->invite_code);
    $fired = false;
    $expected = null;

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, &$expected, $family, $user): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'insert') || ! str_contains($sql, '"family_members"')) {
            return;
        }
        $fired = true;
        $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'ADMIN', 'joined_at' => now()->subDay()]);
        $expected = $member->fresh()->getAttributes();
    });

    $panel->call('join');

    expect($fired)->toBeTrue();
    expect(FamilyMember::where('family_id', $family->id)->where('user_id', $user->id)->count())->toBe(1);
    expect(FamilyMember::where('family_id', $family->id)->where('user_id', $user->id)->first()->getAttributes())->toBe($expected);
    $panel->assertStatus(409)->assertNotDispatched('notification');
});

it('rechecks family administration in the final settings write', function (string $action, string $change) {
    $family = Family::factory()->create();
    $admin = User::factory()->create();
    $grant = FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
    $panel = Livewire::actingAs($admin)->test(FamilySettings::class, ['family' => $family])
        ->set('name', 'Not authorized')->set('description', 'Must not persist');
    $fired = false;
    $expected = null;

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, &$expected, $family, $grant, $change): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'update "families" set ')) {
            return;
        }
        $fired = true;
        match ($change) {
            'demotion' => $grant->update(['role' => 'MEMBER']),
            'revocation' => $grant->delete(),
            'archive' => $family->update(['is_archived' => true]),
        };
        $expected = $family->fresh()->getAttributes();
    });

    $panel->call($action);

    expect($fired)->toBeTrue();
    expect($family->fresh()->getAttributes())->toBe($expected);
    $panel->assertStatus(409)->assertNotDispatched('notification');
})->with(['update', 'archive', 'regenerateCode'])->with(['demotion', 'revocation', 'archive']);

it('rechecks family administration in the final membership write', function (string $action, string $change) {
    $family = Family::factory()->create();
    $admin = User::factory()->create();
    $grant = FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER']);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    $fired = false;
    $expected = $member->fresh()->getAttributes();

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, $family, $grant, $change, $action): void {
        $prefix = $action === 'removeMember' ? 'delete from "family_members"' : 'update "family_members" set ';
        if ($fired || ! str_starts_with(strtolower($sql), $prefix)) {
            return;
        }
        $fired = true;
        match ($change) {
            'demotion' => $grant->update(['role' => 'MEMBER']),
            'revocation' => $grant->delete(),
            'archive' => $family->update(['is_archived' => true]),
        };
    });

    $panel->call($action, $member->id);

    expect($fired)->toBeTrue();
    expect($member->fresh()?->getAttributes())->toBe($expected);
    $panel->assertStatus(409)->assertNotDispatched('notification');
})->with(['removeMember', 'promoteMember'])->with(['demotion', 'revocation', 'archive']);

it('refuses to mutate a membership rebound after its scoped lookup', function (string $action, string $change) {
    $family = Family::factory()->create();
    $other = Family::factory()->create();
    $replacement = User::factory()->create();
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER']);
    $panel = Livewire::actingAs($family->creator)->test(FamilyRoster::class, ['family' => $family]);
    $fired = false;
    $expected = null;

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, &$expected, $family, $other, $replacement, $member, $change, $action): void {
        $prefix = $action === 'removeMember' ? 'delete from "family_members"' : 'update "family_members" set ';
        if ($fired || ! str_starts_with(strtolower($sql), $prefix)) {
            return;
        }
        $fired = true;
        $member->update(match ($change) {
            'family' => ['family_id' => $other->id],
            'user' => ['user_id' => $replacement->id],
            'self' => ['user_id' => $family->creator_id],
        });
        $expected = $member->fresh()->getAttributes();
    });

    $panel->call($action, $member->id);

    expect($fired)->toBeTrue();
    expect($member->fresh()?->getAttributes())->toBe($expected);
    $panel->assertStatus(409)->assertNotDispatched('notification');
})->with(['removeMember', 'promoteMember'])->with(['family', 'user', 'self']);

it('preserves the current creator membership if ownership changes before removal', function () {
    $family = Family::factory()->create();
    $admin = User::factory()->create();
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER']);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    $fired = false;
    $expected = $member->fresh()->getAttributes();

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, $family, $member): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'delete from "family_members"')) {
            return;
        }
        $fired = true;
        $family->update(['creator_id' => $member->user_id]);
    });

    $panel->call('removeMember', $member->id);

    expect($fired)->toBeTrue();
    expect($member->fresh()?->getAttributes())->toBe($expected);
    $panel->assertStatus(409)->assertNotDispatched('notification');
});

it('denies a former creator without another grant at the final write', function (string $action) {
    $family = Family::factory()->create();
    $newCreator = User::factory()->create();
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER']);
    $roster = in_array($action, ['removeMember', 'promoteMember'], true);
    $panel = Livewire::actingAs($family->creator)->test($roster ? FamilyRoster::class : FamilySettings::class, ['family' => $family]);
    if ($action === 'update') {
        $panel->set('name', 'Not authorized');
    }
    $fired = false;
    $expected = null;
    $expectedMember = $member->fresh()->getAttributes();

    DB::connection()->beforeExecuting(function (string $sql) use (&$fired, &$expected, $family, $newCreator, $roster, $action): void {
        $prefix = $roster
            ? ($action === 'removeMember' ? 'delete from "family_members"' : 'update "family_members" set ')
            : 'update "families" set ';
        if ($fired || ! str_starts_with(strtolower($sql), $prefix)) {
            return;
        }
        $fired = true;
        $family->update(['creator_id' => $newCreator->id]);
        $expected = $family->fresh()->getAttributes();
    });

    $roster ? $panel->call($action, $member->id) : $panel->call($action);

    expect($fired)->toBeTrue();
    expect($family->fresh()->getAttributes())->toBe($expected);
    expect($member->fresh()?->getAttributes())->toBe($expectedMember);
    $panel->assertStatus(409)->assertNotDispatched('notification');
})->with(['update', 'archive', 'regenerateCode', 'removeMember', 'promoteMember']);
