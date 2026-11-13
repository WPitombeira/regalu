"""Transient-feedback browser checks on an isolated real Laravel HTTP fixture."""
import argparse
import base64
from contextlib import contextmanager
import hashlib
import json
import math
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
CASES = [(locale, scheme, width) for locale in ('en', 'pt_BR')
         for scheme in ('light', 'dark') for width in (320, 390, 1280)]
LABELS = {
    'en': ['Dismiss notification', 'Success', 'Error', 'Warning', 'Information'],
    'pt_BR': ['Dispensar notificação', 'Sucesso', 'Erro', 'Atenção', 'Informação'],
}
PROFILE_SAVED = {
    'en': 'Profile updated successfully.',
    'pt_BR': 'Perfil atualizado com sucesso.',
}
CHECKPOINTS = {'profile': 1, 'stack': 2, 'appended': 2, 'long': 1, 'timer': 1}
INPUTS = (
    'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js',
    '.github/workflows/laravel.yml', 'app/Livewire/Notifications.php', 'app/Livewire/Profile/Settings.php',
    'app/Livewire/Home/LoginForm.php', 'app/Models/User.php', 'bootstrap/app.php', 'routes/web.php',
    'config/filesystems.php', 'config/session.php', 'resources/css/app.css', 'resources/js/app.js',
    'resources/js/bootstrap.js', 'resources/views/livewire/notifications.blade.php',
    'resources/views/livewire/profile/settings.blade.php', 'resources/views/components/layouts/app.blade.php',
    'resources/views/components/ui/navbar.blade.php', 'resources/views/components/ui/user/card.blade.php',
    'resources/views/livewire/notifications/notification-bell.blade.php',
    'resources/views/components/ui/icons/success.blade.php', 'resources/views/components/ui/icons/error.blade.php',
    'resources/views/components/ui/icons/warning.blade.php', 'resources/views/components/ui/icons/info.blade.php',
    'lang/en/toast_feedback.php', 'lang/pt_BR/toast_feedback.php', 'lang/en/messages.php',
    'lang/pt_BR/messages.php', 'lang/pt_BR.json', 'tests/Browser/event_history_smoke.py',
    'tests/Browser/seed-toast-feedback.php', 'tests/Browser/toast_feedback_smoke.py',
    'tests/Browser/toast_feedback_report_test.py',
)


def source_hashes():
    manifest = ROOT / 'public/build/manifest.json'
    assets = {entry['file'] for entry in json.loads(manifest.read_text()).values()}
    paths = set(INPUTS) | {'public/build/manifest.json'} | {'public/build/' + asset for asset in assets}
    return {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in sorted(paths)}


def checkout_identity():
    env = {key: value for key, value in os.environ.items() if not key.startswith('GIT_')}
    env.update(GIT_NO_LAZY_FETCH='1', GIT_OPTIONAL_LOCKS='0', GIT_TERMINAL_PROMPT='0')
    def git(*args):
        return subprocess.check_output(['git', '-c', 'protocol.allow=never', '-c', 'gc.auto=0',
                                        '-c', 'maintenance.auto=false', *args], cwd=ROOT, env=env, text=True).strip()
    # A checkout label is NOT the dirty candidate identity; source_hashes bind tested inputs.
    return {'commit': git('rev-parse', 'HEAD'), 'tree': git('rev-parse', 'HEAD^{tree}'),
            'dirty': bool(git('status', '--porcelain', '--untracked-files=all'))}


@contextmanager
def application(locale):
    scratch = os.environ.get('RUNNER_TEMP') or os.environ.get('TMPDIR')
    assert scratch, 'scratch_required'
    with tempfile.TemporaryDirectory(prefix='regalu-toast-browser-', dir=scratch) as temporary:
        directory = Path(temporary)
        database = directory / 'browser.sqlite'
        database.touch(mode=0o600)
        for name in ('public', 'views', 'storage/app/private', 'storage/app/public',
                     'storage/framework/cache', 'storage/framework/sessions', 'storage/logs'):
            (directory / name).mkdir(parents=True, exist_ok=True)
        for name in ('index.php', 'build', 'media', 'favicon.ico', 'robots.txt'):
            (directory / 'public' / name).symlink_to(ROOT / 'public' / name)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        origin = f'http://127.0.0.1:{port}'
        password = secrets.token_urlsafe(32)
        env = {key: os.environ[key] for key in ('PATH', 'HOME', 'PHPRC', 'PHP_INI_SCAN_DIR', 'SYSTEMROOT') if key in os.environ}
        env.update(TMPDIR=temporary, APP_ENV='browser', APP_DEBUG='false', APP_URL=origin,
                   APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE='en', DB_URL='', DB_CONNECTION='sqlite',
                   DB_DATABASE=str(database), CACHE_STORE='array', CACHE_LIMITER='array',
                   MAIL_MAILER='array', QUEUE_CONNECTION='sync', SESSION_DRIVER='database',
                   SESSION_CONNECTION='sqlite', SESSION_DOMAIN='null', SESSION_SECURE_COOKIE='false',
                   SESSION_COOKIE='regalu_toast_' + secrets.token_hex(8), AUTH_GUARD='web',
                   AUTH_MODEL='App\\Models\\User', BCRYPT_ROUNDS='4', FILESYSTEM_DISK='local',
                   LARAVEL_STORAGE_PATH=str(directory / 'storage'),
                   REGALU_BROWSER_DIRECTORY=temporary, REGALU_BROWSER_PASSWORD=password,
                   APP_CONFIG_CACHE=str(directory / 'config.php'), APP_ROUTES_CACHE=str(directory / 'routes.php'),
                   APP_SERVICES_CACHE=str(directory / 'services.php'), APP_PACKAGES_CACHE=str(directory / 'packages.php'),
                   VIEW_COMPILED_PATH=str(directory / 'views'), LOG_CHANNEL='stderr')
        php = ['php', '-d', 'variables_order=EGPCS']
        seed = subprocess.run([*php, str(ROOT / 'tests/Browser/seed-toast-feedback.php')],
                              cwd=ROOT, env=env, capture_output=True, text=True, timeout=60)
        assert seed.returncode == 0 and isinstance(json.loads(seed.stdout)['user'], int), 'seed_failed'
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
        server = subprocess.Popen([*php, '-S', f'127.0.0.1:{port}', str(router)],
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
    assert not directory.exists(), 'fixture_leak'


def matches(response, method, event=None):
    request = response.request
    if request.method != 'POST' or request.headers.get('content-type', '').split(';', 1)[0] != 'application/json':
        return False
    payload = request.post_data_json or {}
    return any(call.get('method') == method and (event is None or call.get('params', [None])[0] == event)
               for component in payload.get('components', []) for call in component.get('calls', []))


def emit(page, messages):
    # Synthetic presentation inputs exercise the real mounted listener, not a business transition.
    started = time.monotonic()
    with page.expect_response(lambda response: matches(response, '__dispatch', 'notification')) as pending:
        page.evaluate("detail => window.dispatchEvent(new CustomEvent('notification', {detail, bubbles:true}))", messages)
    assert pending.value.status == 200, 'listener_http'
    for message in messages:
        expect(page.locator('#toast-parent [role=alert]').filter(has_text=message['message'])).to_be_visible()
    return started


def geometry(page):
    return page.locator('#toast-parent').evaluate('''stack => {
        const box = el => {const r=el.getBoundingClientRect(); return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height};};
        const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');canvas.width=canvas.height=1;
        const luminance=color=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);const rgb=[...ctx.getImageData(0,0,1,1).data].slice(0,3).map(c=>{c/=255;return c<=0.04045?c/12.92:((c+0.055)/1.055)**2.4;});return rgb[0]*0.2126+rgb[1]*0.7152+rgb[2]*0.0722;};
        const contrast=el=>{const s=getComputedStyle(el),a=luminance(s.color),b=luminance(getComputedStyle(el.closest('[role=alert]')).backgroundColor);return (Math.max(a,b)+0.05)/(Math.min(a,b)+0.05);};
        return {viewport:innerWidth,viewportHeight:innerHeight,content:document.documentElement.scrollWidth,
            cards:[...stack.querySelectorAll('[role=alert]')].filter(el=>el.getBoundingClientRect().height).map(el=>({
                id:el.id,box:box(el),message:box(el.querySelector('div.ms-3')),
                button:box(el.querySelector('button')),
                messageContrast:contrast(el.querySelector('div.ms-3')),buttonContrast:contrast(el.querySelector('button')),
                buttonOnTop:(()=>{const b=el.querySelector('button'),r=b.getBoundingClientRect();return b.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));})(),
                messageOnTop:(()=>{const m=el.querySelector('div.ms-3'),r=m.getBoundingClientRect();return [0.1,0.5,0.9].map(x=>m.contains(document.elementFromPoint(r.x+r.width*x,r.y+r.height/2)));})(),
                unobscured:(()=>{const r=el.getBoundingClientRect();for(let x=r.left+8;x<r.right-8;x+=8)for(let y=r.top+8;y<r.bottom-8;y+=8)if(!el.contains(document.elementFromPoint(x,y)))return false;return true;})()
            }))};
    }''')


def assert_geometry(measurement):
    assert measurement['cards'], 'empty_geometry'
    assert measurement['content'] <= measurement['viewport'], 'page_overflow'
    for card in measurement['cards']:
        for key in ('box', 'message', 'button'):
            assert all(isinstance(value, (int, float)) and not isinstance(value, bool) and math.isfinite(value)
                       for value in card[key].values()), 'invalid_measurement'
            assert card[key]['left'] >= 0 and card[key]['right'] <= measurement['viewport'], 'control_overflow'
            assert card[key]['top'] >= 0 and card[key]['bottom'] <= measurement['viewportHeight'], 'vertical_overflow'
            assert card[key]['width'] > 0 and card[key]['height'] > 0, 'empty_geometry'
        assert card['message']['right'] <= card['button']['left'], 'message_button_overlap'
        assert card['button']['width'] >= 44 and card['button']['height'] >= 44, 'touch_target'
        assert card['unobscured'], 'toast_obscured'
        assert card['buttonOnTop'] and card['messageOnTop'] == [True, True, True], 'toast_obscured'
        assert all(type(card[key]) in (int, float) and math.isfinite(card[key])
                   for key in ('messageContrast', 'buttonContrast')), 'invalid_contrast'
        assert card['messageContrast'] >= 4.5 and card['buttonContrast'] >= 3, 'contrast'


def case_key(locale, scheme, width):
    return f'{locale}-{scheme}-{width}'


def journey(browser, locale, scheme, width, output, result):
    directory = output / result['case']
    directory.mkdir()
    with application(locale) as (origin, password, database):
        context = browser.new_context(viewport={'width': width, 'height': 844},
                                      locale=locale.replace('_', '-'), color_scheme=scheme)
        context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(origin + '/') else route.abort())
        try:
            page = context.new_page()
            page.on('pageerror', lambda error: result['page_errors'].append('page_error'))
            login(page, origin, 'toast@example.test', password)
            def fresh():
                assert page.goto(origin + '/settings').status == 200, 'settings_http'
                expect(page.locator('#name')).to_be_visible()
                expect(page.locator('html')).to_have_attribute('lang', locale.replace('_', '-'))
            def card(message):
                return page.locator('#toast-parent [role=alert]').filter(has_text=message)
            def capture(name):
                measurement = geometry(page)
                result['geometry'][name] = measurement
                assert len(measurement['cards']) == CHECKPOINTS[name], 'checkpoint_count'
                assert_geometry(measurement)
                ids = [entry['id'] for entry in measurement['cards']]
                assert all(ids) and len(set(ids)) == len(ids), 'duplicate_id'
                for button in page.locator('#toast-parent [role=alert]:visible button').all():
                    expect(button).to_have_accessible_name(LABELS[locale][0])
                filename = f'{result["case"]}/{name}.png'
                page.screenshot(path=str(output / filename), full_page=True, animations='disabled',
                                mask=[page.locator('input[type=password]')])
                result['screenshots'].append(filename)
            def dismiss(target, born, key=None):
                # Finish before the earliest possible 3s expiry, including request/render time.
                start = time.monotonic()
                assert start - born < 2, 'dismiss_started_late'
                if key:
                    target.get_by_role('button', name=LABELS[locale][0], exact=True).focus()
                    target.get_by_role('button', name=LABELS[locale][0], exact=True).press(key)
                else:
                    target.get_by_role('button', name=LABELS[locale][0], exact=True).click()
                expect(target).to_be_hidden(timeout=500)
                assert time.monotonic() - start < 1, 'dismiss_slow'
                assert time.monotonic() - born < 2.5, 'dismiss_finished_late'

            fresh()
            # Real business action -> persisted row -> server-dispatched event -> mounted toast.
            page.locator('#name').fill('Browser Toast Saved')
            saved_at = time.monotonic()
            with page.expect_response(lambda response: matches(response, '__dispatch', 'notification')) as notice:
                with page.expect_response(lambda response: matches(response, 'updateProfile')) as saved:
                    page.locator('form[wire\\:submit="updateProfile"] button[type=submit]').click()
                assert saved.value.status == 200, 'save_http'
            assert notice.value.status == 200, 'listener_http'
            # The profile producer is localized once the settings copy lands.
            success = card(PROFILE_SAVED[locale])
            expect(success).to_be_visible()
            with sqlite3.connect(f'file:{database}?mode=ro', uri=True) as connection:
                assert connection.execute('select name from users where email=?', ('toast@example.test',)).fetchone() == ('Browser Toast Saved',), 'profile_not_saved'
            result['profile_persisted'] = True
            capture('profile')
            dismiss(success, saved_at, 'Enter')
            result['enter_dismissed'] = True

            fresh()
            messages = [{'message': 'Primeiro aviso' if locale == 'pt_BR' else 'First notice', 'type': 'warning'},
                        {'message': 'Outro aviso' if locale == 'pt_BR' else 'Another notice', 'type': 'info'}]
            stack_at = emit(page, messages)
            expect(card(messages[0]['message']).locator('div > span.sr-only').first).to_have_text(LABELS[locale][3])
            capture('stack')
            first, second = (card(item['message']) for item in messages)
            first_id, second_id = first.get_attribute('id'), second.get_attribute('id')
            dismiss(first, stack_at, 'Space')
            expect(second).to_be_visible()
            result['space_dismissed'] = True
            third_message = 'Novo aviso' if locale == 'pt_BR' else 'New notice'
            third_at = emit(page, [{'message': third_message, 'type': 'success'}])
            expect(page.locator('#' + first_id)).to_be_hidden()
            expect(second).to_have_attribute('id', second_id)
            capture('appended')
            dismiss(second, stack_at)
            expect(card(third_message)).to_be_visible()
            result['independent_dismissal'] = True
            dismiss(card(third_message), third_at)
            result['pointer_dismissed'] = True

            fresh()
            long_message = ('Aviso ' if locale == 'pt_BR' else 'Notice ') + 'a' * 180 + ' <script>throw 1</script>'
            long_at = emit(page, [{'message': long_message, 'type': 'error'}])
            expect(card(long_message).locator('script')).to_have_count(0)
            expect(card(long_message).locator('div > span.sr-only').first).to_have_text(LABELS[locale][2])
            capture('long')
            dismiss(card(long_message), long_at)
            result['escaped_message'] = True

            fresh()
            started = time.monotonic()
            timer_message = 'Aviso temporário' if locale == 'pt_BR' else 'Temporary notice'
            emit(page, [{'message': timer_message, 'type': 'unexpected'}])
            expect(card(timer_message).locator('div > span.sr-only').first).to_have_text(LABELS[locale][4])
            capture('timer')
            expect(card(timer_message)).to_be_hidden(timeout=5000)
            result['auto_hide_seconds'] = time.monotonic() - started
            assert 2.5 <= result['auto_hide_seconds'] <= 6, 'auto_hide_timing'
            assert not result['page_errors'], 'page_errors'
        finally:
            context.close()
    result.update(stage='complete', passed=True, fixture_cleaned=True)


def validate_png(path, width):
    data = path.read_bytes()
    assert data[:8] == b'\x89PNG\r\n\x1a\n', 'invalid_png'
    offset, chunks, compressed = 8, [], bytearray()
    expected, height = 0, 0
    while offset < len(data):
        assert offset + 12 <= len(data), 'invalid_png'
        size = struct.unpack('>I', data[offset:offset + 4])[0]
        kind = data[offset + 4:offset + 8]
        end = offset + 8 + size
        assert end + 4 <= len(data), 'invalid_png'
        payload = data[offset + 8:end]
        assert zlib.crc32(kind + payload) == struct.unpack('>I', data[end:end + 4])[0], 'invalid_png'
        chunks.append(kind)
        if kind == b'IHDR':
            assert len(chunks) == 1 and size == 13, 'invalid_png'
            w, h, depth, color, compression, filtering, interlace = struct.unpack('>IIBBBBB', payload)
            assert w == width and 844 <= h <= 32768 and depth == 8 and color in (2, 6), 'invalid_png'
            assert compression == filtering == interlace == 0, 'invalid_png'
            expected = h * (1 + w * (3 if color == 2 else 4))
            height = h
        elif kind == b'IDAT':
            compressed.extend(payload)
        elif kind == b'IEND':
            assert size == 0 and end + 4 == len(data), 'invalid_png'
        offset = end + 4
    assert chunks and chunks[0] == b'IHDR' and chunks[-1] == b'IEND' and compressed, 'invalid_png'
    assert expected > 0 and height > 0, 'invalid_png'
    decoder = zlib.decompressobj()
    raw = decoder.decompress(compressed, expected + 1)
    assert len(raw) == expected and decoder.eof and not decoder.unused_data, 'invalid_png'
    assert all(raw[index] <= 4 for index in range(0, expected, expected // height)), 'invalid_png'


def validate_report(report, output, hashes=None, checkout=None, cases=None):
    hashes = source_hashes() if hashes is None else hashes
    checkout = checkout_identity() if checkout is None else checkout
    cases = CASES if cases is None else cases
    assert report['source_hashes'] == hashes, 'source_drift'
    assert report['checkout'] == checkout, 'checkout_drift'
    assert report['complete'] is True and report['schema'] == 1, 'report_incomplete'
    expected = {case_key(*case) for case in cases}
    actual = [result['case'] for result in report['results']]
    assert set(actual) == expected and len(actual) == len(expected), 'case_set'
    for result in report['results']:
        assert result['stage'] == 'complete' and result['passed'] is True and result['page_errors'] == [], 'case_incomplete'
        for key in ('profile_persisted', 'enter_dismissed', 'space_dismissed', 'pointer_dismissed',
                    'independent_dismissal', 'escaped_message', 'fixture_cleaned'):
            assert result[key] is True, 'missing_assertion'
        assert 2.5 <= result['auto_hide_seconds'] <= 6, 'auto_hide_timing'
        assert set(result['geometry']) == set(CHECKPOINTS), 'checkpoint_set'
        width = next(case[2] for case in cases if case_key(*case) == result['case'])
        for name, measurement in result['geometry'].items():
            assert measurement['viewport'] == width and measurement['viewportHeight'] == 844, 'viewport_mismatch'
            assert len(measurement['cards']) == CHECKPOINTS[name], 'checkpoint_count'
            assert_geometry(measurement)
            ids = [card['id'] for card in measurement['cards']]
            assert all(ids) and len(set(ids)) == len(ids), 'duplicate_id'
        screenshots = {f'{result["case"]}/{name}.png' for name in CHECKPOINTS}
        assert set(result['screenshots']) == screenshots and len(result['screenshots']) == len(screenshots), 'screenshot_set'
        for filename in screenshots:
            validate_png(output / filename, width)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--case', choices=[case_key(*case) for case in CASES])
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=False)
    cases = [case for case in CASES if not args.case or case_key(*case) == args.case]
    report = {'schema': 1, 'checkout': checkout_identity(), 'source_hashes': source_hashes(),
              'complete': False, 'results': []}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale, scheme, width in cases:
                result = {'case': case_key(locale, scheme, width), 'passed': False, 'stage': 'started',
                          'page_errors': [], 'screenshots': [], 'geometry': {}}
                report['results'].append(result)
                try:
                    journey(browser, locale, scheme, width, args.output, result)
                except Exception as error:
                    # Never persist raw Playwright traces, live cookies, request URLs or passwords.
                    code = str(error) if isinstance(error, AssertionError) else ''
                    result['failure'] = code if re.fullmatch(r'[a-z_]+', code) else type(error).__name__
                (args.output / 'results.json').write_text(json.dumps(report, indent=2))
                print(json.dumps({'case': result['case'], 'passed': result['passed'], 'failure': result.get('failure')}), flush=True)
        finally:
            browser.close()
    report['complete'] = all(result['passed'] for result in report['results'])
    (args.output / 'results.json').write_text(json.dumps(report, indent=2))
    validate_report(report, args.output, cases=cases)


if __name__ == '__main__':
    main()
