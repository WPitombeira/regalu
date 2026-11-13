<?php

use App\Livewire\Profile\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('keeps profile catalogs complete with matching placeholders and no locale fallback', function () {
    $english = require lang_path('en/profile_settings.php');
    $portuguese = require lang_path('pt_BR/profile_settings.php');
    expect($english)->not->toBeEmpty();
    expect(array_diff_key($english, $portuguese))->toBe([]);
    expect(array_diff_key($portuguese, $english))->toBe([]);

    foreach ($english as $key => $text) {
        foreach (['en' => $text, 'pt_BR' => $portuguese[$key]] as $locale => $translation) {
            expect($translation)->toBeString();
            expect(trim($translation))->not->toBe('');
            expect(app('translator')->hasForLocale('profile_settings.'.$key, $locale))->toBeTrue();
            expect(app('translator')->get('profile_settings.'.$key, [], $locale, false))->toBe($translation);
        }

        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $text, $englishPlaceholders);
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $portuguese[$key], $portuguesePlaceholders);
        sort($englishPlaceholders[0]);
        sort($portuguesePlaceholders[0]);
        expect($portuguesePlaceholders[0])->toBe($englishPlaceholders[0]);
    }

    foreach (['app/Livewire/Profile/Settings.php', 'resources/views/livewire/profile/settings.blade.php'] as $path) {
        preg_match_all("/profile_settings\.([a-z_]+)/", file_get_contents(base_path($path)), $matches);
        expect($matches[1])->not->toBeEmpty();
        foreach (array_unique($matches[1]) as $key) {
            expect(array_key_exists($key, $english))->toBeTrue('Missing profile key: '.$key);
        }
    }
});

it('renders profile settings in the requested locale on both mounted routes', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $user = User::factory()->create();

    foreach (['settings', 'profile'] as $route) {
        $response = $this->actingAs($user)->get(route($route));
        $response->assertOk()->assertSeeLivewire('profile.settings');
        foreach ($copy as $text) {
            expect(str_contains(strip_tags($response->getContent()), $text))->toBeTrue('Missing localized copy: '.$text);
        }
        $response->assertDontSee('profile_settings.')->assertDontSee('messages.profile.');
    }

    expect(Livewire::actingAs($user)->test('profile.settings')->instance())->toBeInstanceOf(Settings::class);
})->with([
    'English' => ['en', ['Profile settings', 'Profile information', 'Name', 'Phone (optional)', 'Profile photo', 'Save profile', 'Change password', 'Current password', 'New password', 'Confirm new password', 'Update password']],
    'Brazilian Portuguese' => ['pt_BR', ['Configurações de perfil', 'Informações do perfil', 'Nome', 'Telefone (opcional)', 'Foto de perfil', 'Salvar perfil', 'Alterar senha', 'Senha atual', 'Nova senha', 'Confirmar nova senha', 'Atualizar senha']],
]);

it('uses localized field names for required validation', function (string $locale, string $field, string $message) {
    app()->setLocale($locale);
    $component = Livewire::actingAs(User::factory()->create())->test('profile.settings');
    $component->set($field, '')->assertHasErrors([$field => 'required']);

    expect($component->instance()->getErrorBag()->first($field))->toBe($message);
})->with([
    ['en', 'name', 'The Name field is required.'],
    ['pt_BR', 'name', 'O campo Nome é obrigatório.'],
    ['en', 'currentPassword', 'The Current password field is required.'],
    ['pt_BR', 'currentPassword', 'O campo Senha atual é obrigatório.'],
    ['en', 'newPassword', 'The New password field is required.'],
    ['pt_BR', 'newPassword', 'O campo Nova senha é obrigatório.'],
    ['en', 'newPasswordConfirm', 'The Confirm new password field is required.'],
    ['pt_BR', 'newPasswordConfirm', 'O campo Confirmar nova senha é obrigatório.'],
]);

it('localizes mutation feedback without changing password or profile authorization', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $user = User::factory()->create(['password' => 'original-password']);
    $before = $user->fresh()->getAttributes();
    $component = Livewire::actingAs($user)->test('profile.settings')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-password')
        ->set('newPasswordConfirm', 'new-password')
        ->call('updatePassword')->assertHasErrors(['currentPassword'])->assertNotDispatched('notification');
    expect($component->instance()->getErrorBag()->first('currentPassword'))->toBe($copy['wrong']);
    expect($user->fresh()->getAttributes() === $before)->toBeTrue('Invalid password must not mutate the user.');

    $component->set('currentPassword', 'original-password')->set('newPasswordConfirm', 'different-password')
        ->call('updatePassword')->assertHasErrors(['newPasswordConfirm'])->assertNotDispatched('notification');
    expect($component->instance()->getErrorBag()->first('newPasswordConfirm'))->toBe($copy['mismatch']);
    expect($user->fresh()->getAttributes() === $before)->toBeTrue('Mismatched confirmation must not mutate the user.');

    $component->set('newPasswordConfirm', 'new-password')->call('updatePassword')->assertHasNoErrors()
        ->assertDispatched('notification', ['message' => $copy['password'], 'type' => 'success']);
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    $component->set('name', 'Updated profile')->call('updateProfile')->assertHasNoErrors()
        ->assertDispatched('notification', ['message' => $copy['profile'], 'type' => 'success']);
    expect($user->fresh()->name)->toBe('Updated profile');
})->with([
    ['en', ['wrong' => 'The current password is incorrect.', 'mismatch' => 'The new passwords do not match.', 'password' => 'Password updated successfully.', 'profile' => 'Profile updated successfully.']],
    ['pt_BR', ['wrong' => 'A senha atual está incorreta.', 'mismatch' => 'As novas senhas não coincidem.', 'password' => 'Senha atualizada com sucesso.', 'profile' => 'Perfil atualizado com sucesso.']],
]);
