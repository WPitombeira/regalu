<?php

use App\Livewire\Dashboard;
use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Notifications\NotificationList;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\NotificationService;
use Livewire\Livewire;

it('provides keyboard-operable notification actions with current ownership checks on every surface', function (string $surface) {
    $owner = User::factory()->create();
    $list = Wishlist::factory()->create(['user_id' => $owner->id]);
    NotificationService::wishlistPurchased($list, 123);
    $notice = Notification::sole();
    $foreign = NotificationService::send(User::factory()->create(), 'system', 'Foreign notice sentinel', 'Private');
    $component = Livewire::actingAs($owner)->test($surface)->assertSee(__('inbox.wishlist_purchase_title'))->assertDontSee($foreign->title);
    $dom = new DOMDocument;
    @$dom->loadHTML($component->html());
    $buttons = (new DOMXPath($dom))->query('//button[@*[name()="wire:click"]="markAsRead('.$notice->id.')"]');
    expect($buttons->length)->toBe(1);
    $component->call('markAsRead', $foreign->id)->assertNoRedirect();
    expect($foreign->fresh()->is_read)->toBeFalse();
    $component->call('markAsRead', $notice->id)->assertRedirect(route('wishlists.show', $list));
    expect($notice->fresh()->is_read)->toBeTrue();
    $component = Livewire::actingAs($owner)->test($surface);
    $list->update(['is_archived' => true]);
    $component->call('markAsRead', $notice->id)->assertNoRedirect()->assertSee(__('inbox.unavailable'));
})->with([Dashboard::class, NotificationBell::class, NotificationList::class]);
