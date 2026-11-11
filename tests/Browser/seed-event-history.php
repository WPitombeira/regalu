<?php

// Synthetic browser fixtures only. Refuse every database not owned by this run.
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
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
$host = User::factory()->create(['name' => 'Browser Host', 'email' => 'host@example.test', 'password' => $password]);
$member = User::factory()->create(['name' => 'Browser Member', 'email' => 'member@example.test', 'password' => $password]);
$wishlist = Wishlist::factory()->create(['user_id' => $member->id, 'name' => 'Private browser wishlist']);
$item = WishlistItem::factory()->create([
    'wishlist_id' => $wishlist->id, 'name' => 'Private browser gift', 'description' => null, 'url' => null,
]);
$event = AmigoSecretoEvent::factory()->create([
    'organizer_id' => $host->id, 'name' => 'Winter gift exchange',
    'description' => 'A synthetic event for browser verification.', 'event_type' => 'CHRISTMAS',
    'status' => 'REVEALED', 'event_date' => today()->subDay(), 'reveal_date' => today()->subDays(2),
    'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL', 'budget_min' => 20, 'budget_max' => 50,
]);
AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $host->id, 'status' => 'ACCEPTED']);
$participant = AmigoSecretoParticipant::create([
    'event_id' => $event->id, 'user_id' => $member->id, 'status' => 'ACCEPTED', 'wishlist_id' => $wishlist->id,
]);
AmigoSecretoDraw::create([
    'event_id' => $event->id, 'drawer_user_id' => $host->id, 'target_user_id' => $member->id, 'draw_date' => now(),
]);
AmigoSecretoEvent::factory()->count(13)->create([
    'organizer_id' => $host->id, 'status' => 'COMPLETED', 'name' => 'Earlier exchange',
    'description' => null, 'event_date' => today()->subYear(), 'created_at' => now()->subYear(),
]);
AmigoSecretoEvent::factory()->create(['organizer_id' => $host->id, 'name' => 'Next exchange']);
echo json_encode([
    'event' => $event->id, 'name' => $event->name, 'code' => $event->event_code,
    'participant' => $participant->id, 'item' => $item->id,
    'copy' => __('event_history'),
], JSON_THROW_ON_ERROR);
