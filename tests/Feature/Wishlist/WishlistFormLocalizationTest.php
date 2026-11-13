<?php

use App\Livewire\Wishlist\CreateWishlist;
use App\Livewire\Wishlist\WishlistSettings;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistShare;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

it('keeps every scoped form key and interpolation available in both locale catalogs', function () {
    $english = require lang_path('en/wishlist_forms.php');
    $portuguese = require lang_path('pt_BR/wishlist_forms.php');
    expect(array_keys($portuguese))->toBe(array_keys($english));
    $used = [];
    foreach ([
        'app/Livewire/Wishlist/CreateWishlist.php',
        'app/Livewire/Wishlist/WishlistSettings.php',
        'resources/views/livewire/wishlist/create-wishlist.blade.php',
        'resources/views/livewire/wishlist/wishlist-settings.blade.php',
    ] as $path) {
        preg_match_all('/__\([\'\"]wishlist_forms\.([a-z_]+)[\'\"]/', file_get_contents(base_path($path)), $matches);
        $used = array_merge($used, $matches[1]);
    }
    expect(array_values(array_unique($used)))->toEqualCanonicalizing(array_keys($english));
    foreach ($english as $key => $text) {
        expect($text)->toBeString()->not->toBeEmpty();
        expect($portuguese[$key])->toBeString()->not->toBeEmpty();
        foreach (['en', 'pt_BR'] as $locale) {
            expect(Lang::hasForLocale('wishlist_forms.'.$key, $locale))->toBeTrue();
        }
        preg_match_all('/:[a-z_]+/', $text, $englishParameters);
        preg_match_all('/:[a-z_]+/', $portuguese[$key], $portugueseParameters);
        expect($portugueseParameters[0])->toEqualCanonicalizing($englishParameters[0]);
    }
});

it('uses localized field names in validation without weakening the rules', function (string $locale, array $messages) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'name' => 'Unchanged list']);
    $family = Family::factory()->create();
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $owner->id, 'role' => 'MEMBER']);
    $cases = [
        'name' => ['', 'required'],
        'description' => [str_repeat('x', 1001), 'max'],
        'type' => ['INVALID', 'in'],
        'privacy' => ['INVALID', 'in'],
    ];
    foreach ([CreateWishlist::class => 'create', WishlistSettings::class => 'update'] as $class => $method) {
        foreach ($cases as $field => [$value, $rule]) {
            Livewire::actingAs($owner)->test($class, $class === WishlistSettings::class ? ['wishlist' => $wishlist] : [])
                ->set('name', 'Valid name')->set($field, $value)->call($method)
                ->assertHasErrors([$field => $rule])->assertSee($messages[$field])
                ->assertNotDispatched('notification');
        }
    }
    Livewire::actingAs($owner)->test(CreateWishlist::class)->set('name', 'Valid name')->set('family_id', 999999)
        ->call('create')->assertHasErrors(['family_id' => 'exists'])->assertSee($messages['family_id']);
    Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist])
        ->set('shareEmail', 'invalid-email')->call('addShare')->assertHasErrors(['shareEmail' => 'email'])
        ->assertSee($messages['shareEmail']);
    expect(Wishlist::count())->toBe(1)->and($wishlist->fresh()->name)->toBe('Unchanged list')
        ->and(WishlistShare::count())->toBe(0);
})->with([
    'English' => ['en', [
        'name' => 'The wishlist name field is required.',
        'description' => 'The description field must not be greater than 1000 characters.',
        'type' => 'The selected type is invalid.',
        'privacy' => 'The selected privacy is invalid.',
        'family_id' => 'The selected family is invalid.',
        'shareEmail' => 'The email address field must be a valid email address.',
    ]],
    'Portuguese' => ['pt_BR', [
        'name' => 'O campo nome da lista é obrigatório.',
        'description' => 'O campo descrição não pode ser superior a 1000 caracteres.',
        'type' => 'O campo tipo selecionado é inválido.',
        'privacy' => 'O campo privacidade selecionado é inválido.',
        'family_id' => 'O campo família selecionado é inválido.',
        'shareEmail' => 'O campo e-mail deve ser um endereço de e-mail válido.',
    ]],
]);

it('renders sharing refusals in the active locale without creating access', function (string $locale, array $errors) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $share = $wishlist->shares()->create(['shared_with_user_id' => $friend->id, 'access_level' => 'VIEW']);
    foreach (['missing@example.test', $owner->email, $friend->email] as $index => $email) {
        Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist])
            ->set('shareEmail', $email)->call('addShare')->assertHasErrors(['shareEmail'])
            ->assertSee($errors[$index])->assertNotDispatched('notification');
    }
    expect($wishlist->shares()->pluck('id')->all())->toBe([$share->id]);
})->with([
    'English' => ['en', ['User not found with that email.', 'You cannot share a wishlist with yourself.', 'This user already has access.']],
    'Portuguese' => ['pt_BR', ['Nenhuma pessoa encontrada com esse e-mail.', 'Você não pode compartilhar uma lista consigo mesmo.', 'Esta pessoa já tem acesso.']],
]);

it('emits localized success notices through the wishlist lifecycle', function (string $locale, array $notices) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    Livewire::actingAs($owner)->test(CreateWishlist::class)->set('name', 'Original list')
        ->call('create')->assertDispatched('notification', ['message' => $notices['created'], 'type' => 'success']);
    $wishlist = Wishlist::where('user_id', $owner->id)->sole();
    $component = Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist])
        ->set('privacy', 'SPECIFIC')->call('update')
        ->assertDispatched('notification', ['message' => $notices['updated'], 'type' => 'success'])
        ->set('shareEmail', $friend->email)->call('addShare')
        ->assertDispatched('notification', ['message' => $notices['added'], 'type' => 'success']);
    $share = $wishlist->shares()->sole();
    expect($share->access_level)->toBe('VIEW')->and($share->shared_with_user_id)->toBe($friend->id);
    $component->call('removeShare', $share->id)
        ->assertDispatched('notification', ['message' => $notices['removed'], 'type' => 'success']);
    expect($share->fresh())->toBeNull();
    $component->call('archive')->assertRedirect(route('wishlists.index'))
        ->assertDispatched('notification', ['message' => $notices['updated'], 'type' => 'success']);
    expect($wishlist->fresh()->is_archived)->toBeTrue();
    Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist])
        ->call('delete')->assertRedirect(route('wishlists.index'))
        ->assertDispatched('notification', ['message' => $notices['deleted'], 'type' => 'success']);
    expect($wishlist->fresh())->toBeNull();
})->with([
    'English' => ['en', ['created' => 'Wishlist created successfully.', 'updated' => 'Wishlist updated successfully.', 'deleted' => 'Wishlist deleted.', 'added' => 'User added successfully.', 'removed' => 'User removed.']],
    'Portuguese' => ['pt_BR', ['created' => 'Lista criada com sucesso.', 'updated' => 'Lista atualizada com sucesso.', 'deleted' => 'Lista excluída.', 'added' => 'Pessoa adicionada com sucesso.', 'removed' => 'Pessoa removida.']],
]);

it('renders creation copy in the active locale without translating submitted values', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $family = Family::factory()->create(['name' => 'Untranslated family data']);
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $owner->id, 'role' => 'MEMBER']);

    $component = Livewire::actingAs($owner)->test(CreateWishlist::class)
        ->assertSet('privacy', 'PRIVATE')->assertSet('type', 'GENERIC');
    foreach ($copy as $text) {
        $component->assertSee($text);
    }
    foreach (['PRIVATE', 'FAMILY', 'SPECIFIC', 'GENERIC', 'CHRISTMAS', 'BIRTHDAY', 'WEDDING'] as $value) {
        $component->assertSee('value="'.$value.'"', false);
    }
    $component->assertSee('Untranslated family data')
        ->set('name', 'Untranslated wishlist data')->set('family_id', $family->id)
        ->set('type', 'BIRTHDAY')->call('create')->assertHasNoErrors();
    $wishlist = Wishlist::where('user_id', $owner->id)->sole();
    expect($wishlist->name)->toBe('Untranslated wishlist data')
        ->and($wishlist->type)->toBe('BIRTHDAY')->and($wishlist->privacy)->toBe('PRIVATE')
        ->and($wishlist->family_id)->toBe($family->id)->and($wishlist->shares()->count())->toBe(0);
})->with([
    'English' => ['en', ['Create Wishlist', 'Wishlist Name', 'Description', 'Type', 'Privacy', 'My Birthday Wishlist', 'What is this wishlist for?', 'Generic', 'Christmas', 'Birthday', 'Wedding', 'Private', 'Family', 'Specific People', 'Family (optional)', 'None', 'Cancel', 'Create']],
    'Portuguese' => ['pt_BR', ['Criar lista de desejos', 'Nome da lista', 'Descrição', 'Tipo', 'Privacidade', 'Minha lista de aniversário', 'Para que é esta lista?', 'Geral', 'Natal', 'Aniversário', 'Casamento', 'Privada', 'Família', 'Pessoas específicas', 'Família (opcional)', 'Nenhuma', 'Cancelar', 'Criar']],
]);

it('localizes settings and sharing controls without changing data or access values', function (string $locale, array $copy, string $removeLabel, string $viewLabel, string $editLabel) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'name' => 'Original list', 'privacy' => 'PRIVATE']);
    $component = Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist]);
    foreach ($copy as $text) {
        $component->assertSee($text);
    }
    foreach (['PRIVATE', 'FAMILY', 'SPECIFIC', 'GENERIC', 'CHRISTMAS', 'BIRTHDAY', 'WEDDING'] as $value) {
        $component->assertSee('value="'.$value.'"', false);
    }

    $viewer = User::factory()->create(['name' => 'Ana <script>']);
    $legacyEditor = User::factory()->create();
    $wishlist->shares()->create(['shared_with_user_id' => $viewer->id, 'access_level' => 'VIEW']);
    $wishlist->shares()->create(['shared_with_user_id' => $legacyEditor->id, 'access_level' => 'EDIT']);
    $component->call('$refresh')->assertSee($viewer->name)
        ->assertSee('aria-label="'.e($removeLabel).'"', false)
        ->assertSee($viewLabel)->assertSee($editLabel)
        ->set('type', 'WEDDING')->set('privacy', 'SPECIFIC')->call('update')->assertHasNoErrors();
    expect($wishlist->fresh()->type)->toBe('WEDDING')
        ->and($wishlist->fresh()->privacy)->toBe('SPECIFIC')
        ->and($wishlist->shares()->orderBy('id')->pluck('access_level')->all())->toBe(['VIEW', 'EDIT']);
    Livewire::actingAs($viewer)->test(WishlistSettings::class, ['wishlist' => $wishlist])->assertForbidden();
    Livewire::actingAs($legacyEditor)->test(WishlistSettings::class, ['wishlist' => $wishlist])->assertForbidden();
})->with([
    'English' => ['en', ['Original list - Settings', 'Edit Wishlist', 'Wishlist Name', 'Description', 'Type', 'Privacy', 'Generic', 'Christmas', 'Birthday', 'Wedding', 'Private', 'Family', 'Specific People', 'Save', 'Share Management', 'Email address to share with', 'Enter user email to share...', 'Add', 'No users shared with yet.', 'Danger Zone', 'Archive', 'Delete', 'Are you sure you want to archive this wishlist?', 'Are you sure you want to permanently delete this wishlist? This action cannot be undone.'], 'Remove access for Ana <script>', 'View', 'Edit'],
    'Portuguese' => ['pt_BR', ['Original list - Configurações', 'Editar lista', 'Nome da lista', 'Descrição', 'Tipo', 'Privacidade', 'Geral', 'Natal', 'Aniversário', 'Casamento', 'Privada', 'Família', 'Pessoas específicas', 'Salvar', 'Gerenciar compartilhamento', 'E-mail da pessoa com quem compartilhar', 'Digite o e-mail para compartilhar...', 'Adicionar', 'Esta lista ainda não foi compartilhada.', 'Ações irreversíveis', 'Arquivar', 'Excluir', 'Tem certeza de que deseja arquivar esta lista?', 'Tem certeza de que deseja excluir esta lista permanentemente? Esta ação não pode ser desfeita.'], 'Remover acesso de Ana <script>', 'Visualização', 'Edição'],
]);
