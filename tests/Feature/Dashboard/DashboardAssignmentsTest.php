<?php

use App\Livewire\Dashboard;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Livewire\Livewire;

it('offers existing active assignments without exposing pairings and removes revoked shortcuts', function (string $revocation) {
    $buyer = User::factory()->create();
    $recipient = User::factory()->create(['name' => 'Recipient identity sentinel']);
    $event = AmigoSecretoEvent::factory()->create([
        'status' => 'DRAWS_COMPLETE', 'event_date' => today()->subDay(),
        'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL', 'reveal_date' => today()->addWeek(),
    ]);
    foreach ([$buyer, $recipient] as $user) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
    $draw = AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $buyer->id, 'target_user_id' => $recipient->id, 'draw_date' => now()]);
    Livewire::actingAs($event->organizer)->test(Dashboard::class)->assertViewHas('assignments', fn ($rows) => $rows?->isEmpty() === true);
    $component = Livewire::actingAs($buyer)->test(Dashboard::class)
        ->assertSee(route('amigo-secreto.assignment', $event), false)->assertDontSee($recipient->name)->assertDontSee($event->event_code);
    expect($component->viewData('assignments')->sole()->getAttributes())->toBe(['id' => $event->id, 'name' => $event->name]);

    match ($revocation) {
        'archive' => $event->update(['is_archived' => true]),
        'complete' => $event->update(['status' => 'COMPLETED']),
        'buyer' => $event->participants()->where('user_id', $buyer->id)->delete(),
        'recipient' => $event->participants()->where('user_id', $recipient->id)->delete(),
        'draw' => $draw->delete(),
    };
    $component->call('$refresh')->assertViewHas('assignments', fn ($rows) => $rows->isEmpty())
        ->assertDontSee(route('amigo-secreto.assignment', $event), false);
})->with(['archive', 'complete', 'buyer', 'recipient', 'draw']);
