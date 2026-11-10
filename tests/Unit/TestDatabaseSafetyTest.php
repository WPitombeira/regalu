<?php

use Symfony\Component\Process\Process;

it('isolates the database before integration setup can migrate', function (bool $overrideAfterConfiguration) {
    $path = tempnam(sys_get_temp_dir(), 'regalu-db-sentinel-');
    $database = new PDO('sqlite:'.$path);
    $database->exec('CREATE TABLE sentinel (value TEXT)');
    $database->exec("INSERT INTO sentinel VALUES ('untouched')");

    try {
        $process = new Process([
            PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/pest',
            'tests/Fixtures/DatabaseIsolationProbeTest.php', '--no-coverage',
        ], dirname(__DIR__, 2), [
            'DB_URL' => 'sqlite:///'.$path,
            'REGALU_DATABASE_PROBE_OVERRIDE' => $overrideAfterConfiguration ? 'sqlite:///'.$path : false,
        ]);
        $process->setTimeout(30)->run();

        expect($database->query("SELECT name FROM sqlite_master WHERE name = 'sentinel'")->fetchColumn())->toBe('sentinel');
        expect($database->query('SELECT value FROM sentinel')->fetchColumn())->toBe('untouched');
        if ($overrideAfterConfiguration) {
            expect($process->getExitCode())->not->toBe(0);
            expect($process->getOutput().$process->getErrorOutput())
                ->toContain('Tests require an isolated SQLite :memory: database before migrations.');
        } else {
            expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
        }
    } finally {
        $database = null;
        unlink($path);
    }
})->with(['inherited URL is cleared' => false, 'unsafe resolved URL is rejected' => true]);
