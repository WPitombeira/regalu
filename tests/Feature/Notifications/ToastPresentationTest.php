<?php

use App\Livewire\Notifications;
use Livewire\Livewire;

it('gives each transient dismiss control a localized accessible name', function (string $locale, string $label) {
    app()->setLocale($locale);
    $component = Livewire::test(Notifications::class)
        ->call('__dispatch', 'notification', [['message' => 'Saved', 'type' => 'success']]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
    $buttons = (new DOMXPath($document))->query('//*[@role="alert"]//button');
    expect($buttons)->toHaveCount(1);
    $button = $buttons->item(0);
    expect($button)->toBeInstanceOf(DOMElement::class);
    assert($button instanceof DOMElement);
    expect($button->getAttribute('type'))->toBe('button')
        ->and($button->getAttribute('aria-label'))->toBe($label)
        ->and(trim($button->textContent))->toBe($label);
})->with([
    ['en', 'Dismiss notification'],
    ['pt_BR', 'Dispensar notificação'],
]);

it('keeps distinct stable toast identities when another message is appended', function () {
    $component = Livewire::test(Notifications::class)
        ->call('__dispatch', 'notification', [
            ['message' => 'First', 'type' => 'success'],
            ['message' => 'Second', 'type' => 'info'],
        ]);
    $identities = function () use ($component): array {
        $document = new DOMDocument;
        @$document->loadHTML($component->html());
        $cards = (new DOMXPath($document))->query('//*[@role="alert"]');
        $result = [];
        foreach ($cards as $card) {
            assert($card instanceof DOMElement);
            $result[] = [$card->getAttribute('id'), $card->getAttribute('wire:key')];
        }

        return $result;
    };
    $before = $identities();
    expect($before)->toHaveCount(2)
        ->and(array_unique(array_column($before, 0)))->toHaveCount(2)
        ->and(array_unique(array_column($before, 1)))->toHaveCount(2);
    foreach ($before as [$id, $key]) {
        expect($id)->not->toBe('')->and($key)->not->toBe('');
    }
    $component->call('__dispatch', 'notification', [['message' => 'Third', 'type' => 'warning']]);
    expect($identities())->toHaveCount(3)
        ->and(array_slice($identities(), 0, 2))->toBe($before);
});

it('announces the toast kind in the selected language including unknown kinds', function (string $locale, string $type, string $label) {
    app()->setLocale($locale);
    $component = Livewire::test(Notifications::class)
        ->call('__dispatch', 'notification', [['message' => 'Message', 'type' => $type]]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
    $xpath = new DOMXPath($document);
    $kind = $xpath->query('//*[@role="alert"]/div[1]/span')->item(0);
    expect($kind)->toBeInstanceOf(DOMElement::class);
    assert($kind instanceof DOMElement);
    expect(trim($kind->textContent))->toBe($label);
    $icons = $xpath->query('//*[@role="alert"]//*[local-name()="svg"]');
    expect($icons)->toHaveCount(2);
    foreach ($icons as $icon) {
        assert($icon instanceof DOMElement);
        expect($icon->getAttribute('aria-hidden'))->toBe('true');
    }
})->with([
    ['en', 'success', 'Success'], ['en', 'error', 'Error'],
    ['en', 'warning', 'Warning'], ['en', 'info', 'Information'], ['en', 'unknown', 'Information'],
    ['pt_BR', 'success', 'Sucesso'], ['pt_BR', 'error', 'Erro'],
    ['pt_BR', 'warning', 'Atenção'], ['pt_BR', 'info', 'Informação'], ['pt_BR', 'unknown', 'Informação'],
]);

it('escapes message content without interpreting markup or changing the listener payload', function () {
    $message = '<img src=x onerror="alert(1)"> & <script>throw 1</script>';
    $component = Livewire::test(Notifications::class)
        ->call('__dispatch', 'notification', [['message' => $message, 'type' => 'error']])
        ->assertSet('notifications.0.message', $message)
        ->assertSeeHtml(e($message));
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    expect((new DOMXPath($document))->query('//*[@role="alert"]//img | //*[@role="alert"]//script'))->toHaveCount(0);
    $component->call('clearNotifications')->assertSet('notifications', []);
});

it('defines every toast label in each supported catalog without fallback', function (string $locale) {
    foreach (['dismiss', 'types.success', 'types.error', 'types.warning', 'types.info'] as $key) {
        expect(app('translator')->hasForLocale('toast_feedback.'.$key, $locale))->toBeTrue();
    }
})->with(['en', 'pt_BR']);
