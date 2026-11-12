<?php

use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Notifications\NotificationList;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\Notification;
use App\Models\User;
use App\Services\FamilyInvitationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('projects only safe canonical localized notice data and recognizes stale invitations', function (string $component) {
    Queue::fake();
    $admin = User::factory()->create();
    $recipient = User::factory()->create(['locale' => 'pt_BR']);
    $family = Family::factory()->create(['creator_id' => $admin->id, 'name' => 'Family name sentinel']);
    app(FamilyInvitationService::class)->invite($family, $admin, $recipient->email);
    $notice = Notification::sole();
    $notice->update(['title' => 'Private copy sentinel', 'message' => 'Private copy sentinel', 'action_url' => 'https://example.com']);
    app()->setLocale('pt_BR');
    $mounted = Livewire::actingAs($recipient)->test($component)
        ->assertSee(__('inbox.family_invitation_title'))
        ->assertDontSee('Private copy sentinel')->assertDontSee($family->name)->assertDontSee($family->invite_code);
    $items = $component === NotificationList::class ? $mounted->viewData('notifications')->items() : $mounted->get('recentNotifications')->all();
    $payload = json_encode($items, JSON_THROW_ON_ERROR);
    expect($payload)->not->toContain('deduplication_key', 'related_entity_type', 'related_entity_id', 'user_id', 'https://example.com')
        ->and($items[0]->action_url)->toBeNull()
        ->and($items[0]->available)->toBeTrue();
    FamilyInvitation::sole()->update(['status' => 'CANCELLED']);
    $mounted->call('$refresh')->assertSee(__('inbox.unavailable'));
})->with([NotificationList::class, NotificationBell::class]);

it('renders Portuguese inbox headings actions and empty states without English fallback', function (string $component) {
    $user = User::factory()->create(['locale' => 'pt_BR']);
    app()->setLocale('pt_BR');
    Livewire::actingAs($user)->test($component)
        ->assertSee('Notificações')->assertSee('Nenhuma notificação ainda.')
        ->assertDontSee('Notifications')->assertDontSee('No notifications yet.');

    NotificationService::send($user, 'system', 'Aviso', 'Mensagem');
    Livewire::actingAs($user)->test($component)
        ->assertSee('Marcar todas como lidas')->assertDontSee('Mark all as read');
})->with([NotificationList::class, NotificationBell::class]);
