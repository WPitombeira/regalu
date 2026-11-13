<?php

use App\Livewire\GettingStarted;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('makes the guide reachable from the dashboard without replacing existing shortcuts', function () {
    app()->setLocale('en');
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee('Getting started')->assertSee(route('onboarding'), false)
        ->assertSee(route('wishlists.create'), false)->assertSee(route('amigo-secreto.create'), false);
    Wishlist::factory()->create(['user_id' => $user->id]);
    $this->get(route('dashboard'))->assertOk()->assertSee(route('onboarding'), false);
});

it('offers an authenticated localized getting started guide', function (string $locale, string $title, string $private) {
    app()->setLocale($locale);
    $user = User::factory()->create(['locale' => $locale]);
    $this->actingAs($user)->get('/getting-started')->assertOk()
        ->assertSee($title)->assertSee($private)
        ->assertSee(route('wishlists.create'), false)
        ->assertSee(route('dashboard'), false);
})->with([
    'English' => ['en', 'Your first wishlist, step by step', 'New wishlists start private. You decide if and when to share.'],
    'Portuguese' => ['pt_BR', 'Sua primeira lista, passo a passo', 'Novas listas começam privadas. Você decide se e quando compartilhar.'],
]);

it('resumes the newest active owned wishlist without importing shared or archived state', function () {
    $user = User::factory()->create();
    $old = Wishlist::factory()->create(['user_id' => $user->id, 'name' => 'Earlier list']);
    $own = Wishlist::factory()->create(['user_id' => $user->id, 'name' => 'My first ideas', 'privacy' => 'PRIVATE']);
    $archived = Wishlist::factory()->create(['user_id' => $user->id, 'is_archived' => true, 'name' => 'Archived sentinel']);
    $foreign = Wishlist::factory()->create(['privacy' => 'SPECIFIC', 'name' => 'Shared sentinel']);
    $foreign->shares()->create(['shared_with_user_id' => $user->id, 'access_level' => 'EDIT']);

    $component = Livewire::actingAs($user)->test(GettingStarted::class)
        ->assertSee($own->name)->assertSee(route('wishlists.show', $own), false)
        ->assertDontSee($old->name)->assertDontSee($archived->name)->assertDontSee($foreign->name);
    expect($component->viewData('step'))->toBe('add');
    expect(array_keys($component->viewData('wishlist')->getAttributes()))->toBe(['id', 'name', 'privacy', 'items_exists']);
    $own->update(['is_archived' => true]);
    $component->call('$refresh')->assertDontSee($own->name)->assertSee($old->name);
    $old->delete();
    $component->call('$refresh')->assertSee(route('wishlists.create'), false);
    expect($component->viewData('wishlist'))->toBeNull();
    expect($component->viewData('step'))->toBe('create');
});

it('guides first gift and privacy review using persisted state without purchase details', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    $list = Wishlist::factory()->create(['user_id' => $user->id, 'privacy' => 'PRIVATE']);
    $component = Livewire::actingAs($user)->test(GettingStarted::class)->assertSee($copy)
        ->assertSee('data-step="add"', false)->assertSee(route('wishlists.settings', $list), false);
    $item = WishlistItem::factory()->create(['wishlist_id' => $list->id, 'name' => 'Hidden gift sentinel', 'status' => 'BOUGHT']);
    $newerEmpty = Wishlist::factory()->create(['user_id' => $user->id, 'name' => 'Later empty list']);
    $component->call('$refresh')->assertSee('data-step="privacy"', false)
        ->assertSee($list->name)->assertDontSee($newerEmpty->name)->assertDontSee($item->name)
        ->assertDontSee('bought_by_user_id')->assertDontSee('bought_at')->assertDontSee('BOUGHT');
    expect($list->fresh()->privacy)->toBe('PRIVATE');
    $this->assertDatabaseCount('wishlist_shares', 0);
    $item->delete();
    $component->call('$refresh')->assertSee('data-step="add"', false)->assertSee($newerEmpty->name);
})->with([
    'en' => ['en', ['Create a wishlist', 'Add your first gift', 'Choose who can see it', 'Keeping your list private is a valid choice.']],
    'pt_BR' => ['pt_BR', ['Criar uma lista', 'Adicione seu primeiro presente', 'Escolha quem pode ver', 'Manter sua lista privada é uma escolha válida.']],
]);

it('requires authentication on the guide route and on every component render', function () {
    $this->get(route('onboarding'))->assertRedirect(route('login'));
    Livewire::test(GettingStarted::class)->assertForbidden();
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test(GettingStarted::class)->assertOk();
    Auth::logout();
    $component->call('$refresh')->assertForbidden();
});

it('drops a transferred wishlist on refresh without retaining it in the snapshot', function () {
    $user = User::factory()->create();
    $newOwner = User::factory()->create();
    $list = Wishlist::factory()->create(['user_id' => $user->id, 'name' => '<b>Escaped list</b>']);
    $component = Livewire::actingAs($user)->test(GettingStarted::class)
        ->assertSee('&lt;b&gt;Escaped list&lt;/b&gt;', false)->assertDontSee('<b>Escaped list</b>', false);
    expect($component->snapshot['data'])->toBeEmpty();
    $list->update(['user_id' => $newOwner->id]);
    $component->call('$refresh')->assertDontSee($list->name)->assertSee('data-step="create"', false);
    expect($component->viewData('wishlist'))->toBeNull();
});

it('explains current privacy without changing it or granting access', function (string $locale, string $privacy, string $copy, string $consent) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    $list = Wishlist::factory()->create(['user_id' => $user->id, 'privacy' => $privacy]);
    Livewire::actingAs($user)->test(GettingStarted::class)->assertSee($copy)->assertSee($consent);
    expect($list->fresh()->privacy)->toBe($privacy);
    $this->assertDatabaseCount('wishlist_shares', 0);
})->with([
    'private en' => ['en', 'PRIVATE', 'Private: only you can open the list through ordinary sharing.', 'Secret Santa wishlist consent is separate'],
    'family en' => ['en', 'FAMILY', 'Family: people who share an active family with you can see the list.', 'Secret Santa wishlist consent is separate'],
    'specific en' => ['en', 'SPECIFIC', 'Specific people: only people you explicitly add can see the list.', 'Secret Santa wishlist consent is separate'],
    'private pt' => ['pt_BR', 'PRIVATE', 'Privada: só você pode abrir a lista pelo compartilhamento comum.', 'O consentimento de lista no amigo secreto é separado'],
    'family pt' => ['pt_BR', 'FAMILY', 'Família: pessoas que compartilham uma família ativa com você podem ver a lista.', 'O consentimento de lista no amigo secreto é separado'],
    'specific pt' => ['pt_BR', 'SPECIFIC', 'Pessoas específicas: apenas quem você adicionar explicitamente pode ver a lista.', 'O consentimento de lista no amigo secreto é separado'],
]);

it('protects guide reads with the real password session middleware', function (bool $revoked, string $transport) {
    $user = User::factory()->create();
    $list = Wishlist::factory()->create(['user_id' => $user->id, 'name' => 'Session protected list']);
    $page = $this->actingAs($user)->get(route('onboarding'))->assertOk()->assertSee($list->name);
    $fingerprint = session('password_hash_web');
    expect($fingerprint)->toBeString()->not->toBeEmpty();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn (string $encoded) => html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ->first(fn (string $value) => json_decode($value, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'getting-started');
    expect($snapshot)->toBeString();
    if ($revoked) {
        $user->forceFill(['password' => 'replacement-test-password'])->save();
    }
    $this->actingAs($user->fresh())->withSession(['password_hash_web' => $fingerprint]);
    if ($transport === 'GET') {
        $response = $this->get(route('onboarding'));
        $revoked ? $response->assertRedirect(route('login')) : $response->assertOk()->assertSee($list->name);
    } else {
        $response = $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]]], ['X-Livewire' => 'true']);
        $revoked ? $response->assertUnauthorized() : $response->assertOk()->assertSee($list->name);
    }
    if ($revoked) {
        $this->assertGuest();
        $response->assertDontSee($list->name);
    }
})->with([false, true])->with(['GET', 'Livewire']);

it('keeps onboarding catalog keys complete in both languages', function () {
    $english = require lang_path('en/onboarding.php');
    $portuguese = require lang_path('pt_BR/onboarding.php');
    expect(array_keys($portuguese))->toBe(array_keys($english));
    foreach ([$english, $portuguese] as $catalog) {
        foreach ($catalog as $copy) {
            expect($copy)->toBeString()->not->toBeEmpty();
        }
    }
});
