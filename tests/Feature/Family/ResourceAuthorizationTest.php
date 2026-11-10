<?php

use App\Livewire\Family\FamilyRoster;
use App\Livewire\Family\FamilySettings;
use App\Livewire\Family\JoinFamily;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Livewire\Livewire;

describe('family resource authorization', function () {
    it('allows family members to view a family but denies outsiders', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $owner->id]);

        FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);

        $this->actingAs($member)->get(route('families.show', $family))->assertOk();
        $this->actingAs($outsider)->get(route('families.show', $family))->assertForbidden();
    });

    it('limits family settings to administrators', function () {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $admin->id]);

        FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);

        $this->actingAs($admin)->get(route('families.settings', $family))->assertOk();
        $this->actingAs($member)->get(route('families.settings', $family))->assertForbidden();
    });

    it('allows an administrator to update active family settings', function () {
        $admin = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $admin->id]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);

        Livewire::actingAs($admin)
            ->test(FamilySettings::class, ['family' => $family])
            ->set('name', 'Updated family')
            ->set('description', 'Updated for members')
            ->call('update');

        $this->assertDatabaseHas('families', [
            'id' => $family->id,
            'name' => 'Updated family',
            'description' => 'Updated for members',
        ]);
    });

    it('rechecks settings permission after the administrator loses membership', function () {
        $admin = User::factory()->create();
        $creator = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $creator->id]);
        $membership = FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $admin->id,
            'role' => 'ADMIN',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(FamilySettings::class, ['family' => $family])
            ->set('name', 'Unauthorized update');

        $membership->delete();

        $component->call('update')->assertForbidden();
        $this->assertDatabaseMissing('families', ['id' => $family->id, 'name' => 'Unauthorized update']);
    });

    it('does not let a family administrator mutate a membership in another family', function () {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $admin->id]);
        $otherFamily = Family::factory()->create();

        FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);
        $otherMembership = FamilyMember::create([
            'family_id' => $otherFamily->id,
            'user_id' => $target->id,
            'role' => 'MEMBER',
        ]);

        Livewire::actingAs($admin)
            ->test(FamilyRoster::class, ['family' => $family])
            ->call('removeMember', $otherMembership->id)
            ->assertNotFound();

        $this->assertDatabaseHas('family_members', ['id' => $otherMembership->id]);
    });

    it('does not permit settings mutations on an archived family', function () {
        $admin = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $admin->id, 'is_archived' => true]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $admin->id, 'role' => 'ADMIN']);

        $this->actingAs($admin)->get(route('families.settings', $family))->assertOk();

        Livewire::actingAs($admin)
            ->test(FamilySettings::class, ['family' => $family])
            ->set('name', 'No change')
            ->call('update')
            ->assertForbidden();

        expect($family->fresh()->name)->not->toBe('No change');
    });

    it('does not accept new family members after archive', function () {
        $creator = User::factory()->create();
        $joiner = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $creator->id, 'is_archived' => true]);

        Livewire::actingAs($joiner)
            ->test(JoinFamily::class)
            ->set('inviteCode', $family->invite_code)
            ->call('join')
            ->assertForbidden();

        $this->assertDatabaseMissing('family_members', [
            'family_id' => $family->id,
            'user_id' => $joiner->id,
        ]);
    });
});
