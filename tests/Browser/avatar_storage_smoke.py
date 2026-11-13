"""Real HTTP upload refusal and retry on run-owned storage; no application data."""
import argparse
import base64
from contextlib import contextmanager
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import zlib

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import login

ROOT = Path(__file__).resolve().parents[2]
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==')
COPY = {
    'en': 'Your photo could not be saved. No profile changes were saved. Please try again.',
    'pt_BR': 'Não foi possível salvar sua foto. Nenhuma alteração no perfil foi salva. Tente novamente.',
}
CHECKPOINTS = ('initial', 'selected', 'refused', 'saved')
SOURCES = (
    'app/Livewire/Profile/Settings.php', 'app/Models/User.php', 'app/Providers/AppServiceProvider.php',
    'routes/web.php', 'bootstrap/app.php', 'config/auth.php', 'config/session.php', 'config/filesystems.php',
    'resources/views/settings.blade.php', 'resources/views/components/layouts/app.blade.php',
    'resources/views/components/ui/navbar.blade.php', 'resources/views/components/ui/user/card.blade.php',
    'resources/views/livewire/profile/settings.blade.php', 'resources/css/app.css', 'resources/js/app.js',
    'lang/en/avatar_upload.php', 'lang/pt_BR/avatar_upload.php',
    'tests/Browser/avatar_storage_smoke.py', 'tests/Browser/avatar_storage_report_test.py',
    'tests/Browser/seed-avatar-storage.php', 'tests/Browser/event_history_smoke.py',
    'composer.lock', 'package-lock.json', '.github/workflows/laravel.yml',
)
REASONS = {'seed_failed', 'server_exited', 'server_unready', 'listener_leak', 'storage_isolation',
           'page_http', 'action_http', 'profile_changed', 'draft_changed', 'false_success',
           'avatar_persistence', 'profile_persistence', 'image_bytes', 'page_errors',
           'page_overflow', 'control_overflow', 'empty_measurements', 'png_invalid',
           'case_set', 'incomplete_case', 'source_drift', 'missing_checkpoint', 'artifact_absent',
           'assertion_failed', 'runtime_failed'}


def matrix():
    return [(locale, scheme, width) for locale in COPY for scheme in ('light', 'dark') for width in (390, 1280)]


def case_key(item):
    return '-'.join(map(str, item))


def provenance():
    paths = [ROOT / path for path in (*SOURCES, 'public/build/manifest.json')]
    assert all(path.is_file() for path in paths), 'artifact_absent'
    manifest = json.loads(paths[-1].read_text())
    assets = [ROOT / 'public/build' / entry['file'] for entry in manifest.values()]
    assert assets and all(path.is_file() for path in assets), 'artifact_absent'
    paths += sorted(path for path in (ROOT / 'public/build').rglob('*') if path.is_file())
    return {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest() for path in paths}


@contextmanager
def application(locale):
    scratch = os.environ.get('RUNNER_TEMP') or os.environ.get('TMPDIR')
    if not scratch:
        raise RuntimeError('scratch_required')
    with tempfile.TemporaryDirectory(prefix='regalu-avatar-browser-', dir=scratch) as temporary:
        directory = Path(temporary)
        database = directory / 'browser.sqlite'
        database.touch(mode=0o600)
        for path in ('public', 'views', 'storage/app/private', 'storage/app/public',
                     'storage/framework/cache', 'storage/framework/sessions', 'storage/logs'):
            (directory / path).mkdir(parents=True, exist_ok=True)
        for name in ('index.php', 'build', 'media', 'favicon.ico', 'robots.txt'):
            (directory / 'public' / name).symlink_to(ROOT / 'public' / name)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        origin = f'http://127.0.0.1:{port}'
        password = secrets.token_urlsafe(32)
        # Allow only runtime location/configuration, never inherited app/service credentials.
        env = {key: os.environ[key] for key in ('PATH', 'HOME', 'PHPRC', 'PHP_INI_SCAN_DIR', 'SYSTEMROOT') if key in os.environ}
        env.update(TMPDIR=temporary, APP_ENV='browser', APP_DEBUG='false', APP_URL=origin,
                   APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE='en', DB_URL='', DB_CONNECTION='sqlite',
                   DB_DATABASE=str(database), CACHE_STORE='array', CACHE_LIMITER='array',
                   MAIL_MAILER='array', QUEUE_CONNECTION='sync', SESSION_DRIVER='database',
                   SESSION_CONNECTION='sqlite', SESSION_DOMAIN='null', SESSION_SECURE_COOKIE='false',
                   SESSION_COOKIE='regalu_avatar_' + secrets.token_hex(8), AUTH_GUARD='web',
                   AUTH_MODEL='App\\Models\\User', BCRYPT_ROUNDS='4', FILESYSTEM_DISK='local',
                   LARAVEL_STORAGE_PATH=str(directory / 'storage'),
                   REGALU_BROWSER_DIRECTORY=temporary, REGALU_BROWSER_PASSWORD=password,
                   APP_CONFIG_CACHE=str(directory / 'config.php'), APP_ROUTES_CACHE=str(directory / 'routes.php'),
                   APP_SERVICES_CACHE=str(directory / 'services.php'), APP_PACKAGES_CACHE=str(directory / 'packages.php'),
                   VIEW_COMPILED_PATH=str(directory / 'views'), LOG_CHANNEL='stderr')
        php = ['php', '-d', 'variables_order=EGPCS']
        seed = subprocess.run([*php, str(ROOT / 'tests/Browser/seed-avatar-storage.php')],
                              cwd=ROOT, env=env, capture_output=True, text=True, timeout=60)
        assert seed.returncode == 0, 'seed_failed'
        assert isinstance(json.loads(seed.stdout)['user'], int), 'seed_failed'
        # Check storage through the real HTTP bootstrap, not only the CLI seed.
        probe_path = directory / 'public' / 'fixture-isolation.php'
        probe_path.write_text(
            '<?php require ' + json.dumps(str(ROOT / 'vendor/autoload.php')) + '; '
            '$app = require ' + json.dumps(str(ROOT / 'bootstrap/app.php')) + '; '
            '$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            '$expected = getenv("LARAVEL_STORAGE_PATH"); header("Content-Type: application/json"); '
            'echo json_encode(["owned" => isset($_ENV["LARAVEL_STORAGE_PATH"]) && '
            'storage_path() === $expected && config("filesystems.disks.public.root") === $expected."/app/public" '
            '&& config("filesystems.disks.local.root") === $expected."/app/private"]);'
        )
        router = ROOT / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        server = subprocess.Popen([*php, '-d', 'upload_max_filesize=3M', '-d', 'post_max_size=4M',
                                   '-S', f'127.0.0.1:{port}', str(router)],
                                  cwd=directory / 'public', env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        try:
            for _ in range(100):
                assert server.poll() is None, 'server_exited'
                try:
                    with opener.open(origin + '/fixture-isolation.php', timeout=1) as response:
                        assert response.status == 200 and json.load(response) == {'owned': True}, 'storage_isolation'
                        break
                except (OSError, urllib.error.URLError):
                    time.sleep(0.1)
            else:
                raise AssertionError('server_unready')
            probe_path.unlink()
            yield origin, password, database
        finally:
            if server.poll() is None:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=10)
            with socket.socket() as check:
                assert check.connect_ex(('127.0.0.1', port)) != 0, 'listener_leak'


def record(database):
    with sqlite3.connect(f'file:{database}?mode=ro', uri=True) as connection:
        return connection.execute('select * from users where email = ?', ('avatar@example.test',)).fetchone()


def profile(database):
    with sqlite3.connect(f'file:{database}?mode=ro', uri=True) as connection:
        return connection.execute('select name, phone, avatar_url from users where email = ?', ('avatar@example.test',)).fetchone()


def submit(page):
    def matches(response):
        request = response.request
        if request.method != 'POST' or request.headers.get('content-type', '').split(';', 1)[0] != 'application/json':
            return False
        data = request.post_data_json or {}
        return any(call.get('method') == 'updateProfile' for component in data.get('components', []) for call in component.get('calls', []))
    with page.expect_response(matches) as pending:
        page.locator('form[wire\\:submit="updateProfile"] button[type=submit]').click()
    assert pending.value.status == 200, 'action_http'
    return [event for component in pending.value.json()['components'] for event in component.get('effects', {}).get('dispatches', [])]


def geometry(page):
    return page.locator('form[wire\\:submit="updateProfile"]').evaluate('''form => {
        const box = el => {const r=el.getBoundingClientRect(); return {left:r.left,right:r.right,width:r.width,height:r.height};};
        return {viewport:innerWidth,content:document.documentElement.scrollWidth,
                controls:[...form.querySelectorAll('input,button,img')].map(box)};
    }''')


def check_geometry(data):
    assert len(data['controls']) == 5, 'empty_measurements'
    assert data['content'] <= data['viewport'] + 1, 'page_overflow'
    assert all(box['left'] >= -1 and box['right'] <= data['viewport'] + 1 and box['width'] > 0 and box['height'] > 0
               for box in data['controls']), 'control_overflow'


def validate_png(path):
    """Validate Chromium's noninterlaced 8-bit RGB/RGBA PNG, including deflate data."""
    raw = path.read_bytes()
    assert raw[:8] == b'\x89PNG\r\n\x1a\n', 'png_invalid'
    offset, chunks, compressed = 8, [], bytearray()
    width = height = color = 0
    while offset < len(raw):
        assert offset + 12 <= len(raw), 'png_invalid'
        length = struct.unpack('>I', raw[offset:offset + 4])[0]
        kind = raw[offset + 4:offset + 8]
        payload = raw[offset + 8:offset + 8 + length]
        end = offset + 12 + length
        assert end <= len(raw), 'png_invalid'
        assert zlib.crc32(kind + payload) == struct.unpack('>I', raw[end - 4:end])[0], 'png_invalid'
        chunks.append(kind)
        if kind == b'IHDR':
            assert len(chunks) == 1 and length == 13, 'png_invalid'
            width, height, depth, color, compression, filtering, interlace = struct.unpack('>IIBBBBB', payload)
            assert 0 < width <= 4096 and 0 < height <= 10000 and depth == 8 and color in (2, 6), 'png_invalid'
            assert compression == filtering == interlace == 0, 'png_invalid'
        if kind == b'IDAT':
            compressed.extend(payload)
        if kind == b'IEND':
            assert length == 0 and end == len(raw), 'png_invalid'
        offset = end
    assert chunks and chunks[0] == b'IHDR' and chunks[-1] == b'IEND' and compressed, 'png_invalid'
    stride = 1 + width * (3 if color == 2 else 4)
    decoded = zlib.decompress(compressed)
    assert len(decoded) == height * stride, 'png_invalid'
    assert all(decoded[index] <= 4 for index in range(0, len(decoded), stride)), 'png_invalid'


def journey(browser, item, output, case):
    locale, scheme, width = item
    errors = []
    with application(locale) as (origin, password, database):
        context = browser.new_context(viewport={'width': width, 'height': 900}, locale=locale.replace('_', '-'), color_scheme=scheme)
        context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(origin + '/') else route.abort())
        try:
            page = context.new_page()
            page.on('pageerror', lambda _: errors.append('javascript'))
            page.on('response', lambda response: errors.append('http_5xx') if response.status >= 500 else None)
            login(page, origin, 'avatar@example.test', password)
            assert page.goto(origin + '/settings').status == 200, 'page_http'
            form = page.locator('form[wire\\:submit="updateProfile"]')
            image = form.locator('img')
            expect(image).to_have_attribute('src', '/storage/current.png')
            assert context.request.get(origin + '/storage/current.png').body() == PNG, 'image_bytes'
            before = record(database)
            public = database.parent / 'storage/app/public'
            assert (public / 'avatars').is_file(), 'storage_isolation'

            def capture(name):
                measurements = geometry(page)
                case['geometry'][name] = measurements
                check_geometry(measurements)
                assert not errors, 'page_errors'
                page.screenshot(path=str(output / (name + '.png')), full_page=True, animations='disabled',
                                mask=[page.locator('input[type=password]')])
                validate_png(output / (name + '.png'))
                case['checkpoints'].append(name)

            capture('initial')
            page.locator('#name').fill('Unsaved Browser Profile')
            page.locator('#phone').fill('2222222222')
            page.locator('#avatar').set_input_files({'name': 'photo.png', 'mimeType': 'image/png', 'buffer': PNG})
            expect(image).to_have_attribute('src', re.compile(re.escape(origin) + r'/livewire-[a-f0-9]{8}/preview-file/'))
            expect(image).to_be_visible()
            capture('selected')
            assert submit(page) == [], 'false_success'
            expect(form.get_by_text(COPY[locale], exact=True)).to_be_visible()
            expect(page.locator('#name')).to_have_value('Unsaved Browser Profile')
            expect(page.locator('#phone')).to_have_value('2222222222')
            expect(image).to_have_attribute('src', re.compile(re.escape(origin) + r'/livewire-[a-f0-9]{8}/preview-file/'))
            assert record(database) == before, 'profile_changed'
            assert (public / 'current.png').read_bytes() == PNG, 'image_bytes'
            assert sorted(path.name for path in public.iterdir()) == ['avatars', 'current.png'], 'avatar_persistence'
            capture('refused')
            # Repair only the fixture obstruction; retry the same signed temporary upload.
            (public / 'avatars').unlink()
            events = submit(page)
            assert len(events) == 1 and events[0]['name'] == 'notification', 'false_success'
            expect(form.get_by_text(COPY[locale], exact=True)).to_have_count(0)
            saved = profile(database)
            assert saved[:2] == ('Unsaved Browser Profile', '2222222222'), 'profile_persistence'
            assert saved[2].startswith('/storage/avatars/') and saved[2].endswith('.png'), 'avatar_persistence'
            expect(image).to_have_attribute('src', saved[2])
            assert (public / saved[2].removeprefix('/storage/')).read_bytes() == PNG, 'image_bytes'
            assert context.request.get(origin + saved[2]).body() == PNG, 'image_bytes'
            assert context.request.get(origin + '/storage/current.png').body() == PNG, 'image_bytes'
            capture('saved')
            page.reload()
            expect(page.locator('#name')).to_have_value('Unsaved Browser Profile')
            expect(image).to_have_attribute('src', saved[2])
            assert not errors, 'page_errors'
            case['errors'] = errors
            case['stage'] = 'complete'
        finally:
            context.close()
    assert not database.parent.exists(), 'storage_isolation'


def validate_report(report, output, expected_hashes):
    assert report['source_hashes'] == expected_hashes, 'source_drift'
    keys = [case['key'] for case in report['cases']]
    assert len(keys) == len(set(keys)) and set(keys) == {case_key(item) for item in matrix()}, 'case_set'
    for case in report['cases']:
        assert case['passed'] is True and case['stage'] == 'complete' and case['errors'] == [], 'incomplete_case'
        assert case['checkpoints'] == list(CHECKPOINTS) and set(case['geometry']) == set(CHECKPOINTS), 'missing_checkpoint'
        for name in CHECKPOINTS:
            check_geometry(case['geometry'][name])
            image = output / case['key'] / (name + '.png')
            assert image.is_file(), 'artifact_absent'
            validate_png(image)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--case', choices=[case_key(item) for item in matrix()])
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=False)
    report = {'source': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
              'source_hashes': provenance(), 'cases': []}
    selected = [item for item in matrix() if args.case is None or case_key(item) == args.case]
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for item in selected:
                key = case_key(item)
                output = args.output / key
                output.mkdir()
                case = {'key': key, 'passed': False, 'stage': 'started', 'checkpoints': [], 'geometry': {}, 'errors': []}
                try:
                    journey(browser, item, output, case)
                    case['passed'] = True
                except Exception as error:
                    case['reason'] = str(error) if str(error) in REASONS else ('assertion_failed' if isinstance(error, AssertionError) else 'runtime_failed')
                report['cases'].append(case)
                (args.output / 'results.json').write_text(json.dumps(report, indent=2))
                print(json.dumps({'case': key, 'passed': case['passed'], 'reason': case.get('reason')}), flush=True)
        finally:
            browser.close()
    assert provenance() == report['source_hashes'], 'source_drift'
    if args.case is None:
        validate_report(report, args.output, provenance())
    elif not report['cases'][0]['passed']:
        raise SystemExit(1)


if __name__ == '__main__':
    main()
