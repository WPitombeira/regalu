<?php

use App\Livewire\AmigoSecreto\CreateEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('rolls back a new event when admitting its organizer fails', function () {
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test(CreateEvent::class)
        ->set('name', 'Atomic event');
    $armed = true;
    $fired = false;

    DB::connection()->beforeExecuting(function (string $sql) use (&$armed, &$fired): void {
        if ($armed && str_starts_with($sql, 'insert into "amigo_secreto_participants"')) {
            $armed = false;
            $fired = true;
            throw new RuntimeException('Organizer admission fixture failure');
        }
    });

    try {
        expect(fn () => $component->instance()->create())
            ->toThrow(RuntimeException::class, 'Organizer admission fixture failure');
    } finally {
        $armed = false;
    }

    expect($fired)->toBeTrue();
    $component->assertNotDispatched('notification');
    $this->assertDatabaseCount('amigo_secreto_events', 0);
    $this->assertDatabaseCount('amigo_secreto_participants', 0);
});
