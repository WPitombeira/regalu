<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToWriteFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

function avatarStoragePng(): string
{
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==', true);
    assert(is_string($bytes));

    return $bytes;
}

it('preserves the whole profile and draft when the avatar disk cannot write', function (string $locale, string $message, bool $throw) {
    app()->setLocale($locale);
    Storage::fake('local');
    $public = Storage::fake('public', ['throw' => $throw]);
    $public->put('avatars/current.png', avatarStoragePng());
    $user = User::factory()->create([
        'name' => 'Original Profile',
        'phone' => '1111111111',
        'avatar_url' => '/storage/avatars/current.png',
    ]);
    $before = $user->fresh()->getAttributes();
    $component = Livewire::actingAs($user)->test('profile.settings')
        ->set('name', 'Unsaved Profile')
        ->set('phone', '2222222222')
        ->set('avatar', UploadedFile::fake()->createWithContent('photo.png', avatarStoragePng()))
        ->assertHasNoErrors();
    $upload = $component->get('avatar');
    expect($upload)->toBeInstanceOf(TemporaryUploadedFile::class);
    $path = 'avatars/'.$upload->hashName();

    // A directory at the exact file destination forces a real write failure,
    // even under privileged runners; no mocked upload or application method.
    expect($public->makeDirectory($path))->toBeTrue();
    if ($throw) {
        expect(fn () => $public->put($path, 'failure precondition'))->toThrow(UnableToWriteFile::class);
    } else {
        expect($public->put($path, 'failure precondition'))->toBeFalse();
    }

    $component->call('updateProfile')->assertOk();
    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    $component->assertHasErrors(['avatar'])->assertSee($message)
        ->assertSet('name', 'Unsaved Profile')
        ->assertSet('phone', '2222222222')
        ->assertSet('avatarPreview', '/storage/avatars/current.png')
        ->assertNotDispatched('notification');
    expect($component->get('avatar'))->toBeInstanceOf(TemporaryUploadedFile::class);
    expect($public->get('avatars/current.png'))->toBe(avatarStoragePng());
    expect($public->allFiles())->toBe(['avatars/current.png']);

    // Repair the real disk, then retry without selecting the upload again.
    expect($public->deleteDirectory($path))->toBeTrue();
    $component->call('updateProfile')->assertOk()->assertHasNoErrors()
        ->assertSet('avatar', null)
        ->assertSet('avatarPreview', '/storage/'.$path)
        ->assertDispatched('notification');
    $saved = $user->fresh();
    expect($saved->name)->toBe('Unsaved Profile');
    expect($saved->phone)->toBe('2222222222');
    expect($saved->avatar_url)->toBe('/storage/'.$path);
    expect($public->get($path))->toBe(avatarStoragePng());
    expect($public->get('avatars/current.png'))->toBe(avatarStoragePng());
    expect($saved->password === $before['password'])->toBeTrue();
    expect($saved->remember_token === $before['remember_token'])->toBeTrue();
})->with([
    ['en', 'Your photo could not be saved. No profile changes were saved. Please try again.'],
    ['pt_BR', 'Não foi possível salvar sua foto. Nenhuma alteração no perfil foi salva. Tente novamente.'],
])->with([false, true]);

it('refuses a blocked avatar directory without changing the stored profile', function (bool $throw) {
    Storage::fake('local');
    $public = Storage::fake('public', ['throw' => $throw]);
    expect($public->put('current.png', avatarStoragePng()))->toBeTrue();
    expect($public->put('avatars', 'directory obstruction'))->toBeTrue();
    expect(fn () => $public->put('avatars/probe.png', avatarStoragePng()))
        ->toThrow(UnableToCreateDirectory::class);
    $user = User::factory()->create(['avatar_url' => '/storage/current.png']);
    $before = $user->fresh()->getAttributes();

    Livewire::actingAs($user)->test('profile.settings')
        ->set('name', 'Unsaved Profile')
        ->set('avatar', UploadedFile::fake()->createWithContent('photo.png', avatarStoragePng()))
        ->call('updateProfile')->assertOk()->assertHasErrors(['avatar'])
        ->assertNotDispatched('notification');

    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    expect($public->get('current.png'))->toBe(avatarStoragePng());
    expect($public->get('avatars'))->toBe('directory obstruction');
})->with([false, true]);

it('does not require avatar storage for a profile edit without a new photo', function () {
    $public = Storage::fake('public', ['throw' => true]);
    $public->put('avatars', 'directory obstruction');
    $user = User::factory()->create(['avatar_url' => '/storage/current.png']);

    Livewire::actingAs($user)->test('profile.settings')
        ->set('name', 'Updated Profile')->set('phone', '2222222222')
        ->call('updateProfile')->assertOk()->assertHasNoErrors()
        ->assertDispatched('notification');

    expect($user->fresh()->name)->toBe('Updated Profile');
    expect($user->fresh()->phone)->toBe('2222222222');
    expect($user->fresh()->avatar_url)->toBe('/storage/current.png');
    expect($public->allFiles())->toBe(['avatars']);
});

it('authorizes the avatar action again after logout before writing', function () {
    Storage::fake('local');
    $public = Storage::fake('public');
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('profile.settings')
        ->set('avatar', UploadedFile::fake()->createWithContent('photo.png', avatarStoragePng()));
    Auth::logout();
    // Logout rotates remember_token; measure the denied action after that change.
    $before = $user->fresh()->getAttributes();

    $component->call('updateProfile')->assertUnauthorized();

    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    expect($public->allFiles())->toBe([]);
});

it('validates profile fields before attempting an avatar write', function () {
    Storage::fake('local');
    $public = Storage::fake('public');
    $user = User::factory()->create();
    $before = $user->fresh()->getAttributes();

    Livewire::actingAs($user)->test('profile.settings')
        ->set('avatar', UploadedFile::fake()->createWithContent('photo.png', avatarStoragePng()))
        ->set('name', '')->call('updateProfile')->assertHasErrors(['name'])
        ->assertNotDispatched('notification');

    expect($user->fresh()->getAttributes() === $before)->toBeTrue();
    expect($public->allFiles())->toBe([]);
});
