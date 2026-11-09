<?php

use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->family = Family::factory()->create(['creator_id' => $this->creator->id]);
    $this->creatorMembership = FamilyMember::create([
        'family_id' => $this->family->id, 'user_id' => $this->creator->id, 'role' => 'ADMIN',
    ]);
    FamilyMember::create(['family_id' => $this->family->id, 'user_id' => $this->admin->id, 'role' => 'ADMIN']);
    $this->ordinaryMembership = FamilyMember::create([
        'family_id' => $this->family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER',
    ]);
});

it('rejects a second administrator removing the family creator without reporting success', function () {
    Livewire::actingAs($this->admin)->test(FamilyRoster::class, ['family' => $this->family])
        ->call('removeMember', $this->creatorMembership->id)
        ->assertForbidden()->assertNotDispatched('notification');

    $this->assertDatabaseHas('family_members', ['id' => $this->creatorMembership->id, 'role' => 'ADMIN']);
    expect($this->family->fresh()->creator_id)->toBe($this->creator->id);
});

it('hides creator removal while keeping ordinary member removal available', function () {
    $roster = Livewire::actingAs($this->admin)->test(FamilyRoster::class, ['family' => $this->family]);
    $roster->assertDontSee('removeMember('.$this->creatorMembership->id.')', false)
        ->assertSee('removeMember('.$this->ordinaryMembership->id.')', false);

    $roster->call('removeMember', $this->ordinaryMembership->id)
        ->assertSuccessful()->assertDispatched('notification');
    $this->assertDatabaseMissing('family_members', ['id' => $this->ordinaryMembership->id]);
    $this->assertDatabaseHas('family_members', ['id' => $this->creatorMembership->id]);
});
