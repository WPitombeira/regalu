import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const workflow = readFileSync(new URL('../../.github/workflows/laravel.yml', import.meta.url), 'utf8');
const dependabot = readFileSync(new URL('../../.github/dependabot.yml', import.meta.url), 'utf8');

function job(name) {
    const start = workflow.search(new RegExp(`^  ${name}:\\s*$`, 'm'));
    assert.notEqual(start, -1, `missing ${name} job`);
    const nextJob = [...workflow.matchAll(/^  [a-z][\w-]*:\s*$/gm)]
        .find(match => match.index > start);

    return workflow.slice(start, nextJob?.index ?? workflow.length);
}

test('workflow is restricted to master pushes, PRs, and manual dispatch with read-only permissions', () => {
    assert.match(workflow, /^  push:\n    branches: \[master\]$/m);
    assert.match(workflow, /^  pull_request:\n    branches: \[master\]$/m);
    assert.match(workflow, /^  workflow_dispatch:\s*$/m);
    assert.match(workflow, /^permissions:\n  contents: read$/m);
    assert.match(workflow, /cancel-in-progress: true/);
    assert.match(workflow, /github\.event\.pull_request\.number \|\| github\.ref/);
    assert.doesNotMatch(workflow, /pull_request_target|secrets\.|id-token:\s*write/i);
});

test('every job is bounded and every third-party action is pinned to a full commit SHA', () => {
    for (const name of ['php-quality', 'assets', 'tests']) {
        assert.match(job(name), /^    timeout-minutes: \d+$/m, `${name} needs a timeout`);
    }

    const actions = [...workflow.matchAll(/^\s+- uses: ([^\s]+)(?:\s+#.*)?$/gm)].map(([, uses]) => uses);
    assert.ok(actions.length > 0, 'workflow should use pinned actions');
    for (const uses of actions) {
        assert.match(uses, /^[^@]+@[a-f0-9]{40}(?:\s+#.*)?$/, `${uses} must use a full SHA`);
    }

    assert.match(workflow, /path: ~\/\.cache\/composer\/files/);
    assert.doesNotMatch(workflow, /path:.*(?:^|\s)vendor(?:\/|\s|$)/m);
});

test('PHP quality checks Composer validity and security, Pint, and Larastan', () => {
    const quality = job('php-quality');

    assert.match(quality, /composer validate --strict/);
    assert.match(quality, /composer audit --locked/);
    assert.match(quality, /vendor\/bin\/pint --test/);
    assert.match(quality, /vendor\/bin\/phpstan analyse --no-progress/);
});

test('avatar storage refusal and retry run with runtime and receipt gates and always-uploaded evidence', () => {
    const tests = job('tests');
    const step = tests.match(/      - name: Exercise avatar storage refusal and retry\n([\s\S]*?)(?=      - name:)/)?.[1];
    assert.ok(step, 'missing required avatar browser step');
    assert.doesNotMatch(step, /continue-on-error|if:|\|\|\s*true/);
    assert.match(step, /PLAYWRIGHT_BROWSERS_PATH: \$\{\{ runner\.temp \}\}\/regalu-browser-cache/);
    const commands = [...step.matchAll(/"\$RUNNER_TEMP\/regalu-browser-venv\/bin\/python" -B (.+)/g)].map(([, command]) => command);
    assert.deepEqual(commands, [
        'tests/Browser/avatar_storage_report_test.py',
        'tests/Browser/avatar_storage_smoke.py --output coverage/avatar-storage-browser',
        'tests/Browser/avatar_storage_report_test.py --report coverage/avatar-storage-browser/results.json',
    ]);
    assert.match(tests, /name: Upload avatar storage browser evidence\n\s+if: always\(\)\n\s+uses: actions\/upload-artifact@/);
    assert.match(tests, /name: avatar-storage-browser\n\s+path: \|\n\s+coverage\/avatar-storage-browser\/results\.json\n\s+coverage\/avatar-storage-browser\/\*\*\/\*\.png/);
});

test('assets run native Vite, portable tooling smoke tests, npm audit, and publish the native build', () => {
    const assets = job('assets');

    assert.match(assets, /npm audit/);
    assert.match(assets, /npm run build\s*$/m);
    assert.match(assets, /npm run test:tooling/);
    assert.match(assets, /name: vite-build/);
    assert.match(assets, /path: public\/build/);
});

test('PHP tests use PCOV, an ephemeral test environment, coverage threshold, and always-uploaded reports', () => {
    const tests = job('tests');

    assert.match(tests, /php-version: ['"]8\.5\.12['"]/);
    assert.match(tests, /coverage: pcov/);
    assert.match(tests, /extension_loaded\(/);
    assert.match(tests, /"pcov"/);
    for (const extension of ['curl', 'dom', 'pdo_sqlite', 'sockets']) {
        assert.match(tests, new RegExp(`['\"]${extension}['\"]`), `missing ${extension}`);
    }
    assert.match(tests, /APP_ENV: testing/);
    assert.match(tests, /DB_CONNECTION: sqlite/);
    assert.match(tests, /DB_DATABASE: ':memory:'/);
    assert.match(tests, /Prepare isolated test environment/);
    assert.match(tests, /needs: assets/);
    assert.match(tests, /actions\/download-artifact@/);
    assert.match(tests, /php artisan test --coverage --min=80 --coverage-clover=coverage\/clover\.xml --log-junit=coverage\/junit\.xml/);
    assert.match(tests, /name: Upload PHP coverage reports\n\s+if: always\(\)\n\s+uses: actions\/upload-artifact@/);
    assert.match(tests, /coverage\/clover\.xml\n\s+coverage\/junit\.xml/);
});

test('release smoke builds caches and migrates a disposable SQLite database both directions, then clears cached config', () => {
    const tests = job('tests');

    for (const command of ['config:cache', 'route:cache', 'view:cache', 'migrate --force', 'migrate:rollback --force']) {
        assert.ok(tests.includes(`php artisan ${command}`), `missing ${command}`);
    }
    assert.match(tests, /APP_ENV: production/);
    assert.match(tests, /DB_DATABASE: \$\{\{ runner\.temp \}\}\/regalu-release\.sqlite/);
    assert.match(tests, /name: Clear release smoke caches\n\s+if: always\(\)[\s\S]*?php artisan config:clear/);
    assert.match(tests, /rm -f bootstrap\/cache\/config\.php/);
});

test('profile settings runs the full browser matrix and validates its always-uploaded evidence', () => {
    const tests = job('tests');

    assert.match(tests, /"\$RUNNER_TEMP\/regalu-browser-venv\/bin\/python" -B tests\/Browser\/profile_settings_runtime_test\.py\s*$/m);
    assert.match(tests, /"\$RUNNER_TEMP\/regalu-browser-venv\/bin\/python" -B tests\/Browser\/profile_settings_smoke\.py --output coverage\/profile-settings-browser\s*$/m);
    assert.match(tests, /"\$RUNNER_TEMP\/regalu-browser-venv\/bin\/python" -B tests\/Browser\/profile_settings_report_test\.py --output coverage\/profile-settings-browser\s*$/m);
    assert.match(tests, /name: Upload profile settings browser evidence\n\s+if: always\(\)\n\s+uses: actions\/upload-artifact@/);
    assert.match(tests, /name: profile-settings-browser\n\s+path: \|\n\s+coverage\/profile-settings-browser\/results\.json\n\s+coverage\/profile-settings-browser\/\*\*\/\*\.png/);
});

test('password recovery runs the complete browser matrix and preserves per-case evidence', () => {
    const tests = job('tests');
    const command = tests.split('\n').find(line => line.includes('tests/Browser/password_recovery_smoke.py'));

    assert.ok(command, 'missing password recovery browser runner');
    assert.match(command, /-B tests\/Browser\/password_recovery_smoke\.py --output coverage\/password-recovery-browser\s*$/);
    assert.doesNotMatch(command, /--case/);
    assert.match(tests, /name: Upload password recovery browser evidence\n\s+if: always\(\)\n\s+uses: actions\/upload-artifact@/);
    assert.match(tests, /name: password-recovery-browser\n\s+path: \|\n\s+coverage\/password-recovery-browser\/results\.json\n\s+coverage\/password-recovery-browser\/\*\*\/result\.json\n\s+coverage\/password-recovery-browser\/\*\*\/\*\.png\n\s+if-no-files-found: error/);
});

test('auth forms run real browser journeys and retain only structured evidence and screenshots', () => {
    const tests = job('tests');
    const run = tests.match(/      - name: Exercise auth forms[\s\S]*?(?=\n      - name:)/)?.[0];
    assert.ok(run, 'missing auth form browser gate');
    assert.match(run, /PLAYWRIGHT_BROWSERS_PATH: \$\{\{ runner\.temp \}\}\/regalu-browser-cache/);
    assert.match(run, /"\$RUNNER_TEMP\/regalu-browser-venv\/bin\/python" -B tests\/Browser\/auth_forms_smoke\.py --output coverage\/auth-forms-browser/);
    assert.doesNotMatch(run, /continue-on-error|\|\| true|if: always/);

    const upload = tests.match(/      - name: Upload auth form browser evidence[\s\S]*?(?=\n      - name:)/)?.[0];
    assert.ok(upload, 'missing auth form browser artifact');
    assert.match(upload, /if: always\(\)/);
    assert.match(upload, /actions\/upload-artifact@[a-f0-9]{40}/);
    assert.match(upload, /name: auth-forms-browser/);
    assert.match(upload, /coverage\/auth-forms-browser\/results\.json\n\s+coverage\/auth-forms-browser\/\*\*\/\*\.png/);
});

test('Dependabot checks Composer, npm, and GitHub Actions weekly with grouped patch/minor updates', () => {
    for (const ecosystem of ['composer', 'npm', 'github-actions']) {
        assert.match(dependabot, new RegExp(`package-ecosystem: ${ecosystem}`));
    }
    assert.equal((dependabot.match(/interval: weekly/g) ?? []).length, 3);
    assert.equal((dependabot.match(/^\s+- minor$/gm) ?? []).length, 3);
    assert.equal((dependabot.match(/^\s+- patch$/gm) ?? []).length, 3);
});
