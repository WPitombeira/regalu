<?php

use App\Livewire\AmigoSecreto\AssignmentWishlist;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->buyer = User::factory()->create();
    $this->recipient = User::factory()->create(['name' => 'HiddenRecipientSentinel', 'email' => 'hidden-recipient@example.test']);
    $this->event = AmigoSecretoEvent::factory()->create([
        'status' => 'DRAWS_COMPLETE', 'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
        'reveal_date' => now()->addWeek(),
    ]);
    $this->wishlist = Wishlist::factory()->create([
        'user_id' => $this->recipient->id, 'privacy' => 'PRIVATE',
        'name' => 'IdentifyingWishlistTitle', 'description' => 'IdentifyingWishlistDescription',
    ]);
    $this->item = WishlistItem::factory()->create([
        'wishlist_id' => $this->wishlist->id, 'name' => 'A useful gift',
        'description' => 'IdentifyingItemDescription', 'notes' => 'IdentifyingItemNotes',
    ]);
    $this->buyerParticipant = AmigoSecretoParticipant::create([
        'event_id' => $this->event->id, 'user_id' => $this->buyer->id, 'status' => 'ACCEPTED',
    ]);
    $this->recipientParticipant = AmigoSecretoParticipant::create([
        'event_id' => $this->event->id, 'user_id' => $this->recipient->id, 'status' => 'ACCEPTED',
        'wishlist_id' => $this->wishlist->id,
    ]);
    $this->draw = AmigoSecretoDraw::create([
        'event_id' => $this->event->id, 'drawer_user_id' => $this->buyer->id,
        'target_user_id' => $this->recipient->id, 'draw_date' => now(),
    ]);
});

it('provides an anonymous shopping page without granting general access to the private list', function () {
    $this->actingAs($this->buyer)->get('/secret-santa/'.$this->event->id.'/assignment')
        ->assertOk()->assertSee('A useful gift')
        ->assertDontSee('HiddenRecipientSentinel')
        ->assertDontSee('hidden-recipient@example.test')
        ->assertDontSee('IdentifyingWishlistTitle')
        ->assertDontSee('IdentifyingWishlistDescription')
        ->assertDontSee('IdentifyingItemDescription')
        ->assertDontSee('IdentifyingItemNotes')
        ->assertDontSee('target_user_id')->assertDontSee('wishlist_id')->assertDontSee('bought_by_user_id');

    $this->get(route('wishlists.show', $this->wishlist))->assertForbidden();
});

it('does not leak a hidden recipient through the existing assignment surfaces', function (string $route) {
    $this->actingAs($this->buyer)->get(route($route, $this->event))
        ->assertOk()->assertDontSee('HiddenRecipientSentinel')
        ->assertDontSee('hidden-recipient@example.test')
        ->assertSee('/secret-santa/'.$this->event->id.'/assignment', false);
})->with(['amigo-secreto.show', 'amigo-secreto.draw']);

it('lets the assigned buyer mark a gift bought using an opaque event-scoped reference', function () {
    $component = Livewire::actingAs($this->buyer)->test(AssignmentWishlist::class, ['event' => $this->event])
        ->assertSee('markAsBought', false);
    $item = $component->viewData('items')->first();
    expect($item)->toBeArray()->not->toHaveKeys(['id', 'wishlist_id', 'bought_by_user_id']);

    $component->call('markAsBought', $item['token'])
        ->assertHasNoErrors()->assertSee(__('assignments.bought'))
        ->assertDontSee('HiddenRecipientSentinel');
    expect($this->item->fresh()->status)->toBe('BOUGHT');
    expect($this->item->fresh()->bought_by_user_id)->toBe($this->buyer->id);

    $component->call('markAsBought', $item['token'])->assertHasNoErrors();
    expect($this->item->fresh()->bought_by_user_id)->toBe($this->buyer->id);
});

it('rejects a stale purchase after event or participation access is revoked', function (string $change, int $status) {
    $component = Livewire::actingAs($this->buyer)->test(AssignmentWishlist::class, ['event' => $this->event]);
    $token = $component->viewData('items')->first()['token'];
    match ($change) {
        'event' => $this->event->update(['is_archived' => true]),
        'completed' => $this->event->update(['status' => 'COMPLETED']),
        'buyer' => $this->buyerParticipant->update(['status' => 'WITHDRAWN']),
        'recipient' => $this->recipientParticipant->update(['status' => 'WITHDRAWN']),
        'consent' => $this->recipientParticipant->update(['wishlist_id' => null]),
        'list' => $this->wishlist->update(['is_archived' => true]),
        'deleted' => $this->wishlist->delete(),
        'draw' => $this->draw->delete(),
    };
    $component->call('markAsBought', $token)->assertStatus($status);
    if ($change !== 'deleted') {
        expect($this->item->fresh()->status)->toBe('AVAILABLE');
    }
})->with([
    ['event', 403], ['completed', 404], ['buyer', 403], ['recipient', 404], ['consent', 404],
    ['list', 404], ['deleted', 404], ['draw', 404],
]);

it('rejects authorization revoked at the final purchase write', function (string $change) {
    $component = Livewire::actingAs($this->buyer)->test(AssignmentWishlist::class, ['event' => $this->event]);
    $token = $component->viewData('items')->first()['token'];
    $otherList = Wishlist::factory()->create(['user_id' => $this->recipient->id]);
    $revoked = false;

    // Deterministically interleave a real database revocation after every read,
    // immediately before SQLite executes the purchase's final UPDATE.
    DB::connection()->beforeExecuting(function (string $sql) use ($change, $otherList, &$revoked): void {
        if ($revoked || ! str_starts_with($sql, 'update "wishlist_items" set ')) {
            return;
        }
        $revoked = true;
        match ($change) {
            'consent' => $this->recipientParticipant->update(['wishlist_id' => null]),
            'selected-list' => $this->recipientParticipant->update(['wishlist_id' => $otherList->id]),
            'list' => $this->wishlist->update(['is_archived' => true]),
            'owner' => $this->wishlist->update(['user_id' => $this->buyer->id]),
            'buyer' => $this->buyerParticipant->update(['status' => 'WITHDRAWN']),
            'recipient' => $this->recipientParticipant->update(['status' => 'WITHDRAWN']),
            'event' => $this->event->update(['is_archived' => true]),
            'event-status' => $this->event->update(['status' => 'PLANNING']),
            'completed' => $this->event->update(['status' => 'COMPLETED']),
            'draw' => $this->draw->delete(),
            'reassigned' => $this->draw->update(['target_user_id' => $this->event->organizer_id]),
            'self' => $this->draw->update(['target_user_id' => $this->buyer->id]),
            'item-list' => $this->item->update(['wishlist_id' => $otherList->id]),
        };
    });

    $component->call('markAsBought', $token);
    expect($revoked)->toBeTrue();
    expect($this->item->fresh()->status)->toBe('AVAILABLE');
    expect($this->item->fresh()->bought_by_user_id)->toBeNull();
    expect($this->item->fresh()->bought_at)->toBeNull();
    $component->assertNotFound();
})->with([
    'consent', 'selected-list', 'list', 'owner', 'buyer', 'recipient',
    'event', 'event-status', 'completed', 'draw', 'reassigned', 'self', 'item-list',
]);

it('ends event wishlist access at completion without changing the permitted identity summary', function () {
    $component = Livewire::actingAs($this->buyer)->test(AssignmentWishlist::class, ['event' => $this->event]);
    $this->event->update(['status' => 'COMPLETED', 'reveal_date' => today()]);
    $component->call('$refresh')->assertSee('HiddenRecipientSentinel')
        ->assertDontSee('A useful gift')->assertDontSee('markAsBought', false);
    expect($component->viewData('items'))->toBeNull();
    $this->actingAs($this->buyer)->get(route('amigo-secreto.assignment', $this->event))
        ->assertOk()->assertSee('HiddenRecipientSentinel')->assertDontSee('A useful gift');
    $this->get(route('wishlists.show', $this->wishlist))->assertForbidden();
});

it('does not let hosts or unrelated participants inspect someone else’s assignment', function (string $role) {
    $user = $role === 'host' ? $this->event->organizer : User::factory()->create();
    if ($role === 'participant') {
        AmigoSecretoParticipant::create(['event_id' => $this->event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    $this->actingAs($user)->get(route('amigo-secreto.assignment', $this->event))
        ->assertStatus($role === 'stranger' ? 403 : 404);
})->with(['host', 'participant', 'stranger']);

it('shows the recipient only when the configured visibility permits it', function (string $visibility, string $status, bool $past, bool $visible) {
    $this->event->update([
        'recipient_visibility' => $visibility, 'status' => $status,
        'reveal_date' => $past ? today()->subDay() : today()->addWeek(),
        'organizer_id' => $this->buyer->id,
    ]);
    foreach (['amigo-secreto.assignment', 'amigo-secreto.show', 'amigo-secreto.draw'] as $route) {
        $response = $this->actingAs($this->buyer)->get(route($route, $this->event))->assertOk();
        $visible ? $response->assertSee('HiddenRecipientSentinel') : $response->assertDontSee('HiddenRecipientSentinel');
    }
})->with([
    ['NAMED', 'DRAWS_COMPLETE', false, true],
    ['ANONYMOUS_UNTIL_REVEAL', 'DRAWS_COMPLETE', true, false],
    ['ANONYMOUS_UNTIL_REVEAL', 'REVEALED', false, false],
    ['ANONYMOUS_UNTIL_REVEAL', 'REVEALED', true, true],
    ['ANONYMOUS_UNTIL_REVEAL', 'COMPLETED', true, true],
]);

it('rejects tampered references and references to a different event or list', function (string $reference) {
    $foreign = WishlistItem::factory()->create();
    $token = match ($reference) {
        'tampered' => 'not-an-authentic-reference',
        'event' => Crypt::encryptString('999:'.$this->buyer->id.':'.$this->item->id),
        'buyer' => Crypt::encryptString($this->event->id.':999:'.$this->item->id),
        'item' => Crypt::encryptString($this->event->id.':'.$this->buyer->id.':'.$foreign->id),
    };
    Livewire::actingAs($this->buyer)->test(AssignmentWishlist::class, ['event' => $this->event])
        ->call('markAsBought', $token)->assertNotFound();
    expect($this->item->fresh()->status)->toBe('AVAILABLE');
    expect($foreign->fresh()->status)->toBe('AVAILABLE');
})->with(['tampered', 'event', 'buyer', 'item']);

it('protects assignment purchases through the real Livewire endpoint without serializing the recipient', function (bool $revokeSession) {
    $page = $this->actingAs($this->buyer)->get(route('amigo-secreto.assignment', $this->event))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ->first(fn ($value) => json_decode($value, true)['memo']['name'] === 'amigo-secreto.assignment-wishlist');
    expect($snapshot)->toBeString()->not->toContain('target_user_id', 'wishlist_id', 'HiddenRecipientSentinel');
    preg_match('/markAsBought\(\'([^\']+)\'\)/', $page->getContent(), $token);
    $sessionHash = session('password_hash_web');
    if ($revokeSession) {
        $this->buyer->update(['password' => 'changed-session-password']);
    }
    $response = $this->actingAs($this->buyer->fresh())->withSession(['password_hash_web' => $sessionHash])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => 'markAsBought', 'params' => [$token[1]]]],
        ]]], ['X-Livewire' => 'true']);
    $response->assertStatus($revokeSession ? 401 : 200)->assertDontSee('HiddenRecipientSentinel');
    expect($this->item->fresh()->status)->toBe($revokeSession ? 'AVAILABLE' : 'BOUGHT');
})->with([false, true]);
