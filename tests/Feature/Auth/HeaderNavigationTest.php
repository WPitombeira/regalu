<?php

use App\Models\User;
use Illuminate\Support\Facades\Lang;

function headerNavigationDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    return new DOMXPath($document);
}

it('renders one localized primary disclosure for guests and members', function (string $locale, bool $member, string $navigation, string $menu, array $links, string $login) {
    if ($member) {
        $this->actingAs(User::factory()->create());
    }
    app()->setLocale($locale);
    $xpath = headerNavigationDocument(view('components.ui.navbar')->render());

    expect($xpath->query('//nav[@aria-label="'.$navigation.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="navbar-user"]')->length)->toBe(1)
        ->and($xpath->query('//button[@aria-controls="navbar-user"]')->length)->toBe(1);
    /** @var DOMElement $trigger */
    $trigger = $xpath->query('//button[@aria-controls="navbar-user"]')->item(0);
    expect($trigger->getAttribute('type'))->toBe('button')
        ->and($trigger->getAttribute('aria-expanded'))->toBe('false')
        ->and(trim($trigger->textContent))->toBe($menu);
    $labels = [];
    foreach ($xpath->query('//*[@id="navbar-user"]//a') as $link) {
        $labels[] = trim($link->textContent);
    }
    expect($labels)->toBe($links)
        ->and($xpath->query('//*[@id="navbar-user"]//*[@role="menuitem"]')->length)->toBe(0);
    if (! $member) {
        expect($xpath->query('//*[@id="navbar-user"]//a[@href="/login"]')->length)->toBe(0)
            ->and(trim($xpath->query('//a[@href="/login"]')->item(0)->textContent))->toBe($login)
            ->and($xpath->query('//form[@action="'.route('logout').'"]')->length)->toBe(0);
    }
})->with([
    ['en', false, 'Primary navigation', 'Main menu', ['Home', 'About Us', 'Features', 'Contact'], 'Login'],
    ['pt_BR', false, 'Navegação principal', 'Menu principal', ['Início', 'Sobre Nós', 'Recursos', 'Contato'], 'Entrar'],
    ['en', true, 'Primary navigation', 'Main menu', ['Dashboard', 'Families', 'Wishlists', 'Secret Santa'], 'Login'],
    ['pt_BR', true, 'Navegação principal', 'Menu principal', ['Dashboard', 'Famílias', 'Listas de Desejos', 'Amigo Secreto'], 'Entrar'],
]);

it('resolves primary navigation copy in both locale catalogs without fallback', function () {
    foreach (['en', 'pt_BR'] as $locale) {
        foreach (['navigation.label', 'navigation.menu', 'messages.navbar.home', 'messages.navbar.about', 'messages.navbar.features', 'messages.navbar.contact', 'messages.navbar.login', 'messages.navbar.dashboard', 'messages.navbar.families', 'messages.navbar.wishlists', 'messages.navbar.secret_santa'] as $key) {
            expect(Lang::hasForLocale($key, $locale))->toBeTrue($locale.': '.$key);
        }
    }
});

it('renders the public entry page in each supported locale', function (string $locale, string $subheadline) {
    config(['app.locale' => $locale]);
    app()->setLocale($locale);

    $this->get('/')->assertOk()->assertSeeText($subheadline);
})->with([
    ['en', 'Discover a variety of features designed to enhance your experience.'],
    ['pt_BR', 'Descubra uma variedade de recursos projetados para melhorar sua experiência.'],
]);
