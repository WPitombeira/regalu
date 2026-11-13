<?php

use App\Livewire\Home;
use App\Livewire\Home\Index;
use Livewire\Livewire;

it('explains contact unavailability before presenting disabled labelled fields', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $response = $this->withoutVite()->get(route('home'))->assertOk()->assertSeeLivewire('home.index');
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $section = '//section[@id="contact"]';

    expect($xpath->evaluate('string('.$section.'/@aria-labelledby)'))->toBe('contact-heading');
    expect(trim($xpath->evaluate('string('.$section.'//h2[@id="contact-heading"])')))->toBe($copy['heading']);
    expect(trim($xpath->evaluate('string('.$section.'//*[@id="contact-unavailable"])')))->toBe($copy['notice']);
    expect($xpath->evaluate('string('.$section.'//form/@aria-describedby)'))->toBe('contact-unavailable');
    expect($xpath->query($section.'//fieldset[@disabled]')->length)->toBe(1);

    foreach (['name', 'email', 'message'] as $field) {
        $id = 'feedback_'.$field;
        expect($xpath->query('//*[@id="'.$id.'"]')->length)->toBe(1);
        expect($xpath->query($section.'//fieldset//*[@id="'.$id.'" and @disabled]')->length)->toBe(1);
        $labels = $xpath->query($section.'//label[@for="'.$id.'"]');
        expect($labels->length)->toBe(1);
        $label = $labels->item(0);
        assert($label instanceof DOMElement);
        expect(trim($label->textContent))->toBe($copy[$field]);
        expect($label->getAttribute('class'))->not->toContain('hidden', 'sr-only');
    }

    expect(trim($xpath->evaluate('string('.$section.'//button[@type="submit" and @disabled])')))->toBe($copy['button']);
    expect($xpath->evaluate('string('.$section.')'))->not->toContain('We would love to hear you', 'Missing field', 'contact.');
})->with([
    'English' => ['en', [
        'heading' => 'Contact',
        'notice' => 'Contact delivery is unavailable. Please do not enter a message; this form cannot send it.',
        'name' => 'Your name (optional)',
        'email' => 'Your email (optional)',
        'message' => 'Your message',
        'button' => 'Sending unavailable',
    ]],
    'Portuguese' => ['pt_BR', [
        'heading' => 'Contato',
        'notice' => 'O envio de mensagens está indisponível. Não preencha uma mensagem; este formulário não pode enviá-la.',
        'name' => 'Seu nome (opcional)',
        'email' => 'Seu e-mail (opcional)',
        'message' => 'Sua mensagem',
        'button' => 'Envio indisponível',
    ]],
]);

it('starts both contact aliases without a fabricated draft', function (string $alias, string $class) {
    $this->withoutVite();
    $component = Livewire::test($alias);
    expect($component->instance())->toBeInstanceOf($class);
    $component->assertSet('feedback_name', '')->assertSet('feedback_email', '')->assertSet('feedback_message', '');
})->with(['mounted' => ['home.index', Index::class], 'legacy' => ['home', Home::class]]);
