"""Isolated HTTP fixture and integrity checks for the event-join smoke."""
import base64
from contextlib import contextmanager
import hashlib
import json
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

ROOT = Path(__file__).resolve().parents[2]
INPUTS = (
    'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js',
    '.github/workflows/laravel.yml', 'public/index.php', 'public/media/logo.png',
    'tests/Browser/event_join_support.py', 'tests/Browser/event_join_smoke.py',
    'tests/Browser/event_join_report_test.py', 'tests/Browser/seed-event-join.php',
    'tests/Browser/event_history_smoke.py',
    'tests/Feature/AmigoSecreto/JoinEventBoundaryTest.php',
    'tests/Feature/AmigoSecreto/JoinEventHttpTest.php',
    'tests/Feature/AmigoSecreto/JoinEventPresentationTest.php',
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
    with tempfile.TemporaryDirectory(prefix='regalu-join-browser-', dir=scratch) as temporary:
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
        password = secrets.token_urlsafe(24)
        env = {key: os.environ[key] for key in ('PATH', 'HOME', 'PHPRC', 'PHP_INI_SCAN_DIR', 'SYSTEMROOT') if key in os.environ}
        env.update(REGALU_BROWSER_PASSWORD=password, BCRYPT_ROUNDS='4', TMPDIR=temporary, APP_ENV='browser', APP_DEBUG='false', APP_URL=origin,
                   APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE='en', DB_URL='', DB_CONNECTION='sqlite',
                   DB_DATABASE=str(database), CACHE_STORE='array', CACHE_LIMITER='array',
                   MAIL_MAILER='array', QUEUE_CONNECTION='sync', SESSION_DRIVER='database',
                   SESSION_CONNECTION='sqlite', SESSION_DOMAIN='null', SESSION_SECURE_COOKIE='false',
                   SESSION_COOKIE='regalu_join_' + secrets.token_hex(8), AUTH_GUARD='web',
                   AUTH_MODEL='App\\Models\\User', FILESYSTEM_DISK='local',
                   LARAVEL_STORAGE_PATH=str(directory / 'storage'), REGALU_BROWSER_DIRECTORY=temporary,
                   APP_CONFIG_CACHE=str(directory / 'config.php'), APP_ROUTES_CACHE=str(directory / 'routes.php'),
                   APP_SERVICES_CACHE=str(directory / 'services.php'), APP_PACKAGES_CACHE=str(directory / 'packages.php'),
                   VIEW_COMPILED_PATH=str(directory / 'views'), LOG_CHANNEL='stderr')
        php = ['php', '-d', 'variables_order=EGPCS']
        seed = subprocess.run([*php, str(ROOT / 'tests/Browser/seed-event-join.php')],
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
