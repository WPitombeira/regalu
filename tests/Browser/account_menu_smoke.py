"""Account disclosure keyboard/viewport checks on the real disposable Laravel fixture."""
import argparse
import hashlib
import json
from pathlib import Path
import sqlite3
import subprocess

from playwright.sync_api import expect, sync_playwright

from event_history_smoke import application, login

ROOT = Path(__file__).resolve().parents[2]
COPY = {
    "en": {"trigger": "Account menu", "settings": "Settings", "logout": "Sign out"},
    "pt_BR": {"trigger": "Menu da conta", "settings": "Configurações", "logout": "Sair"},
}
VIEWPORTS = {
    "desktop": {"width": 1280, "height": 900},
    "mobile": {"width": 390, "height": 844},
    "narrow": {"width": 360, "height": 800},
}


def journey(browser, locale, viewport, output):
    errors = []
    geometry = []
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
        # Only synthetic local data; never request a third-party avatar or send mail.
        context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
        page = context.new_page()
        page.on("pageerror", lambda error: errors.append(str(error)))
        try:
            login(page, origin, "newcomer@example.test", password)
            trigger = page.get_by_role("button", name=COPY[locale]["trigger"], exact=True)
            panel = page.locator("#user-menu-panel")

            def capture(name, controls):
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                measurements = page.evaluate("({width: innerWidth, content: document.documentElement.scrollWidth})")
                boxes = []
                for control in controls:
                    expect(control).to_be_visible()
                    boxes.append(control.bounding_box())
                geometry.append({"checkpoint": name, **measurements, "boxes": boxes})
                (output / "geometry.json").write_text(json.dumps(geometry, indent=2) + "\n")
                assert measurements["content"] <= measurements["width"] + 1, f"{name}: page overflow {measurements}"
                for box in boxes:
                    assert box and box["x"] >= -1 and box["x"] + box["width"] <= measurements["width"] + 1, f"{name}: control outside viewport {box}"
                assert not errors, f"JavaScript errors: {errors}"

            expect(trigger).to_have_attribute("aria-expanded", "false")
            expect(panel).to_be_hidden()
            expect(trigger.locator("svg[aria-hidden=true]")).to_be_visible()
            expect(trigger.locator("img")).to_have_count(0)
            capture("closed", [trigger])
            trigger.focus()
            trigger.press("Enter")
            expect(panel).to_be_visible()
            capture("keyboard-open", [trigger, panel])
            expect(trigger).to_have_attribute("aria-expanded", "true")
            expect(trigger).to_be_focused()
            trigger.press("Tab")
            dashboard = panel.get_by_role("link", name="Dashboard", exact=True)
            settings = panel.get_by_role("link", name=COPY[locale]["settings"], exact=True)
            logout = panel.get_by_role("button", name=COPY[locale]["logout"], exact=True)
            expect(dashboard).to_be_focused()
            dashboard.press("Tab")
            expect(settings).to_be_focused()
            settings.press("Escape")
            expect(panel).to_be_hidden()
            expect(trigger).to_have_attribute("aria-expanded", "false")
            expect(trigger).to_be_focused()
            trigger.press("Space")
            expect(panel).to_be_visible()
            expect(trigger).to_have_attribute("aria-expanded", "true")
            trigger.press("Tab")
            dashboard.press("Tab")
            settings.press("Tab")
            expect(logout).to_be_focused()
            logout.press("Tab")
            expect(panel).to_be_hidden()
            expect(trigger).to_have_attribute("aria-expanded", "false")
            assert not panel.evaluate("element => element.contains(document.activeElement)")
            trigger.click()
            expect(panel).to_be_visible()
            page.locator("main").click(position={"x": 5, "y": 5})
            expect(panel).to_be_hidden()
            expect(trigger).to_have_attribute("aria-expanded", "false")
            trigger.click()
            expect(panel).to_be_visible()
            capture("actions", [trigger, panel, settings, logout])

            # Presentation-only fixtures, not evidence of the avatar-upload workflow.
            display_name = "Browser " + "LongName" * 20
            for image, loaded in (("/media/logo.png", True), ("/media/missing-account-avatar.png", False)):
                with sqlite3.connect(database) as connection:
                    connection.execute("update users set avatar_url = ?, name = ? where email = ?", (image, display_name, "newcomer@example.test"))
                page.reload()
                photo = trigger.locator("img")
                expect(photo).to_have_attribute("src", image)
                page.wait_for_function("() => document.querySelector('#user-menu-button img').complete")
                if loaded:
                    expect(photo).to_be_visible()
                    assert photo.evaluate("element => element.naturalWidth > 0")
                    expect(trigger.locator("svg")).to_be_hidden()
                else:
                    expect(photo).to_be_hidden()
                    expect(trigger.locator("svg")).to_be_visible()
                trigger.click()
                expect(panel).to_be_visible()
                expect(panel.get_by_text(display_name, exact=True)).to_be_visible()
                expect(trigger).to_have_attribute("aria-expanded", "true")
                capture("loaded-avatar" if loaded else "failed-avatar", [trigger, panel])
            settings.click()
            page.wait_for_url(origin + "/settings")
            expect(page.locator("#user-menu-panel")).to_be_hidden()
            trigger = page.get_by_role("button", name=COPY[locale]["trigger"], exact=True)
            trigger.click()
            logout = page.locator("#user-menu-panel").get_by_role("button", name=COPY[locale]["logout"], exact=True)
            logout.focus()
            with page.expect_response(lambda response: response.request.method == "POST" and response.url == origin + "/logout") as pending:
                logout.press("Enter")
            assert pending.value.status == 302
            page.wait_for_url(origin + "/")
            expect(page.locator("#user-menu-button")).to_have_count(0)
            page.goto(origin + "/dashboard")
            page.wait_for_url(origin + "/login")
            assert not errors, f"JavaScript errors: {errors}"
            return {"passed": True, "geometry_checkpoints": len(geometry)}
        finally:
            context.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--locale", choices=list(COPY))
    parser.add_argument("--viewport", choices=list(VIEWPORTS))
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    source = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    dirty = bool(subprocess.check_output(["git", "status", "--porcelain"], cwd=ROOT, text=True).strip())
    manifest = hashlib.sha256((ROOT / "public/build/manifest.json").read_bytes()).hexdigest()
    results = []
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale in ([args.locale] if args.locale else COPY):
                for name in ([args.viewport] if args.viewport else VIEWPORTS):
                    output = args.output / f"{locale}-{name}"
                    output.mkdir(exist_ok=True)
                    result = {"locale": locale, "viewport": name, "passed": False}
                    results.append(result)
                    try:
                        result.update(journey(browser, locale, VIEWPORTS[name], output))
                    except Exception as error:
                        result["error"] = str(error)
                        raise
                    finally:
                        (args.output / "results.json").write_text(json.dumps({
                            "source": source, "worktree_dirty": dirty, "asset_manifest_sha256": manifest,
                            "results": results,
                        }, indent=2) + "\n")
        finally:
            browser.close()
    expected = (1 if args.locale else len(COPY)) * (1 if args.viewport else len(VIEWPORTS))
    assert len(results) == expected and all(result["passed"] for result in results)
    print(json.dumps({"passed": True, "cases": len(results), "source": source}))


if __name__ == "__main__":
    main()
