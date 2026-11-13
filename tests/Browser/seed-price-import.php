<?php

// Synthetic browser fixtures only. Never seed an application database.
use App\Models\AmigoSecretoDraw;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
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
$owner = User::factory()->create(['name' => 'Gift Owner', 'email' => 'owner@example.test', 'password' => $password, 'locale' => app()->getLocale()]);
$buyer = User::factory()->create(['name' => 'Gift Buyer', 'email' => 'buyer@example.test', 'password' => $password, 'locale' => app()->getLocale()]);
$wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'name' => 'My gift ideas', 'description' => null, 'type' => 'GENERIC', 'privacy' => 'SPECIFIC']);
WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
WishlistItem::factory()->create([
    'wishlist_id' => $wishlist->id, 'name' => 'Legacy gift', 'description' => null,
    'url' => null, 'image_url' => null, 'price_min' => '12.34', 'price_max' => '12.34', 'currency' => null,
    'priority' => 'MEDIUM', 'status' => 'AVAILABLE',
]);
$event = AmigoSecretoEvent::factory()->create([
    'organizer_id' => $owner->id, 'name' => 'Gift exchange', 'description' => null,
    'status' => 'DRAWS_COMPLETE', 'event_date' => today()->addDays(10), 'reveal_date' => today()->addWeek(),
    'recipient_visibility' => 'ANONYMOUS_UNTIL_REVEAL',
]);
AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $owner->id, 'status' => 'ACCEPTED', 'wishlist_id' => $wishlist->id]);
AmigoSecretoParticipant::create(['event_id' => $event->id, 'user_id' => $buyer->id, 'status' => 'ACCEPTED']);
AmigoSecretoDraw::create(['event_id' => $event->id, 'drawer_user_id' => $buyer->id, 'target_user_id' => $owner->id, 'draw_date' => now()]);
echo json_encode(['wishlist' => $wishlist->id, 'event' => $event->id], JSON_THROW_ON_ERROR);
