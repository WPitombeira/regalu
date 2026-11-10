<?php

use App\Models\User;
use App\Services\NotificationService;
use Livewire\Livewire;

function publicPageSnapshot(string $html, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException('The page did not mount '.$component);
}

it('does not disclose home page notifications to a revoked session', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    NotificationService::send($user, 'system', 'Private notification sentinel', 'Private message sentinel');
    $this->actingAs($user)->get(route('settings'))->assertOk();
    $oldFingerprint = session('password_hash_web');
    $user->forceFill(['password' => 'new-password'])->save();

    $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldFingerprint])
        ->get(route('home'))->assertRedirect(route('login'))
        ->assertDontSee('Private notification sentinel')->assertDontSee('Private message sentinel');
    $this->assertGuest();
});

it('checks home bell HTTP updates against the original session fingerprint', function (bool $revoked) {
    $user = User::factory()->create(['password' => 'old-password']);
    $notice = NotificationService::send($user, 'system', 'Private notification sentinel', 'Private message sentinel');
    $this->actingAs($user)->get(route('settings'))->assertOk();
    $oldFingerprint = session('password_hash_web');
    $home = $this->get(route('home'))->assertOk()->assertSee('Private notification sentinel');
    $snapshot = publicPageSnapshot($home->getContent(), 'notifications.notification-bell');

    if ($revoked) {
        $user->forceFill(['password' => 'new-password'])->save();
    }

    $response = $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldFingerprint])
        ->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'markAllRead', 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true']);

    if ($revoked) {
        $response->assertUnauthorized()->assertDontSee('Private message sentinel');
        $this->assertGuest();
        expect($notice->fresh()->is_read)->toBeFalse();
    } else {
        $response->assertOk();
        expect($notice->fresh()->is_read)->toBeTrue();
    }
})->with(['valid session' => false, 'revoked session' => true]);

it('keeps the public home and login pages accessible to guests without notifications', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('notifications.notification-bell');
    $this->get(route('login'))->assertOk();
    $this->assertGuest();
});

it('returns newly registered guests to the intended shared destination over HTTP', function (?string $destination) {
    $intended = match ($destination) {
        'event' => route('amigo-secreto.public-join', ['code' => 'invitation-code']),
        'wishlist' => route('wishlists.public', ['wishlist' => 123]),
        default => null,
    };
    if ($intended !== null) {
        $this->get($intended)->assertRedirect(route('login'));
        expect(session('url.intended'))->toBe($intended);
    }
    $login = $this->get(route('login'))->assertOk();
    $snapshot = publicPageSnapshot($login->getContent(), 'home.login-form');

    $this->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [
                'registerName' => 'New invitee',
                'registerEmail' => 'invitee@example.com',
                'registerPassword' => 'new-test-password',
                'registerPasswordConfirm' => 'new-test-password',
            ],
            'calls' => [['path' => '', 'method' => 'register', 'params' => []]],
        ]],
    ], ['X-Livewire' => 'true'])->assertOk()
        ->assertJsonPath('components.0.effects.redirect', $intended ?? route('dashboard'));

    $this->assertAuthenticatedAs(User::where('email', 'invitee@example.com')->sole());
    expect(session()->has('url.intended'))->toBeFalse();
})->with(['event invitation' => 'event', 'shared wishlist' => 'wishlist', 'dashboard fallback' => null]);
