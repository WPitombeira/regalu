import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { once } from 'node:events';
import test from 'node:test';
import { setTimeout } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

test('Octane watches PHP globs with the installed Chokidar and Node', { timeout: 15000 }, async () => {
    const directory = await mkdtemp(join(tmpdir(), 'regalu-octane-'));
    const watcher = fileURLToPath(new URL('../../vendor/laravel/octane/bin/file-watcher.cjs', import.meta.url));
    const child = spawn(process.execPath, [watcher, JSON.stringify([`${directory.replaceAll('\\', '/')}/*.php`])], {
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    let output = '';
    child.stdout.on('data', chunk => output += chunk);
    child.stderr.on('data', chunk => output += chunk);
    const closed = once(child, 'close');

    try {
        // Repeated writes make this independent of the watcher's startup timing.
        for (let attempt = 0; attempt < 50 && !/File (added|changed)/.test(output); attempt++) {
            assert.equal(child.exitCode, null, output);
            await writeFile(join(directory, 'probe.php'), `<?php // ${attempt}\n`);
            await setTimeout(100);
        }
        assert.match(output, /File (added|changed)/);
    } finally {
        child.kill();
        await closed;
        await rm(directory, { recursive: true, force: true });
    }
});
