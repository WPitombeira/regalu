<?php

// Refuse anything except a fresh, run-owned browser fixture before migration.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
$password = (string) getenv('REGALU_BROWSER_PASSWORD');
if ($directory === false || ! str_starts_with(basename($directory), 'regalu-toast-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite'
    || filesize($database) !== 0 || strlen($password) < 24
    || ! $app->environment('browser') || $app->runningUnitTests() || $app->configurationIsCached()
    || config('database.default') !== 'sqlite'
    || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || config('cache.default') !== 'array' || ! in_array(config('cache.limiter'), [null, 'array'], true)
    || config('session.driver') !== 'database' || config('session.connection') !== 'sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync'
    || config('filesystems.default') !== 'local'
    || storage_path() !== $directory.'/storage'
    || config('filesystems.disks.local.root') !== $directory.'/storage/app/private'
    || config('filesystems.disks.public.root') !== $directory.'/storage/app/public') {
    throw new RuntimeException('Browser fixture isolation refused.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Browser fixture migration failed.');
}
$user = User::factory()->create([
    'name' => 'Browser Toast', 'email' => 'toast@example.test', 'password' => $password,
    'locale' => app()->getLocale(),
]);
echo json_encode(['user' => $user->id], JSON_THROW_ON_ERROR);
