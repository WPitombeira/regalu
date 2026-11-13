<?php

use App\Livewire\Family\FamilyRoster;
use App\Livewire\Notifications;
use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Js;
use Livewire\Livewire;

it('uses the toast listeners positional payload for localized clipboard feedback', function (string $locale, string $message) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    $roster = Livewire::actingAs($owner)->test(FamilyRoster::class, ['family' => $family]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$roster->html());
    $xpath = new DOMXPath($document);
    $buttons = $xpath->query('//button[contains(@*[name()="x-on:click"], "clipboard.writeText")]');
    expect($buttons)->toHaveCount(1);
    $button = $buttons->item(0);
    assert($button instanceof DOMElement);

    // Markup contract only; the browser gate executes the actual click and clipboard API.
    expect($button->getAttribute('x-on:click'))
        ->toContain("\$dispatch('notification', [{ message: ".Js::from($message).", type: 'success' }])");

    // Exercise the installed Livewire event boundary, not a direct listener-method call.
    Livewire::test(Notifications::class)
        ->call('__dispatch', 'notification', [['message' => $message, 'type' => 'success']])
        ->assertStatus(200)
        ->assertSet('notifications.0.message', $message)
        ->assertSet('notifications.0.type', 'success')
        ->assertSee($message);
})->with([
    'English' => ['en', 'Copied!'],
    'Portuguese' => ['pt_BR', 'Copiado!'],
]);
