import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const vite = JSON.parse(readFileSync(new URL('node_modules/vite/package.json', root), 'utf8'));

test('the portable Vite command builds the real application assets', { timeout: 60000 }, () => {
    const result = spawnSync(process.execPath, ['--run', 'build:portable'], {
        cwd: root,
        encoding: 'utf8',
        timeout: 55000,
    });

    assert.equal(result.status, 0, result.stdout + result.stderr);
    assert.match(result.stdout, new RegExp(`vite v${vite.version.replaceAll('.', '\\.')}`));

    const manifest = JSON.parse(readFileSync(new URL('public/build/manifest.json', root), 'utf8'));
    for (const entry of ['resources/css/app.css', 'resources/js/app.js']) {
        assert.equal(manifest[entry].isEntry, true);
        assert.ok(readFileSync(new URL(`public/build/${manifest[entry].file}`, root)).length > 0);
    }
});
