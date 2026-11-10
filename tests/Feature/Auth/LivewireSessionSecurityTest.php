<?php

use App\Models\User;
use Livewire\Livewire;

function settingsSnapshot(string $html): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

        if ($data['memo']['name'] === 'profile.settings') {
            return $snapshot;
        }
    }

    throw new RuntimeException('The settings page did not mount profile.settings.');
}

it('checks the password generation before a real Livewire mutation', function (bool $passwordChanged) {
    $user = User::factory()->create(['name' => 'Original name', 'password' => 'old-password']);
    $response = $this->actingAs($user)->get(route('settings'))->assertOk();
    $snapshot = settingsSnapshot($response->getContent());
    $sessionHash = session('password_hash_web');
    expect($sessionHash)->toBeString()->not->toBeEmpty();

    if ($passwordChanged) {
        $user->forceFill(['password' => 'new-password'])->save();
    }

    // A fresh principal models authentication loading the database on the next request.
    $response = $this->actingAs($user->fresh())
        ->withSession(['password_hash_web' => $sessionHash])
        ->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['name' => 'Updated name'],
                'calls' => [['path' => '', 'method' => 'updateProfile', 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true']);

    if ($passwordChanged) {
        $response->assertUnauthorized();
        $this->assertGuest();
        expect($user->fresh()->name)->toBe('Original name');
    } else {
        $response->assertOk();
        $this->assertAuthenticatedAs($user);
        expect($user->fresh()->name)->toBe('Updated name');
    }
})->with(['current session' => false, 'revoked session' => true]);

it('keeps the password-changing Livewire session usable without reviving older sessions', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $page = $this->actingAs($user)->get(route('settings'))->assertOk();
    $oldSessionHash = session('password_hash_web');

    $response = $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => settingsSnapshot($page->getContent()),
            'updates' => [
                'currentPassword' => 'old-password',
                'newPassword' => 'replacement-password',
                'newPasswordConfirm' => 'replacement-password',
            ],
            'calls' => [['path' => '', 'method' => 'updatePassword', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();

    expect(session('password_hash_web'))->not->toBe($oldSessionHash);

    $this->actingAs($user->fresh())->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $response->json('components.0.snapshot'),
            'updates' => ['name' => 'Still authenticated'],
            'calls' => [['path' => '', 'method' => 'updateProfile', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk();

    expect($user->fresh()->name)->toBe('Still authenticated');
    $this->withSession(['password_hash_web' => $oldSessionHash])
        ->get(route('settings'))->assertRedirect(route('login'));
    $this->assertGuest();
});
