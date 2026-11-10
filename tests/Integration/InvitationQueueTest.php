<?php

use App\Jobs\SendEventInvitation;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['queue.default' => 'database']);
});

it('persists only committed invitations on the routed mail queue and delivers through a real worker', function () {
    $event = AmigoSecretoEvent::factory()->create(['name' => 'Queue delivery']);
    DB::beginTransaction();
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => 'worker@example.com',
        'status' => 'INVITED',
        'invited_at' => now(),
    ]);
    SendEventInvitation::dispatch($participant->id)->afterCommit();
    expect(DB::table('jobs')->count())->toBe(0);
    DB::commit();

    $queued = DB::table('jobs')->sole();
    $payload = json_decode($queued->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($queued->queue)->toBe('mail')
        ->and($payload['maxTries'])->toBe(3)
        ->and($payload['timeout'])->toBe(30)
        ->and($payload['backoff'])->toBe('10,60,180');

    // Use Laravel's actual console bootstrap, not a forced URL-generator root.
    config(['app.url' => 'http://127.0.0.1:8123']);
    (new SetRequestForConsole)->bootstrap($this->app);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])
        ->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $message = $transport->messages()->sole()->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe('worker@example.com')
        ->and($message->getSubject())->toContain('Queue delivery')
        ->and($message->getHtmlBody())->toContain("http://127.0.0.1:8123/join-event/{$event->event_code}");
});

it('does not publish invitation work from a rolled back transaction', function () {
    $event = AmigoSecretoEvent::factory()->create();
    DB::beginTransaction();
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => 'rolled-back@example.com',
        'status' => 'INVITED',
    ]);
    SendEventInvitation::dispatch($participant->id)->afterCommit();
    DB::rollBack();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(AmigoSecretoParticipant::find($participant->id))->toBeNull();
});

it('drops revoked or no longer pending invitations without sending mail', function (string $state) {
    Mail::fake();
    $event = AmigoSecretoEvent::factory()->create();
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => 'stale@example.com',
        'status' => 'INVITED',
    ]);
    if ($state === 'deleted') {
        $participant->delete();
    } elseif ($state === 'archived') {
        $event->update(['is_archived' => true]);
    } elseif ($state === 'drawn') {
        $event->update(['status' => 'DRAWS_COMPLETE']);
    } else {
        $participant->update(['status' => 'ACCEPTED']);
    }
    (new SendEventInvitation($participant->id))->handle();
    Mail::assertNothingSent();
})->with(['deleted', 'archived', 'drawn', 'accepted']);
