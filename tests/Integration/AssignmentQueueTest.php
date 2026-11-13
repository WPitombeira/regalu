<?php

use App\Jobs\SendAssignment;
use App\Livewire\AmigoSecreto\ExecuteDraw;
use App\Mail\AmigoSecretoAssignmentMail;
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['queue.default' => 'database']);
    $this->host = User::factory()->create();
    $this->event = AmigoSecretoEvent::factory()->create([
        'organizer_id' => $this->host->id, 'reveal_date' => today()->addWeek(),
    ]);
    $this->participants = collect([$this->host, ...User::factory()->count(2)->create()]);
    foreach ($this->participants as $user) {
        AmigoSecretoParticipant::create(['event_id' => $this->event->id, 'user_id' => $user->id, 'status' => 'ACCEPTED']);
    }
});

it('queues assignments only after the draw commits and delivers privacy-safe mail through a real worker', function (string $visibility) {
    $this->event->update(['recipient_visibility' => $visibility]);
    foreach ($this->participants as $participant) {
        $participant->update(['name' => "Assignment O'{$participant->id} <recipient>"]);
    }
    DB::beginTransaction();
    Livewire::actingAs($this->host)->test(ExecuteDraw::class, ['event' => $this->event])->call('executeDraw');
    expect(DB::table('jobs')->count())->toBe(0);
    DB::commit();
    expect(DB::table('jobs')->count())->toBe(3);
    expect(Notification::where('kind', 'assignment_available')->count())->toBe(3);
    foreach (DB::table('jobs')->get() as $job) {
        $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
        expect($job->queue)->toBe('mail');
        expect($payload['maxTries'])->toBe(3);
        expect($payload['timeout'])->toBe(30);
        expect($payload['backoff'])->toBe('10,60,180');
        expect($payload['data']['command'])->not->toContain('target_user_id');
    }

    config(['app.url' => 'http://127.0.0.1:8123']);
    (new SetRequestForConsole)->bootstrap($this->app);
    foreach ($this->participants as $participant) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])->assertSuccessful();
    }
    expect(DB::table('jobs')->count())->toBe(0);
    expect(DB::table('failed_jobs')->count())->toBe(0);
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    expect($transport->messages())->toHaveCount(3);
    foreach ($transport->messages() as $sent) {
        $message = $sent->getOriginalMessage();
        $buyer = User::where('email', $message->getTo()[0]->getAddress())->sole();
        $target = AmigoSecretoDraw::where('event_id', $this->event->id)->where('drawer_user_id', $buyer->id)->sole()->target;
        $content = $message->getSubject().$message->getHtmlBody();
        // The HTML body escapes the rendered name, so compare the escaped form.
        $escapedTargetName = e($target->name);
        expect($content)->toContain('http://127.0.0.1:8123/secret-santa/'.$this->event->id.'/assignment');
        expect($content)->not->toContain($target->email);
        expect($content)->not->toContain('/wishlists/');
        if ($visibility === 'NAMED') {
            expect($content)->toContain($escapedTargetName);
            if ($escapedTargetName !== $target->name) {
                // The raw form must never leak next to the escaped one.
                expect($content)->not->toContain($target->name);
            }
        } else {
            expect($content)->not->toContain($target->name);
            expect($content)->not->toContain($escapedTargetName);
            expect($content)->toContain(__('assignments.hidden_recipient'));
        }
    }
})->with(['NAMED', 'ANONYMOUS_UNTIL_REVEAL']);

it('does not publish assignment jobs when the draw transaction rolls back', function () {
    DB::beginTransaction();
    Livewire::actingAs($this->host)->test(ExecuteDraw::class, ['event' => $this->event])->call('executeDraw');
    expect(AmigoSecretoDraw::count())->toBe(3);
    DB::rollBack();
    expect(DB::table('jobs')->count())->toBe(0);
    expect(AmigoSecretoDraw::count())->toBe(0);
    expect(Notification::count())->toBe(0);
    expect($this->event->fresh()->status)->toBe('PLANNING');
});

it('does not enqueue duplicate assignments for a repeated draw', function () {
    $component = Livewire::actingAs($this->host)->test(ExecuteDraw::class, ['event' => $this->event]);
    $component->call('executeDraw')->call('executeDraw');
    expect(DB::table('jobs')->count())->toBe(3);
    expect(AmigoSecretoDraw::count())->toBe(3);
    expect(Notification::where('kind', 'assignment_available')->count())->toBe(3);
});

it('drops stale assignment deliveries after access is lost', function (string $change) {
    Mail::fake();
    Livewire::actingAs($this->host)->test(ExecuteDraw::class, ['event' => $this->event])->call('executeDraw');
    $draw = $this->event->draws()->where('drawer_user_id', $this->host->id)->sole();
    $job = new SendAssignment($draw->id);
    match ($change) {
        'archived' => $this->event->update(['is_archived' => true]),
        'completed' => $this->event->update(['status' => 'COMPLETED']),
        'deleted' => $draw->delete(),
        'buyer' => $this->event->participants()->where('user_id', $draw->drawer_user_id)->update(['status' => 'WITHDRAWN']),
        'target' => $this->event->participants()->where('user_id', $draw->target_user_id)->update(['status' => 'WITHDRAWN']),
        'not-drawn' => $this->event->refresh()->update(['status' => 'PLANNING']),
    };
    $job->handle();
    Mail::assertNothingSent();
})->with(['archived', 'completed', 'deleted', 'buyer', 'target', 'not-drawn']);

it('drains obsolete assignment jobs without mail after event completion', function () {
    Livewire::actingAs($this->host)->test(ExecuteDraw::class, ['event' => $this->event])->call('executeDraw');
    expect(DB::table('jobs')->count())->toBe(3);
    $this->event->update(['status' => 'COMPLETED']);

    foreach ($this->participants as $participant) {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])->assertSuccessful();
    }

    expect(DB::table('jobs')->count())->toBe(0);
    expect(DB::table('failed_jobs')->count())->toBe(0);
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    expect($transport->messages())->toHaveCount(0);
});

it('resolves the current visibility rather than a serialized recipient name', function () {
    Mail::fake();
    Livewire::actingAs($this->host)->test(ExecuteDraw::class, ['event' => $this->event])->call('executeDraw');
    $draw = $this->event->draws()->where('drawer_user_id', $this->host->id)->sole();
    $job = unserialize(serialize(new SendAssignment($draw->id)));
    $this->event->update(['recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL']);
    $job->handle();
    Mail::assertSent(AmigoSecretoAssignmentMail::class, fn ($mail) => $mail->hasTo($this->host->email)
        && $mail->targetName === null
        && ! str_contains($mail->render(), $draw->target->name)
        && ! str_contains($mail->render(), e($draw->target->name)));
});
