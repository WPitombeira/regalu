"""Real auth, Livewire and SQLite price-import journeys; external catalog HTTP/DNS are fixtures."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, action, application, call_response, login, row

LABELS = {
    "en": {
        "add": "Add item", "fetch": "Fetch details", "apply": "Use this price", "save": "Save",
        "currency": "Currency", "quote": "USD 29.95", "legacy": "Currency not specified 12.34",
        "fallback": "No reliable price was found. You can enter it manually.",
        "manual": "EUR 0.00 – 10.00", "zero": "BRL 0.00",
    },
    "pt_BR": {
        "add": "Adicionar item", "fetch": "Buscar detalhes", "apply": "Usar este preço", "save": "Salvar",
        "currency": "Moeda", "quote": "USD 29,95", "legacy": "Moeda não informada 12,34",
        "fallback": "Nenhum preço confiável foi encontrado. Você pode informá-lo manualmente.",
        "manual": "EUR 0,00 – 10,00", "zero": "BRL 0,00",
    },
}


def journey(browser, locale, viewport, output):
    labels = LABELS[locale]
    errors = []
    with application(locale, "seed-price-import.php") as (origin, password, fixture, database):
        contexts = []
        page = None
        try:
            def user(email):
                context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
                contexts.append(context)
                result = context.new_page()
                result.on("pageerror", lambda error: errors.append(str(error)))
                login(result, origin, email, password)
                return result

            def capture(name):
                assert page is not None
                widths = page.evaluate("({content: document.documentElement.scrollWidth, viewport: innerWidth})")
                assert widths["content"] <= widths["viewport"] + 1, f"{name}: horizontal overflow {widths}"
                assert not errors, f"JavaScript errors: {errors}"
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")

            def persisted(name, expected):
                assert row(database, "select printf('%.2f', price_min), printf('%.2f', price_max), currency from wishlist_items where wishlist_id = ? and name = ?", (fixture["wishlist"], name)) == expected

            page = user("owner@example.test")
            assert page.goto(f'{origin}/wishlists/{fixture["wishlist"]}').status == 200
            expect(page.get_by_text(labels["legacy"], exact=True)).to_be_visible()
            page.get_by_role("button", name=labels["add"], exact=True).click()
            form = page.locator('form[wire\\:submit="save"]')
            expect(form).to_be_visible()
            expect(form.get_by_label(labels["currency"], exact=True)).to_have_value("BRL")
            capture("empty-form")
            url = form.locator("#item-url")
            minimum = form.locator("#item-price-min")
            maximum = form.locator("#item-price-max")
            currency = form.locator("#item-currency")
            name = form.locator("#item-name")
            fetch = form.get_by_role("button", name=labels["fetch"], exact=True)
            save = form.get_by_role("button", name=labels["save"], exact=True)

            url.fill("https://catalog.example.com/gift")
            action(page, fetch, "fetchMeta")
            expect(form.get_by_text(labels["quote"], exact=True)).to_be_visible()
            expect(name).to_have_value("Imported gift")
            expect(minimum).to_have_value("")
            expect(maximum).to_have_value("")
            assert row(database, "select count(*) from wishlist_items where wishlist_id = ?", (fixture["wishlist"],)) == (1,)
            apply = form.get_by_role("button", name=labels["apply"], exact=True)
            expect(apply).to_be_enabled()
            capture("review-price")
            apply.focus()
            with page.expect_response(lambda response: call_response(response, "applySuggestedPrice")) as pending:
                page.keyboard.press("Enter")
            assert pending.value.status == 200
            expect(minimum).to_have_value("29.95")
            expect(maximum).to_have_value("29.95")
            expect(currency).to_have_value("USD")
            capture("applied-price")
            action(page, save, "save")
            expect(name).to_have_value("")
            expect(page.get_by_role("heading", name="Imported gift", exact=True)).to_be_visible()
            expect(page.get_by_text(labels["quote"], exact=True)).to_be_visible()
            persisted("Imported gift", ("29.95", "29.95", "USD"))
            capture("saved-price")

            name.fill("Manual range")
            form.locator("#item-description").fill("Keep my notes")
            minimum.fill("0")
            maximum.fill("10.00")
            currency.select_option("EUR")
            url.fill("https://catalog.example.com/gift")
            action(page, fetch, "fetchMeta")
            expect(name).to_have_value("Manual range")
            expect(form.locator("#item-description")).to_have_value("Keep my notes")
            expect(minimum).to_have_value("0")
            expect(maximum).to_have_value("10.00")
            expect(currency).to_have_value("EUR")
            expect(apply).to_be_disabled()
            capture("manual-price-preserved")

            # Blur is the actual invalidation boundary, not a synthetic component call.
            url.fill("https://catalog.example.com/no-price")
            url.press("Tab")
            expect(apply).to_have_count(0)
            action(page, fetch, "fetchMeta")
            expect(form.get_by_text(labels["fallback"], exact=True)).to_be_visible()
            expect(minimum).to_have_value("0")
            expect(currency).to_have_value("EUR")
            capture("manual-fallback")
            action(page, save, "save")
            expect(name).to_have_value("")
            expect(page.get_by_role("heading", name="Manual range", exact=True)).to_be_visible()
            expect(page.get_by_text(labels["manual"], exact=True)).to_be_visible()
            persisted("Manual range", ("0.00", "10.00", "EUR"))

            name.fill("Zero gift")
            minimum.fill("0")
            maximum.fill("0")
            action(page, save, "save")
            expect(name).to_have_value("")
            expect(page.get_by_text(labels["zero"], exact=True)).to_be_visible()
            persisted("Zero gift", ("0.00", "0.00", "BRL"))

            url.fill("https://catalog.example.com/ambiguous")
            action(page, fetch, "fetchMeta")
            expect(form.get_by_text(labels["fallback"], exact=True)).to_be_visible()
            expect(apply).to_have_count(0)
            expect(minimum).to_have_value("")
            expect(maximum).to_have_value("")
            action(page, save, "save")
            expect(name).to_have_value("")
            expect(page.get_by_role("heading", name="Variant gift", exact=True)).to_be_visible()
            assert row(database, "select price_min, price_max, currency from wishlist_items where name = ?", ("Variant gift",)) == (None, None, None)
            capture("saved-wishlist")

            page = user("buyer@example.test")
            assert page.goto(f'{origin}/wishlists/{fixture["wishlist"]}').status == 200
            expect(page.get_by_role("button", name=labels["add"], exact=True)).to_have_count(0)
            for key in ("quote", "manual", "zero", "legacy"):
                expect(page.get_by_text(labels[key], exact=True)).to_be_visible()
            capture("buyer-wishlist")
            assert page.goto(f'{origin}/secret-santa/{fixture["event"]}/assignment').status == 200
            for key in ("quote", "manual", "zero", "legacy"):
                expect(page.get_by_text(labels[key], exact=True)).to_be_visible()
            for private in ("Gift Owner", "owner@example.test", "My gift ideas", "target_user_id", "bought_by_user_id"):
                assert private not in page.content(), f"Anonymous recipient leak: {private}"
            capture("anonymous-assignment")
        except Exception:
            if page is not None:
                page.screenshot(path=str(output / "failure.png"), full_page=True, animations="disabled")
            raise
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
                    (args.output / "results.json").write_text(json.dumps({
                        "source": source, "worktree_dirty": dirty, "asset_manifest_sha256": manifest,
                        "external_catalog": "synthetic HTTP and DNS; real transport covered separately", "cases": results,
                    }, indent=2))
        finally:
            browser.close()
    if len(results) != 4 or not all(case["passed"] for case in results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
