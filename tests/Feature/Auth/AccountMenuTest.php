<?php

use App\Models\User;
use Illuminate\Support\Facades\Lang;

function accountMenuDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    return new DOMXPath($document);
}

it('renders a self-contained avatar when the account has no photo', function (?string $avatar) {
    $this->actingAs(User::factory()->create(['avatar_url' => $avatar]));
    $html = view('components.ui.user.card')->render();
    $xpath = accountMenuDocument($html);

    expect($xpath->query('//button[@id="user-menu-button"]//img')->length)->toBe(0)
        ->and($xpath->query('//button[@id="user-menu-button"]//svg[@aria-hidden="true"]')->length)->toBe(1);
})->with([null, '']);

it('renders localized account actions without changing their routes', function (string $locale, string $trigger, string $settings, string $logout, string $mainMenu) {
    $this->actingAs(User::factory()->create());
    app()->setLocale($locale);
    $xpath = accountMenuDocument(view('components.ui.user.card')->render());

    expect(trim($xpath->query('//button[@id="user-menu-button"]')->item(0)->textContent))->toBe($trigger)
        ->and(trim($xpath->query('//a[@href="'.route('settings').'"]')->item(0)->textContent))->toBe($settings)
        ->and(trim($xpath->query('//form[@action="'.route('logout').'"]/button')->item(0)->textContent))->toBe($logout)
        ->and(trim($xpath->query('//button[@aria-controls="navbar-user"]')->item(0)->textContent))->toBe($mainMenu);
})->with([
    ['en', 'Account menu', 'Settings', 'Sign out', 'Main menu'],
    ['pt_BR', 'Menu da conta', 'Configurações', 'Sair', 'Menu principal'],
]);

it('renders an initially closed account disclosure with native links and buttons', function () {
    $this->actingAs(User::factory()->create());
    $xpath = accountMenuDocument(view('components.ui.user.card')->render());
    /** @var DOMElement $trigger */
    $trigger = $xpath->query('//button[@id="user-menu-button"]')->item(0);

    expect($trigger->getAttribute('type'))->toBe('button')
        ->and($trigger->getAttribute('aria-expanded'))->toBe('false')
        ->and($trigger->getAttribute('aria-controls'))->toBe('user-menu-panel')
        ->and($trigger->hasAttribute('aria-haspopup'))->toBeFalse();

    /** @var DOMElement $panel */
    $panel = $xpath->query('//*[@id="user-menu-panel"]')->item(0);
    expect($panel)->not->toBeNull()
        ->and($panel->getAttribute('style'))->toContain('display: none')
        ->and($panel->hasAttribute('role'))->toBeFalse()
        ->and($xpath->query('//*[@id="user-menu-panel"]//a')->length)->toBe(2)
        ->and($xpath->query('//*[@id="user-menu-panel"]//form[@method="POST"]/input[@name="_token"]')->length)->toBe(1);
});

it('keeps a decorative fallback while a supplied avatar is unavailable', function () {
    $this->actingAs(User::factory()->create(['avatar_url' => '/storage/avatars/example.png']));
    $xpath = accountMenuDocument(view('components.ui.user.card')->render());
    /** @var DOMElement $photo */
    $photo = $xpath->query('//button[@id="user-menu-button"]//img')->item(0);

    expect($photo->getAttribute('src'))->toBe('/storage/avatars/example.png')
        ->and($photo->getAttribute('alt'))->toBe('')
        ->and($xpath->query('//button[@id="user-menu-button"]//svg[@aria-hidden="true"]')->length)->toBe(1)
        ->and($photo->getAttribute('style'))->toContain('display: none');
});

it('escapes account identity and avatar attributes', function () {
    $name = '<script>window.accountNameExecuted = true</script>';
    $avatar = '/storage/avatar.png?name=" onerror="window.avatarExecuted=true';
    $this->actingAs(User::factory()->create(['name' => $name, 'avatar_url' => $avatar]));
    $xpath = accountMenuDocument(view('components.ui.user.card')->render());
    /** @var DOMElement $photo */
    $photo = $xpath->query('//button[@id="user-menu-button"]//img')->item(0);

    expect($xpath->query('//script')->length)->toBe(0)
        ->and($photo->getAttribute('src'))->toBe($avatar)
        ->and($photo->hasAttribute('onerror'))->toBeFalse()
        ->and($xpath->query('//*[@id="user-menu-panel"]')->item(0)->textContent)->toContain($name);
});

it('does not expose account details or logout controls to a guest', function () {
    $xpath = accountMenuDocument(view('components.ui.user.card')->render());

    expect($xpath->query('//*[@id="user-menu-button" or @id="user-menu-panel"]')->length)->toBe(0)
        ->and($xpath->query('//form[@action="'.route('logout').'"]')->length)->toBe(0)
        ->and($xpath->query('//a[@href="/login"]')->length)->toBe(1);
});

it('provides matching account copy and the shared dashboard label in each locale', function () {
    $english = require lang_path('en/account_menu.php');
    $portuguese = require lang_path('pt_BR/account_menu.php');

    expect(array_keys($english))->toBe(array_keys($portuguese));
    foreach (['en' => $english, 'pt_BR' => $portuguese] as $locale => $catalog) {
        foreach ($catalog as $key => $value) {
            expect($value)->toBeString()->not->toBe('');
            expect(Lang::hasForLocale('account_menu.'.$key, $locale))->toBeTrue();
        }
        expect(Lang::hasForLocale('messages.navbar.dashboard', $locale))->toBeTrue();
    }
});
