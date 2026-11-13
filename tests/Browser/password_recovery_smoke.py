"""Real HTML recovery journeys; disposable SQLite, array mail, no saved capabilities."""
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
import urllib.parse
import urllib.request

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
COPY = {
    "en": {
        "forgot": "Forgot your password?", "reset": "Choose a new password", "email": "Email",
        "password": "New password", "confirmation": "Confirm new password", "send": "Send reset link",
        "submit": "Reset password", "back": "Back to sign in", "another": "Request another reset link",
        "requested": "If an account matches this email, you will receive a password reset link. Please check your inbox and spam folder.",
        "required_email": "The email field is required.", "required_password": "The new password field is required.",
        "mismatch": "The new password field confirmation does not match.",
        "invalid": "This reset link is invalid or has expired. Request another link and check the email address.",
    },
    "pt_BR": {
        "forgot": "Esqueceu sua senha?", "reset": "Escolha uma nova senha", "email": "E-mail",
        "password": "Nova senha", "confirmation": "Confirme a nova senha", "send": "Enviar link de redefinição",
        "submit": "Redefinir senha", "back": "Voltar para entrar", "another": "Solicitar outro link de redefinição",
        "requested": "Se houver uma conta com este e-mail, você receberá um link para redefinir sua senha. Confira sua caixa de entrada e a pasta de spam.",
        "required_email": "O campo e-mail é obrigatório.", "required_password": "O campo nova senha é obrigatório.",
        "mismatch": "O campo nova senha de confirmação não confere.",
        "invalid": "Este link de redefinição é inválido ou expirou. Solicite outro link e confira o endereço de e-mail.",
    },
}
CHECKPOINTS = ("forgot", "requested", "reset", "required", "mismatch", "invalid", "expired", "malformed", "replay")
MEASUREMENTS = {"forgot": (3, 5), "requested": (3, 6), "reset": (6, 8), "required": (6, 10),
                "mismatch": (6, 9), "invalid": (6, 9), "expired": (6, 9), "malformed": (6, 10), "replay": (6, 9)}
SOURCES = (
    "app/Http/Controllers/PasswordResetController.php", "app/Models/User.php", "routes/web.php",
    "bootstrap/app.php", "config/auth.php", "config/session.php", "resources/css/app.css", "resources/js/app.js",
    "resources/views/components/layouts/app.blade.php", "resources/views/livewire/auth/forgot-password.blade.php",
    "resources/views/livewire/auth/reset-password.blade.php", "lang/en/password_recovery.php",
    "lang/pt_BR/password_recovery.php", "lang/en/validation.php", "lang/pt_BR/validation.php",
    "tests/Browser/password_recovery_smoke.py", "tests/Browser/seed-password-recovery.php",
    "composer.lock", "package-lock.json", ".github/workflows/laravel.yml",
)
REASONS = {
    "seed_failed", "server_exited", "server_unready", "listener_leak", "page_http", "action_http",
    "native_validation", "native_dispatched", "native_restore", "field_description", "page_overflow",
    "control_overflow", "footer_overlap", "touch_target", "primary_background", "contrast",
    "empty_measurements", "credentials_changed", "request_token", "request_throttle", "unexpected_token",
    "password_verification", "remember_rotation", "token_consumption", "authentication", "guest",
    "malformed_prefill", "page_errors", "assertion_failed", "runtime_failed", "incomplete_case",
    "missing_checkpoint", "missing_screenshot", "source_drift", "case_set", "artifact_absent",
}


def matrix():
    return [(locale, scheme, width, True) for locale in COPY for scheme in ("light", "dark")
            for width in (320, 390, 1280)] + [(locale, "light", 320, False) for locale in COPY]


def case_key(item):
    locale, scheme, width, javascript = item
    return f"{locale}-{scheme}-{width}-{'js' if javascript else 'nojs'}"


def provenance():
    paths = [ROOT / path for path in (*SOURCES, "public/build/manifest.json")]
    assert all(path.is_file() for path in paths), "artifact_absent"
    manifest = json.loads(paths[-1].read_text())
    assets = [ROOT / "public/build" / entry["file"] for entry in manifest.values()]
    assert assets and all(path.is_file() for path in assets), "artifact_absent"
    paths += sorted(path for path in (ROOT / "public/build").rglob("*") if path.is_file())
    return {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest() for path in paths}


@contextmanager
def application(locale):
    scratch = os.environ.get("RUNNER_TEMP") or os.environ.get("TMPDIR")
    if not scratch:
        raise RuntimeError("scratch_required")
    with tempfile.TemporaryDirectory(prefix="regalu-recovery-browser-", dir=scratch) as temporary:
        directory = Path(temporary)
        database = directory / "browser.sqlite"
        database.touch(mode=0o600)
        (directory / "views").mkdir()
        with socket.socket() as probe:
            probe.bind(("127.0.0.1", 0))
            port = probe.getsockname()[1]
        origin = f"http://127.0.0.1:{port}"
        env = dict(os.environ, APP_ENV="testing", APP_DEBUG="false", APP_URL=origin,
                   APP_KEY="base64:" + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE="en", DB_URL="", DB_CONNECTION="sqlite",
                   DB_DATABASE=str(database), CACHE_STORE="array", CACHE_LIMITER="array",
                   MAIL_MAILER="array", QUEUE_CONNECTION="sync", SESSION_DRIVER="database",
                   SESSION_CONNECTION="sqlite", SESSION_DOMAIN="null", SESSION_SECURE_COOKIE="false",
                   SESSION_COOKIE="regalu_recovery_" + secrets.token_hex(8), AUTH_GUARD="web",
                   AUTH_PASSWORD_BROKER="users", AUTH_MODEL="App\\Models\\User",
                   AUTH_PASSWORD_RESET_TOKEN_TABLE="password_reset_tokens", BCRYPT_ROUNDS="4",
                   REGALU_BROWSER_DIRECTORY=temporary, REGALU_BROWSER_PASSWORD=secrets.token_urlsafe(32),
                   APP_CONFIG_CACHE=str(directory / "config.php"), APP_ROUTES_CACHE=str(directory / "routes.php"),
                   APP_SERVICES_CACHE=str(directory / "services.php"), APP_PACKAGES_CACHE=str(directory / "packages.php"),
                   VIEW_COMPILED_PATH=str(directory / "views"), LOG_CHANNEL="stderr")
        seed = subprocess.run(["php", str(ROOT / "tests/Browser/seed-password-recovery.php")],
                              cwd=ROOT, env=env, capture_output=True, text=True, timeout=60)
        assert seed.returncode == 0, "seed_failed"
        fixture = json.loads(seed.stdout)  # Never write this capability-bearing output to disk.
        router = ROOT / "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
        server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", str(router)],
                                  cwd=ROOT / "public", env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            for _ in range(100):
                assert server.poll() is None, "server_exited"
                try:
                    with urllib.request.urlopen(origin + "/forgot-password", timeout=1) as response:
                        if response.status == 200:
                            break
                except (OSError, urllib.error.URLError):
                    time.sleep(0.1)
            else:
                raise AssertionError("server_unready")
            yield origin, database, fixture
        finally:
            if server.poll() is None:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=10)
            with socket.socket() as check:
                assert check.connect_ex(("127.0.0.1", port)) != 0, "listener_leak"


def row(database, query, values=()):
    with sqlite3.connect(f"file:{database}?mode=ro", uri=True) as connection:
        return connection.execute(query, values).fetchone()


def credentials(database, email):
    return row(database, "select password, remember_token from users where email = ?", (email,))


def submit(page, path, *, negative=False, keyboard=False):
    form = page.locator("main form")
    with page.expect_response(lambda r: r.request.method == "POST" and urllib.parse.urlparse(r.url).path == path) as pending:
        if negative:
            form.evaluate("""form => {
                const original = form.noValidate;
                try { form.noValidate = true; form.requestSubmit(form.querySelector('button[type=submit]')); }
                finally { form.noValidate = original; }
            }""")
        elif keyboard:
            form.locator("button[type=submit]").focus()
            page.keyboard.press("Enter")
        else:
            form.locator("button[type=submit]").click()
    assert pending.value.status == 302, "action_http"
    page.wait_for_load_state("networkidle")
    if negative:
        assert page.locator("main form").evaluate("el => el.noValidate") is False, "native_restore"


def error(page, field, text):
    control = page.locator(f"main input[name='{field}']")
    expect(control).to_have_attribute("aria-invalid", "true")
    descriptions = (control.get_attribute("aria-describedby") or "").split()
    assert descriptions, "field_description"
    texts = []
    for identifier in descriptions:
        assert identifier != control.get_attribute("id"), "field_description"
        node = page.locator(f"[id='{identifier}']")
        expect(node).to_have_count(1)
        expect(node).to_be_visible()
        texts.append(node.inner_text().strip())
    assert text in texts, "field_description"


def check_geometry(data):
    assert data["controls"] and data["contrast"], "empty_measurements"
    assert data["content"] <= data["viewport"] + 1, "page_overflow"
    for control in [data["panel"], *data["controls"]]:
        assert control["left"] >= -1 and control["right"] <= data["viewport"] + 1, "control_overflow"
    assert data["panel"]["bottom"] <= data["footerTop"] + 1, "footer_overlap"
    assert all(control["height"] >= 44 for control in data["controls"]), "touch_target"
    assert data["primaryAlpha"] == 255, "primary_background"
    assert all(isinstance(ratio, (float, int)) and math.isfinite(ratio) and ratio >= 4.5
               for ratio in data["contrast"]), "contrast"


def capture(page, output, name, case):
    panel = page.locator("main section > div > div")
    expect(panel).to_be_visible()
    data = panel.evaluate("""panel => {
        const box = el => { const r = el.getBoundingClientRect(); return {
            left:r.left, right:r.right, width:r.width, height:r.height, bottom:r.bottom + scrollY}; };
        const ctx = document.createElement('canvas').getContext('2d');
        const rgba = color => { ctx.clearRect(0,0,1,1); ctx.fillStyle=color;
            ctx.fillRect(0,0,1,1); return [...ctx.getImageData(0,0,1,1).data]; };
        const lum = rgb => { const [r,g,b] = rgb.slice(0,3).map(v=>v/255)
            .map(v=>v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4); return r*.2126+g*.7152+b*.0722; };
        const contrast = el => {
            const fg = rgba(getComputedStyle(el).color); let ancestor=el, bg;
            while(ancestor) { const style=getComputedStyle(ancestor);
                if(style.backgroundImage!=='none') return null;
                bg=rgba(style.backgroundColor); if(bg[3]!==0) break; ancestor=ancestor.parentElement; }
            if(!ancestor || bg[3]!==255 || fg[3]!==255) return null;
            const a=lum(fg), b=lum(bg); return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
        };
        const visible = selector => [...panel.querySelectorAll(selector)].filter(el=>el.getClientRects().length);
        return {viewport:innerWidth, content:document.documentElement.scrollWidth, panel:box(panel),
            footerTop:document.querySelector('footer').getBoundingClientRect().top+scrollY,
            controls:visible('input:not([type=hidden]),button,a').map(box),
            // Only measure elements painting text, not an empty-color wrapper around a styled link.
            contrast:visible('h1,label,p,a,button,[role=status],[role=alert]')
                .filter(el=>[...el.childNodes].some(node=>node.nodeType===Node.TEXT_NODE && node.textContent.trim())).map(contrast),
            primaryAlpha:rgba(getComputedStyle(panel.querySelector('button[type=submit]')).backgroundColor)[3]};
    }""")
    case["geometry"][name] = data
    assert (len(data["controls"]), len(data["contrast"])) == MEASUREMENTS[name], "empty_measurements"
    check_geometry(data)
    page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled",
                    mask=[page.locator("input[type=password]")])
    case["screenshots"].append(f"{name}.png")


def journey(browser, item, output, case):
    locale, scheme, width, javascript = item
    copy = COPY[locale]
    case["stage"] = "bootstrap"
    with application(locale) as (origin, database, fixture):
        contexts = []

        def new_page():
            context = browser.new_context(viewport={"width": width, "height": 844}, color_scheme=scheme,
                                          locale=locale.replace("_", "-"), java_script_enabled=javascript)
            contexts.append(context)
            context.route("**/*", lambda r: r.continue_() if r.request.url.startswith(origin + "/") else r.abort())
            page = context.new_page()
            page.on("pageerror", lambda _: case["page_errors"].append("javascript_error"))
            return page

        def visit(page, path):
            assert page.goto(origin + path).status == 200, "page_http"
            page.wait_for_load_state("networkidle")

        def reset_path(token, email):
            return "/reset-password/" + urllib.parse.quote(token, safe="") + "?" + urllib.parse.urlencode({"email": email})

        def fill_password(page, password, confirmation=None):
            page.locator("#password").fill(password)
            page.locator("#password_confirmation").fill(password if confirmation is None else confirmation)

        def guest():
            assert row(database, "select count(*) from sessions where user_id is not null")[0] == 0, "guest"

        try:
            page = new_page()
            case["stage"] = "requests"
            visit(page, "/forgot-password")
            expect(page.get_by_role("heading", name=copy["forgot"], exact=True)).to_be_visible()
            expect(page.get_by_label(copy["email"], exact=True)).to_have_attribute("autocomplete", "email")
            expect(page.locator("main form")).to_have_attribute("action", origin + "/forgot-password")
            capture(page, output, "forgot", case)
            requests = []
            page.on("request", lambda r: requests.append(1) if r.method == "POST" else None)
            page.locator("main button[type=submit]").click()
            assert page.locator("#email").evaluate("e => e.validity.valueMissing"), "native_validation"
            assert not requests, "native_dispatched"
            submit(page, "/forgot-password", negative=True)
            error(page, "email", copy["required_email"])
            for email in ("request@example.test", "request@example.test", "missing@example.test"):
                previous = row(database, "select token, created_at from password_reset_tokens where email = ?", (email,))
                page.locator("#email").fill(email)
                submit(page, "/forgot-password", keyboard=True)
                expect(page.locator("main [role=status]")).to_have_text(copy["requested"])
                current = row(database, "select token, created_at from password_reset_tokens where email = ?", (email,))
                if email.startswith("missing"):
                    assert current is None, "unexpected_token"
                elif previous:
                    assert current == previous, "request_throttle"
                else:
                    assert current is not None, "request_token"
            capture(page, output, "requested", case)
            guest()

            case["stage"] = "reset_validation"
            path = reset_path(fixture["valid"], "recovery@example.test")
            before = credentials(database, "recovery@example.test")
            visit(page, path)
            expect(page.get_by_role("heading", name=copy["reset"], exact=True)).to_be_visible()
            expect(page.locator("main form")).to_have_attribute("action", origin + "/reset-password")
            for label in (copy["password"], copy["confirmation"]):
                expect(page.get_by_label(label, exact=True)).to_have_attribute("autocomplete", "new-password")
                expect(page.get_by_label(label, exact=True)).to_have_attribute("minlength", "8")
            page.locator("#email").focus()
            page.keyboard.press("Tab")
            expect(page.locator("#password")).to_be_focused()
            page.keyboard.press("Tab")
            expect(page.locator("#password_confirmation")).to_be_focused()
            capture(page, output, "reset", case)
            page.locator("main button[type=submit]").click()
            assert page.locator("#password").evaluate("e => e.validity.valueMissing"), "native_validation"
            page.locator("#email").fill("")
            submit(page, "/reset-password", negative=True)
            error(page, "email", copy["required_email"])
            error(page, "password", copy["required_password"])
            error(page, "password_confirmation", copy["required_password"])
            capture(page, output, "required", case)
            password = secrets.token_urlsafe(32)
            page.locator("#email").fill("recovery@example.test")
            fill_password(page, password, secrets.token_urlsafe(32))
            submit(page, "/reset-password")
            error(page, "password_confirmation", copy["mismatch"])
            capture(page, output, "mismatch", case)
            assert credentials(database, "recovery@example.test") == before, "credentials_changed"
            guest()

            case["stage"] = "invalid_links"
            visit(page, reset_path("non-redeemable", "recovery@example.test"))
            fill_password(page, password)
            submit(page, "/reset-password")
            error(page, "email", copy["invalid"])
            capture(page, output, "invalid", case)
            expired_before = credentials(database, "expired@example.test")
            visit(page, reset_path(fixture["expired"], "expired@example.test"))
            fill_password(page, password)
            submit(page, "/reset-password")
            error(page, "email", copy["invalid"])
            capture(page, output, "expired", case)
            assert credentials(database, "expired@example.test") == expired_before, "credentials_changed"
            assert credentials(database, "recovery@example.test") == before, "credentials_changed"
            guest()

            case["stage"] = "malformed"
            visit(page, "/reset-password/presentation-only?email%5B%5D=malformed")
            expect(page.locator("#email")).to_have_value("")
            for route in ("/forgot-password", "/reset-password/presentation-only"):
                visit(page, route)
                page.locator("#email").evaluate("e => e.name = 'email[]'")
                page.locator("#email").fill("malformed@example.test")
                submit(page, "/forgot-password" if route == "/forgot-password" else "/reset-password", negative=True)
                expect(page.locator("#email")).to_have_value("")
                expect(page.locator("#email-error")).to_be_visible()
            capture(page, output, "malformed", case)
            guest()

            case["stage"] = "success"
            visit(page, path)
            fill_password(page, password)
            submit(page, "/reset-password", keyboard=True)
            page.wait_for_url(origin + "/dashboard")
            expect(page.locator("main")).to_be_visible()
            after = credentials(database, "recovery@example.test")
            check = subprocess.run(["php", "-r", "$v=json_decode(stream_get_contents(STDIN),true); echo password_verify($v[0],$v[1])?'yes':'no';"],
                                   input=json.dumps([password, after[0]]), capture_output=True, text=True, timeout=10)
            assert check.returncode == 0 and check.stdout == "yes", "password_verification"
            assert after[1] and after[1] != before[1], "remember_rotation"
            assert row(database, "select count(*) from password_reset_tokens where email = ?", ("recovery@example.test",))[0] == 0, "token_consumption"
            assert row(database, "select count(*) from sessions s join users u on u.id=s.user_id where u.email = ?", ("recovery@example.test",))[0] == 1, "authentication"
            case["authenticated_reset"] = True
            case["stage"] = "replay"
            page = new_page()
            visit(page, path)
            fill_password(page, secrets.token_urlsafe(32))
            submit(page, "/reset-password")
            error(page, "email", copy["invalid"])
            capture(page, output, "replay", case)
            assert credentials(database, "recovery@example.test") == after, "credentials_changed"
            page.goto(origin + "/dashboard")
            page.wait_for_url(origin + "/login")
            case["replay_guest"] = True
            # Exercise both actual recovery exit links, not their hrefs alone.
            visit(page, path)
            page.get_by_role("link", name=copy["another"], exact=True).click()
            page.wait_for_url(origin + "/forgot-password")
            page.get_by_role("link", name=copy["back"], exact=True).click()
            page.wait_for_url(origin + "/login")
            assert not case["page_errors"], "page_errors"
        finally:
            for context in contexts:
                context.close()
    case["stage"] = "complete"
    case["passed"] = True


def validate(report, output, planned, expected_sources):
    assert report["source_before"] == report["source_after"] == expected_sources, "source_drift"
    cases = report["cases"]
    assert len(cases) == len(planned) and {case["key"] for case in cases} == set(planned), "case_set"
    for case in cases:
        assert case["passed"] is True and case["stage"] == "complete" and not case["page_errors"], "incomplete_case"
        assert case.get("authenticated_reset") is True and case.get("replay_guest") is True, "incomplete_case"
        assert set(case["geometry"]) == set(CHECKPOINTS), "missing_checkpoint"
        assert sorted(case["screenshots"]) == sorted(f"{name}.png" for name in CHECKPOINTS), "missing_screenshot"
        for name, data in case["geometry"].items():
            assert (len(data["controls"]), len(data["contrast"])) == MEASUREMENTS[name], "empty_measurements"
            check_geometry(data)
        for image in case["screenshots"]:
            assert (output / case["key"] / image).is_file(), "artifact_absent"
        assert json.loads((output / case["key"] / "result.json").read_text()) == case, "incomplete_case"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--case", choices=[case_key(item) for item in matrix()])
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    planned = [item for item in matrix() if not args.case or case_key(item) == args.case]
    report = {"source_before": provenance(), "cases": []}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for item in planned:
                key = case_key(item)
                folder = args.output / key
                folder.mkdir(exist_ok=True)
                case = {"key": key, "stage": "start", "passed": False, "page_errors": [], "geometry": {}, "screenshots": []}
                report["cases"].append(case)
                try:
                    journey(browser, item, folder, case)
                except Exception as error_value:
                    reason = str(error_value)
                    case["failure"] = {"type": type(error_value).__name__, "reason": reason if reason in REASONS else "runtime_failed"}
                    raise RuntimeError(f"{key}: {case['stage']}: {case['failure']['reason']}") from None
                finally:
                    report["source_after"] = provenance()
                    (folder / "result.json").write_text(json.dumps(case, indent=2))
                    (args.output / "results.json").write_text(json.dumps(report, indent=2))
                print(json.dumps({"case": key, "passed": case["passed"]}), flush=True)
        finally:
            browser.close()
    validate(report, args.output, [case_key(item) for item in planned], provenance())


if __name__ == "__main__":
    main()
