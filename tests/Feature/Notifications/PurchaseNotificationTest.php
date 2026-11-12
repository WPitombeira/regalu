<?php

use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('records a generic owner notice exactly once for a successful ordinary purchase', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $buyer = User::factory()->create(['name' => 'Buyer identity sentinel']);
    $list = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'SPECIFIC']);
    WishlistShare::create(['wishlist_id' => $list->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $list->id, 'name' => 'Secret gift sentinel']);
    expect($item->markAsBought($buyer))->toBeTrue()
        ->and(Notification::count())->toBe(1);
    $notice = Notification::sole();
    expect($notice->user_id)->toBe($owner->id)
        ->and($notice->kind)->toBe('wishlist_purchase')
        ->and($notice->related_entity_type)->toBe(Wishlist::class)
        ->and($notice->related_entity_id)->toBe($list->id)
        ->and($notice->action_url)->toBeNull()
        ->and($notice->title.' '.$notice->message)->not->toContain($item->name, $buyer->name, $buyer->email);
    expect($item->markAsBought($buyer))->toBeFalse()->and(Notification::count())->toBe(1);
});

it('rolls back a purchase if its persistent notice cannot be stored', function () {
    Queue::fake();
    $buyer = User::factory()->create();
    $list = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    WishlistShare::create(['wishlist_id' => $list->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $list->id]);
    Event::listen('eloquent.creating: '.Notification::class, function (): void {
        throw new RuntimeException('Controlled notice storage failure');
    });
    expect(fn () => $item->markAsBought($buyer))->toThrow(RuntimeException::class, 'Controlled notice storage failure');
    expect($item->fresh()->status)->toBe('AVAILABLE')
        ->and($item->fresh()->bought_by_user_id)->toBeNull()
        ->and(Notification::count())->toBe(0);
    Queue::assertNothingPushed();
});
