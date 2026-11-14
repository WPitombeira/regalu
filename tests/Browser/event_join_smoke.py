"""Real login/join HTTP journeys; synthetic lifecycle changes use only the owned DB."""
import argparse
import hashlib
import json
from pathlib import Path
import sqlite3
from urllib.parse import urlsplit

from playwright.sync_api import expect, sync_playwright

from event_join_support import application, checkout_identity, source_hashes, validate_png

CASES = {(locale, width) for locale in ('en', 'pt_BR') for width in (390, 1280)}
STAGES = ('dashboard-before', 'invitation-form', 'invitation-accepted', 'dashboard-after',
          'code-accepted', 'closed-form', 'revoked-form', 'revoked-login')
CHECKS = ('normalized_invitation_reused', 'dashboard_invitation_removed', 'code_joined',
          'closed_refused', 'revoked_refused', 'protected_get_refused')


def query(database, sql, args=()):
    with sqlite3.connect(f'file:{database}?mode=ro', uri=True) as connection:
        return connection.execute(sql, args).fetchall()


def mutate_fixture(database, sql):
    # These are explicit synthetic revocations, not claimed organizer/password actions.
    with sqlite3.connect(f'file:{database}?mode=rw', uri=True) as connection:
        connection.execute(sql)


def call_response(response, method):
    request = response.request
    if request.method != 'POST' or 'application/json' not in request.headers.get('content-type', ''):
        return False
    payload = request.post_data_json or {}
    return any(call.get('method') == method for component in payload.get('components', [])
               for call in component.get('calls', []))


def action(page, method):
    with page.expect_response(lambda response: call_response(response, method)) as pending:
        page.locator(f'form[wire\\:submit="{method}"] button[type="submit"]').click()
    return pending.value


def capture(page, directory, stage, result):
    page.wait_for_load_state('networkidle')
    metrics = page.locator('main').evaluate('''element => {
        const visible = e => !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
        return {viewport: innerWidth, scroll: document.documentElement.scrollWidth,
            controls: [...element.querySelectorAll('input:not([type=hidden]),button,select,a[href]')].filter(visible).map(e => {
                const r=e.getBoundingClientRect(); return {x:r.x, right:r.right, width:r.width, height:r.height};
            })};
    }''')
    assert metrics['scroll'] <= metrics['viewport'] + 1, 'page_overflow'
    assert metrics['controls'], 'empty_controls'
    assert all(c['x'] >= -1 and c['right'] <= metrics['viewport'] + 1 and c['width'] > 0 and c['height'] > 0
               for c in metrics['controls']), 'control_overflow'
    path = directory / (stage + '.png')
    page.screenshot(path=str(path), full_page=False, animations='disabled',
                    mask=[page.locator('input[type=password]')])
    validate_png(path, result['width'])
    result['screenshots'][stage] = hashlib.sha256(path.read_bytes()).hexdigest()
    result['geometry'][stage] = metrics


def journey(browser, locale, width, output):
    case = f'{locale}-{width}'
    directory = output / case
    directory.mkdir()
    result = {'case': case, 'locale': locale, 'width': width, 'passed': False,
              'checks': {}, 'http': {}, 'screenshots': {}, 'geometry': {}, 'page_errors': []}
    with application(locale) as (origin, password, database):
        context = browser.new_context(viewport={'width': width, 'height': 844}, color_scheme='dark', locale=locale.replace('_', '-'))
        # No requests to real providers/CDNs; fixture mutations are loopback-only.
        context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(origin + '/') else route.abort())
        page = context.new_page()
        page.on('pageerror', lambda _: result['page_errors'].append('page_error'))
        try:
            page.goto(origin + '/login')
            page.locator('form[wire\\:submit="login"] input[type=email]').fill('guest@example.test')
            page.locator('form[wire\\:submit="login"] input[type=password]').fill(password)
            assert action(page, 'login').status == 200, 'login_http'
            page.wait_for_url(origin + '/dashboard')
            invitation_text = '1 pending Secret Santa invitation' if locale == 'en' else '1 convite de amigo secreto pendente'
            expect(page.locator('[aria-labelledby="dashboard-invitations"]')).to_contain_text(invitation_text)
            capture(page, directory, 'dashboard-before', result)

            event_id = query(database, 'select id from amigo_secreto_events where event_code=?', ('INVITATION01',))[0][0]
            invite_id = query(database, 'select id from amigo_secreto_participants where event_id=?', (event_id,))[0][0]
            guest_id = query(database, 'select id from users where email=?', ('guest@example.test',))[0][0]
            page.goto(origin + '/join-event/INVITATION01')
            expect(page.locator('#eventCode')).to_have_value('INVITATION01')
            capture(page, directory, 'invitation-form', result)
            result['http']['invitation'] = action(page, 'join').status
            assert result['http']['invitation'] == 200, 'invitation_http'
            page.wait_for_url(origin + f'/secret-santa/{event_id}')
            expect(page.locator('main h1')).to_have_text('Fixture INVITATION01')
            participants = query(database, 'select id,user_id,status,accepted_at from amigo_secreto_participants where event_id=?', (event_id,))
            assert len(participants) == 1 and participants[0][:3] == (invite_id, guest_id, 'ACCEPTED') and participants[0][3], 'invite_not_reused'
            result['checks']['normalized_invitation_reused'] = True
            capture(page, directory, 'invitation-accepted', result)
            page.goto(origin + '/dashboard')
            expect(page.locator('[aria-labelledby="dashboard-invitations"]')).to_have_count(0)
            result['checks']['dashboard_invitation_removed'] = True
            capture(page, directory, 'dashboard-after', result)

            page.goto(origin + '/secret-santa/join')
            page.locator('#eventCode').fill('directcode01')
            result['http']['code'] = action(page, 'join').status
            assert result['http']['code'] == 200, 'code_http'
            direct_id = query(database, 'select id from amigo_secreto_events where event_code=?', ('DIRECTCODE01',))[0][0]
            page.wait_for_url(origin + f'/secret-santa/{direct_id}')
            expect(page.locator('main h1')).to_have_text('Fixture DIRECTCODE01')
            rows = query(database, 'select user_id,status,accepted_at from amigo_secreto_participants where event_id=?', (direct_id,))
            assert len(rows) == 1 and rows[0][:2] == (guest_id, 'ACCEPTED') and rows[0][2], 'code_not_joined'
            result['checks']['code_joined'] = True
            capture(page, directory, 'code-accepted', result)

            page.goto(origin + '/join-event/CLOSEDCASE01')
            expect(page.locator('#eventCode')).to_have_value('CLOSEDCASE01')
            capture(page, directory, 'closed-form', result)
            mutate_fixture(database, "update amigo_secreto_events set status='COMPLETED' where event_code='CLOSEDCASE01'")
            result['http']['closed'] = action(page, 'join').status
            assert result['http']['closed'] == 403, 'closed_http'
            assert query(database, "select count(*) from amigo_secreto_participants p join amigo_secreto_events e on e.id=p.event_id where e.event_code='CLOSEDCASE01'") == [(0,)], 'closed_joined'
            result['checks']['closed_refused'] = True

            page.goto(origin + '/join-event/REVOKEDSES01')
            expect(page.locator('#eventCode')).to_have_value('REVOKEDSES01')
            capture(page, directory, 'revoked-form', result)
            mutate_fixture(database, "update users set password=(select password from users where email='host@example.test') where email='guest@example.test'")
            response = action(page, 'join')
            result['http']['revoked'] = response.status
            assert response.status == 302 and urlsplit(response.headers['location']).path == '/login', 'revoked_http'
            assert query(database, "select count(*) from amigo_secreto_participants p join amigo_secreto_events e on e.id=p.event_id where e.event_code='REVOKEDSES01'") == [(0,)], 'revoked_joined'
            result['checks']['revoked_refused'] = True
            # Finish Livewire's redirect before testing a separate protected GET.
            page.wait_for_url(origin + '/login')
            expect(page.locator('form[wire\\:submit="login"]')).to_be_visible()
            page.goto(origin + '/dashboard')
            page.wait_for_url(origin + '/login')
            expect(page.locator('form[wire\\:submit="login"]')).to_be_visible()
            result['checks']['protected_get_refused'] = True
            capture(page, directory, 'revoked-login', result)
            assert not result['page_errors'], 'page_errors'
            result['passed'] = True
        finally:
            context.close()
    return result


def validate_report(report, output, expected_identity=None, expected_hashes=None):
    assert report['schema'] == 1 and report['complete'] is True, 'incomplete_report'
    assert report['checkout'] == (checkout_identity() if expected_identity is None else expected_identity), 'checkout_identity'
    assert report['source_hashes'] == (source_hashes() if expected_hashes is None else expected_hashes), 'source_drift'
    cases = report['cases']
    assert len(cases) == len(CASES) and {(c['locale'], c['width']) for c in cases} == CASES, 'case_matrix'
    for case in cases:
        assert case['case'] == f"{case['locale']}-{case['width']}" and case['passed'] is True, 'case_incomplete'
        assert case['checks'] == dict.fromkeys(CHECKS, True) and case['page_errors'] == [], 'journey_checks'
        assert case['http'] == {'invitation': 200, 'code': 200, 'closed': 403, 'revoked': 302}, 'http_contract'
        assert set(case['screenshots']) == set(case['geometry']) == set(STAGES), 'checkpoints'
        for stage in STAGES:
            metrics = case['geometry'][stage]
            assert metrics['viewport'] == case['width'] and metrics['scroll'] <= case['width'] + 1, 'page_overflow'
            assert metrics['controls'] and all(c['x'] >= -1 and c['right'] <= case['width'] + 1 and c['width'] > 0 and c['height'] > 0 for c in metrics['controls']), 'control_overflow'
            path = output / case['case'] / (stage + '.png')
            assert path.resolve(strict=True).is_relative_to(output.resolve(strict=True)), 'screenshot_escape'
            validate_png(path, case['width'])
            assert hashlib.sha256(path.read_bytes()).hexdigest() == case['screenshots'][stage], 'screenshot_hash'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=False)
    identity, hashes = checkout_identity(), source_hashes()
    report = {'schema': 1, 'complete': False, 'checkout': identity, 'source_hashes': hashes, 'cases': []}
    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            try:
                for locale, width in sorted(CASES):
                    report['cases'].append(journey(browser, locale, width, args.output))
                    print(json.dumps({'case': f'{locale}-{width}', 'passed': True}), flush=True)
            finally:
                browser.close()
        assert hashes == source_hashes() and identity == checkout_identity(), 'source_drift'
        report['complete'] = True
        validate_report(report, args.output)
    finally:
        (args.output / 'results.json').write_text(json.dumps(report, indent=2) + '\n')


if __name__ == '__main__':
    main()
