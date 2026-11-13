<?php

use App\Livewire\Dashboard;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use Livewire\Livewire;

it('mounts an authenticated dashboard with only current personal shortcuts', function (string $locale, string $heading) {
    app()->setLocale($locale);
    $user = User::factory()->create(['locale' => $locale]);
    $own = Wishlist::factory()->create(['user_id' => $user->id, 'name' => 'My birthday list']);
    $archived = Wishlist::factory()->create(['user_id' => $user->id, 'is_archived' => true, 'name' => 'Archived list sentinel']);
    $foreign = Wishlist::factory()->create(['name' => 'Foreign list sentinel']);
    $family = Family::factory()->create(['name' => 'Our family', 'description' => 'Private description sentinel']);
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'MEMBER', 'joined_at' => now()]);
    $otherFamily = Family::factory()->create(['name' => 'Other family sentinel']);
    Family::factory()->create(['creator_id' => $user->id, 'is_archived' => true, 'name' => 'Archived family sentinel']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSeeLivewire(Dashboard::class)
        ->assertSee($heading)->assertSee($own->name)->assertSee($family->name)
        ->assertSee(route('wishlists.show', $own), false)->assertSee(route('families.show', $family), false)
        ->assertDontSee($archived->name)->assertDontSee($foreign->name)->assertDontSee($otherFamily->name)
        ->assertDontSee('Archived family sentinel')->assertDontSee('Private description sentinel')->assertDontSee($family->invite_code);
    $component = Livewire::actingAs($user)->test(Dashboard::class);
    expect(array_keys($component->viewData('wishlists')->sole()->getAttributes()))->toBe(['id', 'name']);
    expect(array_keys($component->viewData('families')->sole()->getAttributes()))->toBe(['id', 'name']);
    $family->members()->delete();
    $component->call('$refresh')->assertDontSee($family->name);
})->with(['English' => ['en', 'Your gift plans'], 'Portuguese' => ['pt_BR', 'Seus planos de presentes']]);

it('gives new users localized next steps instead of empty panels', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertSee($copy)->assertSee(route('wishlists.create'), false)
        ->assertSee(route('families.join'), false)->assertSee(route('amigo-secreto.create'), false);
})->with([
    'en' => ['en', ['Start your first wishlist', 'Join a family', 'Plan a Secret Santa', 'Nothing to catch up on yet.']],
    'pt_BR' => ['pt_BR', ['Crie sua primeira lista', 'Entre em uma família', 'Organize um amigo secreto', 'Nenhuma novidade por enquanto.']],
]);
