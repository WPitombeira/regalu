<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function profilePhotoFixture(): string
{
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==', true);
    assert(is_string($bytes));

    return $bytes;
}

it('renders a rejected non-image upload without losing the current photo or changing the profile', function (string $locale, string $message) {
    app()->setLocale($locale);
    Storage::fake('local');
    Storage::fake('public');
    $user = User::factory()->create(['avatar_url' => '/storage/avatars/current.png']);
    $before = $user->fresh()->getAttributes();

    $component = Livewire::actingAs($user)->test('profile.settings')
        ->set('avatar', UploadedFile::fake()->createWithContent('not-image.txt', 'not an image'))
        ->assertOk()
        ->assertHasErrors(['avatar' => 'image'])
        ->assertNotDispatched('notification');

    expect(str_contains($component->html(), $message))->toBeTrue();
    expect(str_contains($component->html(), '/storage/avatars/current.png'))->toBeTrue();
    $component->call('updateProfile')->assertHasErrors(['avatar' => 'image'])->assertNotDispatched('notification');
    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    ['en', 'The Profile photo field must be an image.'],
    ['pt_BR', 'O campo Foto de perfil deve ser uma imagem.'],
]);

it('keeps invalid previewable uploads out of the preview and public storage', function (string $kind, string $rule) {
    Storage::fake('local');
    Storage::fake('public');
    $user = User::factory()->create(['avatar_url' => '/storage/avatars/current.png']);
    $before = $user->fresh()->getAttributes();
    $upload = $kind === 'svg'
        ? UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" /></svg>')
        : UploadedFile::fake()->createWithContent('large.png', profilePhotoFixture().str_repeat('x', 2048 * 1024));

    $component = Livewire::actingAs($user)->test('profile.settings')->set('avatar', $upload)
        ->assertOk()->assertHasErrors(['avatar' => $rule]);
    expect(str_contains($component->html(), '/livewire/preview-file/'))->toBeFalse();
    expect(str_contains($component->html(), '/storage/avatars/current.png'))->toBeTrue();
    $component->call('updateProfile')->assertHasErrors(['avatar' => $rule])->assertNotDispatched('notification');
    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([['svg', 'image'], ['oversize', 'max']]);

it('previews a valid photo and saves its exact bytes only after the profile action', function (string $locale, string $preview, string $current) {
    app()->setLocale($locale);
    Storage::fake('local');
    Storage::fake('public');
    $user = User::factory()->create(['avatar_url' => '/storage/avatars/current.png']);
    $before = $user->fresh()->getAttributes();
    $component = Livewire::actingAs($user)->test('profile.settings')
        ->set('avatar', UploadedFile::fake()->createWithContent('photo.png', profilePhotoFixture()))
        ->assertOk()->assertHasNoErrors();
    expect(str_contains($component->html(), 'alt="'.$preview.'"'))->toBeTrue();
    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    expect(Storage::disk('public')->allFiles())->toBe([]);

    $component->call('updateProfile')->assertHasNoErrors()->assertSet('avatar', null)->assertDispatched('notification');
    $files = Storage::disk('public')->allFiles();
    expect($files)->toHaveCount(1);
    expect(Storage::disk('public')->get($files[0]))->toBe(profilePhotoFixture());
    expect($user->fresh()->avatar_url)->toBe('/storage/'.$files[0]);
    expect(str_contains($component->html(), 'alt="'.$current.'"'))->toBeTrue();
})->with([
    ['en', 'Preview of the selected profile photo', 'Current profile photo'],
    ['pt_BR', 'Prévia da foto de perfil selecionada', 'Foto de perfil atual'],
]);
