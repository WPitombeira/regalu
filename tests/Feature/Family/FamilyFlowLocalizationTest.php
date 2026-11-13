<?php

use App\Livewire\Family\CreateFamily;
use App\Livewire\Family\FamilyRoster;
use App\Livewire\Family\FamilySettings;
use App\Livewire\Family\JoinFamily;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('localizes family creation without translating submitted data', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    $this->withoutVite();
    $page = $this->actingAs($user)->get(route('families.create'))->assertOk();
    foreach ($copy['page'] as $text) {
        $page->assertSee($text);
    }

    $component = Livewire::actingAs($user)->test(CreateFamily::class)
        ->call('create')->assertHasErrors(['name' => 'required'])
        ->assertSee($copy['required'])->assertNotDispatched('notification')
        ->set('name', 'x')->call('create')->assertHasErrors(['name' => 'min'])
        ->assertSee($copy['minimum'])->assertNotDispatched('notification');
    expect(Family::count())->toBe(0);

    $component->set('name', 'Família <amigos>')->set('description', 'My unchanged description')
        ->call('create')->assertHasNoErrors()
        ->assertDispatched('notification', ['message' => $copy['created'], 'type' => 'success']);
    $family = Family::sole();
    $component->assertRedirect(route('families.show', $family));
    expect($family->name)->toBe('Família <amigos>')
        ->and($family->description)->toBe('My unchanged description')
        ->and($family->creator_id)->toBe($user->id)
        ->and($family->members()->sole()->role)->toBe('ADMIN');
})->with([
    'English' => ['en', [
        'page' => ['Create Family', 'Family Name', 'Description', 'Create', 'Cancel'],
        'required' => 'The family name field is required.',
        'minimum' => 'The family name field must be at least 2 characters.',
        'created' => 'Family created successfully.',
    ]],
    'Portuguese' => ['pt_BR', [
        'page' => ['Criar família', 'Nome da família', 'Descrição', 'Criar', 'Cancelar'],
        'required' => 'O campo nome da família é obrigatório.',
        'minimum' => 'O campo nome da família deve ter pelo menos 2 caracteres.',
        'created' => 'Família criada com sucesso.',
    ]],
]);

it('localizes code entry and refusals while retaining explicit membership', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    $family = Family::factory()->create();
    $this->withoutVite();
    $page = $this->actingAs($user)->get(route('families.join'))->assertOk();
    foreach ($copy['page'] as $text) {
        $page->assertSee($text);
    }
    $component = Livewire::actingAs($user)->test(JoinFamily::class)
        ->call('join')->assertHasErrors(['inviteCode' => 'required'])->assertSee($copy['required'])
        ->set('inviteCode', 'short')->call('join')->assertHasErrors(['inviteCode' => 'size'])->assertSee($copy['size'])
        ->set('inviteCode', str_repeat('0', 12))->call('join')->assertHasErrors(['inviteCode'])->assertSee($copy['invalid'])
        ->assertNotDispatched('notification');
    expect($user->families()->count())->toBe(0);
    $component->set('inviteCode', $family->invite_code)->call('join')->assertHasNoErrors()
        ->assertRedirect(route('families.show', $family))
        ->assertDispatched('notification', ['message' => $copy['joined'], 'type' => 'success']);
    expect($family->members()->where('user_id', $user->id)->sole()->role)->toBe('MEMBER');
    Livewire::actingAs($user)->test(JoinFamily::class)->set('inviteCode', $family->invite_code)
        ->call('join')->assertHasErrors(['inviteCode'])->assertSee($copy['duplicate'])
        ->assertNotDispatched('notification');
    expect($family->members()->where('user_id', $user->id)->count())->toBe(1);
})->with([
    'English' => ['en', [
        'page' => ['Join Family', 'Invite Code', 'Enter invite code', 'Cancel'],
        'required' => 'The invite code field is required.',
        'size' => 'The invite code field must be 12 characters.',
        'invalid' => 'Invalid invite code.',
        'joined' => 'You have joined the family.',
        'duplicate' => 'You are already a member of this family.',
    ]],
    'Portuguese' => ['pt_BR', [
        'page' => ['Entrar na família', 'Código de convite', 'Digite o código de convite', 'Cancelar'],
        'required' => 'O campo código de convite é obrigatório.',
        'size' => 'O campo código de convite deve ser 12 caracteres.',
        'invalid' => 'Código de convite inválido.',
        'joined' => 'Você entrou na família.',
        'duplicate' => 'Você já faz parte desta família.',
    ]],
]);

it('localizes settings and describes the action actually completed', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id, 'name' => 'Original name']);
    $oldCode = $family->invite_code;
    $this->withoutVite();
    $page = $this->actingAs($owner)->get(route('families.settings', $family))->assertOk();
    foreach ($copy['page'] as $text) {
        $page->assertSee($text);
    }
    $component = Livewire::actingAs($owner)->test(FamilySettings::class, ['family' => $family])
        ->set('name', 'x')->call('update')->assertHasErrors(['name' => 'min'])->assertSee($copy['minimum'])
        ->assertNotDispatched('notification');
    expect($family->fresh()->name)->toBe('Original name');
    $component->set('name', 'Updated <data>')->set('description', 'Descrição sem tradução')
        ->call('update')->assertHasNoErrors()
        ->assertDispatched('notification', ['message' => $copy['updated'], 'type' => 'success']);
    expect($family->fresh()->name)->toBe('Updated <data>')
        ->and($family->fresh()->description)->toBe('Descrição sem tradução');
    $component->call('regenerateCode')
        ->assertDispatched('notification', ['message' => $copy['regenerated'], 'type' => 'success']);
    expect($family->fresh()->invite_code)->not->toBe($oldCode)->toHaveLength(12);
    $component->call('archive')
        ->assertDispatched('notification', ['message' => $copy['archived'], 'type' => 'success']);
    expect($family->fresh()->is_archived)->toBeTrue();
})->with([
    'English' => ['en', [
        'page' => ['Family Settings', 'Family Name', 'Description', 'Update', 'Invite Code', 'Regenerate', 'Danger Zone', 'Archive', 'Regenerate the invite code? The old code will stop working.', 'Archive this family? Members will no longer see it in their list.'],
        'minimum' => 'The family name field must be at least 2 characters.',
        'updated' => 'Family updated successfully.',
        'regenerated' => 'Invite code regenerated.',
        'archived' => 'Family archived.',
    ]],
    'Portuguese' => ['pt_BR', [
        'page' => ['Configurações da família', 'Nome da família', 'Descrição', 'Atualizar', 'Código de convite', 'Gerar novo código', 'Zona de atenção', 'Arquivar', 'Gerar um novo código de convite? O código anterior deixará de funcionar.', 'Arquivar esta família? Ela não aparecerá mais na lista dos membros.'],
        'minimum' => 'O campo nome da família deve ter pelo menos 2 caracteres.',
        'updated' => 'Família atualizada com sucesso.',
        'regenerated' => 'Novo código de convite gerado.',
        'archived' => 'Família arquivada.',
    ]],
]);

it('localizes roster actions and validation without changing membership values', function (string $locale, array $copy) {
    app()->setLocale($locale);
    Queue::fake();
    $owner = User::factory()->create();
    $user = User::factory()->create(['name' => 'Pessoa <amiga>']);
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $owner->id, 'role' => 'ADMIN']);
    $membership = FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'MEMBER']);
    $this->withoutVite();
    $page = $this->actingAs($owner)->get(route('families.show', $family))->assertOk();
    foreach ($copy['page'] as $text) {
        $page->assertSee($text);
    }
    $page->assertSee('Pessoa <amiga>')->assertDontSee('Pessoa <amiga>', false);

    $component = Livewire::actingAs($owner)->test(FamilyRoster::class, ['family' => $family])
        ->call('inviteByEmail')->assertHasErrors(['inviteEmail' => 'required'])->assertSee($copy['required'])
        ->set('inviteEmail', 'not-an-email')->call('inviteByEmail')
        ->assertHasErrors(['inviteEmail' => 'email'])->assertSee($copy['email'])
        ->assertNotDispatched('notification');
    expect(FamilyInvitation::count())->toBe(0)->and($family->members()->count())->toBe(2);
    Queue::assertNothingPushed();

    $component->call('promoteMember', $membership->id)
        ->assertDispatched('notification', ['message' => $copy['promoted'], 'type' => 'success']);
    expect($membership->fresh()->role)->toBe('ADMIN')
        ->and($membership->fresh()->user->name)->toBe('Pessoa <amiga>');
    $component->call('removeMember', $membership->id)
        ->assertDispatched('notification', ['message' => $copy['removed'], 'type' => 'success']);
    expect($membership->fresh())->toBeNull()->and($family->members()->sole()->user_id)->toBe($owner->id);
})->with([
    'English' => ['en', [
        'page' => ['Family Settings', 'Invite Code', 'Copy', 'Copied!', 'Email invitations', 'Email address', 'Send invitation', 'No email invitations yet.', 'Members', 'Admin', 'Member', 'Promote', 'Remove', 'Promote this member to admin?', 'Remove this member from the family?'],
        'required' => 'The email address field is required.',
        'email' => 'The email address field must be a valid email address.',
        'promoted' => 'Member promoted to admin.',
        'removed' => 'Member removed from family.',
    ]],
    'Portuguese' => ['pt_BR', [
        'page' => ['Configurações da família', 'Código de convite', 'Copiar', 'Copiado!', 'Convites por e-mail', 'Endereço de e-mail', 'Enviar convite', 'Nenhum convite por e-mail ainda.', 'Membros', 'Administrador', 'Membro', 'Promover', 'Remover', 'Promover este membro a administrador?', 'Remover este membro da família?'],
        'required' => 'O campo e-mail é obrigatório.',
        'email' => 'O campo e-mail deve ser um endereço de e-mail válido.',
        'promoted' => 'Membro promovido a administrador.',
        'removed' => 'Membro removido da família.',
    ]],
]);

it('localizes the empty family index and member counts', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    $this->withoutVite();
    $page = $this->actingAs($user)->get(route('families.index'))->assertOk();
    foreach ($copy['empty'] as $text) {
        expect(html_entity_decode(strip_tags($page->getContent()), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toContain($text);
    }
    $family = Family::factory()->create(['creator_id' => $user->id, 'name' => 'Our <family>']);
    FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'ADMIN']);
    $this->get(route('families.index'))->assertOk()->assertSee('Our <family>')->assertSee($copy['one']);
    FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER']);
    $this->get(route('families.index'))->assertOk()->assertSee($copy['two']);
    $family->update(['is_archived' => true]);
    $this->get(route('families.index'))->assertOk()->assertDontSee('Our <family>')->assertSee($copy['empty'][0]);
})->with([
    'English' => ['en', [
        'empty' => ["You don't belong to any family yet.", 'My Families', 'Families', 'Join Family', 'Create Family'],
        'one' => '1 member',
        'two' => '2 members',
    ]],
    'Portuguese' => ['pt_BR', [
        'empty' => ['Você ainda não faz parte de nenhuma família.', 'Minhas famílias', 'Famílias', 'Entrar na família', 'Criar família'],
        'one' => '1 membro',
        'two' => '2 membros',
    ]],
]);
