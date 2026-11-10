import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8');

test('public avatar storage links are documented locally and refreshed during deployment', () => {
    const readme = read('../../README.md');
    const deploy = read('../../.scripts/deploy.sh');
    const setup = readme.indexOf('php artisan storage:link');
    const deployment = deploy.indexOf('php artisan storage:link --force');
    const productionStart = deploy.indexOf('php artisan up');

    assert.notEqual(setup, -1, 'local setup must create the public storage link');
    assert.notEqual(deployment, -1, 'deployment must refresh the configured storage symlink');
    assert.ok(productionStart > deployment, 'refresh the link before leaving maintenance mode');
});

test('local and production invitation URLs follow APP_URL through cache refresh and worker restart', () => {
    const envExample = read('../../.env.example');
    const readme = read('../../README.md');
    const deploy = read('../../.scripts/deploy.sh');
    const invitationJob = read('../../app/Jobs/SendEventInvitation.php');

    assert.match(envExample, /^APP_URL=http:\/\/127\.0\.0\.1:8000$/m);
    assert.match(readme, /APP_URL=http:\/\/127\.0\.0\.1:8000/);
    assert.match(readme, /php artisan serve --host=127\.0\.0\.1 --port=8000/);
    assert.match(readme, /canonical HTTPS origin/i);
    assert.match(readme, /php artisan config:clear && php artisan config:cache/);
    assert.match(readme, /php artisan queue:restart/);
    assert.ok(
        deploy.indexOf('php artisan queue:restart') > deploy.indexOf('php artisan optimize'),
        'restart long-running workers only after deployment config caches are refreshed',
    );
    assert.match(invitationJob, /route\('amigo-secreto\.public-join'/);
    assert.doesNotMatch(invitationJob, /https?:\/\/localhost/);
});
