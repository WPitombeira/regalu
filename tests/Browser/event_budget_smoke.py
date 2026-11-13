"""Real event-budget validation/persistence, with bounded EN/PT presentation evidence."""
import argparse
import hashlib
import json
import re
import sqlite3
from pathlib import Path

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import login
from event_budget_support import application, checkout_identity, source_hashes, validate_png

CASES = [(locale, scheme, width, True) for locale in ('en', 'pt_BR')
         for scheme in ('light', 'dark') for width in (320, 390, 1280)] + [
             (locale, 'light', 320, False) for locale in ('en', 'pt_BR')]
CHECKPOINTS = ('initial', 'ordered', 'decimal', 'upper', 'boundary')
COPY = {
    'en': {
        'labels': ['Minimum budget (optional)', 'Maximum budget (optional)'],
        'help': 'Leave either limit blank if not needed. Zero is allowed. Use up to two decimal places, no more than 99999999.99, and a maximum at least as large as the minimum.',
        'ordered': 'The maximum budget must be at least the minimum budget.',
        'decimal': ['Use a regular number with up to two decimal places for minimum budget.', 'Use a regular number with up to two decimal places for maximum budget.'],
        'upper': ['The minimum budget must not exceed 99999999.99.', 'The maximum budget must not exceed 99999999.99.'],
    },
    'pt_BR': {
        'labels': ['Orçamento mínimo (opcional)', 'Orçamento máximo (opcional)'],
        'help': 'Deixe um limite em branco se não precisar dele. Zero é permitido. Use até duas casas decimais, no máximo 99999999.99, e um máximo maior ou igual ao mínimo.',
        'ordered': 'O orçamento máximo deve ser maior ou igual ao orçamento mínimo.',
        'decimal': ['Use um número comum com até duas casas decimais para o orçamento mínimo.', 'Use um número comum com até duas casas decimais para o orçamento máximo.'],
        'upper': ['O orçamento mínimo não deve ultrapassar 99999999.99.', 'O orçamento máximo não deve ultrapassar 99999999.99.'],
    },
}
FIELDS = ('budget_min', 'budget_max')
VALID = [('zero', '0', '0', '0.00', '0.00'),
         ('minimum-only', '0', '', '0.00', None),
         ('maximum-only', '', '0', None, '0.00'),
         ('unspecified', '', '', None, None),
         ('boundary', '99999999.98', '99999999.99', '99999999.98', '99999999.99')]
FORM = 'form[wire\\:submit="create"]'


def case_key(case):
    locale, scheme, width, javascript = case
    return f'{locale}-{scheme}-{width}-' + ('js' if javascript else 'nojs')


def create_request(request):
    if request.method != 'POST' or 'application/json' not in request.headers.get('content-type', ''):
        return False
    payload = request.post_data_json
    return any(call.get('method') == 'create' for component in payload.get('components', [])
               for call in component.get('calls', []))


def counts(database):
    with sqlite3.connect(f'file:{database}?mode=ro', uri=True) as db:
        return [db.execute('select count(*) from ' + table).fetchone()[0]
                for table in ('amigo_secreto_events', 'amigo_secreto_participants')]


def geometry(page):
    return page.locator('[data-event-budget]').evaluate('''root => {
        const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d');
        function rgba(color) { ctx.clearRect(0, 0, 1, 1); ctx.fillStyle=color; ctx.fillRect(0, 0, 1, 1); return [...ctx.getImageData(0,0,1,1).data]; }
        function light(c) { return c.slice(0,3).map(v => { v/=255; return v<=.04045?v/12.92:((v+.055)/1.055)**2.4; }).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0); }
        return [...root.querySelectorAll('label,input,p')].map(el => {
            const s=getComputedStyle(el), r=el.getBoundingClientRect();
            let ancestor=el, bg;
            do { bg=rgba(getComputedStyle(ancestor).backgroundColor); ancestor=ancestor.parentElement; } while(bg[3]===0 && ancestor);
            const fg=rgba(s.color), a=light(fg), b=light(bg);
            const range=document.createRange(); range.selectNodeContents(el);
            ctx.font=s.font;
            return {id:el.id || el.htmlFor, tag:el.tagName, x:r.x, right:r.right, y:r.y, bottom:r.bottom,
                    width:r.width, height:r.height, opacity:Number(s.opacity),
                    contrast:(Math.max(a,b)+.05)/(Math.min(a,b)+.05), opaque:fg[3]===255 && bg[3]===255,
                    text_width:el.tagName==='INPUT'?ctx.measureText(el.value || el.placeholder).width+parseFloat(s.paddingLeft)+parseFloat(s.paddingRight)+16:0,
                    lines:[...range.getClientRects()].map(x=>({x:x.x,right:x.right,y:x.y,bottom:x.bottom}))};
        });
    }''')


def assert_geometry(rows, width, stage):
    expected = 5 + (1 if stage == 'ordered' else 2 if stage in ('decimal', 'upper') else 0)
    assert len(rows) == expected, 'measurement_count'
    assert len([r for r in rows if r['tag'] == 'INPUT']) == 2, 'input_count'
    for row in rows:
        assert row['width'] > 0 and row['height'] > 0 and row['opacity'] == 1, 'hidden_budget'
        assert row['x'] >= 0 and row['right'] <= width + 1, 'budget_overflow'
        assert row['y'] >= -1 and row['bottom'] <= 845, 'budget_capture_clipping'
        assert row['opaque'] and row['contrast'] >= 4.5, 'budget_contrast'
        if row['tag'] == 'INPUT':
            assert row['height'] >= 44, 'budget_touch_target'
            assert row['text_width'] <= row['width'], 'budget_value_clipped'
        else:
            assert row['lines'], 'empty_text_measurement'
            assert all(line['x'] >= 0 and line['right'] <= width + 1 for line in row['lines']), 'budget_text_overflow'


def assert_markup(page, locale, errors=None):
    errors = errors or {}
    expect(page.locator('#event-budget-help')).to_have_text(COPY[locale]['help'])
    for index, field in enumerate(FIELDS):
        control = page.locator('#' + field)
        expect(control).to_have_count(1)
        expect(page.locator('label[for="' + field + '"]')).to_have_text(COPY[locale]['labels'][index])
        for key, value in {'type': 'number', 'min': '0', 'max': '99999999.99', 'step': '0.01',
                           'aria-invalid': 'true' if field in errors else 'false',
                           'aria-describedby': 'event-budget-help' + (' ' + field + '-error' if field in errors else '')}.items():
            expect(control).to_have_attribute(key, value)
        assert control.get_attribute('required') is None, 'optional_constraint'
        error = page.locator('#' + field + '-error')
        if field in errors:
            expect(error).to_have_count(1)
            expect(error).to_have_attribute('role', 'alert')
            expect(error).to_have_text(errors[field])
        else:
            expect(error).to_have_count(0)


def journey(browser, case, output):
    locale, scheme, width, javascript = case
    key = case_key(case)
    directory = output / key
    directory.mkdir()
    record = {'case': key, 'passed': False, 'stage': 'start', 'page_errors': [], 'http': [],
              'checkpoints': {}, 'screenshots': {}, 'native_blocked': [], 'saved': [], 'invalid_unchanged': [],
              'keyboard': False, 'fixture_cleanup': False}
    try:
        with application(locale) as (origin, password, database):
            options = dict(viewport={'width': width, 'height': 844}, device_scale_factor=1,
                           locale=locale.replace('_', '-'), color_scheme=scheme, reduced_motion='reduce')
            contexts = []
            def context(**extra):
                ctx = browser.new_context(**options, **extra)
                contexts.append(ctx)
                ctx.route('**/*', lambda r: r.continue_() if r.request.url.startswith(origin + '/') else r.abort())
                return ctx
            try:
                authenticated = context()
                page = authenticated.new_page()
                page.on('pageerror', lambda e: record['page_errors'].append(type(e).__name__))
                login(page, origin, 'budget@example.test', password)
                if not javascript:
                    # Cookies remain in memory; no session material enters the report.
                    page = context(java_script_enabled=False, storage_state=authenticated.storage_state()).new_page()
                    page.on('pageerror', lambda e: record['page_errors'].append(type(e).__name__))
                requests = []
                page.on('request', lambda request: requests.append(True) if create_request(request) else None)
                def open_form(name):
                    response = page.goto(origin + '/secret-santa/create', wait_until='networkidle')
                    assert response.status == 200, 'form_http'
                    record['http'].append(response.status)
                    assert_markup(page, locale)
                    page.locator('#name').fill('Budget ' + name)
                def fill(minimum, maximum):
                    page.locator('#budget_min').fill(minimum)
                    page.locator('#budget_max').fill(maximum)
                def capture(stage):
                    record['stage'] = stage
                    page.locator('[data-event-budget]').scroll_into_view_if_needed()
                    measurement = geometry(page)
                    record['checkpoints'][stage] = measurement
                    path = directory / (stage + '.png')
                    page.screenshot(path=str(path), animations='disabled', mask=[page.locator('input[type=password]')])
                    record['screenshots'][stage] = hashlib.sha256(path.read_bytes()).hexdigest()
                    assert_geometry(measurement, width, stage)
                def submit(bypass=False):
                    form = page.locator(FORM)
                    assert form.evaluate('(f)=>f.noValidate') is False, 'native_validation_restore'
                    with page.expect_response(lambda r: create_request(r.request)) as pending:
                        if bypass:
                            form.evaluate('''f=>{const old=f.noValidate;try{f.noValidate=true;f.requestSubmit(f.querySelector('button[type=submit]'));}finally{f.noValidate=old;}}''')
                        else:
                            form.locator('button[type=submit]').click()
                    assert pending.value.status == 200, 'action_http'
                    record['http'].append(pending.value.status)
                open_form('validation')
                capture('initial')
                page.locator('#budget_min').click()
                page.keyboard.press('Tab')
                expect(page.locator('#budget_max')).to_be_focused()
                page.keyboard.press('Shift+Tab')
                expect(page.locator('#budget_min')).to_be_focused()
                record['keyboard'] = True
                for label, value in [('precision', '0.001'), ('upper', '100000000.00'), ('negative', '-0.01')]:
                    fill(value, '')
                    before = len(requests)
                    assert page.locator('#budget_min').evaluate('(el)=>el.validity.valid') is False, 'native_invalid_missing'
                    page.locator(FORM + ' button[type=submit]').click()
                    expect(page.locator('#budget_min')).to_be_focused()
                    assert page.locator(FORM).evaluate('(f)=>f.noValidate') is False, 'native_validation_restore'
                    assert len(requests) == before and counts(database) == [0, 0], 'native_submission_leaked'
                    record['native_blocked'].append(label)
                if javascript:
                    for stage, values, errors, bypass in [
                        ('ordered', ('100', '20'), {'budget_max': COPY[locale]['ordered']}, False),
                        ('decimal', ('0.001', '0.002'), dict(zip(FIELDS, COPY[locale]['decimal'])), True),
                        ('upper', ('100000000.00', '100000001.00'), dict(zip(FIELDS, COPY[locale]['upper'])), True),
                    ]:
                        fill(*values)
                        submit(bypass)
                        assert_markup(page, locale, errors)
                        assert page.locator(FORM).evaluate('(f)=>f.noValidate') is False, 'native_validation_restore'
                        for field, value in zip(FIELDS, values):
                            expect(page.locator('#' + field)).to_have_value(value)
                        assert counts(database) == [0, 0], 'invalid_persisted'
                        record['invalid_unchanged'].append(stage)
                        capture(stage)
                    for label, minimum, maximum, expected_min, expected_max in VALID:
                        if label != 'zero':
                            open_form(label)
                        else:
                            page.locator('#name').fill('Budget zero')
                        fill(minimum, maximum)
                        if label == 'boundary':
                            capture('boundary')
                        submit()
                        with sqlite3.connect(f'file:{database}?mode=ro', uri=True) as db:
                            row = db.execute("select id, case when budget_min is null then null else printf('%.2f', budget_min) end, case when budget_max is null then null else printf('%.2f', budget_max) end from amigo_secreto_events where name=?", ('Budget ' + label,)).fetchall()
                            assert len(row) == 1 and list(row[0][1:]) == [expected_min, expected_max], 'saved_budget_mismatch'
                            identifier = row[0][0]
                            assert db.execute("select count(*) from amigo_secreto_participants p join amigo_secreto_events e on e.id=p.event_id where e.id=? and p.user_id=e.organizer_id and p.status='ACCEPTED'", (identifier,)).fetchone() == (1,), 'organizer_missing'
                        page.wait_for_url(origin + '/secret-santa/' + str(identifier))
                        expect(page.get_by_role('heading', name='Budget ' + label, exact=True)).to_be_visible()
                        if label == 'zero':
                            expect(page.get_by_text('$0.00 - $0.00', exact=True)).to_be_visible()
                        record['saved'].append([label, expected_min, expected_max])
                        assert counts(database) == [len(record['saved']), len(record['saved'])], 'unexpected_records'
                else:
                    # Scope is SSR budget semantics/native refusal, NOT a functional no-JS create flow.
                    fill('99999999.98', '99999999.99')
                    assert_markup(page, locale)
                    capture('boundary')
                assert record['page_errors'] == [], 'page_errors'
            finally:
                for ctx in reversed(contexts):
                    ctx.close()
        record.update(fixture_cleanup=True, passed=True, stage='complete')
    except Exception as error:
        record['failure'] = str(error) if isinstance(error, AssertionError) and re.fullmatch(r'[a-z_]+', str(error)) else type(error).__name__
    return record


def validate_report(report, output):
    assert report['schema'] == 1 and report['complete'] is True, 'report_incomplete'
    assert report['checkout'] == checkout_identity(), 'checkout_drift'
    assert report['source_hashes'] == source_hashes(), 'source_drift'
    expected = {case_key(case): case for case in CASES}
    records = report['cases']
    assert len(records) == len(expected) and {r['case'] for r in records} == set(expected), 'case_matrix'
    images = set()
    for record in records:
        assert record['passed'] is True and record['stage'] == 'complete' and 'failure' not in record, 'case_incomplete'
        assert record['fixture_cleanup'] is True and record['page_errors'] == [], 'fixture_or_page_error'
        assert record['keyboard'] is True and record['native_blocked'] == ['precision', 'upper', 'negative'], 'native_or_keyboard_missing'
        _, _, width, javascript = expected[record['case']]
        checkpoints = CHECKPOINTS if javascript else ('initial', 'boundary')
        assert set(record['checkpoints']) == set(checkpoints) and set(record['screenshots']) == set(checkpoints), 'checkpoint_set'
        assert record['saved'] == [[v[0], v[3], v[4]] for v in VALID] if javascript else record['saved'] == [], 'saved_cases'
        assert record['invalid_unchanged'] == (['ordered', 'decimal', 'upper'] if javascript else []), 'rejection_missing'
        assert record['http'] == [200] * (13 if javascript else 1), 'http_matrix'
        for stage in checkpoints:
            assert_geometry(record['checkpoints'][stage], width, stage)
            name = record['case'] + '/' + stage + '.png'
            images.add(name)
            path = output / name
            assert path.resolve(strict=True).is_relative_to(output.resolve(strict=True)), 'screenshot_escape'
            assert hashlib.sha256(path.read_bytes()).hexdigest() == record['screenshots'][stage], 'screenshot_hash'
            validate_png(path, width)
    assert {p.relative_to(output).as_posix() for p in output.rglob('*.png')} == images, 'screenshot_set'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--case', choices=[case_key(case) for case in CASES], help='Diagnostic subset; never complete evidence.')
    args = parser.parse_args()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    report = {'schema': 1, 'complete': False, 'checkout': checkout_identity(), 'source_hashes': source_hashes(), 'cases': []}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for case in CASES:
                if args.case and args.case != case_key(case):
                    continue
                result = journey(browser, case, output)
                report['cases'].append(result)
                (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
                print(json.dumps({k: result[k] for k in ('case', 'passed', 'stage', 'failure') if k in result}), flush=True)
        finally:
            browser.close()
    assert report['checkout'] == checkout_identity() and report['source_hashes'] == source_hashes(), 'runtime_source_drift'
    report['complete'] = not args.case and all(r['passed'] for r in report['cases'])
    (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
    assert all(r['passed'] for r in report['cases']), 'browser_failure'
    if not args.case:
        validate_report(report, output)


if __name__ == '__main__':
    main()
