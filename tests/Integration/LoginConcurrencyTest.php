<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('admits at most one credential check across processes when four attempts are reserved', function () {
    $directory = sys_get_temp_dir().'/regalu-login-race-'.Str::random(20);
    mkdir($directory, 0700, true);
    $workers = [];
    $command = fn (string $worker) => new Process([PHP_BINARY, base_path('tests/Fixtures/login-race.php'), $directory, $worker], base_path(), timeout: 20);
    $await = function (callable $condition): void {
        $deadline = microtime(true) + 15;
        while (! $condition()) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Login race did not reach the barrier.');
            }
            usleep(1000);
            clearstatcache();
        }
    };

    try {
        $prime = $command('prime');
        $prime->run();
        expect($prime->isSuccessful())->toBeTrue($prime->getErrorOutput().$prime->getOutput());
        foreach (range(1, 6) as $index) {
            $workers[$index] = $command((string) $index);
            $workers[$index]->start();
        }
        $await(fn () => count(glob($directory.'/ready-*')) === 6);
        touch($directory.'/start');
        $await(fn () => is_file($directory.'/credential-checks'));
        // The admitted request remains inside the real authentication guard while
        // its competitors must finish without entering any credential check.
        $await(fn () => count(array_filter($workers, fn (Process $worker) => $worker->isRunning())) === 1);
        expect(file($directory.'/credential-checks', FILE_IGNORE_NEW_LINES))->toHaveCount(1);
        touch($directory.'/release-credentials');
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());
            expect($worker->getOutput())->toBe("completed\n");
        }
    } finally {
        touch($directory.'/release-credentials');
        foreach ($workers as $worker) {
            $worker->stop(0);
        }
        (new Filesystem)->deleteDirectory($directory);
    }
});
