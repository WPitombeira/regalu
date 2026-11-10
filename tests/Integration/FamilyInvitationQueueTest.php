<?php

use App\Jobs\SendFamilyInvitation;
use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\User;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('commits one family invitation job on mail and delivers a signed review link through a real worker', function () {
    config(['queue.default' => 'database']);
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id, 'name' => 'Delivery family']);
    DB::beginTransaction();
    Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family])
        ->set('inviteEmail', 'delivery@example.com')->call('inviteByEmail')->assertHasNoErrors();
    expect(DB::table('jobs')->count())->toBe(0);
    DB::commit();
    $job = DB::table('jobs')->sole();
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($job->queue)->toBe('mail')
        ->and($payload['maxTries'])->toBe(3)
        ->and($payload['timeout'])->toBe(30)
        ->and($payload['backoff'])->toBe('10,60,180');
    config(['app.url' => 'http://127.0.0.1:8123']);
    (new SetRequestForConsole)->bootstrap($this->app);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true])->assertSuccessful();
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $message = $transport->messages()->sole()->getOriginalMessage();
    $invitation = FamilyInvitation::sole();
    expect($message->getTo()[0]->getAddress())->toBe('delivery@example.com')
        ->and($message->getHtmlBody())->toContain('Review invitation')
        ->toContain("http://127.0.0.1:8123/family-invitations/{$invitation->id}?")
        ->toContain('signature=')->toContain('generation='.$invitation->generation)
        ->not->toContain($family->invite_code);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('does not queue family invitation work on transaction rollback', function () {
    config(['queue.default' => 'database']);
    $family = Family::factory()->create();
    DB::beginTransaction();
    $invitation = FamilyInvitation::create([
        'family_id' => $family->id, 'inviter_id' => $family->creator_id,
        'email' => 'rollback@example.com', 'generation' => (string) Str::uuid(),
        'status' => 'PENDING', 'expires_at' => now()->addWeek(),
    ]);
    SendFamilyInvitation::dispatch($invitation->id, $invitation->generation)->afterCommit();
    DB::rollBack();
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('family_invitations', 0);
});
