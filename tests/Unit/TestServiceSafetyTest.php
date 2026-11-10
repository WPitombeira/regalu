<?php

use Symfony\Component\Process\Process;

it('isolates services from inherited shell values before any test setup', function (bool $overrideAfterConfiguration) {
    $process = new Process([
        PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/pest',
        'tests/Fixtures/ServiceIsolationProbeTest.php', '--no-coverage',
    ], dirname(__DIR__, 2), [
        'CACHE_STORE' => 'redis',
        'SESSION_DRIVER' => 'redis',
        'QUEUE_CONNECTION' => 'redis',
        'MAIL_MAILER' => 'smtp',
        'REGALU_CACHE_PROBE_OVERRIDE' => $overrideAfterConfiguration ? '1' : false,
    ]);
    $process->setTimeout(30)->run();
    $output = $process->getOutput().$process->getErrorOutput();

    if ($overrideAfterConfiguration) {
        expect($process->getExitCode())->not->toBe(0);
        expect($output)->toContain('Tests require isolated array cache/session/mail and sync queue services.');
    } else {
        expect($process->getExitCode())->toBe(0, $output);
    }
})->with(['inherited services are isolated' => false, 'unsafe effective cache is rejected' => true]);
