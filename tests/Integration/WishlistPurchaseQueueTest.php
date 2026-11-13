<?php

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['queue.default' => 'database']);
    $this->owner = User::factory()->create(['name' => 'Owner Canary', 'email' => 'owner@example.test']);
    $this->buyer = User::factory()->create(['name' => 'Buyer Canary', 'email' => 'buyer@example.test']);
    $this->wishlist = Wishlist::factory()->create([
        'user_id' => $this->owner->id, 'privacy' => 'SPECIFIC', 'name' => 'List Canary',
    ]);
    WishlistShare::create(['wishlist_id' => $this->wishlist->id, 'shared_with_user_id' => $this->buyer->id, 'access_level' => 'VIEW']);
    $this->item = WishlistItem::factory()->create([
        'wishlist_id' => $this->wishlist->id, 'name' => 'Item Canary',
        'description' => 'Description Canary', 'notes' => 'Notes Canary',
        'url' => 'https://example.test/hidden-gift',
    ]);
});

it('queues only committed purchases and delivers a generic owner-localized message through a real worker', function (string $locale, string $subject) {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale === 'en' ? 'pt_BR' : 'en');
    DB::beginTransaction();
    expect($this->item->markAsBought($this->buyer))->toBeTrue()
        ->and(DB::table('jobs')->count())->toBe(0);
    DB::commit();

    expect(DB::table('jobs')->count())->toBe(1);
    $queued = DB::table('jobs')->sole();
    $payload = json_decode($queued->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($queued->queue)->toBe('mail')
        ->and($payload['maxTries'])->toBe(3)
        ->and($payload['timeout'])->toBe(30)
        ->and($payload['backoff'])->toBe('10,60,180');
    foreach (['Owner Canary', 'Buyer Canary', 'Item Canary', 'owner@example.test', 'buyer@example.test', 'hidden-gift'] as $canary) {
        expect($queued->payload)->not->toContain($canary);
    }

    // Resolve the recipient's current address, never a captured queue payload address.
    $this->owner->update(['email' => 'current-owner@example.test']);
    config(['app.url' => 'http://127.0.0.1:8123']);
    (new SetRequestForConsole)->bootstrap($this->app);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(0);
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $message = $transport->messages()->sole()->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe('current-owner@example.test')
        ->and($message->getSubject())->toBe($subject)
        ->and($message->getHtmlBody())->toContain("http://127.0.0.1:8123/wishlists/{$this->wishlist->id}")
        ->and($message->getTextBody())->toBeString()->toContain("http://127.0.0.1:8123/wishlists/{$this->wishlist->id}");
    foreach (['Owner Canary', 'Buyer Canary', 'List Canary', 'Item Canary', 'Description Canary', 'Notes Canary', 'buyer@example.test', 'hidden-gift', 'bought_by_user_id', 'bought_at'] as $canary) {
        expect($message->getHtmlBody())->not->toContain($canary);
        expect($message->getTextBody())->not->toContain($canary);
    }
    expect(app()->getLocale())->toBe($locale === 'en' ? 'pt_BR' : 'en');
})->with([
    ['en', 'Someone bought a gift for you!'],
    ['pt_BR', 'Alguém comprou um presente para você!'],
]);

it('publishes no purchase notice when the transaction rolls back', function () {
    DB::beginTransaction();
    expect($this->item->markAsBought($this->buyer))->toBeTrue()
        ->and(DB::table('jobs')->count())->toBe(0);
    DB::rollBack();
    expect($this->item->fresh()->status)->toBe('AVAILABLE')
        ->and($this->item->fresh()->bought_by_user_id)->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('publishes one purchase notice only for the winning buyer', function () {
    $secondBuyer = User::factory()->create();
    WishlistShare::create(['wishlist_id' => $this->wishlist->id, 'shared_with_user_id' => $secondBuyer->id, 'access_level' => 'VIEW']);
    $staleItem = $this->item->fresh();
    expect($this->item->markAsBought($this->buyer))->toBeTrue()
        ->and($staleItem->markAsBought($secondBuyer))->toBeFalse()
        ->and($this->item->fresh()->bought_by_user_id)->toBe($this->buyer->id)
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('does not deliver stale purchases or redirect notices to another owner or list', function (string $change) {
    expect($this->item->markAsBought($this->buyer))->toBeTrue();
    $other = User::factory()->create();
    $otherList = Wishlist::factory()->create(['user_id' => $this->owner->id]);
    match ($change) {
        'item-deleted' => $this->item->delete(),
        'item-available' => $this->item->update(['status' => 'AVAILABLE']),
        'item-moved' => $this->item->update(['wishlist_id' => $otherList->id]),
        'list-deleted' => $this->wishlist->delete(),
        'list-archived' => $this->wishlist->update(['is_archived' => true]),
        'owner-changed' => $this->wishlist->update(['user_id' => $other->id]),
        'owner-deleted' => $this->owner->delete(),
    };

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(0);
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    expect($transport->messages())->toHaveCount(0);
})->with(['item-deleted', 'item-available', 'item-moved', 'list-deleted', 'list-archived', 'owner-changed', 'owner-deleted']);

it('uses the fresh supported locale or English fallback even after a gift is received', function (string $locale, string $subject) {
    expect($this->item->markAsBought($this->buyer))->toBeTrue();
    $this->owner->update(['locale' => $locale]);
    $this->item->update(['status' => 'RECEIVED']);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])->assertSuccessful();
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    expect($transport->messages()->sole()->getOriginalMessage()->getSubject())->toBe($subject)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
})->with([
    ['pt_BR', 'Alguém comprou um presente para você!'],
    ['unsupported', 'Someone bought a gift for you!'],
]);
