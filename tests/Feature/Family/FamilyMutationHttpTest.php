<?php

use App\Livewire\Family\FamilyRoster;
use App\Livewire\Family\FamilySettings;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Livewire\Livewire;

function familyMutationSnapshot(string $html, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException('The expected family component was not mounted.');
}

it('enforces current family authority through the real Livewire HTTP endpoint', function (string $action, string $authority) {
    $family = Family::factory()->create();
    $actor = $authority === 'creator without membership' ? $family->creator : User::factory()->create();
    $grant = $authority === 'creator without membership' ? null
        : FamilyMember::create(['family_id' => $family->id, 'user_id' => $actor->id, 'role' => 'ADMIN']);
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'MEMBER']);
    $roster = in_array($action, ['removeMember', 'promoteMember'], true);
    $page = $this->actingAs($actor)->get(route($roster ? 'families.show' : 'families.settings', $family))->assertOk();
    $snapshot = familyMutationSnapshot($page->getContent(), $roster ? 'family.family-roster' : 'family.family-settings');
    $sessionHash = session('password_hash_web');
    expect($sessionHash)->toBeString()->not->toBeEmpty();
    if ($authority === 'revoked administrator') {
        $grant->delete();
    }
    $beforeFamily = $family->fresh()->getAttributes();
    $beforeMember = $member->fresh()->getAttributes();

    $response = $this->actingAs($actor->fresh())->withSession(['password_hash_web' => $sessionHash])
        ->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => $action === 'update' ? ['name' => 'Current administrator', 'description' => 'Saved via HTTP'] : [],
            'calls' => [['path' => '', 'method' => $action, 'params' => $roster ? [$member->id] : []]],
        ]]], ['X-Livewire' => 'true']);

    if ($authority === 'revoked administrator') {
        $response->assertForbidden();
        expect($family->fresh()->getAttributes())->toBe($beforeFamily);
        expect($member->fresh()->getAttributes())->toBe($beforeMember);

        return;
    }

    $response->assertOk();
    match ($action) {
        'update' => expect($family->fresh()->name)->toBe('Current administrator')
            ->and($family->fresh()->description)->toBe('Saved via HTTP'),
        'archive' => expect($family->fresh()->is_archived)->toBeTrue(),
        'regenerateCode' => expect($family->fresh()->invite_code)->not->toBe($beforeFamily['invite_code'])->toHaveLength(12),
        'removeMember' => expect($member->fresh())->toBeNull(),
        'promoteMember' => expect($member->fresh()->role)->toBe('ADMIN'),
    };
})->with(['update', 'archive', 'regenerateCode', 'removeMember', 'promoteMember'])
    ->with(['creator without membership', 'administrator', 'revoked administrator']);

it('preserves successful no-op settings and already-admin promotions', function () {
    $family = Family::factory()->create();
    $member = FamilyMember::create(['family_id' => $family->id, 'user_id' => User::factory()->create()->id, 'role' => 'ADMIN']);
    Livewire::actingAs($family->creator)->test(FamilySettings::class, ['family' => $family])
        ->call('update')->assertOk()->assertDispatched('notification');
    Livewire::actingAs($family->creator)->test(FamilyRoster::class, ['family' => $family])
        ->call('promoteMember', $member->id)->assertOk()->assertDispatched('notification');
    expect($family->fresh()->name)->toBe($family->name);
    expect($member->fresh()->role)->toBe('ADMIN');
});
