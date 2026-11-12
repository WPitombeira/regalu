<?php

use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Notifications\NotificationList;
use App\Models\User;
use App\Services\NotificationService;
use Livewire\Livewire;

it('exposes the bell expansion state and supports escape with focus return', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(NotificationBell::class);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    $trigger = $xpath->query('//button[@*[name()="x-ref"]="trigger"]')->item(0);
    expect($trigger)->not->toBeNull();
    assert($trigger instanceof DOMElement);
    expect($trigger->getAttribute(':aria-expanded'))->toBe('open');
    expect($trigger->getAttribute(':aria-controls'))->toBe("\$id('notification-panel')");
    // libxml's HTML parser discards @-prefixed attributes; assert the rendered hook directly.
    $component->assertSeeHtml('@keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }"');
    $panel = $xpath->query('//*[@*[name()="x-show"]="open"]')->item(0);
    assert($panel instanceof DOMElement);
    expect($panel->getAttribute(':id'))->toBe("\$id('notification-panel')");
});

it('announces the current unread count in the bell accessible name', function (string $locale, int $count, string $label) {
    $user = User::factory()->create(['locale' => $locale]);
    app()->setLocale($locale);
    for ($i = 0; $i < $count; $i++) {
        NotificationService::send($user, 'system', 'Notice', 'Message');
    }
    Livewire::actingAs($user)->test(NotificationBell::class)->assertSeeHtml('aria-label="'.$label.'"');
})->with([
    ['en', 0, 'Notifications, no unread notifications'],
    ['en', 1, 'Notifications, 1 unread notification'],
    ['en', 2, 'Notifications, 2 unread notifications'],
    ['pt_BR', 0, 'Notificações, nenhuma não lida'],
    ['pt_BR', 1, 'Notificações, 1 não lida'],
    ['pt_BR', 2, 'Notificações, 2 não lidas'],
]);

it('exposes unread state in each notice button and removes it after reading', function (string $component, string $locale, string $label) {
    $user = User::factory()->create(['locale' => $locale]);
    app()->setLocale($locale);
    $notice = NotificationService::send($user, 'system', 'Notice', 'Message');
    $mounted = Livewire::actingAs($user)->test($component);
    $buttonText = function () use ($mounted, $notice): string {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$mounted->html());
        $xpath = new DOMXPath($document);
        $button = $xpath->query('//button[@*[name()="wire:click"]="markAsRead('.$notice->id.')"]')->item(0);
        expect($button)->not->toBeNull();

        return $button->textContent;
    };
    expect($buttonText())->toContain($label);
    $mounted->call('markAsRead', $notice->id)->assertNoRedirect();
    expect($notice->fresh()->is_read)->toBeTrue();
    expect($buttonText())->not->toContain($label);
})->with([NotificationBell::class, NotificationList::class])->with([
    ['en', 'Unread'], ['pt_BR', 'Não lida'],
]);
