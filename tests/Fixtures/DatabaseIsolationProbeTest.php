<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Only run explicitly from TestDatabaseSafetyTest, never against an application database.
if (getenv('REGALU_DATABASE_PROBE_OVERRIDE')) {
    $url = getenv('REGALU_DATABASE_PROBE_OVERRIDE');
    putenv('DB_URL='.$url);
    $_ENV['DB_URL'] = $_SERVER['DB_URL'] = $url;
}

pest()->extend(TestCase::class)->use(DatabaseMigrations::class)->in(__FILE__);

it('uses only the disposable in-memory connection before database migrations', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');
});
