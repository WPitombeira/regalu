<?php

use App\Models\User;
use App\Services\LoginAttemptService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$script, $directory, $worker] = $argv;
foreach (['APP_ENV' => 'testing', 'DB_URL' => '', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || ! $app->environment('testing')
    || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('Login race requires uncached, isolated SQLite memory configuration.');
}

// Only the file-cache directory is shared. Every process has its own real SQL
// provider, user row and session; no application database or Redis is contacted.
config(['cache.limiter' => 'login-race', 'cache.stores.login-race' => [
    'driver' => 'file', 'path' => $directory.'/cache', 'lock_path' => $directory.'/locks',
]]);
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
User::create(['name' => 'Race fixture', 'email' => 'race@example.com', 'password' => 'fixture-password']);
$service = app(LoginAttemptService::class);

$await = function (string $path): void {
    $deadline = microtime(true) + 15;
    while (! is_file($path)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Login race barrier timed out.');
        }
        usleep(1000);
        clearstatcache(true, $path);
    }
};

if ($worker === 'prime') {
    foreach (range(1, 4) as $attempt) {
        if ($service->attempt('race@example.com', 'incorrect', false, '192.0.2.1')) {
            throw new RuntimeException('Invalid credentials authenticated.');
        }
    }
} else {
    Event::listen(Attempting::class, function () use ($directory, $worker, $await) {
        file_put_contents($directory.'/credential-checks', $worker."\n", FILE_APPEND | LOCK_EX);
        $await($directory.'/release-credentials');
    });
    touch($directory.'/ready-'.$worker);
    $await($directory.'/start');
    if ($service->attempt('race@example.com', 'incorrect', false, '192.0.2.1')) {
        throw new RuntimeException('Invalid credentials authenticated.');
    }
}
fwrite(STDOUT, "completed\n");
