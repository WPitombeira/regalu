<?php

// Synthetic account and public image: every mutable path must belong to this run.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
$password = (string) getenv('REGALU_BROWSER_PASSWORD');
if ($directory === false || ! str_starts_with(basename($directory), 'regalu-profile-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite'
    || filesize($database) !== 0 || strlen($password) < 24
    || ! $app->environment('browser') || $app->runningUnitTests() || $app->configurationIsCached()
    || config('database.default') !== 'sqlite'
    || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || config('cache.default') !== 'array' || ! in_array(config('cache.limiter'), [null, 'array'], true)
    || config('session.driver') !== 'database' || config('session.connection') !== 'sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync'
    || config('filesystems.default') !== 'local'
    || config('filesystems.disks.local.driver') !== 'local' || config('filesystems.disks.public.driver') !== 'local'
    || config('filesystems.disks.local.root') !== $directory.'/storage/app/private'
    || config('filesystems.disks.public.root') !== $directory.'/storage/app/public'
    || realpath($directory.'/public') !== $directory.'/public') {
    throw new RuntimeException('Browser fixture isolation refused.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Browser fixture migration failed.');
}
config(['filesystems.links' => [$directory.'/public/storage' => $directory.'/storage/app/public']]);
if (Artisan::call('storage:link') !== 0 || ! is_link($directory.'/public/storage')) {
    throw new RuntimeException('Browser storage link failed.');
}
$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==', true);
if (! is_string($bytes) || ! Storage::disk('public')->put('avatars/current.png', $bytes)) {
    throw new RuntimeException('Browser image fixture failed.');
}
$user = User::factory()->create([
    'name' => 'Browser Profile', 'email' => 'profile@example.test', 'password' => $password,
    'phone' => '', 'avatar_url' => '/storage/avatars/current.png', 'locale' => app()->getLocale(),
]);
echo json_encode(['user' => $user->id], JSON_THROW_ON_ERROR);
