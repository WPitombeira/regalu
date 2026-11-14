<?php

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects a second participant for the same event and account at the database boundary', function () {
    $event = AmigoSecretoEvent::factory()->create();
    $user = User::factory()->create();
    $participant = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => 'ACCEPTED',
        'accepted_at' => now(),
    ]);
    $before = $participant->fresh()->getAttributes();

    expect(fn () => DB::table('amigo_secreto_participants')->insert([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => 'WITHDRAWN',
    ]))->toThrow(QueryException::class);

    expect($event->participants()->count())->toBe(1)
        ->and($participant->fresh()->getAttributes())->toBe($before);
});

it('rejects a second invitation identity regardless of case spaces or status', function (string $status) {
    $event = AmigoSecretoEvent::factory()->create();
    $invite = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => '  Guest@Example.com  ',
        'status' => $status,
    ]);
    $before = $invite->fresh()->getAttributes();

    expect(fn () => DB::table('amigo_secreto_participants')->insert([
        'event_id' => $event->id,
        'invite_email' => 'guest@example.com',
        'status' => 'INVITED',
    ]))->toThrow(QueryException::class);

    expect($event->participants()->count())->toBe(1)
        ->and($invite->fresh()->getAttributes())->toBe($before);
})->with(['INVITED', 'ACCEPTED', 'DECLINED', 'WITHDRAWN']);
