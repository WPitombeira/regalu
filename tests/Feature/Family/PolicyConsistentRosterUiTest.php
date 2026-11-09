<?php

use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Livewire\Livewire;

it('shows the family settings link only to managers on active and archived rosters', function () {
    $ordinaryMember = User::factory()->create();
    $admin = User::factory()->create();
    $creator = User::factory()->create();
    $settingsUrl = static fn (Family $family): string => route('families.settings', $family);
    $assertLink = static function (User $viewer, Family $family, bool $visible) use ($settingsUrl): void {
        $roster = Livewire::actingAs($viewer)->test(FamilyRoster::class, ['family' => $family]);
        if ($visible) {
            $roster->assertSee('href="'.$settingsUrl($family).'"', false);
        } else {
            $roster->assertDontSee('href="'.$settingsUrl($family).'"', false);
        }
    };

    foreach ([false, true] as $archived) {
        $family = Family::factory()->create([
            'creator_id' => $creator->id,
            'is_archived' => $archived,
        ]);
        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $ordinaryMember->id,
            'role' => 'MEMBER',
        ]);
        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $admin->id,
            'role' => 'ADMIN',
        ]);

        $assertLink($ordinaryMember, $family, false);
        $assertLink($admin, $family, true);
        $assertLink($creator, $family, true);
    }
});
