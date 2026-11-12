<?php

// Synthetic browser fixtures only. Refuse every database not owned by this run.
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use App\Services\FamilyInvitationService;
use App\Services\NotificationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
$password = (string) getenv('REGALU_BROWSER_PASSWORD');
if ($directory === false || ! str_starts_with(basename($directory), 'regalu-event-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite'
    || filesize($database) !== 0 || strlen($password) < 24
    || ! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'sqlite'
    || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || config('cache.default') !== 'array' || ! in_array(config('cache.limiter'), [null, 'array'], true)
    || config('session.driver') !== 'database' || config('session.connection') !== 'sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync') {
    throw new RuntimeException('Browser fixture isolation refused.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Browser fixture migration failed.');
}
$member = User::factory()->create(['name' => 'Browser Member', 'email' => 'member@example.test', 'password' => $password, 'locale' => app()->getLocale()]);
$host = User::factory()->create(['name' => 'Private Host Sentinel', 'email' => 'host@example.test', 'password' => $password]);
$buyer = User::factory()->create(['name' => 'Private Buyer Sentinel', 'email' => 'buyer@example.test', 'password' => $password]);
$recipient = User::factory()->create(['name' => 'Private Recipient Sentinel', 'email' => 'recipient@example.test', 'password' => $password]);
User::factory()->create(['name' => 'Browser Newcomer', 'email' => 'newcomer@example.test', 'password' => $password]);

// Retained legacy rows exercise pagination without simulating domain payloads.
for ($index = 1; $index <= 13; $index++) {
    $legacy = new Notification([
        'user_id' => $member->id, 'type' => 'system', 'title' => 'Earlier update '.$index,
        'message' => 'Synthetic earlier update.', 'is_read' => true,
    ]);
    $legacy->created_at = now()->subDay();
    $legacy->updated_at = now()->subDay();
    $legacy->save();
}
$foreign = NotificationService::send($buyer, 'system', 'Foreign notice sentinel', 'Only for the other account.');
Family::factory()->create(['creator_id' => $member->id, 'name' => 'My family', 'description' => null]);
$family = Family::factory()->create(['creator_id' => $host->id, 'name' => 'Private invited family sentinel', 'description' => null]);
app(FamilyInvitationService::class)->invite($family, $host, $member->email);
$invitation = FamilyInvitation::where('family_id', $family->id)->sole();

$invitedEvent = AmigoSecretoEvent::factory()->create([
    'organizer_id' => $host->id, 'name' => 'Private invited event sentinel', 'description' => null,
    'status' => 'PLANNING', 'event_date' => today()->addWeek(),
]);
$participant = AmigoSecretoParticipant::create(['event_id' => $invitedEvent->id, 'invite_email' => $member->email, 'status' => 'INVITED']);
NotificationService::eventInvited($participant);

$wishlist = Wishlist::factory()->create(['user_id' => $member->id, 'name' => 'My gift ideas', 'description' => null, 'privacy' => 'SPECIFIC']);
WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
$item = WishlistItem::factory()->create([
    'wishlist_id' => $wishlist->id, 'name' => 'Private purchased gift sentinel', 'description' => null,
    'url' => null, 'image_url' => null, 'price_min' => null, 'price_max' => null, 'status' => 'AVAILABLE',
]);
$privateList = Wishlist::factory()->create(['user_id' => $recipient->id, 'name' => 'Private recipient wishlist sentinel', 'privacy' => 'PRIVATE']);
WishlistItem::factory()->create(['wishlist_id' => $privateList->id, 'name' => 'Anonymous gift idea', 'description' => null, 'url' => null, 'image_url' => null]);
$event = AmigoSecretoEvent::factory()->create([
    'organizer_id' => $host->id, 'name' => 'Winter gift exchange', 'description' => null,
    'status' => 'DRAWS_COMPLETE', 'event_date' => today()->addDays(10), 'reveal_date' => today()->addWeek(),
    'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
]);
AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED']);
AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $recipient->id, 'status' => 'ACCEPTED', 'wishlist_id' => $privateList->id]);
AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $member->id, 'target_user_id' => $recipient->id, 'draw_date' => now()]);
NotificationService::assignmentsAvailable($event);

$notices = Notification::where('user_id', $member->id)->whereNotNull('kind')->pluck('id', 'kind');
echo json_encode([
    'member' => $member->id, 'buyer' => $buyer->id, 'foreign' => $foreign->id,
    'wishlist' => $wishlist->id, 'item' => $item->id, 'event' => $event->id,
    'family_invitation' => $invitation->id, 'event_invitation' => $participant->id, 'notices' => $notices,
    'private' => [$host->name, $buyer->name, $recipient->name, $recipient->email, $family->name, $family->invite_code,
        $invitedEvent->name, $invitedEvent->event_code, $privateList->name, $item->name],
    'copy' => ['dashboard' => __('dashboard'), 'inbox' => __('inbox'), 'notifications' => __('messages.notifications'),
        'bell' => __('messages.navbar.notifications'), 'bought' => __('wishlist_purchase.bought')],
], JSON_THROW_ON_ERROR);
