<?php

// Synthetic, disposable browser fixtures; stdout is an in-memory private pipe only.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
$password = (string) getenv('REGALU_BROWSER_PASSWORD');
if (PHP_SAPI !== 'cli' || $directory === false || ! str_starts_with(basename($directory), 'regalu-recovery-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite'
    || filesize($database) !== 0 || strlen($password) < 24
    || ! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'sqlite'
    || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || config('cache.default') !== 'array' || ! in_array(config('cache.limiter'), [null, 'array'], true)
    || config('session.driver') !== 'database' || config('session.connection') !== 'sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync'
    || config('auth.defaults.passwords') !== 'users' || config('auth.defaults.guard') !== 'web'
    || config('auth.passwords.users.table') !== 'password_reset_tokens'
    || config('auth.providers.users.model') !== User::class) {
    throw new RuntimeException('Browser fixture isolation refused.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Browser fixture migration failed.');
}
$valid = User::factory()->create(['name' => 'Recovery Browser', 'email' => 'recovery@example.test', 'password' => $password]);
$expired = User::factory()->create(['name' => 'Expired Browser', 'email' => 'expired@example.test', 'password' => $password]);
User::factory()->create(['name' => 'Request Browser', 'email' => 'request@example.test', 'password' => $password]);
$validToken = Password::createToken($valid);
$expiredToken = Password::createToken($expired);
// Expiration is a labelled synthetic precondition, not an elapsed-time claim.
DB::table('password_reset_tokens')->where('email', $expired->email)->update([
    'created_at' => now()->subMinutes((int) config('auth.passwords.users.expire') + 1),
]);
echo json_encode(['valid' => $validToken, 'expired' => $expiredToken], JSON_THROW_ON_ERROR);
