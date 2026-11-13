<?php

use Carbon\Carbon;

it('renders the home destination in each supported locale', function (string $locale) {
    app()->setLocale($locale);

    expect($this->get(route('home'))->status())->toBe(200);
})->with(['en', 'pt_BR']);

it('does not advertise unavailable footer destinations as links', function (string $locale, string $notice) {
    app()->setLocale($locale);
    $response = $this->get(route('home'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//footer'))->toHaveCount(1);
    expect($xpath->query('//footer//a[not(@href) or normalize-space(@href)="" or starts-with(@href,"#")]'))->toHaveCount(0);
    expect($xpath->query('//footer//button | //footer//*[@role="button"] | //footer//*[@role="link"]'))->toHaveCount(0);
    $footer = $xpath->query('//footer')->item(0);
    expect($footer->textContent)->toContain($notice);
    expect($xpath->query('//footer//svg'))->toHaveCount(0);
})->with([
    ['en', 'Privacy policy unavailable.'],
    ['pt_BR', 'Política de privacidade indisponível.'],
]);

it('offers one localized native link to the home route instead of the configured external URL', function (string $locale, string $label) {
    app()->setLocale($locale);
    config(['app.name' => 'Regalu', 'app.url' => 'https://unavailable.example.test/elsewhere']);
    $response = $this->get('/login')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//footer//a');

    expect($links)->toHaveCount(1);
    $link = $links->item(0);
    expect($link)->toBeInstanceOf(DOMElement::class);
    expect($link->getAttribute('href'))->toBe(route('home'));
    expect($link->getAttribute('aria-label'))->toBe($label);
    expect($link->hasAttribute('tabindex'))->toBeFalse();
    expect($link->hasAttribute('onclick'))->toBeFalse();
    expect($xpath->query('//footer//a//img[@alt=""]'))->toHaveCount(1);
})->with([
    ['en', 'Regalu home'],
    ['pt_BR', 'Página inicial do Regalu'],
]);

it('renders a localized copyright with the current year and one final period', function (string $locale, string $rights) {
    app()->setLocale($locale);
    config(['app.name' => 'Regalu']);
    $this->travelTo(Carbon::parse('2035-01-02'));
    try {
        $response = $this->get('/login')->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new DOMXPath($document);
        $footer = $xpath->query('//footer')->item(0);
        expect($footer->textContent)->toContain('© 2035 Regalu™. '.$rights)->not->toContain('..');
    } finally {
        $this->travelBack();
    }
})->with([
    ['en', 'All rights reserved.'],
    ['pt_BR', 'Todos os direitos reservados.'],
]);

it('provides complete own-locale footer catalogs without fallback', function (string $locale) {
    $catalog = require lang_path($locale.'/footer_navigation.php');
    expect(array_keys($catalog))->toBe(['home', 'privacy_unavailable', 'rights']);
    foreach ($catalog as $key => $value) {
        expect($value)->toBeString()->not->toBeEmpty();
        expect(app('translator')->hasForLocale('footer_navigation.'.$key, $locale))->toBeTrue();
    }
    expect(substr_count($catalog['home'], ':name'))->toBe(1);
})->with(['en', 'pt_BR']);

it('escapes configured branding in footer text and its accessible name', function () {
    app()->setLocale('en');
    $name = 'Regalu <em>"&</em>';
    config(['app.name' => $name]);
    $response = $this->get('/login')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//footer//em'))->toHaveCount(0);
    expect($xpath->query('//footer')->item(0)->textContent)->toContain($name);
    $link = $xpath->query('//footer//a')->item(0);
    expect($link)->toBeInstanceOf(DOMElement::class);
    expect($link->getAttribute('aria-label'))->toBe($name.' home');
});
