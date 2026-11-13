"""Private-first onboarding through real authenticated Laravel/Livewire forms."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, action, application, login, row


LABELS = {
    "en": {
        "entry": "Getting started", "title": "Your first wishlist, step by step",
        "create": "Create a wishlist", "add": "Add your first gift",
        "privacy": "Choose who can see it", "open": "Open your wishlist",
        "review": "Review privacy and sharing", "back": "Back to dashboard",
        "private_first": "New wishlists start private. You decide if and when to share.",
        "private": "Private: only you can open the list through ordinary sharing.",
        "optional": "Keeping your list private is a valid choice.",
        "consent": "Secret Santa wishlist consent is separate and is managed in each event.",
        "add_item": "Add Item", "added": "Gift idea added",
    },
    "pt_BR": {
        "entry": "Primeiros passos", "title": "Sua primeira lista, passo a passo",
        "create": "Criar uma lista", "add": "Adicione seu primeiro presente",
        "privacy": "Escolha quem pode ver", "open": "Abrir sua lista",
        "review": "Revisar privacidade e compartilhamento", "back": "Voltar ao painel",
        "private_first": "Novas listas começam privadas. Você decide se e quando compartilhar.",
        "private": "Privada: só você pode abrir a lista pelo compartilhamento comum.",
        "optional": "Manter sua lista privada é uma escolha válida.",
        "consent": "O consentimento de lista no amigo secreto é separado e gerenciado em cada evento.",
        "add_item": "Adicionar Item", "added": "Ideia de presente adicionada",
    },
}


def journey(browser, locale, viewport, output):
    errors = []
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        contexts = []
        try:
            context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
            contexts.append(context)
            context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
            page = context.new_page()
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.on("response", lambda response: errors.append(f"HTTP {response.status}") if response.status >= 500 else None)
            labels = LABELS[locale]
            login(page, origin, "newcomer@example.test", password)
            newcomer = row(database, "select id from users where email = ?", ("newcomer@example.test",))[0]
            guide = page.get_by_test_id("getting-started")

            def reopen():
                # The existing brand link remains reachable in the mobile shell.
                page.locator(f'nav a[href="{origin}/dashboard"]').first.click()
                page.wait_for_url("**/dashboard")
                entry = page.get_by_role("link", name=labels["entry"], exact=True)
                entry.focus()
                entry.press("Enter")
                page.wait_for_url("**/getting-started")
                expect(guide.get_by_role("heading", name=labels["title"], exact=True)).to_be_visible()

            def capture(name, stage):
                expect(guide).to_have_attribute("data-step", stage)
                expect(guide.locator('li[aria-current="step"]')).to_have_count(1)
                expect(guide.locator('li[aria-current="step"] h2')).to_have_text(labels[stage])
                for key in ("create", "add", "privacy"):
                    expect(guide.get_by_role("heading", name=labels[key], exact=True)).to_be_visible()
                expect(guide.get_by_text(labels["optional"], exact=True)).to_be_visible()
                expect(guide.get_by_text(labels["consent"], exact=False)).to_be_visible()
                content = page.content()
                for private in fixture["private"] + ["bought_by_user_id", "target_user_id", "deduplication_key", "My browser gift"]:
                    assert private not in content, "Guide disclosed private fixture or item metadata"
                widths = page.evaluate("({content: document.documentElement.scrollWidth, viewport: innerWidth})")
                assert widths["content"] <= widths["viewport"] + 1, f"{name}: horizontal overflow"
                for link in guide.get_by_role("link").all():
                    box = link.bounding_box()
                    assert box and box["x"] >= 0 and box["x"] + box["width"] <= viewport["width"] + 1, "Guide control outside viewport"
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                assert not errors, f"Browser errors: {errors}"

            reopen()
            expect(guide.get_by_text(labels["private_first"], exact=True)).to_be_visible()
            capture("01-create", "create")
            # Leaving an untouched guide is allowed; no completion flag or forced setup.
            guide.get_by_role("link", name=labels["back"], exact=True).click()
            page.wait_for_url("**/dashboard")
            assert row(database, "select count(*) from wishlists where user_id = ?", (newcomer,)) == (0,)
            reopen()
            guide.get_by_role("link", name=labels["create"], exact=True).click()
            page.wait_for_url("**/wishlists/create")
            form = page.locator('form[wire\\:submit="create"]')
            expect(form.locator("#privacy")).to_have_value("PRIVATE")
            form.locator("#name").fill("My birthday ideas")
            action(page, form.locator('button[type="submit"]'), "create")
            page.wait_for_url("**/wishlists/*")
            expect(page.get_by_role("heading", name="My birthday ideas", exact=True)).to_be_visible()
            wishlist = row(database, "select id, privacy from wishlists where user_id = ?", (newcomer,))
            assert wishlist and wishlist[1] == "PRIVATE"
            list_id = wishlist[0]
            reopen()
            expect(guide.get_by_test_id("onboarding-wishlist")).to_have_text("My birthday ideas")
            capture("02-add", "add")
            guide.get_by_role("link", name=labels["open"], exact=True).click()
            page.wait_for_url(f"**/wishlists/{list_id}")
            page.get_by_role("button", name=labels["add_item"], exact=True).click()
            form = page.locator('form[wire\\:submit="save"]')
            expect(form.locator("#item-name")).to_be_visible()
            form.locator("#item-name").fill("My browser gift")
            action(page, form.locator('button[type="submit"]'), "save")
            expect(form.locator("#item-name")).to_have_value("")
            assert row(database, "select name, status, url, price_min, price_max from wishlist_items where wishlist_id = ?", (list_id,)) == ("My browser gift", "AVAILABLE", None, None, None)
            # This guide resumes after navigation, not through a new cross-component event.
            reopen()
            expect(guide.get_by_text(labels["added"], exact=True)).to_be_visible()
            expect(guide.get_by_test_id("onboarding-privacy")).to_have_text(labels["private"])
            capture("03-privacy", "privacy")
            guide.get_by_role("link", name=labels["review"], exact=True).click()
            page.wait_for_url(f"**/wishlists/{list_id}/settings")
            expect(page.locator("#privacy")).to_have_value("PRIVATE")
            reopen()
            expect(guide).to_have_attribute("data-step", "privacy")
            guide.get_by_role("link", name=labels["back"], exact=True).click()
            page.wait_for_url("**/dashboard")
            expect(page.get_by_role("link", name="My birthday ideas", exact=True)).to_be_visible()
            assert row(database, "select privacy from wishlists where id = ?", (list_id,)) == ("PRIVATE",)
            assert row(database, "select count(*) from wishlist_shares where wishlist_id = ?", (list_id,)) == (0,)
            page.screenshot(path=str(output / "04-dashboard.png"), full_page=True, animations="disabled")
            # A copied link still requires authorization, even for another signed-in account.
            outsider = browser.new_context(viewport=viewport)
            contexts.append(outsider)
            outsider.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
            other = outsider.new_page()
            login(other, origin, "buyer@example.test", password)
            assert other.goto(f"{origin}/wishlists/{list_id}").status == 403
            assert "My birthday ideas" not in other.content()
            assert not errors, f"Browser errors: {errors}"
        finally:
            for context in contexts:
                context.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    source = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    dirty = bool(subprocess.check_output(["git", "status", "--porcelain"], cwd=ROOT))
    manifest = hashlib.sha256((ROOT / "public/build/manifest.json").read_bytes()).hexdigest()
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
                    (args.output / "results.json").write_text(json.dumps({"source": source, "worktree_dirty": dirty, "asset_manifest_sha256": manifest, "cases": results}, indent=2))
        finally:
            browser.close()
    if len(results) != 4 or not all(case["passed"] for case in results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
