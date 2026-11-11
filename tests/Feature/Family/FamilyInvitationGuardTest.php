<?php

use App\Jobs\SendFamilyInvitation;
use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->recipient = User::factory()->create(['email' => 'guard@example.com']);
    $this->family = Family::factory()->create(['creator_id' => $this->admin->id]);
    $this->invitation = FamilyInvitation::create([
        'family_id' => $this->family->id, 'inviter_id' => $this->admin->id,
        'email' => $this->recipient->email, 'generation' => (string) Str::uuid(),
        'status' => 'PENDING', 'expires_at' => now()->addWeek(),
    ]);
    $this->url = URL::temporarySignedRoute('family-invitations.show', $this->invitation->expires_at, [
        'invitation' => $this->invitation->id, 'generation' => $this->invitation->generation,
    ]);
});

it('discards stale delivery work without emitting an email', function (string $change) {
    Mail::fake();
    $job = new SendFamilyInvitation($this->invitation->id, $this->invitation->generation);
    expect(serialize($job))->not->toContain($this->recipient->email)->not->toContain($this->family->name);
    match ($change) {
        'deleted' => $this->invitation->delete(),
        'cancelled' => $this->invitation->update(['status' => 'CANCELLED']),
        'accepted' => $this->invitation->update(['status' => 'ACCEPTED']),
        'declined' => $this->invitation->update(['status' => 'DECLINED']),
        'expired' => $this->invitation->update(['expires_at' => now()->subSecond()]),
        'archived' => $this->family->update(['is_archived' => true]),
        'revoked' => $this->invitation->update(['inviter_id' => $this->recipient->id]),
        'reissued' => $this->invitation->update(['generation' => (string) Str::uuid()]),
    };
    $job->handle();
    Mail::assertNothingSent();
})->with(['deleted', 'cancelled', 'accepted', 'declined', 'expired', 'archived', 'revoked', 'reissued']);

it('requires login before showing an invitation and retains its intended URL', function () {
    $this->get($this->url)->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe($this->url);
    $this->post($this->url, ['decision' => 'accept'])->assertRedirect(route('login'));
    $this->assertDatabaseCount('family_members', 0);
});

it('rejects malformed decisions without changing invitation state', function (mixed $decision) {
    $this->actingAs($this->recipient)->post($this->url, ['decision' => $decision])->assertSessionHasErrors('decision');
    expect($this->invitation->fresh()->status)->toBe('PENDING');
    $this->assertDatabaseCount('family_members', 0);
})->with(['missing' => [null], 'unknown' => ['join'], 'array' => [['accept']]]);

it('enforces real request forgery middleware on recipient decisions', function () {
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $this->actingAs($this->recipient)->withHeader('Sec-Fetch-Site', 'cross-site')
        ->post($this->url, ['decision' => 'accept'])->assertStatus(419);
    expect($this->invitation->fresh()->status)->toBe('PENDING');
    $this->withHeader('Sec-Fetch-Site', 'same-origin')
        ->post($this->url, ['decision' => 'accept'])->assertRedirect(route('families.show', $this->family));
    expect(FamilyMember::sole()->user_id)->toBe($this->recipient->id);
});

it('fails closed when the send reservation is already held', function () {
    Queue::fake();
    $lock = Cache::lock('family-invitations:sender:'.$this->admin->id.':reservation', 10);
    expect($lock->get())->toBeTrue();
    try {
        Livewire::actingAs($this->admin)->test(FamilyRoster::class, ['family' => $this->family])
            ->set('inviteEmail', 'locked@example.com')->call('inviteByEmail')->assertHasErrors('inviteEmail');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('family_invitations', 1);
    } finally {
        $lock->release();
    }
});

it('does not let an admin cancel another family invitation', function () {
    $other = Family::factory()->create(['creator_id' => $this->admin->id]);
    Livewire::actingAs($this->admin)->test(FamilyRoster::class, ['family' => $other])
        ->call('cancelInvitation', $this->invitation->id)->assertNotFound();
    expect($this->invitation->fresh()->status)->toBe('PENDING');
});

it('validates email before admitting any send', function (string $email) {
    Queue::fake();
    Livewire::actingAs($this->admin)->test(FamilyRoster::class, ['family' => $this->family])
        ->set('inviteEmail', $email)->call('inviteByEmail')->assertHasErrors('inviteEmail');
    Queue::assertNothingPushed();
    $this->assertDatabaseCount('family_invitations', 1);
})->with(['', 'not-an-email', str_repeat('a', 256).'@example.com']);
