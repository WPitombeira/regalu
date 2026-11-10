<?php

use App\Jobs\SendEventInvitation;
use App\Livewire\AmigoSecreto\ManageParticipants;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('queues invitation delivery instead of claiming it was sent in the request', function (string $locale, string $message) {
    app()->setLocale($locale);
    Mail::fake();
    Queue::fake();
    $organizer = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create(['organizer_id' => $organizer->id]);

    Livewire::actingAs($organizer)
        ->test(ManageParticipants::class, ['event' => $event])
        ->set('inviteEmail', 'queued@example.com')
        ->call('inviteByEmail')
        ->assertHasNoErrors()
        ->assertDispatched('notification', ['message' => $message, 'type' => 'success']);

    Mail::assertNothingSent();
    $participant = AmigoSecretoParticipant::where('invite_email', 'queued@example.com')->sole();
    Queue::assertPushed(SendEventInvitation::class, fn (SendEventInvitation $job) => $job->participantId === $participant->id && $job->afterCommit === true
    );
    Queue::assertPushedTimes(SendEventInvitation::class, 1);
})->with([
    ['en', 'Invitation queued for delivery.'],
    ['pt_BR', 'Convite na fila de envio.'],
]);
