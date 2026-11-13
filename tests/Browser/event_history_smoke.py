"""Real Laravel/Livewire lifecycle smoke on disposable SQLite, never application data."""
import argparse
import base64
from contextlib import contextmanager
import json
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

ROOT = Path(__file__).resolve().parents[2]


def call_response(response, method):
    if response.request.method != "POST":
        return False
    try:
        payload = response.request.post_data_json or {}
        return any(call.get("method") == method
                   for component in payload.get("components", [])
                   for call in component.get("calls", []))
    except (ValueError, TypeError):
        return False


def action(page, locator, method, confirmation=None):
    if confirmation is not None:
        page.once("dialog", lambda dialog: dialog.accept() if confirmation else dialog.dismiss())
    if confirmation is False:
        locator.click()
        return
    with page.expect_response(lambda response: call_response(response, method)) as pending:
        locator.click()
    assert pending.value.status == 200, f"{method}: HTTP {pending.value.status}"


def row(database, sql, args):
    with sqlite3.connect(f"file:{database}?mode=ro", uri=True) as connection:
        return connection.execute(sql, args).fetchone()


@contextmanager
def application(locale, seed_script="seed-event-history.php"):
    if seed_script not in ("seed-event-history.php", "seed-dashboard.php", "seed-newsletter.php"):
        raise ValueError("Unknown browser fixture")
    # Array cache is request-local: newsletter HTTP throttling needs a real shared store.
    cache_store = "database" if seed_script == "seed-newsletter.php" else "array"
    scratch = os.environ.get("RUNNER_TEMP") or os.environ.get("TMPDIR")
    if not scratch:
        raise RuntimeError("Set RUNNER_TEMP or TMPDIR to task-owned scratch storage")
    with tempfile.TemporaryDirectory(prefix="regalu-event-browser-", dir=scratch) as temporary:
        directory = Path(temporary)
        database = directory / "browser.sqlite"
        database.touch(mode=0o600)
        with socket.socket() as probe:
            probe.bind(("127.0.0.1", 0))
            port = probe.getsockname()[1]
        origin = f"http://127.0.0.1:{port}"
        password = secrets.token_urlsafe(32)
        env = dict(os.environ, APP_ENV="testing", APP_DEBUG="false", APP_URL=origin,
                   APP_KEY="base64:" + base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_LOCALE=locale, APP_FALLBACK_LOCALE="en", DB_URL="", DB_CONNECTION="sqlite",
                   DB_DATABASE=str(database), CACHE_STORE=cache_store, CACHE_LIMITER=cache_store,
                   DB_CACHE_CONNECTION="sqlite", DB_CACHE_TABLE="cache",
                   DB_CACHE_LOCK_CONNECTION="sqlite", DB_CACHE_LOCK_TABLE="cache_locks",
                   CACHE_PREFIX="browser_" + secrets.token_hex(8) + "_",
                   MAIL_MAILER="array", QUEUE_CONNECTION="sync", SESSION_DRIVER="database",
                   SESSION_CONNECTION="sqlite", SESSION_DOMAIN="null", SESSION_SECURE_COOKIE="false",
                   SESSION_COOKIE="regalu_browser_" + secrets.token_hex(8),
                   REGALU_BROWSER_DIRECTORY=temporary, REGALU_BROWSER_PASSWORD=password,
                   APP_CONFIG_CACHE=str(directory / "config.php"), APP_ROUTES_CACHE=str(directory / "routes.php"),
                   APP_SERVICES_CACHE=str(directory / "services.php"), APP_PACKAGES_CACHE=str(directory / "packages.php"),
                   VIEW_COMPILED_PATH=str(directory / "views"), LOG_CHANNEL="stderr")
        (directory / "views").mkdir()

        seed = subprocess.run(["php", str(ROOT / "tests/Browser" / seed_script)],
                              cwd=ROOT, env=env, capture_output=True, text=True, timeout=60)
        if seed.returncode:
            # Do not print environment, database contents, or bootstrap exception traces.
            raise RuntimeError(f"Fixture seed failed (exit {seed.returncode})")
        fixture = json.loads(seed.stdout)
        router = ROOT / "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
        with (directory / "server.log").open("w") as log:
            server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", str(router)],
                                      cwd=ROOT / "public", env=env, stdout=log, stderr=log)
            try:
                for _ in range(100):
                    if server.poll() is not None:
                        raise RuntimeError("Browser server exited before readiness")
                    try:
                        with urllib.request.urlopen(origin + "/login", timeout=1) as response:
                            if response.status == 200:
                                break
                    except (OSError, urllib.error.URLError):
                        time.sleep(0.1)
                else:
                    raise RuntimeError("Browser server did not become ready")
                yield origin, password, fixture, database
            finally:
                if server.poll() is None:
                    server.terminate()
                    try:
                        server.wait(timeout=10)
                    except subprocess.TimeoutExpired:
                        server.kill()
                        server.wait(timeout=10)
                with socket.socket() as check:
                    assert check.connect_ex(("127.0.0.1", port)) != 0, "Browser server leaked its listener"


def login(page, origin, email, password):
    assert page.goto(origin + "/login").status == 200
    form = page.locator('form[wire\\:submit="login"]')
    expect(form).to_be_visible()
    form.locator('input[type="email"]').fill(email)
    form.locator('input[type="password"]').fill(password)
    action(page, form.locator('button[type="submit"]'), "login")
    page.wait_for_url("**/dashboard")


def journey(browser, locale, viewport, output):
    errors = []
    with application(locale) as (origin, password, fixture, database):
        contexts = []
        try:
            pages = []
            for email in ("host@example.test", "member@example.test"):
                context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
                # No third-party requests, mail, or data leave the synthetic fixture.
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
                contexts.append(context)
                page = context.new_page()
                page.on("pageerror", lambda error: errors.append(str(error)))
                login(page, origin, email, password)
                pages.append(page)
            host, member = pages
            detail = f"{origin}/secret-santa/{fixture['event']}"
            history = detail + "/history"
            copy = fixture["copy"]

            def capture(page, name):
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                widths = page.evaluate("({content: document.documentElement.scrollWidth, viewport: window.innerWidth})")
                assert widths["content"] <= widths["viewport"] + 1, f"{name}: horizontal overflow {widths}"
                assert not errors, f"JavaScript errors: {errors}"

            assert host.goto(detail + "/assignment").status == 200
            expect(host.get_by_text("Private browser gift", exact=True)).to_be_visible()
            assert member.goto(detail).status == 200
            expect(member.locator("#wishlist_id")).to_be_visible()
            assert host.goto(detail).status == 200
            complete = host.get_by_role("button", name=copy["complete"], exact=True)
            expect(complete).to_be_visible()
            capture(host, "before-completion")
            action(host, complete, "complete", confirmation=False)
            assert row(database, "select status from amigo_secreto_events where id = ?", (fixture["event"],)) == ("REVEALED",)
            action(host, complete, "complete", confirmation=True)
            archive = host.get_by_role("button", name=copy["archive"], exact=True)
            expect(archive).to_be_visible()
            expect(complete).to_have_count(0)
            expect(host.get_by_text(copy["closed"], exact=True)).to_be_visible()
            assert row(database, "select status from amigo_secreto_events where id = ?", (fixture["event"],)) == ("COMPLETED",)
            assert fixture["code"] not in host.content()
            capture(host, "completed")
            assert host.goto(detail + "/assignment").status == 200
            expect(host.get_by_text(copy["closed"], exact=True)).to_be_visible()
            expect(host.get_by_text("Private browser gift", exact=True)).to_have_count(0)
            assert row(database, "select status from wishlist_items where id = ?", (fixture["item"],)) == ("AVAILABLE",)

            member.reload()
            expect(member.locator("#wishlist_id")).to_have_count(0)
            revoke = member.get_by_role("button", name=copy["revoke"], exact=True)
            expect(revoke).to_be_visible()
            action(member, revoke, "revokeWishlist")
            expect(revoke).to_have_count(0)
            assert row(database, "select wishlist_id from amigo_secreto_participants where id = ?", (fixture["participant"],)) == (None,)
            capture(member, "consent-removed")

            assert host.goto(history).status == 200
            expect(host.get_by_role("heading", name=fixture["name"], exact=True)).to_be_visible()
            expect(host.get_by_text(copy["privacy"], exact=True)).to_be_visible()
            content = host.content()
            for private in (fixture["code"], "Private browser gift", "Private browser wishlist", "member@example.test", "Browser Member"):
                assert private not in content, f"History disclosed {private}"
            capture(host, "history")
            host.get_by_role("link", name=copy["details"], exact=True).click()
            expect(host.get_by_role("button", name=copy["archive"], exact=True)).to_be_visible()
            action(host, host.get_by_role("button", name=copy["archive"], exact=True), "archive", confirmation=True)
            host.wait_for_url("**/secret-santa?period=past")
            expect(host.get_by_role("button", name=copy["past"], exact=True)).to_have_attribute("aria-pressed", "true")
            assert row(database, "select is_archived from amigo_secreto_events where id = ?", (fixture["event"],)) == (1,)
            expect(host.locator('a[href$="/history"]')).to_have_count(12)
            capture(host, "past-events")
            action(host, host.locator('button[wire\\:click^="nextPage"]:visible').first, "nextPage")
            expect(host.locator('a[href$="/history"]')).to_have_count(2)
            action(host, host.get_by_role("button", name=copy["active"], exact=True), "$set")
            expect(host.get_by_text("Next exchange", exact=True)).to_be_visible()
            expect(host.get_by_role("button", name=copy["active"], exact=True)).to_have_attribute("aria-pressed", "true")
            expect(host.get_by_text(fixture["name"], exact=True)).to_have_count(0)
            for page in pages:
                assert page.goto(history).status == 200
                expect(page.get_by_text(copy["archived"], exact=True)).to_be_visible()
                expect(page.get_by_role("link", name=copy["details"], exact=True)).to_have_count(0)
                for closed in (detail, detail + "/assignment"):
                    assert page.goto(closed).status == 403
            assert not errors, f"JavaScript errors: {errors}"
        finally:
            for context in contexts:
                context.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    source = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    results = []
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale in ("en", "pt_BR"):
                for size, viewport in (("desktop", {"width": 1280, "height": 900}), ("mobile", {"width": 390, "height": 844})):
                    case = {"locale": locale, "viewport": size, "passed": False}
                    output = args.output / f"{locale}-{size}"
                    output.mkdir(exist_ok=True)
                    try:
                        journey(browser, locale, viewport, output)
                        case["passed"] = True
                    except Exception as error:
                        case["error"] = str(error)
                    results.append(case)
                    print(json.dumps(case), flush=True)
                    (args.output / "results.json").write_text(json.dumps({"source": source, "cases": results}, indent=2))
        finally:
            browser.close()
    if len(results) != 4 or not all(case["passed"] for case in results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
