"""Real profile actions, isolated SQLite/uploads, ephemeral browser-only credentials."""
import argparse
import base64
from contextlib import contextmanager
import hashlib
import json
import math
import os
from pathlib import Path
import secrets
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import call_response as livewire_call_response, login

ROOT = Path(__file__).resolve().parents[2]
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==')
COPY = {
    'en': {
        'title': 'Profile settings', 'save': 'Save profile', 'update': 'Update password',
        'labels': ['Name', 'Phone (optional)', 'Profile photo', 'Current password', 'New password', 'Confirm new password'],
        'name_error': 'The Name field is required.',
        'phone_error': 'The Phone (optional) field must not be greater than 20 characters.',
        'avatar_error': 'The Profile photo field must be an image.',
        'wrong': 'The current password is incorrect.', 'mismatch': 'The new passwords do not match.',
        'profile_saved': 'Profile updated successfully.', 'password_saved': 'Password updated successfully.',
        'preview': 'Preview of the selected profile photo', 'current': 'Current profile photo',
        'nojs': 'Enable JavaScript to edit your profile or change your password. No changes can be submitted while JavaScript is disabled.',
    },
    'pt_BR': {
        'title': 'Configurações de perfil', 'save': 'Salvar perfil', 'update': 'Atualizar senha',
        'labels': ['Nome', 'Telefone (opcional)', 'Foto de perfil', 'Senha atual', 'Nova senha', 'Confirmar nova senha'],
        'name_error': 'O campo Nome é obrigatório.',
        'phone_error': 'O campo Telefone (opcional) não pode ser superior a 20 caracteres.',
        'avatar_error': 'O campo Foto de perfil deve ser uma imagem.',
        'wrong': 'A senha atual está incorreta.', 'mismatch': 'As novas senhas não coincidem.',
        'profile_saved': 'Perfil atualizado com sucesso.', 'password_saved': 'Senha atualizada com sucesso.',
        'preview': 'Prévia da foto de perfil selecionada', 'current': 'Foto de perfil atual',
        'nojs': 'Ative o JavaScript para editar seu perfil ou alterar sua senha. Nenhuma alteração pode ser enviada enquanto o JavaScript estiver desativado.',
    },
}
CHECKPOINTS = ('initial', 'name-error', 'phone-error', 'profile-saved', 'avatar-error', 'avatar-preview', 'avatar-saved', 'wrong-password', 'mismatch', 'password-saved')
SOURCES = (
    'app/Livewire/Profile/Settings.php', 'app/Models/User.php', 'app/Providers/AppServiceProvider.php',
    'routes/web.php', 'bootstrap/app.php', 'config/auth.php', 'config/session.php', 'config/filesystems.php',
    'resources/views/settings.blade.php', 'resources/views/components/layouts/app.blade.php',
    'resources/views/components/ui/navbar.blade.php', 'resources/views/components/ui/user/card.blade.php',
    'resources/views/livewire/profile/settings.blade.php', 'resources/css/app.css', 'resources/js/app.js',
    'lang/en/profile_settings.php', 'lang/pt_BR/profile_settings.php', 'lang/en/validation.php', 'lang/pt_BR/validation.php',
    'tests/Browser/profile_settings_smoke.py', 'tests/Browser/profile_settings_report_test.py',
    'tests/Browser/profile_settings_runtime_test.py',
    'tests/Browser/seed-profile-settings.php', 'tests/Browser/event_history_smoke.py',
    'composer.lock', 'package-lock.json', '.github/workflows/laravel.yml',
)
REASONS = {
    'seed_failed', 'server_exited', 'server_unready', 'listener_leak', 'page_http', 'action_http',
    'native_validation', 'native_dispatched', 'native_restore', 'field_description', 'page_overflow',
    'control_overflow', 'footer_overlap', 'touch_target', 'primary_background', 'contrast', 'empty_measurements',
    'credentials_changed', 'profile_changed', 'profile_persistence', 'avatar_persistence', 'image_bytes',
    'remember_rotation', 'stale_session', 'nojs_mutation', 'page_errors', 'assertion_failed', 'runtime_failed',
    'incomplete_case', 'missing_checkpoint', 'missing_screenshot', 'source_drift', 'case_set', 'case_geometry', 'artifact_absent',
}


def matrix():
    return [(locale, scheme, width, True) for locale in COPY for scheme in ('light', 'dark')
            for width in (320, 390, 1280)] + [(locale, 'light', 320, False) for locale in COPY]


def case_key(item):
    locale, scheme, width, javascript = item
    return f"{locale}-{scheme}-{width}-{'js' if javascript else 'nojs'}"


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
    with tempfile.TemporaryDirectory(prefix='regalu-profile-browser-', dir=scratch) as temporary:
        directory = Path(temporary)
        database = directory / 'browser.sqlite'
        database.touch(mode=0o600)
        for path in ('public', 'views', 'storage/app/private', 'storage/app/public', 'storage/framework/cache', 'storage/framework/sessions', 'storage/logs'):
            (directory / path).mkdir(parents=True, exist_ok=True)
        # Static source/build remain read-only links; uploads and the public storage link are run-owned.
        for name in ('index.php', 'build', 'media', 'favicon.ico', 'robots.txt'):
            (directory / 'public' / name).symlink_to(ROOT / 'public' / name)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 0))
            port = probe.getsockname()[1]
        origin = f'http://127.0.0.1:{port}'
        password = secrets.token_urlsafe(32)
        # Livewire substitutes a unit-test disk in APP_ENV=testing. Exercise real HTTP storage/CSRF here.
        env = dict(os.environ, APP_ENV='browser', APP_DEBUG='false', APP_URL=origin,
                   APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE='en', DB_URL='', DB_CONNECTION='sqlite',
                   DB_DATABASE=str(database), CACHE_STORE='array', CACHE_LIMITER='array',
                   MAIL_MAILER='array', QUEUE_CONNECTION='sync', SESSION_DRIVER='database',
                   SESSION_CONNECTION='sqlite', SESSION_DOMAIN='null', SESSION_SECURE_COOKIE='false',
                   SESSION_COOKIE='regalu_profile_' + secrets.token_hex(8), AUTH_GUARD='web',
                   AUTH_MODEL='App\\Models\\User', BCRYPT_ROUNDS='4', FILESYSTEM_DISK='local',
                   LARAVEL_STORAGE_PATH=str(directory / 'storage'),
                   REGALU_BROWSER_DIRECTORY=temporary, REGALU_BROWSER_PASSWORD=password,
                   APP_CONFIG_CACHE=str(directory / 'config.php'), APP_ROUTES_CACHE=str(directory / 'routes.php'),
                   APP_SERVICES_CACHE=str(directory / 'services.php'), APP_PACKAGES_CACHE=str(directory / 'packages.php'),
                   VIEW_COMPILED_PATH=str(directory / 'views'), LOG_CHANNEL='stderr')
        # Laravel storagePath() reads $_ENV/$_SERVER, not getenv(). The built-in
        # HTTP server omits inherited variables from both when E is disabled.
        php = ['php', '-d', 'variables_order=EGPCS']
        seed = subprocess.run([*php, str(ROOT / 'tests/Browser/seed-profile-settings.php')],
                              cwd=ROOT, env=env, capture_output=True, text=True, timeout=60)
        assert seed.returncode == 0, 'seed_failed'
        json.loads(seed.stdout)
        router = ROOT / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        server = subprocess.Popen([*php, '-d', 'upload_max_filesize=3M', '-d', 'post_max_size=4M', '-S', f'127.0.0.1:{port}', str(router)],
                                  cwd=directory / 'public', env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            for _ in range(100):
                assert server.poll() is None, 'server_exited'
                try:
                    with urllib.request.urlopen(origin + '/login', timeout=1) as response:
                        if response.status == 200:
                            break
                except (OSError, urllib.error.URLError):
                    time.sleep(0.1)
            else:
                raise AssertionError('server_unready')
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
        return connection.execute('select name, phone, avatar_url, password, remember_token from users where email = ?', ('profile@example.test',)).fetchone()


def call_response(response, method):
    # Upload transport is multipart; only Livewire JSON actions have components/calls.
    if response.request.headers.get('content-type', '').split(';', 1)[0] != 'application/json':
        return False
    return livewire_call_response(response, method)


def form(page, method):
    return page.locator(f'form[wire\\:submit="{method}"]')


def submit(page, method, negative=False, keyboard=False):
    target = form(page, method)
    with page.expect_response(lambda response: call_response(response, method)) as pending:
        if negative:
            target.evaluate("""form => { const old = form.noValidate;
                try { form.noValidate = true; form.requestSubmit(form.querySelector('button[type=submit]')); }
                finally { form.noValidate = old; } }""")
        elif keyboard:
            target.locator('button[type=submit]').focus()
            page.keyboard.press('Enter')
        else:
            target.locator('button[type=submit]').click()
    assert pending.value.status == 200, 'action_http'
    if negative:
        assert not target.evaluate('form => form.noValidate'), 'native_restore'


def field_error(page, field, message):
    control = page.locator('#' + field)
    expect(control).to_have_attribute('aria-invalid', 'true')
    expect(page.locator('#' + field + '-error')).to_have_text(message)
    ids = (control.get_attribute('aria-describedby') or '').split()
    assert field + '-error' in ids, 'field_description'
    for identifier in ids:
        assert identifier != field, 'field_description'
        node = page.locator('#' + identifier)
        expect(node).to_have_count(1)
        expect(node).to_be_visible()
        assert node.inner_text().strip(), 'field_description'


def check_geometry(data):
    assert len(data['controls']) == 8 and len(data['contrast']) >= 13, 'empty_measurements'
    assert not data['profileOverflow'], 'control_overflow'
    if data['content'] > data['viewport'] + 1:
        # The unchanged account menu lacks a no-JS hiding rule (separate PR #19).
        # Preserve/attribute its measured defect, never hide it or waive profile overflow.
        assert not data['javascript'] and data['externalOverflow'], 'page_overflow'
        assert all(item['source'] == 'legacy-account-menu' for item in data['externalOverflow']), 'page_overflow'
        assert max(item['right'] for item in data['externalOverflow']) >= data['content'] - 1, 'page_overflow'
    for control in [data['panel'], *data['controls']]:
        assert control['left'] >= -1 and control['right'] <= data['viewport'] + 1, 'control_overflow'
    assert data['panel']['bottom'] <= data['footerTop'] + 1, 'footer_overlap'
    assert data['primaryAlpha'] == [255, 255], 'primary_background'
    assert all(control['height'] >= 44 for control in data['controls']), 'touch_target'
    assert all(isinstance(ratio, (float, int)) and math.isfinite(ratio) and ratio >= 4.5 for ratio in data['contrast']), 'contrast'


def capture(page, output, name, case):
    panel = page.locator('main > div')
    expect(panel).to_be_visible()
    data = panel.evaluate("""panel => {
        const box = el => { const r=el.getBoundingClientRect(); return {left:r.left,right:r.right,height:r.height,bottom:r.bottom+scrollY}; };
        const ctx=document.createElement('canvas').getContext('2d');
        const rgba=color=>{ ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data]; };
        const lum=rgb=>{const [r,g,b]=rgb.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return r*.2126+g*.7152+b*.0722;};
        const contrast=el=>{const fg=rgba(getComputedStyle(el).color);let ancestor=el,bg;
            while(ancestor){const s=getComputedStyle(ancestor);if(s.backgroundImage!=='none')return null;
                bg=rgba(s.backgroundColor);if(bg[3]!==0)break;ancestor=ancestor.parentElement;}
            if(!ancestor||bg[3]!==255||fg[3]!==255)return null;const a=lum(fg),b=lum(bg);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);};
        const visible=selector=>[...panel.querySelectorAll(selector)].filter(el=>el.getClientRects().length);
        const overflows=el=>{const r=el.getBoundingClientRect();return r.width>0&&(r.left < -1 || r.right > innerWidth+1);};
        return {viewport:innerWidth,content:document.documentElement.scrollWidth,panel:box(panel),
            profileOverflow:visible('*').filter(overflows).map(box),
            externalOverflow:[...document.body.querySelectorAll('*')].filter(el=>!panel.contains(el)&&overflows(el)).map(el=>({
                ...box(el),source:el.closest('nav [role="menu"][aria-labelledby="user-menu-button"]')?'legacy-account-menu':'other'})),
            footerTop:document.querySelector('footer').getBoundingClientRect().top+scrollY,
            controls:visible('input,button').map(box),
            contrast:visible('h1,h2,label,p,button,[role=alert]').filter(el=>[...el.childNodes].some(n=>n.nodeType===Node.TEXT_NODE&&n.textContent.trim())).map(contrast),
            primaryAlpha:visible('button[type=submit]').map(el=>rgba(getComputedStyle(el).backgroundColor)[3])};
    }""")
    data['javascript'] = not case['key'].endswith('-nojs')
    case['geometry'][name] = data
    page.screenshot(path=str(output / (name + '.png')), full_page=True, animations='disabled', mask=[page.locator('input[type=password]')])
    case['screenshots'].append(name + '.png')
    check_geometry(data)
    assert not case['page_errors'], 'page_errors'


def journey(browser, item, output, case):
    locale, scheme, width, javascript = item
    copy = COPY[locale]
    with application(locale) as (origin, password, database):
        contexts = []

        def new_page(enabled=True, cookies=None):
            context = browser.new_context(viewport={'width': width, 'height': 844}, color_scheme=scheme,
                                          locale=locale.replace('_', '-'), java_script_enabled=enabled)
            contexts.append(context)
            context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(origin + '/') else route.abort())
            if cookies:
                context.add_cookies(cookies)
            page = context.new_page()
            page.on('pageerror', lambda _: case['page_errors'].append('javascript_error'))
            return page

        def visit(page, path):
            assert page.goto(origin + path).status == 200, 'page_http'
            page.wait_for_load_state('networkidle')
            expect(page.locator('main h1')).to_have_text(copy['title'])

        try:
            page = new_page()
            login(page, origin, 'profile@example.test', password)
            visit(page, '/settings')
            before = record(database)
            if not javascript:
                page = new_page(False, page.context.cookies())
                visit(page, '/settings')
                expect(page.locator('noscript p')).to_have_text(copy['nojs'])
                for control in page.locator('main input,main button').all():
                    expect(control).to_be_disabled()
                requests = []
                page.on('request', lambda request: requests.append(request.method))
                for method in ('updateProfile', 'updatePassword'):
                    form(page, method).locator('button').dispatch_event('click')
                assert not any(method == 'POST' for method in requests), 'nojs_mutation'
                assert record(database) == before, 'profile_changed'
                capture(page, output, 'no-javascript', case)
                case['stage'] = 'complete'
                return

            for field, label in zip(('name', 'phone', 'avatar', 'currentPassword', 'newPassword', 'newPasswordConfirm'), copy['labels']):
                expect(page.get_by_label(label, exact=True)).to_have_attribute('id', field)
                expect(page.locator('#' + field)).to_be_enabled()
            capture(page, output, 'initial', case)
            page.locator('#name').fill('')
            native_requests = []
            def observe(request):
                if request.method == 'POST':
                    native_requests.append(request)
            page.on('request', observe)
            form(page, 'updateProfile').locator('button').click()
            assert not page.locator('#name').evaluate('el => el.checkValidity()'), 'native_validation'
            assert not native_requests, 'native_dispatched'
            page.remove_listener('request', observe)
            submit(page, 'updateProfile', negative=True)
            field_error(page, 'name', copy['name_error'])
            assert record(database) == before, 'profile_changed'
            capture(page, output, 'name-error', case)

            page.locator('#name').fill('Updated Browser')
            page.locator('#phone').evaluate("el => {el.value='1'.repeat(21);el.dispatchEvent(new Event('input',{bubbles:true}));}")
            submit(page, 'updateProfile', negative=True)
            field_error(page, 'phone', copy['phone_error'])
            assert record(database) == before, 'profile_changed'
            capture(page, output, 'phone-error', case)
            page.locator('#phone').fill('11999998888')
            submit(page, 'updateProfile', keyboard=True)
            expect(page.get_by_text(copy['profile_saved'], exact=True)).to_be_visible()
            assert record(database)[:2] == ('Updated Browser', '11999998888'), 'profile_persistence'
            visit(page, '/profile')
            expect(page.locator('#name')).to_have_value('Updated Browser')
            expect(page.locator('#phone')).to_have_value('11999998888')
            capture(page, output, 'profile-saved', case)
            saved = record(database)
            with page.expect_response(lambda response: call_response(response, '_finishUpload')) as pending:
                page.locator('#avatar').set_input_files({'name': 'not-image.txt', 'mimeType': 'text/plain', 'buffer': b'not an image'})
            assert pending.value.status == 200, 'action_http'
            field_error(page, 'avatar', copy['avatar_error'])
            expect(page.get_by_alt_text(copy['current'], exact=True)).to_be_visible()
            submit(page, 'updateProfile')
            field_error(page, 'avatar', copy['avatar_error'])
            assert record(database) == saved, 'profile_changed'
            capture(page, output, 'avatar-error', case)

            with page.expect_response(lambda response: call_response(response, '_finishUpload')) as pending:
                page.locator('#avatar').set_input_files({'name': 'photo.png', 'mimeType': 'image/png', 'buffer': PNG})
            assert pending.value.status == 200, 'action_http'
            preview = page.get_by_alt_text(copy['preview'], exact=True)
            expect(preview).to_be_visible()
            expect(preview).to_have_js_property('naturalWidth', 1)
            assert record(database) == saved, 'profile_changed'
            capture(page, output, 'avatar-preview', case)
            submit(page, 'updateProfile')
            current = page.get_by_alt_text(copy['current'], exact=True)
            expect(current).to_be_visible()
            expect(current).to_have_js_property('naturalWidth', 1)
            saved = record(database)
            assert saved[2].startswith('/storage/avatars/') and saved[2] != before[2], 'avatar_persistence'
            response = page.request.get(origin + saved[2])
            assert response.status == 200 and response.body() == PNG, 'image_bytes'
            capture(page, output, 'avatar-saved', case)

            stale = new_page()
            login(stale, origin, 'profile@example.test', password)
            visit(stale, '/settings')
            new_password = secrets.token_urlsafe(32)
            page.locator('#currentPassword').fill('invalid-current-password')
            page.locator('#newPassword').fill(new_password)
            page.locator('#newPasswordConfirm').fill(new_password)
            submit(page, 'updatePassword')
            field_error(page, 'currentPassword', copy['wrong'])
            assert record(database) == saved, 'credentials_changed'
            capture(page, output, 'wrong-password', case)
            page.locator('#currentPassword').fill(password)
            page.locator('#newPasswordConfirm').fill('mismatch-password')
            submit(page, 'updatePassword')
            field_error(page, 'newPasswordConfirm', copy['mismatch'])
            assert record(database) == saved, 'credentials_changed'
            capture(page, output, 'mismatch', case)
            page.locator('#newPasswordConfirm').fill(new_password)
            submit(page, 'updatePassword', keyboard=True)
            expect(page.get_by_text(copy['password_saved'], exact=True)).to_be_visible()
            for field in ('currentPassword', 'newPassword', 'newPasswordConfirm'):
                expect(page.locator('#' + field)).to_have_value('')
            changed = record(database)
            assert changed[3] != saved[3] and changed[4] != saved[4], 'remember_rotation'
            capture(page, output, 'password-saved', case)
            # The current session remains useful, while the already-mounted old session cannot mutate.
            page.locator('#name').fill('Current session retained')
            submit(page, 'updateProfile')
            assert record(database)[0] == 'Current session retained', 'profile_persistence'
            stale.locator('#name').fill('Revoked session must not save')
            with stale.expect_response(lambda response: call_response(response, 'updateProfile')) as denied:
                form(stale, 'updateProfile').locator('button').click()
            assert denied.value.status == 302 and denied.value.headers.get('location') == origin + '/login', 'stale_session'
            stale.wait_for_url('**/login')
            assert record(database)[0] == 'Current session retained', 'stale_session'
            stale.goto(origin + '/profile')
            stale.wait_for_url('**/login')
            visit(page, '/profile')
            expect(page.locator('#name')).to_have_value('Current session retained')
            fresh = new_page()
            login(fresh, origin, 'profile@example.test', new_password)
            visit(fresh, '/settings')
            assert not case['page_errors'], 'page_errors'
            case['stage'] = 'complete'
        finally:
            for context in contexts:
                context.close()


def validate(report, output, expected, hashes):
    assert report['source_hashes'] == hashes == provenance(), 'source_drift'
    assert [case['key'] for case in report['cases']] == [case_key(item) for item in expected], 'case_set'
    for item, case in zip(expected, report['cases']):
        assert case['stage'] == 'complete' and case['passed'] is True and not case['page_errors'], 'incomplete_case'
        checkpoints = CHECKPOINTS if item[3] else ('no-javascript',)
        assert list(case['geometry']) == list(checkpoints), 'missing_checkpoint'
        assert case['screenshots'] == [name + '.png' for name in checkpoints], 'missing_screenshot'
        for name in case['screenshots']:
            image = output / case['key'] / name
            assert image.is_file() and image.read_bytes().startswith(b'\x89PNG\r\n\x1a\n'), 'missing_screenshot'
        for data in case['geometry'].values():
            assert data['javascript'] is item[3] and data['viewport'] == item[2], 'case_geometry'
            check_geometry(data)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--case', choices=[case_key(item) for item in matrix()])
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    expected = [item for item in matrix() if not args.case or case_key(item) == args.case]
    hashes = provenance()
    report = {'source_hashes': hashes, 'cases': []}
    passed = True
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for item in expected:
                key = case_key(item)
                output = args.output / key
                output.mkdir(exist_ok=True)
                case = {'key': key, 'stage': 'started', 'passed': False, 'page_errors': [], 'geometry': {}, 'screenshots': []}
                report['cases'].append(case)
                try:
                    journey(browser, item, output, case)
                    case['passed'] = True
                except Exception as error:
                    reason = str(error)
                    case['reason'] = reason if reason in REASONS else ('assertion_failed' if isinstance(error, AssertionError) else 'runtime_failed')
                    passed = False
                finally:
                    (args.output / 'results.json').write_text(json.dumps(report, indent=2, ensure_ascii=False))
                    print(json.dumps({'case': key, 'passed': case['passed'], 'reason': case.get('reason')}), flush=True)
        finally:
            browser.close()
    if passed:
        validate(report, args.output, expected, hashes)
    raise SystemExit(0 if passed else 1)


if __name__ == '__main__':
    main()
