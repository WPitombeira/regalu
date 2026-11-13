<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Models\AmigoSecretoEvent;
use App\Models\User;
use Livewire\Livewire;

it('persists optional budget bounds exactly at the supported decimal scale', function (?string $minimum, ?string $maximum, ?string $storedMinimum, ?string $storedMaximum) {
    $host = User::factory()->create();

    Livewire::actingAs($host)->test(CreateEvent::class)
        ->set('name', 'Budget boundary exchange')
        ->set('budget_min', $minimum)
        ->set('budget_max', $maximum)
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event = AmigoSecretoEvent::sole();
    expect($event->budget_min)->toBe($storedMinimum)
        ->and($event->budget_max)->toBe($storedMaximum);
    $this->assertDatabaseHas('amigo_secreto_participants', [
        'event_id' => $event->id, 'user_id' => $host->id, 'status' => 'ACCEPTED',
    ]);
})->with([
    'both zero' => ['0', '0', '0.00', '0.00'],
    'minimum zero only' => ['0', null, '0.00', null],
    'maximum zero only' => [null, '0', null, '0.00'],
    'omitted bounds' => [null, null, null, null],
    'empty bounds' => ['', '', null, null],
    'whitespace bounds' => ['  ', '  ', null, null],
    'minimum only with empty maximum' => ['0.01', '', '0.01', null],
    'maximum only with empty minimum' => ['', '99999999.99', null, '99999999.99'],
    'equal nonzero bounds' => ['12.3', '12.30', '12.30', '12.30'],
    'upper column boundary' => ['99999999.99', '99999999.99', '99999999.99', '99999999.99'],
    'one cent interval' => ['99999999.98', '99999999.99', '99999999.98', '99999999.99'],
]);

it('rejects a maximum below the minimum without creating an event', function (string $minimum, string $maximum) {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Unordered budget exchange')
        ->set('budget_min', $minimum)
        ->set('budget_max', $maximum)
        ->call('create')
        ->assertHasErrors(['budget_max' => 'gte'])
        ->assertSet('budget_min', $minimum)
        ->assertSet('budget_max', $maximum)
        ->assertNotDispatched('notification')
        ->assertNoRedirect();

    $this->assertDatabaseCount('amigo_secreto_events', 0);
    $this->assertDatabaseCount('amigo_secreto_participants', 0);
})->with([
    'ordinary range' => ['100', '20'],
    'zero maximum' => ['0.01', '0'],
    'one cent near column limit' => ['99999999.99', '99999999.98'],
]);

it('rejects non-decimal or over-precision numeric budgets without rounding', function (string $field, string $value) {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Exact decimal exchange')
        ->set($field, $value)
        ->call('create')
        ->assertHasErrors([$field => 'decimal'])
        ->assertSet($field, $value)
        ->assertNotDispatched('notification')
        ->assertNoRedirect();

    $this->assertDatabaseCount('amigo_secreto_events', 0);
    $this->assertDatabaseCount('amigo_secreto_participants', 0);
})->with(['budget_min', 'budget_max'])->with([
    'fraction of a cent' => '0.001',
    'extra decimal at limit' => '99999999.999',
    'positive exponent' => '1e2',
    'negative exponent' => '1e-2',
]);

it('rejects a budget above the stored decimal column limit', function (string $field, string $value) {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Oversized budget exchange')
        ->set($field, $value)
        ->call('create')
        ->assertHasErrors([$field => 'max'])
        ->assertSet($field, $value)
        ->assertNotDispatched('notification')
        ->assertNoRedirect();

    $this->assertDatabaseCount('amigo_secreto_events', 0);
    $this->assertDatabaseCount('amigo_secreto_participants', 0);
})->with(['budget_min', 'budget_max'])->with([
    'one cent above limit' => '100000000.00',
    'large whole number' => '1000000000000',
]);

it('retains numeric and nonnegative validation for each budget bound', function (string $field, string $value, string $rule) {
    Livewire::actingAs(User::factory()->create())->test(CreateEvent::class)
        ->set('name', 'Invalid budget exchange')
        ->set($field, $value)
        ->call('create')
        ->assertHasErrors([$field => $rule])
        ->assertSet($field, $value)
        ->assertNotDispatched('notification')
        ->assertNoRedirect();

    $this->assertDatabaseCount('amigo_secreto_events', 0);
    $this->assertDatabaseCount('amigo_secreto_participants', 0);
})->with(['budget_min', 'budget_max'])->with([
    'negative' => ['-0.01', 'min'],
    'text' => ['not money', 'numeric'],
    'comma decimal' => ['12,34', 'numeric'],
]);

it('allows correcting an invalid range and persists one event with its organizer', function () {
    $host = User::factory()->create();
    $component = Livewire::actingAs($host)->test(CreateEvent::class)
        ->set('name', 'Corrected exchange')
        ->set('budget_min', '10')->set('budget_max', '5')
        ->call('create')->assertHasErrors(['budget_max' => 'gte']);

    $component->set('budget_min', '0')->set('budget_max', '0.01')
        ->call('create')->assertHasNoErrors()->assertRedirect();

    $event = AmigoSecretoEvent::sole();
    expect($event->budget_min)->toBe('0.00')
        ->and($event->budget_max)->toBe('0.01')
        ->and($event->organizer_id)->toBe($host->id);
    $this->assertDatabaseCount('amigo_secreto_participants', 1);
    $this->actingAs($host)->get(route('amigo-secreto.show', $event))
        ->assertOk()->assertSee('$0.00 - $0.01');
});
