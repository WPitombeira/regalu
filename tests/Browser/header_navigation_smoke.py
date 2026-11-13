"""Primary navigation on the real Laravel fixture; no external accounts or services."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess

from playwright.sync_api import expect, sync_playwright

from event_history_smoke import application, login

ROOT = Path(__file__).resolve().parents[2]
VIEWPORTS = {
    "desktop": {"width": 1280, "height": 900},
    "tablet": {"width": 768, "height": 1024},
    "wide-tablet": {"width": 1024, "height": 768},
    "mobile": {"width": 390, "height": 844},
    "narrow": {"width": 360, "height": 800},
}


def journey(browser, locale, viewport, output):
    errors = []
    geometry = []
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
        context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
        page = context.new_page()
        page.on("pageerror", lambda error: errors.append(str(error)))
        page.on("response", lambda response: errors.append(f"HTTP {response.status}") if response.status >= 500 else None)
        try:
            response = page.goto(origin + "/")
            assert response.status == 200, f"Guest entry returned HTTP {response.status}"
            trigger = page.locator('button[aria-controls="navbar-user"]')
            panel = page.locator("#navbar-user")

            def capture(name, controls):
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                measurements = page.evaluate("({width: innerWidth, content: document.documentElement.scrollWidth})")
                boxes = []
                for control in controls:
                    expect(control).to_be_visible()
                    boxes.append(control.bounding_box())
                header_boxes = page.locator("nav > div > div > *:visible").evaluate_all("elements => elements.map(el => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; })")
                geometry.append({"checkpoint": name, **measurements, "boxes": boxes, "header_boxes": header_boxes})
                (output / "geometry.json").write_text(json.dumps(geometry, indent=2) + "\n")
                assert measurements["content"] <= measurements["width"] + 1, f"{name}: page overflow {measurements}"
                for box in boxes:
                    assert box and box["x"] >= -1 and box["x"] + box["width"] <= measurements["width"] + 1, f"{name}: off-screen control {box}"
                for index, box in enumerate(header_boxes):
                    assert box["x"] >= -1 and box["x"] + box["width"] <= measurements["width"] + 1, f"{name}: off-screen header {box}"
                    for other in header_boxes[index + 1:]:
                        overlap_x = min(box["x"] + box["width"], other["x"] + other["width"]) - max(box["x"], other["x"])
                        overlap_y = min(box["y"] + box["height"], other["y"] + other["height"]) - max(box["y"], other["y"])
                        assert overlap_x <= 1 or overlap_y <= 1, f"{name}: overlapping header controls {box}, {other}"
                assert not errors, f"Browser errors: {errors}"

            def disclosure(audience):
                expect(panel).to_have_count(1)
                links = panel.get_by_role("link")
                if viewport["width"] < 768:
                    expect(trigger).to_be_visible()
                    expect(panel).to_be_hidden()
                    capture(f"{audience}-closed", [trigger])
                    trigger.focus()
                    trigger.press("Enter")
                    expect(trigger).to_have_attribute("aria-expanded", "true")
                    expect(panel).to_be_visible()
                    expect(links).to_have_count(4)
                    capture(f"{audience}-open", [trigger, panel, *links.all()])
                    trigger.press("Tab")
                    expect(links.nth(0)).to_be_focused()
                    links.nth(0).press("Escape")
                    expect(panel).to_be_hidden()
                    expect(trigger).to_have_attribute("aria-expanded", "false")
                    expect(trigger).to_be_focused()
                    trigger.press("Space")
                    expect(panel).to_be_visible()
                    page.locator("h1").first.click()
                    expect(panel).to_be_hidden()
                    expect(trigger).to_have_attribute("aria-expanded", "false")
                    trigger.focus()
                    trigger.press("Space")
                    expect(panel).to_be_visible()
                    links.last.focus()
                    links.last.press("Tab")
                    expect(panel).to_be_hidden()
                    expect(trigger).to_have_attribute("aria-expanded", "false")
                    assert not page.evaluate("document.querySelector('nav').contains(document.activeElement)")
                    trigger.click()
                    expect(panel).to_be_visible()
                    trigger.press("Tab")
                    expect(links.first).to_be_focused()
                    page.set_viewport_size(VIEWPORTS["desktop"])
                    expect(trigger).to_be_hidden()
                    expect(panel).to_be_visible()
                    expect(trigger).to_have_attribute("aria-expanded", "false")
                    page.set_viewport_size(viewport)
                    expect(trigger).to_be_visible()
                    expect(panel).to_be_hidden()
                else:
                    expect(trigger).to_be_hidden()
                    expect(panel).to_be_visible()
                    expect(links).to_have_count(4)
                    capture(f"{audience}-desktop", [panel, *links.all()])

            sign_in = page.locator("nav").get_by_role("link", name="Entrar" if locale == "pt_BR" else "Login", exact=True)
            expect(sign_in).to_be_visible()
            capture("guest-entry", [sign_in])
            disclosure("guest")
            sign_in.click()
            page.wait_for_url(origin + "/login")
            expect(page.locator('form[wire\\:submit="login"]')).to_be_visible()
            login(page, origin, "newcomer@example.test", password)
            disclosure("authenticated")
            destinations = ["/families", "/wishlists", "/secret-santa", "/dashboard"]
            for destination in destinations:
                if viewport["width"] < 768:
                    trigger.click()
                    expect(panel).to_be_visible()
                link = panel.locator(f'a[href="{origin}{destination}"], a[href="{destination}"]')
                expect(link).to_have_count(1)
                with page.expect_response(lambda response: response.url == origin + destination and response.request.is_navigation_request()) as pending:
                    link.click()
                assert pending.value.status == 200, f"{destination}: HTTP {pending.value.status}"
                page.wait_for_url(origin + destination)
                expect(page.locator("main h1")).to_be_visible()
                if viewport["width"] < 768:
                    expect(panel).to_be_hidden()
                    expect(trigger).to_have_attribute("aria-expanded", "false")
                    capture(destination.strip("/") + "-destination", [trigger])
                else:
                    capture(destination.strip("/") + "-destination", [panel, *panel.get_by_role("link").all()])
            return {"passed": True, "geometry_checkpoints": len(geometry), "destinations": destinations, "browser_errors": errors}
        finally:
            context.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--locale", choices=["en", "pt_BR"])
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
            for locale in ([args.locale] if args.locale else ["en", "pt_BR"]):
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
    expected = (1 if args.locale else 2) * (1 if args.viewport else len(VIEWPORTS))
    assert len(results) == expected and all(result["passed"] for result in results)
    print(json.dumps({"passed": True, "cases": len(results), "source": source}))


if __name__ == "__main__":
    main()
