<?php

use App\Models\User;
use Livewire\Livewire;

function profileSettingsDom(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
}

it('associates each rendered field error with its uniquely labelled control', function (string $locale, string $field, string $value) {
    app()->setLocale($locale);
    $component = Livewire::actingAs(User::factory()->create())->test('profile.settings')->set($field, $value)->assertHasErrors([$field]);
    $xpath = profileSettingsDom($component->html());
    $controls = $xpath->query('//*[@id="'.$field.'"]');
    expect($controls->length)->toBe(1);
    $control = $controls->item(0);
    expect($control)->toBeInstanceOf(DOMElement::class);
    assert($control instanceof DOMElement);
    expect($control->getAttribute('aria-invalid'))->toBe('true');
    expect($xpath->query('//label[@for="'.$field.'"]')->length)->toBe(1);
    $descriptionIds = explode(' ', $control->getAttribute('aria-describedby'));
    expect($descriptionIds)->toContain($field.'-error');
    foreach ($descriptionIds as $id) {
        $descriptions = $xpath->query('//*[@id="'.$id.'"]');
        expect($descriptions->length)->toBe(1);
        expect(trim($descriptions->item(0)->textContent))->not->toBe('');
    }
    $error = $xpath->query('//*[@id="'.$field.'-error"]')->item(0);
    expect($error)->toBeInstanceOf(DOMElement::class);
    assert($error instanceof DOMElement);
    expect($error->getAttribute('role'))->toBe('alert');
    expect(trim($error->textContent))->toBe($component->instance()->getErrorBag()->first($field));
})->with(['en', 'pt_BR'])->with([
    ['name', ''], ['phone', str_repeat('1', 21)], ['currentPassword', ''], ['newPassword', ''], ['newPasswordConfirm', ''],
]);

it('serves an honest no-JavaScript notice with both Livewire-only forms disabled', function (string $locale, string $notice) {
    app()->setLocale($locale);
    $html = Livewire::actingAs(User::factory()->create())->test('profile.settings')->html();
    $xpath = profileSettingsDom($html);
    expect(trim($xpath->query('//noscript')->item(0)?->textContent ?? ''))->toBe($notice);
    expect($xpath->query('//form/fieldset[@disabled]')->length)->toBe(2);
    expect($xpath->query('//form/fieldset[@disabled]//input')->length)->toBe(6);
    expect($xpath->query('//form/fieldset[@disabled]//button[@type="submit"]')->length)->toBe(2);
})->with([
    ['en', 'Enable JavaScript to edit your profile or change your password. No changes can be submitted while JavaScript is disabled.'],
    ['pt_BR', 'Ative o JavaScript para editar seu perfil ou alterar sua senha. Nenhuma alteração pode ser enviada enquanto o JavaScript estiver desativado.'],
]);

it('exposes native input constraints and localized guidance before submission', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $html = Livewire::actingAs(User::factory()->create(['avatar_url' => '/storage/avatars/existing.png']))->test('profile.settings')->html();
    $xpath = profileSettingsDom($html);
    $expected = [
        'name' => ['type' => 'text', 'autocomplete' => 'name', 'required' => '', 'minlength' => '2', 'maxlength' => '255'],
        'phone' => ['type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => '20'],
        'avatar' => ['type' => 'file', 'accept' => 'image/*'],
        'currentPassword' => ['type' => 'password', 'autocomplete' => 'current-password', 'required' => ''],
        'newPassword' => ['type' => 'password', 'autocomplete' => 'new-password', 'required' => '', 'minlength' => '8'],
        'newPasswordConfirm' => ['type' => 'password', 'autocomplete' => 'new-password', 'required' => '', 'minlength' => '8'],
    ];
    foreach ($expected as $id => $attributes) {
        $control = $xpath->query('//input[@id="'.$id.'"]')->item(0);
        expect($control)->toBeInstanceOf(DOMElement::class);
        assert($control instanceof DOMElement);
        foreach ($attributes as $name => $value) {
            expect($control->hasAttribute($name))->toBeTrue($id.' requires '.$name);
            expect($control->getAttribute($name))->toBe($value);
        }
        expect($control->getAttribute('aria-invalid'))->toBe('false');
    }
    foreach (['avatar', 'newPassword'] as $id) {
        $control = $xpath->query('//input[@id="'.$id.'"]')->item(0);
        assert($control instanceof DOMElement);
        $description = $control->getAttribute('aria-describedby');
        expect($description)->toBe($id.'-hint');
        expect(trim($xpath->query('//*[@id="'.$description.'"]')->item(0)?->textContent ?? ''))->toBe($copy[$id]);
    }
    $image = $xpath->query('//img')->item(0);
    expect($image)->toBeInstanceOf(DOMElement::class);
    assert($image instanceof DOMElement);
    expect($image->getAttribute('alt'))->toBe($copy['alt']);
})->with([
    ['en', ['avatar' => 'Choose an image up to 2 MB. Save your profile to apply it.', 'newPassword' => 'Use at least 8 characters.', 'alt' => 'Current profile photo']],
    ['pt_BR', ['avatar' => 'Escolha uma imagem de até 2 MB. Salve o perfil para aplicá-la.', 'newPassword' => 'Use pelo menos 8 caracteres.', 'alt' => 'Foto de perfil atual']],
]);
