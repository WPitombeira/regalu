<?php

// Test-only server router: real auth/Livewire/SQLite, synthetic external catalog.
use App\Services\PublicUrlResolver;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$root = dirname(__DIR__, 2);
$directory = realpath((string) getenv('REGALU_BROWSER_DIRECTORY'));
$database = realpath((string) getenv('DB_DATABASE'));
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing'
    || $directory === false || ! str_starts_with(basename($directory), 'regalu-event-browser-')
    || $database === false || dirname($database) !== $directory || basename($database) !== 'browser.sqlite') {
    http_response_code(404);

    return;
}
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$file = realpath($root.'/public'.$uri);
if ($file !== false && str_starts_with($file, $root.'/public/') && is_file($file)
    && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

define('LARAVEL_START', microtime(true));
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || config('cache.default') !== 'array' || config('session.driver') !== 'database'
    || config('session.connection') !== 'sqlite' || config('mail.default') !== 'array' || config('queue.default') !== 'sync') {
    throw new RuntimeException('Browser server isolation refused.');
}
// Exercise the real parser and application; replace DNS and outbound HTTP only.
// The separate MetadataTransportTest exercises real pinned cURL transport.
$app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => $host === 'catalog.example.com' ? ['93.184.216.34'] : []));
Http::preventStrayRequests();
Http::fake([
    'https://catalog.example.com/gift' => Http::response('<title>Imported gift</title><script type="application/ld+json">{"@type":"Product","offers":{"@type":"Offer","price":"29.95","priceCurrency":"USD"}}</script>'),
    'https://catalog.example.com/no-price' => Http::response('<title>Manual gift</title>'),
    'https://catalog.example.com/ambiguous' => Http::response('<title>Variant gift</title><script type="application/ld+json">{"@type":"Product","offers":{"@type":"AggregateOffer","lowPrice":"10","highPrice":"100","priceCurrency":"USD"}}</script>'),
]);
$app->handleRequest(Request::capture());
