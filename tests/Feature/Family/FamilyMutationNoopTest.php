<?php

use App\Livewire\Family\FamilyRoster;
use App\Livewire\Family\FamilySettings;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Execute real SQLite statements, but exercise the zero-changed-row PDO contract.
// This is a driver-result seam, not a MySQL/MariaDB runtime qualification.
class ZeroRowFamilyUpdateStatement extends PDOStatement
{
    public static bool $reported = false;

    public static ?Closure $afterUpdate = null;

    public function rowCount(): int
    {
        $count = parent::rowCount();
        if (! self::$reported && (str_starts_with($this->queryString, 'update "families" set ')
            || str_starts_with($this->queryString, 'update "family_members" set '))) {
            self::$reported = true;
            if (self::$afterUpdate !== null) {
                (self::$afterUpdate)();
            }

            return 0;
        }

        return $count;
    }
}

it('accepts a still-authorized no-op when PDO reports zero changed rows', function (string $action, string $authority) {
    $this->freezeSecond();
    $family = Family::factory()->create();
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'ADMIN']);
    $actor = $authority === 'creator' ? $family->creator : $member->user;
    $panel = Livewire::actingAs($actor)->test($action === 'update' ? FamilySettings::class : FamilyRoster::class, ['family' => $family]);
    $beforeFamily = $family->fresh()->getAttributes();
    $beforeMember = $member->fresh()->getAttributes();
    $pdo = DB::connection()->getPdo();
    $original = $pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS);
    ZeroRowFamilyUpdateStatement::$reported = false;

    try {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ZeroRowFamilyUpdateStatement::class]);
        $action === 'update' ? $panel->call($action) : $panel->call($action, $member->id);

        expect(ZeroRowFamilyUpdateStatement::$reported)->toBeTrue();
        expect($family->fresh()->getAttributes())->toBe($beforeFamily);
        expect($member->fresh()->getAttributes())->toBe($beforeMember);
        $panel->assertOk()->assertDispatched('notification');
    } finally {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, $original);
        ZeroRowFamilyUpdateStatement::$reported = false;
        ZeroRowFamilyUpdateStatement::$afterUpdate = null;
    }
})->with(['update', 'promoteMember'])->with(['creator', 'administrator']);

it('does not confirm a zero-row update after eligibility or desired state changes', function (string $action, string $change) {
    $this->freezeSecond();
    $family = Family::factory()->create();
    $other = Family::factory()->create();
    $replacement = User::factory()->create();
    $admin = User::factory()->create();
    $grant = FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'ADMIN']);
    $actor = $change === 'creator' ? $family->creator : $admin;
    $panel = Livewire::actingAs($actor)->test($action === 'update' ? FamilySettings::class : FamilyRoster::class, ['family' => $family]);
    $pdo = DB::connection()->getPdo();
    $original = $pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS);
    $expectedFamily = $expectedMember = $expectedGrant = null;
    $fired = false;
    ZeroRowFamilyUpdateStatement::$reported = false;
    ZeroRowFamilyUpdateStatement::$afterUpdate = function () use (&$fired, &$expectedFamily, &$expectedMember, &$expectedGrant, $family, $other, $replacement, $grant, $member, $change): void {
        $fired = true;
        match ($change) {
            'creator' => $family->update(['creator_id' => $replacement->id]),
            'demotion' => $grant->update(['role' => 'MEMBER']),
            'revocation' => $grant->delete(),
            'archive' => $family->update(['is_archived' => true]),
            'name' => $family->update(['name' => 'Changed after the write']),
            'description' => $family->update(['description' => 'Changed after the write']),
            'deleted family' => $family->delete(),
            'role' => $member->update(['role' => 'MEMBER']),
            'family' => $member->update(['family_id' => $other->id]),
            'user' => $member->update(['user_id' => $replacement->id]),
            'deleted member' => $member->delete(),
        };
        $expectedFamily = $family->fresh()?->getAttributes();
        $expectedMember = $member->fresh()?->getAttributes();
        $expectedGrant = $grant->fresh()?->getAttributes();
    };

    try {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ZeroRowFamilyUpdateStatement::class]);
        $action === 'update' ? $panel->call($action) : $panel->call($action, $member->id);

        expect(ZeroRowFamilyUpdateStatement::$reported)->toBeTrue();
        expect($fired)->toBeTrue();
        expect($family->fresh()?->getAttributes())->toBe($expectedFamily);
        expect($member->fresh()?->getAttributes())->toBe($expectedMember);
        expect($grant->fresh()?->getAttributes())->toBe($expectedGrant);
        $panel->assertStatus(409)->assertNotDispatched('notification');
    } finally {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, $original);
        ZeroRowFamilyUpdateStatement::$reported = false;
        ZeroRowFamilyUpdateStatement::$afterUpdate = null;
    }
})->with([
    ['update', 'creator'],
    ['update', 'demotion'],
    ['update', 'revocation'],
    ['update', 'archive'],
    ['update', 'name'],
    ['update', 'description'],
    ['update', 'deleted family'],
    ['promoteMember', 'creator'],
    ['promoteMember', 'demotion'],
    ['promoteMember', 'revocation'],
    ['promoteMember', 'archive'],
    ['promoteMember', 'role'],
    ['promoteMember', 'family'],
    ['promoteMember', 'user'],
    ['promoteMember', 'deleted member'],
]);
