<?php

// Synthetic guest fixtures with persistent cache, exclusively inside this browser run.
use App\Models\Newsletter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
if ($directory === false || ! str_starts_with(basename($directory), 'regalu-event-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite'
    || filesize($database) !== 0 || ! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'sqlite'
    || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || config('cache.default') !== 'database' || ! in_array(config('cache.limiter'), [null, 'database'], true)
    || config('cache.stores.database.connection') !== 'sqlite' || config('cache.stores.database.lock_connection') !== 'sqlite'
    || config('cache.stores.database.table') !== 'cache' || config('cache.stores.database.lock_table') !== 'cache_locks'
    || config('session.driver') !== 'database' || config('session.connection') !== 'sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync') {
    throw new RuntimeException('Browser fixture isolation refused.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Browser fixture migration failed.');
}
Newsletter::factory()->create([
    'email' => 'Inactive@Example.Test', 'active' => false,
    'created_at' => now()->subMonth(), 'updated_at' => now()->subMonth(),
]);
echo json_encode(['cache' => 'database', 'fixture' => 'inactive newsletter only'], JSON_THROW_ON_ERROR);
