<?php

use App\Livewire\Family\FamilyRoster;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('exposes sending and cancelling invitations through the mounted admin roster', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $this->actingAs($admin)->get(route('families.show', $family))->assertOk()
        ->assertSee('wire:submit="inviteByEmail"', false)->assertSee('Email invitations');
    $panel = Livewire::test(FamilyRoster::class, ['family' => $family]);
    $panel->set('inviteEmail', 'invite-ui@example.com')->call('inviteByEmail')
        ->assertHasNoErrors()->assertSee('invite-ui@example.com')->assertSee('Pending')
        ->assertDispatched('notification', fn ($event, $params) => $params[0]['message'] === __('family_invitations.sent'));
    $invitation = FamilyInvitation::sole();
    $panel->assertDontSee($invitation->generation)->assertSee('wire:click="cancelInvitation('.$invitation->id.')"', false)
        ->call('cancelInvitation', $invitation->id)->assertSee('Invitation cancelled.')
        ->assertDontSee('wire:click="cancelInvitation(', false);
    $member = User::factory()->create();
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
    Livewire::actingAs($member)->test(FamilyRoster::class, ['family' => $family])
        ->assertDontSee('Email invitations')->assertDontSee('invite-ui@example.com')
        ->call('cancelInvitation', $invitation->id)->assertForbidden();
});

it('does not claim a terminal invitation was cancelled', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    $panel->set('inviteEmail', 'responded@example.com')->call('inviteByEmail');
    $invitation = FamilyInvitation::sole();
    $invitation->update(['status' => 'DECLINED', 'responded_at' => now()]);
    $panel->call('cancelInvitation', $invitation->id)->assertSee('Invitation declined.')
        ->assertNotDispatched('notification');
    expect($invitation->fresh()->status)->toBe('DECLINED');
});

it('paginates the admin invitation history and resets after sending', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    foreach (range(1, 11) as $number) {
        FamilyInvitation::create([
            'family_id' => $family->id, 'inviter_id' => $admin->id,
            'email' => "page-{$number}@example.com", 'generation' => (string) Str::uuid(),
            'status' => 'PENDING', 'expires_at' => now()->addWeek(),
        ]);
    }
    $panel = Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family]);
    $panel->assertSee('page-11@example.com')->assertDontSee('page-1@example.com')
        ->call('setPage', 2, 'invitationsPage')->assertSee('page-1@example.com')->assertDontSee('page-11@example.com')
        ->set('inviteEmail', 'latest@example.com')->call('inviteByEmail')
        ->assertSet('paginators.invitationsPage', 1)->assertSee('latest@example.com');
    $family->update(['is_archived' => true]);
    $panel->call('$refresh')->assertSee('latest@example.com')->assertDontSee('wire:submit="inviteByEmail"', false)
        ->assertDontSee('wire:click="cancelInvitation(', false);
});

it('renders the new invitation copy in both supported locales', function (string $locale, string $heading) {
    app()->setLocale($locale);
    $admin = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $admin->id]);
    Livewire::actingAs($admin)->test(FamilyRoster::class, ['family' => $family])
        ->assertSee($heading)->assertDontSee('family_invitations.');
    $en = Arr::dot(require lang_path('en/family_invitations.php'));
    $pt = Arr::dot(require lang_path('pt_BR/family_invitations.php'));
    expect(array_keys($pt))->toBe(array_keys($en));
    foreach ($en as $key => $value) {
        preg_match_all('/:[a-z_]+/', $value, $enPlaceholders);
        preg_match_all('/:[a-z_]+/', $pt[$key], $ptPlaceholders);
        expect($ptPlaceholders[0])->toBe($enPlaceholders[0]);
    }
})->with([['en', 'Email invitations'], ['pt_BR', 'Convites por e-mail']]);
