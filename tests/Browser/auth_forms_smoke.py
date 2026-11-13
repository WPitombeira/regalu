"""Real auth-form journeys on disposable SQLite; no providers or application data."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, application, call_response, row

COPY = {
    "en": {
        "login": "Sign in", "login_heading": "Sign in to your account",
        "register": "Create an account", "name": "Name", "email": "Email",
        "password": "Password", "confirmation": "Confirm password",
        "required": "The Name field is required.",
        "mismatch": "Passwords do not match.", "duplicate": "This email is already registered.",
        "credentials": "Unable to sign in. Check your email and password, or try again shortly.",
        "providers": "Apple and Google sign-in are not available. Use your email and password.",
        "nojs": "Enable JavaScript to sign in or create an account.",
    },
    "pt_BR": {
        "login": "Entrar", "login_heading": "Entre na sua conta",
        "register": "Criar conta", "name": "Nome", "email": "E-mail",
        "password": "Senha", "confirmation": "Confirme a senha",
        "required": "O campo Nome é obrigatório.",
        "mismatch": "As senhas não coincidem.", "duplicate": "Este e-mail já está cadastrado.",
        "credentials": "Não foi possível entrar. Confira seu e-mail e senha ou tente novamente em instantes.",
        "providers": "O acesso com Apple e Google não está disponível. Use seu e-mail e senha.",
        "nojs": "Ative o JavaScript para entrar ou criar uma conta.",
    },
}
SOURCES = [
    "app/Livewire/Home/LoginForm.php", "app/Services/LoginAttemptService.php",
    "resources/views/livewire/home/login-form.blade.php",
    "resources/views/components/ui/buttons/login.blade.php",
    "lang/en/auth_forms.php", "lang/pt_BR/auth_forms.php",
    "tests/Browser/auth_forms_smoke.py", "tests/Browser/event_history_smoke.py",
    "tests/Browser/seed-dashboard.php", "composer.lock", "package-lock.json",
]
REASONS = {
    "action_http", "native_validation_restore", "page_overflow", "control_overflow",
    "narrow_field", "footer_overlap", "control_contrast", "reset_link_wrapped",
    "provider_description", "login_http", "unexpected_user", "primary_background",
    "native_validation", "native_invalid_dispatched", "invalid_user_created",
    "mismatch_user_created", "duplicate_user_created", "registration_not_persisted",
    "registration_count", "registration_not_authenticated", "guest_route",
    "invalid_login_authenticated", "remember_not_persisted", "login_not_authenticated",
    "incomplete_case", "missing_checkpoint", "missing_screenshot", "screenshot_absent",
}


def provenance():
    paths = [ROOT / path for path in [*SOURCES, "public/build/manifest.json"]]
    if not all(path.is_file() for path in paths):
        raise RuntimeError("required_provenance_missing")
    manifest = json.loads((ROOT / "public/build/manifest.json").read_text())
    assets = [ROOT / "public/build" / entry["file"] for entry in manifest.values()]
    if not assets or not all(path.is_file() for path in assets):
        raise RuntimeError("required_assets_missing")
    paths += [path for path in sorted((ROOT / "public/build").rglob("*")) if path.is_file()]
    return {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
            for path in paths}


def submit(page, form, method, *, server_negative=False):
    with page.expect_response(lambda response: call_response(response, method)) as pending:
        if server_negative:
            form.evaluate("""form => {
                const original = form.noValidate;
                try { form.noValidate = true; form.requestSubmit(form.querySelector('button[type=submit]')); }
                finally { form.noValidate = original; }
            }""")
        else:
            form.locator('button[type="submit"]').click()
    assert pending.value.status == 200, "action_http"
    if server_negative:
        assert form.evaluate("el => el.noValidate") is False, "native_validation_restore"


def capture(page, panel, output, name, case):
    expect(panel).to_be_visible()
    # Record both horizontal edges; scrollWidth alone misses negative-left clipping.
    geometry = panel.evaluate("""panel => {
        const box = element => {
            const r = element.getBoundingClientRect();
            return {left: r.left, right: r.right, width: r.width, height: r.height, bottom: r.bottom + scrollY};
        };
        const canvas = document.createElement('canvas').getContext('2d');
        const rgba = color => {
            canvas.clearRect(0, 0, 1, 1);
            canvas.fillStyle = color;
            canvas.fillRect(0, 0, 1, 1);
            return [...canvas.getImageData(0, 0, 1, 1).data];
        };
        const luminance = rgb => {
            const [r, g, b] = rgb.slice(0, 3).map(v => v / 255)
                .map(v => v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
            return r * 0.2126 + g * 0.7152 + b * 0.0722;
        };
        const contrast = element => {
            const foreground = rgba(getComputedStyle(element).color);
            let ancestor = element;
            let background;
            while (ancestor) {
                background = rgba(getComputedStyle(ancestor).backgroundColor);
                if (background[3] !== 0) break;
                ancestor = ancestor.parentElement;
            }
            // This scoped probe supports opaque cards/controls, not compositing or gradients.
            if (!ancestor || background[3] !== 255 || foreground[3] !== 255) return null;
            const a = luminance(foreground), b = luminance(background);
            return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
        };
        const reset = panel.querySelector('a');
        return {viewport: innerWidth, content: document.documentElement.scrollWidth,
            panel: box(panel), footerTop: document.querySelector('footer').getBoundingClientRect().top + scrollY,
            controls: [...panel.querySelectorAll('input,button,a')].filter(el => el.getClientRects().length).map(box),
            contrast: [...panel.querySelectorAll('a,button:enabled')].filter(el => el.getClientRects().length).map(contrast),
            reset: reset ? box(reset) : null,
            fields: [...panel.querySelectorAll('input:not([type=checkbox])')].map(box)};
    }""")
    case["geometry"][name] = geometry
    assert geometry["content"] <= geometry["viewport"] + 1, "page_overflow"
    for control in [geometry["panel"], *geometry["controls"]]:
        assert control["left"] >= -1 and control["right"] <= geometry["viewport"] + 1, "control_overflow"
    assert all(field["width"] >= 200 for field in geometry["fields"]), "narrow_field"
    assert geometry["panel"]["bottom"] <= geometry["footerTop"] + 1, "footer_overlap"
    assert all(ratio is not None and ratio >= 4.5 for ratio in geometry["contrast"]), "control_contrast"
    assert geometry["reset"] is None or geometry["reset"]["height"] <= 24, "reset_link_wrapped"
    page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled",
                    mask=[page.locator('input[type="password"]')])
    case["screenshots"].append(f"{name}.png")


def panel_for(form):
    # The panel owns the heading and reciprocal switch as well as the form.
    return form.locator('xpath=ancestor::div[@x-show or @x-show.important][1]')


def check_provider_notice(panel, text):
    expect(panel.get_by_text(text, exact=True)).to_be_visible()
    for provider in ("Apple", "Google"):
        button = panel.get_by_role("button", name=provider, exact=True)
        expect(button).to_be_disabled()
        description = button.get_attribute("aria-describedby")
        assert description, "provider_description"
        expect(panel.locator(f"#{description}")).to_have_text(text)


def journey(browser, locale, scheme, width, javascript, output, case):
    copy = COPY[locale]
    case["stage"] = "bootstrap"
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        contexts = []

        def new_page():
            context = browser.new_context(viewport={"width": width, "height": 900 if width == 1280 else 844},
                                          locale=locale.replace("_", "-"), color_scheme=scheme,
                                          java_script_enabled=javascript)
            contexts.append(context)
            context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
            page = context.new_page()
            # Never persist raw exceptions, browser snapshots, request bodies or credentials.
            page.on("pageerror", lambda error: case["page_errors"].append("javascript_error"))
            return page

        try:
            page = new_page()
            assert page.goto(origin + "/login").status == 200, "login_http"
            login = page.locator('form[wire\\:submit="login"]')
            register = page.locator('form[wire\\:submit="register"]')
            login_panel = panel_for(login)
            register_panel = panel_for(register)
            expect(login_panel.get_by_role("heading", name=copy["login_heading"], exact=True)).to_be_visible()
            expect(register).not_to_be_visible()
            check_provider_notice(login_panel, copy["providers"])
            initial_users = row(database, "select count(*) from users", ())[0]
            if not javascript:
                case["stage"] = "no_javascript"
                expect(page.get_by_text(copy["nojs"], exact=True)).to_be_visible()
                for field in login.locator("input").all():
                    expect(field).to_be_disabled()
                for button in login.locator("button").all():
                    expect(button).to_be_disabled()
                capture(page, login_panel, output, "no-javascript", case)
                assert row(database, "select count(*) from users", ()) == (initial_users,), "unexpected_user"
                case["stage"] = "complete"
                return

            case["stage"] = "login_presentation"
            expect(login.locator("#login-email")).to_be_enabled()
            expect(login.get_by_label(copy["email"], exact=True)).to_have_attribute("autocomplete", "username")
            expect(login.get_by_label(copy["password"], exact=True)).to_have_attribute("autocomplete", "current-password")
            button = login.locator('button[type="submit"]')
            assert button.evaluate("el => getComputedStyle(el).backgroundColor") != "rgba(0, 0, 0, 0)", "primary_background"
            capture(page, login_panel, output, "login", case)

            case["stage"] = "keyboard_switches"
            for _ in range(2):
                toggle = login_panel.get_by_role("button", name=copy["register"], exact=True)
                toggle.focus()
                toggle.press("Enter")
                expect(register).to_be_visible()
                expect(login).not_to_be_visible()
                expect(register.locator("#register-name")).to_be_focused()
                reverse = register_panel.get_by_role("button", name=copy["login"], exact=True)
                reverse.focus()
                reverse.press("Space")
                expect(login).to_be_visible()
                expect(register).not_to_be_visible()
                expect(login.locator("#login-email")).to_be_focused()
            login_panel.get_by_role("button", name=copy["register"], exact=True).click()
            expect(register_panel.get_by_role("heading", name=copy["register"], exact=True)).to_be_visible()
            check_provider_notice(register_panel, copy["providers"])
            capture(page, register_panel, output, "registration", case)

            case["stage"] = "native_validation"
            calls = []
            listener = lambda response: calls.append("register") if call_response(response, "register") else None
            page.on("response", listener)
            register.locator('button[type="submit"]').click()
            expect(register.locator("#register-name")).to_be_focused()
            assert register.evaluate("form => !form.checkValidity()") is True, "native_validation"
            page.wait_for_load_state("networkidle")
            assert not calls, "native_invalid_dispatched"
            page.remove_listener("response", listener)

            case["stage"] = "server_required"
            submit(page, register, "register", server_negative=True)
            expect(register.locator("#register-name-error")).to_have_text(copy["required"])
            expect(register.locator("#register-name")).to_have_attribute("aria-invalid", "true")
            expect(register.locator("#register-name")).to_have_attribute("aria-describedby", "register-name-error")
            expect(register.locator("#register-name")).to_be_enabled()
            assert row(database, "select count(*) from users", ()) == (initial_users,), "invalid_user_created"
            capture(page, register_panel, output, "required-error", case)

            case["stage"] = "password_mismatch"
            register.locator("#register-name").fill("Browser Auth Tester")
            register.locator("#register-email").fill("registration@example.test")
            register.locator("#register-password").fill(password)
            register.locator("#register-password-confirm").fill(password + "-different")
            submit(page, register, "register")
            expect(register.locator("#register-password-confirm-error")).to_have_text(copy["mismatch"])
            expect(register.locator("#register-password-confirm")).to_have_attribute("aria-invalid", "true")
            assert row(database, "select count(*) from users", ()) == (initial_users,), "mismatch_user_created"
            capture(page, register_panel, output, "mismatch-error", case)

            case["stage"] = "duplicate_email"
            register.locator("#register-password-confirm").fill(password)
            register.locator("#register-email").fill("member@example.test")
            submit(page, register, "register")
            expect(register.locator("#register-email-error")).to_have_text(copy["duplicate"])
            expect(register.locator("#register-email")).to_have_attribute("aria-describedby", "register-email-error")
            assert row(database, "select count(*) from users", ()) == (initial_users,), "duplicate_user_created"
            capture(page, register_panel, output, "duplicate-error", case)

            case["stage"] = "registration_success"
            register.locator("#register-email").fill("registration@example.test")
            submit(page, register, "register")
            page.wait_for_url(origin + "/dashboard")
            assert row(database, "select count(*) from users where email = ? and name = ?", ("registration@example.test", "Browser Auth Tester")) == (1,), "registration_not_persisted"
            assert row(database, "select count(*) from users", ()) == (initial_users + 1,), "registration_count"
            assert page.goto(origin + "/login").url == origin + "/dashboard", "registration_not_authenticated"

            case["stage"] = "invalid_login"
            page = new_page()
            # Exercise a real intended route in a fresh, unauthenticated browser session.
            assert page.goto(origin + "/settings").url == origin + "/login", "guest_route"
            login = page.locator('form[wire\\:submit="login"]')
            login_panel = panel_for(login)
            login.locator("#login-email").fill("registration@example.test")
            login.locator("#login-password").fill(password + "-wrong")
            submit(page, login, "login")
            expect(login.locator("#login-error")).to_have_text(copy["credentials"])
            assert page.url == origin + "/login", "invalid_login_authenticated"
            capture(page, login_panel, output, "login-error", case)

            case["stage"] = "login_success"
            login.locator("#login-password").fill(password)
            login.locator("#remember").check()
            submit(page, login, "login")
            page.wait_for_url(origin + "/settings")
            assert row(database, "select remember_token is not null from users where email = ?", ("registration@example.test",)) == (1,), "remember_not_persisted"
            assert page.goto(origin + "/login").url == origin + "/dashboard", "login_not_authenticated"
            case["stage"] = "complete"
        finally:
            for context in contexts:
                context.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    before = provenance()
    source = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    matrix = [(locale, scheme, width, True) for locale in COPY for scheme in ("light", "dark") for width in (320, 390, 1280)]
    matrix += [(locale, "light", 320, False) for locale in COPY]
    cases = []
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale, scheme, width, javascript in matrix:
                key = f"{locale}-{scheme}-{width}-{'js' if javascript else 'nojs'}"
                output = args.output / key
                output.mkdir(exist_ok=True)
                case = {"key": key, "locale": locale, "scheme": scheme, "width": width, "javascript": javascript,
                        "passed": False, "stage": "start", "page_errors": [], "geometry": {}, "screenshots": []}
                try:
                    journey(browser, locale, scheme, width, javascript, output, case)
                    assert case["stage"] == "complete" and not case["page_errors"], "incomplete_case"
                    expected = {"login", "registration", "required-error", "mismatch-error", "duplicate-error", "login-error"} if javascript else {"no-javascript"}
                    assert set(case["geometry"]) == expected, "missing_checkpoint"
                    assert {Path(name).stem for name in case["screenshots"]} == expected, "missing_screenshot"
                    assert all((output / name).is_file() for name in case["screenshots"]), "screenshot_absent"
                    case["passed"] = True
                except Exception as error:
                    case["error_type"] = type(error).__name__
                    # Only our fixed reason codes are allowed, never Playwright's raw failure snapshots.
                    reason = str(error)
                    if reason in REASONS:
                        case["reason"] = reason
                cases.append(case)
                print(json.dumps({key: value for key, value in case.items() if key != "geometry"}), flush=True)
                (args.output / "results.json").write_text(json.dumps({"source": source, "provenance": before, "cases": cases}, indent=2))
        finally:
            browser.close()
    unchanged = before == provenance()
    report = {"source": source, "provenance": before, "provenance_unchanged": unchanged, "cases": cases}
    (args.output / "results.json").write_text(json.dumps(report, indent=2))
    expected_keys = {f"{locale}-{scheme}-{width}-{'js' if javascript else 'nojs'}" for locale, scheme, width, javascript in matrix}
    if not unchanged or len(cases) != len(expected_keys) or {case["key"] for case in cases} != expected_keys or not all(case["passed"] for case in cases):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
