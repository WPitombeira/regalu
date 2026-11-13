<?php

use App\Jobs\SendFamilyInvitation;
use App\Livewire\Family\FamilyRoster;
use App\Livewire\Family\FamilySettings;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('localizes invitation status and pagination accessibility', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    foreach (range(1, 12) as $number) {
        FamilyInvitation::create([
            'family_id' => $family->id,
            'inviter_id' => $owner->id,
            'email' => "person{$number}@example.test",
            'generation' => "synthetic-generation-{$number}",
            'status' => match ($number) {
                9 => 'ACCEPTED',
                10 => 'DECLINED',
                11 => 'CANCELLED',
                default => 'PENDING',
            },
            'expires_at' => $number === 12 ? now()->subDay() : now()->addDays(7),
        ]);
    }
    $panel = Livewire::actingAs($owner)->test(FamilyRoster::class, ['family' => $family]);
    foreach ($copy['page'] as $text) {
        $panel->assertSee($text);
    }
    $panel->assertSee($copy['expires'].' '.now()->addDays(7)->toDateString());
    $panel->assertSeeHtml('aria-label="'.$copy['navigation'].'"')
        ->assertSeeHtml('aria-label="'.$copy['page_two'].'"')
        ->assertSee('person12@example.test')->assertDontSee('person1@example.test')
        ->call('nextPage', 'invitationsPage')->assertSee('person1@example.test')->assertDontSee('person12@example.test')
        ->call('previousPage', 'invitationsPage')->assertSee('person12@example.test');
    expect(FamilyInvitation::count())->toBe(12)->and($family->members()->count())->toBe(0);
})->with([
    'English' => ['en', [
        'page' => ['Pending', 'Invitation accepted.', 'Invitation declined.', 'Invitation cancelled.', 'Expired', 'Cancel invitation', 'Showing', 'results'],
        'expires' => 'Expires',
        'navigation' => 'Invitation pages',
        'page_two' => 'Go to page 2',
    ]],
    'Portuguese' => ['pt_BR', [
        'page' => ['Pendente', 'Convite aceito.', 'Convite recusado.', 'Convite cancelado.', 'Expirado', 'Cancelar convite', 'Mostrando', 'resultados'],
        'expires' => 'Expira em',
        'navigation' => 'Páginas de convites',
        'page_two' => 'Ir para a página 2',
    ]],
]);

it('keeps scoped catalogs complete without fallback and preserves placeholders', function () {
    foreach (['family_forms', 'family_invitations'] as $catalog) {
        $english = Arr::dot(require lang_path("en/{$catalog}.php"));
        $portuguese = Arr::dot(require lang_path("pt_BR/{$catalog}.php"));
        expect(array_keys($portuguese))->toBe(array_keys($english));
        foreach ($english as $key => $message) {
            expect($message)->toBeString()->not->toBeEmpty();
            expect($portuguese[$key])->toBeString()->not->toBeEmpty();
            preg_match_all('/:[a-zA-Z_]+/', $message, $enPlaceholders);
            preg_match_all('/:[a-zA-Z_]+/', $portuguese[$key], $ptPlaceholders);
            expect($ptPlaceholders[0])->toBe($enPlaceholders[0]);
            foreach (['en', 'pt_BR'] as $locale) {
                expect(Lang::hasForLocale("{$catalog}.{$key}", $locale))->toBeTrue();
            }
        }
    }
    $messages = require lang_path('en/messages.php');
    $keys = array_merge(
        array_map(fn ($key) => "messages.family.{$key}", array_keys($messages['family'])),
        ['messages.navbar.families', 'messages.buttons.create', 'messages.buttons.cancel', 'messages.buttons.update', 'messages.buttons.archive', 'pagination.previous', 'pagination.next', 'validation.required', 'validation.min.string', 'validation.size.string', 'validation.email', 'validation.max.string'],
    );
    foreach ($keys as $key) {
        foreach (['en', 'pt_BR'] as $locale) {
            expect(Lang::hasForLocale($key, $locale))->toBeTrue();
        }
    }
    $json = json_decode(file_get_contents(lang_path('pt_BR.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach (['Showing', 'to', 'of', 'results'] as $key) {
        expect($json[$key])->toBeString()->not->toBeEmpty();
    }
    expect(trans_choice('family_forms.member_count', 0, [], 'en'))->toBe('0 members')
        ->and(trans_choice('family_forms.member_count', 0, [], 'pt_BR'))->toBe('0 membros');
});

it('keeps translated invitation notices tied to actual invitation state', function (string $locale, array $copy) {
    app()->setLocale($locale);
    Queue::fake();
    $owner = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    $panel = Livewire::actingAs($owner)->test(FamilyRoster::class, ['family' => $family]);
    $panel->set('inviteEmail', str_repeat('a', 250).'@example.test')->call('inviteByEmail')
        ->assertHasErrors(['inviteEmail' => 'max'])->assertSee($copy['max'])->assertNotDispatched('notification');
    expect(FamilyInvitation::count())->toBe(0);
    $panel->set('inviteEmail', ' Invitee@Example.test ')->call('inviteByEmail')->assertHasNoErrors()
        ->assertDispatched('notification', ['message' => $copy['sent'], 'type' => 'success']);
    $invitation = FamilyInvitation::sole();
    expect($invitation->email)->toBe('invitee@example.test')->and($invitation->status)->toBe('PENDING');
    Queue::assertPushed(SendFamilyInvitation::class, 1);
    Livewire::test(FamilyRoster::class, ['family' => $family])->set('inviteEmail', $invitation->email)
        ->call('inviteByEmail')->assertHasErrors('inviteEmail')->assertSee($copy['duplicate'])->assertNotDispatched('notification');
    $panel->call('cancelInvitation', $invitation->id)
        ->assertDispatched('notification', ['message' => $copy['cancelled'], 'type' => 'success']);
    expect($invitation->fresh()->status)->toBe('CANCELLED')->and($family->members()->count())->toBe(0);
})->with([
    'English' => ['en', [
        'max' => 'The email address field must not be greater than 255 characters.',
        'sent' => 'Invitation queued for email delivery.',
        'duplicate' => 'This email is already invited or belongs to a family member.',
        'cancelled' => 'Invitation cancelled.',
    ]],
    'Portuguese' => ['pt_BR', [
        'max' => 'O campo e-mail não pode ser superior a 255 caracteres.',
        'sent' => 'Convite adicionado à fila de envio por e-mail.',
        'duplicate' => 'Este e-mail já foi convidado ou pertence a um membro da família.',
        'cancelled' => 'Convite cancelado.',
    ]],
]);

it('retains authorization on localized settings and roster actions', function (string $locale) {
    app()->setLocale($locale);
    Queue::fake();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    $membership = FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
    $this->withoutVite();
    $this->actingAs($outsider)->get(route('families.show', $family))->assertForbidden();
    $this->actingAs($member)->get(route('families.settings', $family))->assertForbidden();
    foreach (['inviteByEmail' => [], 'promoteMember' => [$membership->id], 'removeMember' => [$membership->id]] as $method => $arguments) {
        Livewire::actingAs($member)->test(FamilyRoster::class, ['family' => $family])
            ->call($method, ...$arguments)->assertForbidden()->assertNotDispatched('notification');
    }
    $settings = Livewire::actingAs($owner)->test(FamilySettings::class, ['family' => $family]);
    $family->update(['is_archived' => true]);
    $settings->call('update')->assertForbidden()->assertNotDispatched('notification');
    expect($membership->fresh()->role)->toBe('MEMBER')->and(FamilyInvitation::count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['en', 'pt_BR']);
