<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;

describe('family management', function () {
    it('can create a family', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('families.create'))
            ->assertStatus(200);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Family\CreateFamily::class)
            ->set('name', 'Test Family')
            ->set('description', 'A test family description')
            ->call('create')
            ->assertRedirect();

        $this->assertDatabaseHas('families', [
            'name' => 'Test Family',
            'description' => 'A test family description',
            'creator_id' => $user->id,
        ]);
    });

    it('creator becomes admin', function () {
        $user = User::factory()->create();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Family\CreateFamily::class)
            ->set('name', 'Admin Family')
            ->call('create');

        $family = Family::where('name', 'Admin Family')->first();

        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'user_id' => $user->id,
            'role' => 'ADMIN',
        ]);
    });

    it('can join a family with valid invite code', function () {
        $creator = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $creator->id]);

        $joiner = User::factory()->create();

        \Livewire\Livewire::actingAs($joiner)
            ->test(\App\Livewire\Family\JoinFamily::class)
            ->set('inviteCode', $family->invite_code)
            ->call('join')
            ->assertRedirect();

        $this->assertDatabaseHas('family_members', [
            'family_id' => $family->id,
            'user_id' => $joiner->id,
            'role' => 'MEMBER',
        ]);
    });

    it('cannot join with invalid invite code', function () {
        $user = User::factory()->create();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Family\JoinFamily::class)
            ->set('inviteCode', 'INVALIDCODE12')
            ->call('join')
            ->assertHasErrors(['inviteCode']);
    });

    it('cannot join a family twice', function () {
        $creator = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $creator->id]);

        $joiner = User::factory()->create();

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $joiner->id,
            'role' => 'MEMBER',
            'joined_at' => now(),
        ]);

        \Livewire\Livewire::actingAs($joiner)
            ->test(\App\Livewire\Family\JoinFamily::class)
            ->set('inviteCode', $family->invite_code)
            ->call('join')
            ->assertHasErrors(['inviteCode']);
    });

    it('admin can remove a member', function () {
        $admin = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $admin->id]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'joined_at' => now(),
        ]);

        $member = User::factory()->create();
        $membership = FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $member->id,
            'role' => 'MEMBER',
            'joined_at' => now(),
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Family\FamilyRoster::class, ['family' => $family])
            ->call('removeMember', $membership->id);

        $this->assertDatabaseMissing('family_members', [
            'id' => $membership->id,
        ]);
    });

    it('can archive a family', function () {
        $admin = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $admin->id]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'joined_at' => now(),
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Family\FamilySettings::class, ['family' => $family])
            ->call('archive');

        $this->assertDatabaseHas('families', [
            'id' => $family->id,
            'is_archived' => true,
        ]);
    });
});
