<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Models\User;
use Livewire\Livewire;

it('renders budget validation in the active locale with a human field name', function (string $locale, string $labelMin, string $labelMax, array $messages, string $field) {
    app()->setLocale($locale);
    $label = $field === 'budget_min' ? $labelMin : $labelMax;
    $component = Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Localized budget exchange');

    foreach ($messages as [$value, $rule, $template]) {
        $expected = str_replace(':attribute', $label, $template);
        $component->set($field, $value)->call('create')
            ->assertHasErrors([$field => $rule])
            ->assertSee($expected)
            ->assertNotDispatched('notification')->assertNoRedirect();
        expect($component->errors()->first($field))->toBe($expected);
    }

    $this->assertDatabaseCount('amigo_secreto_events', 0);
    $this->assertDatabaseCount('amigo_secreto_participants', 0);
})->with([
    'English' => ['en', 'minimum budget', 'maximum budget', [
        ['not money', 'numeric', 'The :attribute must be a number.'],
        ['-0.01', 'min', 'The :attribute must be zero or greater.'],
        ['0.001', 'decimal', 'Use a regular number with up to two decimal places for :attribute.'],
        ['100000000.00', 'max', 'The :attribute must not exceed 99999999.99.'],
    ]],
    'Portuguese' => ['pt_BR', 'orçamento mínimo', 'orçamento máximo', [
        ['not money', 'numeric', 'O :attribute deve ser um número.'],
        ['-0.01', 'min', 'O :attribute deve ser zero ou maior.'],
        ['0.001', 'decimal', 'Use um número comum com até duas casas decimais para o :attribute.'],
        ['100000000.00', 'max', 'O :attribute não deve ultrapassar 99999999.99.'],
    ]],
])->with(['budget_min', 'budget_max']);

it('explains an inverted budget range in the active locale', function (string $locale, string $message) {
    app()->setLocale($locale);
    $component = Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Localized range exchange')
        ->set('budget_min', '10')->set('budget_max', '5')->call('create')
        ->assertHasErrors(['budget_max' => 'gte'])->assertSee($message)
        ->assertNotDispatched('notification')->assertNoRedirect();

    expect($component->errors()->first('budget_max'))->toBe($message);
    $this->assertDatabaseCount('amigo_secreto_events', 0);
})->with([
    ['en', 'The maximum budget must be at least the minimum budget.'],
    ['pt_BR', 'O orçamento máximo deve ser maior ou igual ao orçamento mínimo.'],
]);
