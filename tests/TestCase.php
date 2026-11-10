<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // This runs before RefreshDatabase / DatabaseMigrations can drop any tables.
        if (! $app->environment('testing') || $app->configurationIsCached()) {
            throw new RuntimeException('Tests require uncached testing configuration before migrations.');
        }

        // Reject inherited external services before a test can flush cache or send work.
        $config = $app->make('config');
        if ($config->get('cache.default') !== 'array'
            || $config->get('cache.stores.array.driver') !== 'array'
            || ($config->get('cache.limiter') ?? 'array') !== 'array'
            || $config->get('session.driver') !== 'array'
            || $config->get('mail.default') !== 'array'
            || $config->get('queue.default') !== 'sync') {
            throw new RuntimeException('Tests require isolated array cache/session/mail and sync queue services.');
        }

        // Inspect the resolved connection: a URL can override the named driver's settings.
        $connection = $app->make('db')->connection();

        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Tests require an isolated SQLite :memory: database before migrations.');
        }

        return $app;
    }
}
