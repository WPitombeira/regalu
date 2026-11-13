<?php

// Synthetic account; the ephemeral login secret stays in this disposable fixture.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
if ($directory === false || ! str_starts_with(basename($directory), 'regalu-budget-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite'
    || filesize($database) !== 0
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
$password = getenv('REGALU_BROWSER_PASSWORD');
if (! is_string($password) || strlen($password) < 24) {
    throw new RuntimeException('Browser login fixture missing.');
}
User::create([
    'name' => 'Budget Host', 'email' => 'budget@example.test', 'password' => $password,
    'locale' => config('app.locale'), 'avatar_url' => '/media/logo.png',
]);
echo json_encode(['ready' => true], JSON_THROW_ON_ERROR);
