<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Models\User;
use Livewire\Livewire;

function eventBudgetDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    return new DOMXPath($document);
}

it('presents localized optional budget controls with matching native limits', function (string $locale, string $minimum, string $maximum, string $help) {
    app()->setLocale($locale);
    $response = $this->actingAs(User::factory()->create())->get(route('amigo-secreto.create'));
    $response->assertOk()->assertSeeLivewire('amigo-secreto.create-event')->assertSee($help);
    $xpath = eventBudgetDocument($response->getContent());

    foreach (['budget_min' => $minimum, 'budget_max' => $maximum] as $field => $label) {
        $inputs = $xpath->query('//input[@id="'.$field.'"]');
        expect($inputs)->toHaveCount(1);
        $input = $inputs->item(0);
        expect($input)->toBeInstanceOf(DOMElement::class);
        expect($input->getAttribute('type'))->toBe('number')
            ->and($input->getAttribute('min'))->toBe('0')
            ->and($input->getAttribute('max'))->toBe('99999999.99')
            ->and($input->getAttribute('step'))->toBe('0.01')
            ->and($input->hasAttribute('required'))->toBeFalse()
            ->and($input->getAttribute('aria-invalid'))->toBe('false')
            ->and($input->getAttribute('aria-describedby'))->toBe('event-budget-help');
        $labels = $xpath->query('//label[@for="'.$field.'"]');
        expect($labels)->toHaveCount(1)
            ->and(trim($labels->item(0)->textContent))->toBe($label);
    }
    expect($xpath->query('//*[@id="event-budget-help"]'))->toHaveCount(1);
})->with([
    ['en', 'Minimum budget (optional)', 'Maximum budget (optional)', 'Leave either limit blank if not needed. Zero is allowed. Use up to two decimal places, no more than 99999999.99, and a maximum at least as large as the minimum.'],
    ['pt_BR', 'Orçamento mínimo (opcional)', 'Orçamento máximo (opcional)', 'Deixe um limite em branco se não precisar dele. Zero é permitido. Use até duas casas decimais, no máximo 99999999.99, e um máximo maior ou igual ao mínimo.'],
]);

it('associates a visible budget error with only its invalid control', function (string $field) {
    $component = Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Accessible budget exchange')->set($field, '0.001')->call('create')
        ->assertHasErrors([$field => 'decimal']);
    $xpath = eventBudgetDocument($component->html());
    $input = $xpath->query('//input[@id="'.$field.'"]')->item(0);
    expect($input)->toBeInstanceOf(DOMElement::class);
    expect($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($input->getAttribute('aria-describedby'))->toBe('event-budget-help '.$field.'-error');
    $errors = $xpath->query('//*[@id="'.$field.'-error"]');
    expect($errors)->toHaveCount(1);
    $error = $errors->item(0);
    expect($error)->toBeInstanceOf(DOMElement::class);
    expect($error->getAttribute('role'))->toBe('alert')
        ->and(trim($error->textContent))->toBe($component->errors()->first($field));
    $other = $field === 'budget_min' ? 'budget_max' : 'budget_min';
    expect($xpath->query('//input[@id="'.$other.'"]')->item(0)->getAttribute('aria-invalid'))->toBe('false');
})->with(['budget_min', 'budget_max']);
