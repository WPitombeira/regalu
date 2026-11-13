"""Real public footer navigation against run-owned Laravel HTTP storage."""
import argparse
import base64
from contextlib import contextmanager
import hashlib
import json
import math
import os
from pathlib import Path, PurePosixPath, PureWindowsPath
import secrets
import socket
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import zlib

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
CASES = [(locale, scheme, width, javascript) for locale in ('en', 'pt_BR')
         for scheme in ('light', 'dark') for width in (320, 390, 1280) for javascript in (True, False)]
COPY = {
    'en': ('Regalu home', 'Privacy policy unavailable.', 'All rights reserved.'),
    'pt_BR': ('Página inicial do Regalu', 'Política de privacidade indisponível.', 'Todos os direitos reservados.'),
}
CHECKPOINTS = ('login', 'focus', 'home')
INPUTS = (
    'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js',
    '.github/workflows/laravel.yml', 'public/index.php', 'public/media/logo.png',
    'lang/en/footer_navigation.php', 'lang/pt_BR/footer_navigation.php',
    'tests/Browser/footer_navigation_smoke.py', 'tests/Browser/footer_navigation_report_test.py',
    'tests/Browser/seed-footer-navigation.php', 'tests/Feature/FooterNavigationTest.php',
    'tests/Tooling/ci-workflow.test.mjs',
)


def git(*args):
    env = {key: value for key, value in os.environ.items() if not key.startswith('GIT_')}
    env.update(GIT_NO_LAZY_FETCH='1', GIT_OPTIONAL_LOCKS='0', GIT_TERMINAL_PROMPT='0')
    return subprocess.check_output(['git', '-c', 'protocol.allow=never', '-c', 'gc.auto=0',
                                    '-c', 'maintenance.auto=false', *args], cwd=ROOT, env=env)


def checkout_identity():
    # This labels the checkout, NOT the dirty candidate; input hashes bind tested bytes.
    return {'commit': git('rev-parse', 'HEAD').decode().strip(),
            'tree': git('rev-parse', 'HEAD^{tree}').decode().strip(),
            'dirty': bool(git('status', '--porcelain', '--untracked-files=all'))}


def asset_path(build, name):
    assert isinstance(name, str) and name and '\\' not in name, 'asset_name'
    path = PurePosixPath(name)
    assert not path.is_absolute() and not PureWindowsPath(name).drive and '..' not in path.parts, 'asset_escape'
    root = build.resolve(strict=True)
    resolved = (root / path).resolve(strict=True)
    assert resolved.is_relative_to(root) and resolved.is_file(), 'asset_escape'
    return resolved


def source_hashes():
    paths: set[str] = set(INPUTS)
    for raw in git('ls-files', '-z').decode().split('\0'):
        if raw.startswith(('app/', 'bootstrap/', 'config/', 'lang/', 'resources/', 'routes/', 'database/')):
            paths.add(raw)
    build = ROOT / 'public/build'
    manifest = build / 'manifest.json'
    assert manifest.resolve(strict=True).is_relative_to(build.resolve(strict=True)), 'manifest_escape'
    entries = json.loads(manifest.read_text())
    assert isinstance(entries, dict) and entries, 'empty_manifest'
    assets = set()
    for entry in entries.values():
        assert isinstance(entry, dict) and 'file' in entry, 'manifest_entry'
        names = [entry['file']]
        for field in ('css', 'assets'):
            assert isinstance(entry.get(field, []), list), 'manifest_assets'
            names.extend(entry.get(field, []))
        for name in names:
            asset_path(build, name)  # Validate containment before reading any manifest-provided path.
            assets.add('public/build/' + name)
    paths |= assets | {'public/build/manifest.json'}
    hashes = {}
    for name in sorted(paths):
        path = (ROOT / name).resolve(strict=True)
        assert path.is_relative_to(ROOT) and path.is_file(), 'input_escape'
        hashes[name] = hashlib.sha256(path.read_bytes()).hexdigest()
    return hashes


@contextmanager
def application(locale):
    scratch = os.environ.get('RUNNER_TEMP') or os.environ.get('TMPDIR')
    assert scratch, 'scratch_required'
    with tempfile.TemporaryDirectory(prefix='regalu-footer-browser-', dir=scratch) as temporary:
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
        env = {key: os.environ[key] for key in ('PATH', 'HOME', 'PHPRC', 'PHP_INI_SCAN_DIR', 'SYSTEMROOT') if key in os.environ}
        env.update(TMPDIR=temporary, APP_ENV='browser', APP_DEBUG='false', APP_URL=origin,
                   APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE='en', DB_URL='', DB_CONNECTION='sqlite',
                   DB_DATABASE=str(database), CACHE_STORE='array', CACHE_LIMITER='array',
                   MAIL_MAILER='array', QUEUE_CONNECTION='sync', SESSION_DRIVER='database',
                   SESSION_CONNECTION='sqlite', SESSION_DOMAIN='null', SESSION_SECURE_COOKIE='false',
                   SESSION_COOKIE='regalu_footer_' + secrets.token_hex(8), AUTH_GUARD='web',
                   AUTH_MODEL='App\\Models\\User', FILESYSTEM_DISK='local',
                   LARAVEL_STORAGE_PATH=str(directory / 'storage'), REGALU_BROWSER_DIRECTORY=temporary,
                   APP_CONFIG_CACHE=str(directory / 'config.php'), APP_ROUTES_CACHE=str(directory / 'routes.php'),
                   APP_SERVICES_CACHE=str(directory / 'services.php'), APP_PACKAGES_CACHE=str(directory / 'packages.php'),
                   VIEW_COMPILED_PATH=str(directory / 'views'), LOG_CHANNEL='stderr')
        php = ['php', '-d', 'variables_order=EGPCS']
        seed = subprocess.run([*php, str(ROOT / 'tests/Browser/seed-footer-navigation.php')],
                              cwd=ROOT, env=env, capture_output=True, text=True, timeout=60)
        assert seed.returncode == 0 and json.loads(seed.stdout) == {'ready': True}, 'seed_failed'
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
            yield origin
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


def geometry(page):
    return page.locator('footer').evaluate('''footer => {
        const box = el => {const r=el.getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height};};
        const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d');
        const luminance=color=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);const rgb=[...ctx.getImageData(0,0,1,1).data].slice(0,3).map(c=>{c/=255;return c<=0.04045?c/12.92:((c+0.055)/1.055)**2.4;});return rgb[0]*0.2126+rgb[1]*0.7152+rgb[2]*0.0722;};
        const bg=luminance(getComputedStyle(footer).backgroundColor),link=footer.querySelector('a');
        const elements=[footer.querySelector('a span'),footer.querySelector('p'),footer.querySelector(':scope > div > span')];
        return {viewport:innerWidth,viewportHeight:innerHeight,documentWidth:document.documentElement.scrollWidth,
            footer:box(footer),link:box(link),focusVisible:link.matches(':focus-visible'),
            outlineWidth:parseFloat(getComputedStyle(link).outlineWidth),
            outlineStyle:getComputedStyle(link).outlineStyle,
            linkOnTop:(()=>{const r=link.getBoundingClientRect();return link.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));})(),
            texts:elements.map(el=>{const fg=luminance(getComputedStyle(el).color),range=document.createRange();range.selectNodeContents(el);
                return {box:box(el),contrast:(Math.max(fg,bg)+0.05)/(Math.min(fg,bg)+0.05),
                    lines:[...range.getClientRects()].map(r=>({left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}))};})};
    }''')


def assert_geometry(m, width, focus=False):
    assert m['viewport'] == width and m['viewportHeight'] == 844, 'viewport'
    assert m['linkOnTop'] is True, 'link_obscured'
    assert len(m['texts']) == 3, 'text_measurements'
    boxes = [m['footer'], m['link']] + [text['box'] for text in m['texts']]
    for text in m['texts']:
        assert text['lines'], 'empty_lines'
        boxes.extend(text['lines'])
        assert type(text['contrast']) in (int, float) and math.isfinite(text['contrast']) and text['contrast'] >= 4.5, 'text_contrast'
    for box in boxes:
        assert set(box) == {'left', 'right', 'top', 'bottom', 'width', 'height'}, 'box_shape'
        assert all(type(v) in (int, float) and math.isfinite(v) for v in box.values()), 'box_finite'
        assert box['width'] > 0 and box['height'] > 0, 'empty_box'
        assert box['left'] >= -0.5 and box['right'] <= width + 0.5, 'footer_horizontal_overflow'
        assert box['top'] >= -0.5 and box['bottom'] <= 844.5, 'footer_vertical_overflow'
    assert m['link']['height'] >= 44 and m['link']['width'] >= 44, 'touch_target'
    for index, text in enumerate(m['texts']):
        a = text['box']
        for other in m['texts'][index + 1:]:
            b = other['box']
            assert a['right'] <= b['left'] or b['right'] <= a['left'] or a['bottom'] <= b['top'] or b['bottom'] <= a['top'], 'text_overlap'
    if focus:
        assert m['focusVisible'] is True and m['outlineWidth'] >= 2 and m['outlineStyle'] != 'none', 'keyboard_focus'


def assert_footer(page, locale, origin):
    footer = page.locator('footer')
    expect(footer).to_have_count(1)
    expect(footer.locator('a')).to_have_count(1)
    link = footer.get_by_role('link', name=COPY[locale][0], exact=True)
    expect(link).to_have_attribute('href', origin)
    assert link.evaluate('(el)=>el.href') == origin + '/', 'home_destination'
    expect(link.locator('img')).to_have_attribute('alt', '')
    expect(footer.locator('p')).to_have_text(COPY[locale][1])
    expect(footer.locator(':scope > div > span')).to_contain_text(COPY[locale][2])
    expect(footer.locator('button, [role=button], [role=link], svg, a[href^="#"]')).to_have_count(0)
    assert '..' not in footer.inner_text(), 'copyright_punctuation'
    expect(page.locator('html')).to_have_attribute('lang', locale.replace('_', '-'))
    return link


def case_key(case):
    locale, scheme, width, javascript = case
    return f'{locale}-{scheme}-{width}-' + ('js' if javascript else 'nojs')


def journey(browser, origin, case, output):
    locale, scheme, width, javascript = case
    key = case_key(case)
    directory = output / key
    directory.mkdir()
    record = {'case': key, 'passed': False, 'stage': 'start', 'page_errors': [], 'mutations': [],
              'checkpoints': {}, 'screenshots': {}, 'http': [], 'keyboard_home': False, 'pointer_home': False}
    context = browser.new_context(viewport={'width': width, 'height': 844}, device_scale_factor=1,
                                  color_scheme=scheme, locale=locale.replace('_', '-'),
                                  reduced_motion='reduce', java_script_enabled=javascript)
    def route_request(route):
        if route.request.method not in ('GET', 'HEAD'):
            record['mutations'].append(route.request.method)
            route.abort()
        elif route.request.url.startswith(origin + '/'):
            route.continue_()
        else:
            route.abort()
    context.route('**/*', route_request)
    page = context.new_page()
    page.on('pageerror', lambda error: record['page_errors'].append(type(error).__name__))
    def checkpoint(name):
        page.locator('footer').scroll_into_view_if_needed()
        measurement = geometry(page)
        record['checkpoints'][name] = measurement
        path = directory / (name + '.png')
        page.screenshot(path=str(path), animations='disabled', mask=[page.locator('input[type=password]')])
        record['screenshots'][name] = hashlib.sha256(path.read_bytes()).hexdigest()
        assert_geometry(measurement, width, name == 'focus')
    def open_login():
        response = page.goto(origin + '/login', wait_until='networkidle')
        assert response and response.status == 200, 'login_http'
        record['http'].append(200)
        assert_footer(page, locale, origin)
    try:
        open_login()
        record['stage'] = 'login'
        checkpoint('login')
        # Reach the real link through document tab order, without synthetic focus or forced clicks.
        for _ in range(40):
            page.keyboard.press('Tab')
            if page.locator('footer a').evaluate('(el)=>el === document.activeElement'):
                break
        else:
            raise AssertionError('keyboard_unreachable')
        checkpoint('focus')
        with page.expect_response(lambda r: r.request.is_navigation_request() and r.url == origin + '/') as pending:
            page.keyboard.press('Enter')
        assert pending.value.status == 200, 'keyboard_home_http'
        record['http'].append(pending.value.status)
        page.wait_for_url(origin + '/')
        page.wait_for_load_state('networkidle')
        expect(page.locator('main section:not([id]) h1')).to_be_visible()
        assert_footer(page, locale, origin)
        record['keyboard_home'] = True
        record['stage'] = 'home'
        checkpoint('home')
        open_login()
        with page.expect_response(lambda r: r.request.is_navigation_request() and r.url == origin + '/') as pending:
            page.locator('footer a').click()
        assert pending.value.status == 200, 'pointer_home_http'
        record['http'].append(pending.value.status)
        page.wait_for_url(origin + '/')
        page.wait_for_load_state('networkidle')
        expect(page.locator('main section:not([id]) h1')).to_be_visible()
        assert_footer(page, locale, origin)
        record['pointer_home'] = True
        assert record['page_errors'] == [] and record['mutations'] == [], 'unexpected_page_effect'
        record.update(passed=True, stage='complete')
    except Exception as error:
        # No request bodies, signed URLs, cookie values or environment dumps in receipts.
        record['failure'] = str(error) if isinstance(error, AssertionError) and str(error).replace('_', '').isalnum() else type(error).__name__
    finally:
        context.close()
    return record


def validate_png(path, width):
    data = path.read_bytes()
    assert len(data) < 16_000_000 and data[:8] == b'\x89PNG\r\n\x1a\n', 'png_signature'
    offset, compressed, kinds = 8, bytearray(), []
    depth = color = None
    while offset < len(data):
        assert offset + 12 <= len(data), 'png_truncated'
        size = struct.unpack('>I', data[offset:offset + 4])[0]
        kind = data[offset + 4:offset + 8]
        assert offset + size + 12 <= len(data), 'png_chunk_size'
        payload = data[offset + 8:offset + 8 + size]
        checksum = struct.unpack('>I', data[offset + 8 + size:offset + 12 + size])[0]
        assert zlib.crc32(kind + payload) & 0xffffffff == checksum, 'png_crc'
        if not kinds:
            assert kind == b'IHDR' and size == 13, 'png_header'
            w, h, depth, color, compression, filtering, interlace = struct.unpack('>IIBBBBB', payload)
            assert (w, h) == (width, 844) and depth == 8 and color in (2, 6), 'png_dimensions'
            assert (compression, filtering, interlace) == (0, 0, 0), 'png_encoding'
        else:
            assert kind != b'IHDR', 'png_duplicate_header'
        kinds.append(kind)
        if kind == b'IDAT':
            compressed.extend(payload)
        offset += size + 12
        if kind == b'IEND':
            assert size == 0 and offset == len(data), 'png_end'
            break
    assert kinds and kinds[-1] == b'IEND' and compressed, 'png_incomplete'
    stride = width * (4 if color == 6 else 3) + 1
    expected = stride * 844
    decoder = zlib.decompressobj()
    raw = decoder.decompress(compressed, expected + 1)
    assert decoder.eof and not decoder.unused_data and not decoder.unconsumed_tail, 'png_stream'
    assert len(raw) == expected and all(raw[i] <= 4 for i in range(0, expected, stride)), 'png_scanlines'


def validate_report(report, output):
    assert report['schema'] == 1 and report['complete'] is True, 'report_incomplete'
    assert report['checkout'] == checkout_identity(), 'checkout_drift'
    assert report['source_hashes'] == source_hashes(), 'source_drift'
    assert report['fixture_cleanup'] is True, 'fixture_cleanup'
    expected = {case_key(case): case for case in CASES}
    records = report['cases']
    assert len(records) == len(expected) and {r['case'] for r in records} == set(expected), 'case_matrix'
    for record in records:
        assert record['passed'] is True and record['stage'] == 'complete' and 'failure' not in record, 'case_incomplete'
        assert record['page_errors'] == [] and record['mutations'] == [], 'unexpected_page_effect'
        assert record['http'] == [200, 200, 200, 200], 'navigation_http'
        assert record['keyboard_home'] is True and record['pointer_home'] is True, 'navigation_missing'
        assert set(record['checkpoints']) == set(CHECKPOINTS) and set(record['screenshots']) == set(CHECKPOINTS), 'checkpoint_set'
        width = expected[record['case']][2]
        for name in CHECKPOINTS:
            assert_geometry(record['checkpoints'][name], width, name == 'focus')
            path = output / record['case'] / (name + '.png')
            assert path.resolve(strict=True).is_relative_to(output.resolve(strict=True)), 'screenshot_escape'
            assert hashlib.sha256(path.read_bytes()).hexdigest() == record['screenshots'][name], 'screenshot_hash'
            validate_png(path, width)
    actual = {p.relative_to(output).as_posix() for p in output.rglob('*.png')}
    assert actual == {key + '/' + name + '.png' for key in expected for name in CHECKPOINTS}, 'screenshot_set'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--case', choices=[case_key(case) for case in CASES], help='Diagnostic subset only; never a complete receipt.')
    args = parser.parse_args()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    selected = [case for case in CASES if not args.case or case_key(case) == args.case]
    report = {'schema': 1, 'complete': False, 'checkout': checkout_identity(),
              'source_hashes': source_hashes(), 'fixture_cleanup': False, 'cases': []}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale in ('en', 'pt_BR'):
                cases = [case for case in selected if case[0] == locale]
                if not cases:
                    continue
                with application(locale) as origin:
                    for case in cases:
                        result = journey(browser, origin, case, output)
                        report['cases'].append(result)
                        (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
                        print(json.dumps({key: result[key] for key in ('case', 'passed', 'stage')}), flush=True)
        finally:
            browser.close()
    report['fixture_cleanup'] = True
    assert report['checkout'] == checkout_identity() and report['source_hashes'] == source_hashes(), 'runtime_source_drift'
    report['complete'] = not args.case and all(row['passed'] for row in report['cases'])
    (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    assert all(row['passed'] for row in report['cases']), 'browser_failure'
    if not args.case:
        validate_report(report, output)


if __name__ == '__main__':
    main()
