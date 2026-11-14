<?php

use App\Livewire\AmigoSecreto\JoinEvent;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Services\NotificationInbox;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('accepts the same normalized email invitation that the dashboard offers', function () {
    $user = User::factory()->create(['email' => 'guest@example.test']);
    $event = AmigoSecretoEvent::factory()->create();
    $invite = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => ' Guest@Example.Test ',
        'status' => 'INVITED',
    ]);
    expect(app(NotificationInbox::class)->eventInvitationsFor($user)->sole()->id)->toBe($invite->id);

    Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code])
        ->call('join')->assertHasNoErrors()->assertRedirect(route('amigo-secreto.show', $event));

    expect($event->participants()->count())->toBe(1)
        ->and($invite->fresh()->user_id)->toBe($user->id)
        ->and($invite->fresh()->status)->toBe('ACCEPTED')
        ->and($invite->fresh()->accepted_at)->not->toBeNull()
        ->and(app(NotificationInbox::class)->eventInvitationsFor($user)->exists())->toBeFalse();
});

it('reuses only the lowest eligible invitation id and preserves duplicate history', function () {
    $user = User::factory()->create(['email' => 'guest@example.test']);
    $event = AmigoSecretoEvent::factory()->create();
    $first = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => ' Guest@Example.Test ', 'status' => 'INVITED',
    ]);
    $second = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => 'guest@example.test', 'status' => 'INVITED',
    ]);
    $unchanged = $second->fresh()->getAttributes();
    expect(app(NotificationInbox::class)->eventInvitationsFor($user)->count())->toBe(2);

    Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code])
        ->call('join')->assertHasNoErrors()->assertRedirect(route('amigo-secreto.show', $event));

    expect($event->participants()->count())->toBe(2)
        ->and($event->participants()->where('user_id', $user->id)->sole()->id)->toBe($first->id)
        ->and($first->fresh()->status)->toBe('ACCEPTED')
        ->and($first->fresh()->accepted_at)->not->toBeNull()
        ->and($second->fresh()->getAttributes())->toBe($unchanged)
        ->and(app(NotificationInbox::class)->eventInvitationsFor($user)->exists())->toBeFalse();
});

it('refuses changed event eligibility immediately before the participant write', function (bool $invited, string $change) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create();
    $invite = $invited ? AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => $user->email, 'status' => 'INVITED',
    ]) : null;
    $before = $invite?->fresh()->getAttributes();
    $component = Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code]);
    $fired = false;
    DB::connection()->beforeExecuting(function (string $sql) use ($event, $change, &$fired): void {
        if ($fired || ! preg_match('/^(update|insert into) "amigo_secreto_participants"/i', $sql)) {
            return;
        }
        $fired = true;
        AmigoSecretoEvent::whereKey($event->id)->update(match ($change) {
            'code' => ['event_code' => 'ROTATED00001'],
            'archive' => ['is_archived' => true],
            default => ['status' => $change],
        });
    });

    $component->call('join')->assertForbidden()->assertNotDispatched('notification');

    expect($fired)->toBeTrue()
        ->and($event->participants()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($invite?->fresh()->getAttributes())->toBe($before);
})->with(['code' => false, 'invitation' => true])->with(['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED', 'archive', 'code']);

it('does not create a participant for a missing or already joined actor at write time', function (bool $invited, string $change) {
    $user = User::factory()->create();
    $event = AmigoSecretoEvent::factory()->create();
    $invite = $invited ? AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => $user->email, 'status' => 'INVITED',
    ]) : null;
    $before = $invite?->fresh()->getAttributes();
    $component = Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code]);
    $fired = false;
    $winner = null;
    DB::connection()->beforeExecuting(function (string $sql) use ($event, $user, $change, &$fired, &$winner): void {
        if ($fired || ! preg_match('/^(update|insert into) "amigo_secreto_participants"/i', $sql)) {
            return;
        }
        $fired = true;
        if ($change === 'deleted') {
            User::whereKey($user->id)->delete();
        } else {
            $winner = AmigoSecretoParticipant::create([
                'event_id' => $event->id, 'user_id' => $user->id, 'status' => $change,
                'accepted_at' => now()->subDay(),
            ])->fresh()->getAttributes();
        }
    });

    $component->call('join')->assertForbidden()->assertNotDispatched('notification');

    expect($fired)->toBeTrue()
        ->and($invite?->fresh()->getAttributes())->toBe($before);
    $participants = $event->participants()->where('user_id', $user->id)->get();
    if ($winner === null) {
        expect($participants)->toHaveCount(0);
    } else {
        expect($participants)->toHaveCount(1)
            ->and($participants->sole()->getAttributes())->toBe($winner);
    }
})->with(['code' => false, 'invitation' => true])->with(['deleted', 'ACCEPTED', 'WITHDRAWN']);

it('preserves an invitation whose recipient or identity changes at the final update', function (string $change) {
    $user = User::factory()->create(['email' => 'recipient@example.test']);
    $other = User::factory()->create(['email' => 'other@example.test']);
    $event = AmigoSecretoEvent::factory()->create();
    $otherEvent = AmigoSecretoEvent::factory()->create();
    $invite = AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => $user->email, 'status' => 'INVITED',
    ]);
    $component = Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code]);
    $fired = false;
    $afterChange = null;
    DB::connection()->beforeExecuting(function (string $sql) use ($user, $other, $otherEvent, $invite, $change, &$fired, &$afterChange): void {
        if ($fired || ! str_starts_with(strtolower($sql), 'update "amigo_secreto_participants"')) {
            return;
        }
        $fired = true;
        match ($change) {
            'actor email' => $user->update(['email' => 'changed@example.test']),
            'collision' => $other->update(['email' => 'RECIPIENT@example.test']),
            'invite email' => $invite->update(['invite_email' => $other->email]),
            'event' => $invite->update(['event_id' => $otherEvent->id]),
            'user' => $invite->update(['user_id' => $other->id]),
            'deleted' => $invite->delete(),
            default => $invite->update(['status' => $change]),
        };
        $afterChange = $invite->fresh()?->getAttributes();
    });

    $component->call('join')->assertForbidden()->assertNotDispatched('notification');

    expect($fired)->toBeTrue()
        ->and($invite->fresh()?->getAttributes())->toBe($afterChange)
        ->and($event->participants()->where('user_id', $user->id)->exists())->toBeFalse();
})->with(['actor email', 'collision', 'invite email', 'event', 'user', 'deleted', 'DECLINED', 'WITHDRAWN', 'ACCEPTED']);

it('keeps code admission separate from an unavailable email invitation', function (string $reason) {
    $user = User::factory()->create(['email' => 'recipient@example.test']);
    $event = AmigoSecretoEvent::factory()->create();
    $invite = AmigoSecretoParticipant::create([
        'event_id' => $event->id,
        'invite_email' => $reason === 'other recipient' ? 'other@example.test' : $user->email,
        'status' => in_array($reason, ['DECLINED', 'WITHDRAWN'], true) ? $reason : 'INVITED',
    ]);
    if ($reason === 'collision') {
        User::factory()->create(['email' => 'RECIPIENT@example.test']);
    }
    $before = $invite->fresh()->getAttributes();

    Livewire::actingAs($user)->test(JoinEvent::class, ['code' => $event->event_code])
        ->call('join')->assertHasNoErrors()->assertRedirect(route('amigo-secreto.show', $event))
        ->assertDispatched('notification');

    expect($invite->fresh()->getAttributes())->toBe($before)
        ->and($event->participants()->where('user_id', $user->id)->sole()->status)->toBe('ACCEPTED')
        ->and($event->participants()->count())->toBe(2);
})->with(['other recipient', 'collision', 'DECLINED', 'WITHDRAWN']);
