<?php

use App\Livewire\AmigoSecreto\JoinEvent;
use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$root = dirname(__DIR__, 2);
$directory = realpath((string) getenv('REGALU_MYSQL_DIRECTORY'));
if ($directory === false || ! str_starts_with(basename($directory), 'regalu-join-mysql-')) {
    throw new RuntimeException('Owned fixture directory required.');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->useEnvironmentPath($directory)->loadEnvironmentFrom('.env');
$app->make(Kernel::class)->bootstrap();
// Avoid Laravel's console exception renderer exposing fixture values or exiting 0.
set_exception_handler(function (Throwable $error): never {
    fwrite(STDERR, json_encode(['passed' => false, 'error_class' => $error::class], JSON_THROW_ON_ERROR)."\n");
    exit(1);
});
$connection = DB::connection();
if ($app->configurationIsCached() || ! $app->environment('qualification')
    || $connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'regalu_join_probe'
    || $connection->getConfig('unix_socket') !== $directory.'/socket/mysqld.sock'
    || $connection->getConfig('url') || config('cache.default') !== 'array'
    || config('session.driver') !== 'array' || config('mail.default') !== 'array'
    || config('queue.default') !== 'sync' || storage_path() !== $directory.'/storage'
    || $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('Native fixture isolation refused.');
}
if ($connection->getSchemaBuilder()->getTables() !== []) {
    throw new RuntimeException('Expected an empty disposable database.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Disposable migration failed.');
}
$host = User::create(['name' => 'Host', 'email' => 'host@example.test', 'password' => bin2hex(random_bytes(24))]);
$guest = User::create(['name' => 'Guest', 'email' => 'guest@example.test', 'password' => bin2hex(random_bytes(24))]);
Auth::setUser($guest);
$results = [];
foreach ([true, false] as $invited) {
    $event = AmigoSecretoEvent::create([
        'organizer_id' => $host->id, 'name' => 'Native SQL fixture',
        'event_code' => $invited ? 'INVITATION01' : 'DIRECTCODE01',
        'event_type' => 'CASUAL', 'status' => 'PLANNING', 'is_archived' => false,
    ]);
    $invite = $invited ? AmigoSecretoParticipant::create([
        'event_id' => $event->id, 'invite_email' => ' Guest@Example.Test ', 'status' => 'INVITED',
    ]) : null;
    try {
        $component = Livewire::actingAs($guest)->test(JoinEvent::class, ['code' => strtolower($event->event_code)]);
        $component->call('join')->assertHasNoErrors()->assertRedirect(route('amigo-secreto.show', $event))
            ->assertDispatched('notification');
        $participant = $event->participants()->where('user_id', $guest->id)->sole();
        $passed = $participant->status === 'ACCEPTED' && $participant->accepted_at !== null
            && ($invite === null || $participant->id === $invite->id);
        $results[] = ['case' => $invited ? 'invitation' : 'code', 'passed' => $passed];
    } catch (QueryException $error) {
        $results[] = ['case' => $invited ? 'invitation' : 'code', 'passed' => false,
            'sqlstate' => $error->errorInfo[0] ?? null, 'driver_code' => $error->errorInfo[1] ?? null,
            'sql' => $error->getSql()];
    }
}
$passed = array_all($results, fn ($result) => $result['passed']);
echo json_encode(['passed' => $passed, 'version' => DB::selectOne('select version() as version')->version,
    'driver' => $connection->getDriverName(), 'migrations' => DB::table('migrations')->count(),
    'source_sha256' => hash_file('sha256', $root.'/app/Livewire/AmigoSecreto/JoinEvent.php'),
    'cases' => $results], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
exit($passed ? 0 : 1);
