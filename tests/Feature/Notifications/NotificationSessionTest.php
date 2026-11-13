<?php

use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\NotificationService;
use Livewire\Livewire;

function inboxSnapshot(string $html, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException('Expected inbox component was not mounted.');
}

it('protects real inbox actions with the current password session and resource grant', function (string $component, string $change) {
    $user = User::factory()->create(['password' => 'original-password']);
    $list = Wishlist::factory()->create(['user_id' => $user->id]);
    NotificationService::wishlistPurchased($list, 123);
    $notice = Notification::sole();
    $page = $this->actingAs($user)->get(route($component === 'notifications.notification-list' ? 'notifications.index' : 'dashboard'))->assertOk();
    $snapshot = inboxSnapshot($page->getContent(), $component);
    $fingerprint = session('password_hash_web');
    expect($fingerprint)->toBeString()->not->toBeEmpty();
    expect($snapshot)->not->toContain('related_entity_id', 'deduplication_key', 'bought_by_user_id');

    if ($change === 'password') {
        $user->update(['password' => 'replacement-password']);
    } elseif ($change === 'archive') {
        $list->update(['is_archived' => true]);
    }

    $response = $this->actingAs($user->fresh())->withSession(['password_hash_web' => $fingerprint])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['path' => '', 'method' => 'markAsRead', 'params' => [$notice->id]]],
        ]]], ['X-Livewire' => 'true']);

    if ($change === 'password') {
        $response->assertUnauthorized();
        $this->assertGuest();
        expect($notice->fresh()->is_read)->toBeFalse();
    } else {
        $response->assertOk();
        expect($notice->fresh()->is_read)->toBeTrue();
        if ($change === 'archive') {
            expect($response->json('components.0.effects.redirect'))->toBeNull();
        } else {
            expect($response->json('components.0.effects.redirect'))->toBe(route('wishlists.show', $list));
        }
    }
})->with(['dashboard', 'notifications.notification-bell', 'notifications.notification-list'])
    ->with(['current', 'password', 'archive']);
