<?php

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('preserves populated participant data through exact migration rollback and reapplication', function () {
    $event = AmigoSecretoEvent::factory()->create();
    $user = User::factory()->create();
    foreach (['first@example.test', 'second@example.test', null, null] as $email) {
        AmigoSecretoParticipant::create(['event_id' => $event->id, 'invite_email' => $email]);
    }
    AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    $otherEvent = AmigoSecretoEvent::factory()->create();
    AmigoSecretoParticipant::create(['event_id' => $otherEvent->id, 'user_id' => $user->id, 'invite_email' => 'first@example.test']);
    $before = DB::table('amigo_secreto_participants')->orderBy('id')->get()->toJson();
    $migration = require database_path('migrations/2026_11_14_000001_enforce_event_participant_identity.php');

    $migration->down();
    expect(Schema::hasIndex('amigo_secreto_participants', 'event_participant_account_unique'))->toBeFalse()
        ->and(Schema::hasIndex('amigo_secreto_participants', 'event_participant_email_unique'))->toBeFalse();
    $migration->up();

    expect(DB::table('amigo_secreto_participants')->orderBy('id')->get()->toJson())->toBe($before)
        ->and(Schema::hasIndex('amigo_secreto_participants', 'event_participant_account_unique', 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('amigo_secreto_participants', 'event_participant_email_unique', 'unique'))->toBeTrue();
    expect(fn () => DB::table('amigo_secreto_participants')->insert([
        'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'INVITED',
    ]))->toThrow(QueryException::class);
});

it('refuses historical collisions before DDL and preserves every original row', function (string $identity) {
    $migration = require database_path('migrations/2026_11_14_000001_enforce_event_participant_identity.php');
    $migration->down();
    $event = AmigoSecretoEvent::factory()->create();
    $user = User::factory()->create();
    foreach (['ACCEPTED', 'WITHDRAWN'] as $status) {
        AmigoSecretoParticipant::create([
            'event_id' => $event->id,
            'user_id' => $identity === 'account' ? $user->id : null,
            'invite_email' => $identity === 'email' ? ($status === 'ACCEPTED' ? ' Guest@Example.Test ' : 'guest@example.test') : null,
            'status' => $status,
            'accepted_at' => now()->subDay(),
        ]);
    }
    $before = DB::table('amigo_secreto_participants')->orderBy('id')->get()->toJson();

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'approved preservation plan');

    expect(DB::table('amigo_secreto_participants')->orderBy('id')->get()->toJson())->toBe($before)
        ->and(Schema::hasIndex('amigo_secreto_participants', 'event_participant_account_unique'))->toBeFalse()
        ->and(Schema::hasIndex('amigo_secreto_participants', 'event_participant_email_unique'))->toBeFalse();
})->with(['account', 'email']);
