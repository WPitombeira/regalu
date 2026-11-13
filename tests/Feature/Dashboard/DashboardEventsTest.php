<?php

use App\Livewire\Dashboard;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('lists only current upcoming event metadata with dated events first and stable bounded ordering', function () {
    $user = User::factory()->create();
    $unscheduled = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'event_date' => null]);
    $later = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'event_date' => today()->addWeek()]);
    $next = AmigoSecretoEvent::factory()->create(['event_date' => today(), 'description' => 'Private event notes sentinel']);
    AmigoSecretoParticipant::create(['event_id' => $next->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    $tie = AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'event_date' => today()]);
    foreach ([['event_date' => today()->subDay()], ['is_archived' => true], ['status' => 'COMPLETED']] as $state) {
        AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, ...$state]);
    }
    $foreign = AmigoSecretoEvent::factory()->create(['name' => 'Foreign event sentinel']);
    AmigoSecretoParticipant::create(['event_id' => $foreign->id, 'user_id' => $user->id, 'status' => 'INVITED']);

    $component = Livewire::actingAs($user)->test(Dashboard::class)->assertSee($next->name)
        ->assertDontSee('Private event notes sentinel')->assertDontSee($next->event_code)->assertDontSee($foreign->name);
    $events = $component->viewData('events');
    expect($events->pluck('id')->all())->toBe([$next->id, $tie->id, $later->id, $unscheduled->id]);
    foreach ($events as $event) {
        expect(array_keys($event->getAttributes()))->toBe(['id', 'name', 'event_date', 'status']);
    }
    $next->participants()->delete();
    $component->call('$refresh')->assertDontSee($next->name);
    AmigoSecretoEvent::factory()->count(6)->create(['organizer_id' => $user->id, 'event_date' => today()->addDay()]);
    expect($component->call('$refresh')->viewData('events'))->toHaveCount(4);
});

it('renders upcoming event statuses in Portuguese without English fallback', function (string $status, string $label) {
    $user = User::factory()->create(['locale' => 'pt_BR']);
    app()->setLocale('pt_BR');
    AmigoSecretoEvent::factory()->create(['organizer_id' => $user->id, 'event_date' => today(), 'status' => $status]);
    Livewire::actingAs($user)->test(Dashboard::class)->assertSee($label);
})->with([
    ['PLANNING', 'Planejamento'],
    ['INVITES_SENT', 'Convites enviados'],
    ['DRAW_PENDING', 'Sorteio pendente'],
    ['DRAWS_COMPLETE', 'Sorteio concluído'],
    ['REVEALED', 'Revelado'],
]);
