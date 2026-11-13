"""Public newsletter journeys with real HTTP and disposable database-backed throttling."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import traceback

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, action, application, call_response, row

COPY = {
    "en": {
        "email": "Email address", "consent": "I want to receive Regalu product updates by email.",
        "button": "Notify Me", "invalid": "Enter a valid email address.",
        "required_consent": "Please agree to receive product updates before signing up.",
        "success": "Thanks. Your request has been received. Existing subscription preferences are unchanged.",
        "throttled": "We cannot accept another request right now. Please wait a minute and try again.",
        "contact_unavailable": "Contact messages cannot be sent right now. Your message has not been sent.",
    },
    "pt_BR": {
        "email": "Endereço de e-mail", "consent": "Quero receber novidades do Regalu por e-mail.",
        "button": "Notifique-me", "invalid": "Informe um endereço de e-mail válido.",
        "required_consent": "Concorde em receber novidades do produto antes de se inscrever.",
        "success": "Obrigado. Recebemos sua solicitação. As preferências de inscrições existentes não foram alteradas.",
        "throttled": "Não podemos aceitar outra solicitação agora. Aguarde um minuto e tente novamente.",
        "contact_unavailable": "Não é possível enviar mensagens de contato no momento. Sua mensagem não foi enviada.",
    },
}


def update_response(response, field):
    if response.request.method != "POST":
        return False
    try:
        return any(field in component.get("updates", {})
                   for component in (response.request.post_data_json or {}).get("components", []))
    except (ValueError, TypeError):
        return False


def edit_email(page, text):
    with page.expect_response(lambda response: update_response(response, "email")) as pending:
        page.locator("#newsletter-email").fill(text)
        page.locator("#newsletter-email").press("Tab")
    assert pending.value.status == 200
    expect(page.locator("#newsletter-status")).to_have_count(0)


def consent(page):
    with page.expect_response(lambda response: update_response(response, "newsletterConsent")) as pending:
        page.locator("#newsletter-consent").focus()
        page.locator("#newsletter-consent").press("Space")
    assert pending.value.status == 200
    expect(page.locator("#newsletter-consent")).to_be_checked()


def submit_server_probe(page, form):
    # Livewire morphs remove client-only attributes. Bypass native validation only
    # in the same JS task as this negative probe, never across an awaited update.
    expect(form.locator('button[type="submit"]')).to_be_enabled()
    with page.expect_response(lambda response: call_response(response, "registerNewsletter")) as pending:
        form.evaluate("""form => {
            if (form.noValidate) throw new Error('Native validation unexpectedly disabled');
            form.noValidate = true;
            try {
                form.requestSubmit(form.querySelector('button[type="submit"]'));
            } finally {
                form.noValidate = false;
            }
        }""")
    assert pending.value.status == 200
    assert not form.evaluate("form => form.noValidate")


def source_identity():
    paths = subprocess.check_output(["git", "ls-files", "-c", "-o", "--exclude-standard", "-z"], cwd=ROOT).decode().split("\0")
    files = {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest()
             for path in sorted(set(paths)) if path and (ROOT / path).is_file()}
    return {
        "source": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip(),
        "tree": subprocess.check_output(["git", "rev-parse", "HEAD^{tree}"], cwd=ROOT, text=True).strip(),
        "worktree_dirty": bool(subprocess.check_output(["git", "status", "--porcelain"], cwd=ROOT)),
        "source_files_sha256": hashlib.sha256(json.dumps(files, sort_keys=True).encode()).hexdigest(),
        "asset_manifest_sha256": hashlib.sha256((ROOT / "public/build/manifest.json").read_bytes()).hexdigest(),
    }


def journey(browser, locale, viewport, output):
    labels = COPY[locale]
    errors, geometry, requests = [], [], []
    with application(locale, "seed-newsletter.php") as (origin, _password, fixture, database):
        assert fixture["cache"] == "database"
        inactive = row(database, "select * from newsletters where email = ?", ("Inactive@Example.Test",))
        contexts = []

        def new_page():
            context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
            contexts.append(context)
            context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
            page = context.new_page()
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.on("response", lambda response: requests.append(response.status) if call_response(response, "registerNewsletter") else None)
            assert page.goto(origin + "/").status == 200
            expect(page.locator("#newsletter-email")).to_be_visible()
            return page

        def capture(page, name):
            section = page.locator("#newsletter")
            assert not section.locator("form").evaluate("form => form.noValidate")
            section.scroll_into_view_if_needed()
            widths = page.evaluate("({content: document.documentElement.scrollWidth, viewport: innerWidth})")
            boxes = section.locator('input[type="email"], label[for="newsletter-consent"], button[type="submit"]').evaluate_all(
                "els => els.map(el => {const b=el.getBoundingClientRect(); return {x:b.x, width:b.width, height:b.height}})")
            checkpoint = {"name": name, **widths, "controls": boxes}
            geometry.append(checkpoint)
            (output / "geometry.json").write_text(json.dumps(geometry, indent=2))
            assert widths["content"] <= widths["viewport"] + 1, checkpoint
            assert len(boxes) == 3 and all(b["x"] >= 0 and b["x"] + b["width"] <= viewport["width"] + 1 and b["height"] >= 44 for b in boxes), checkpoint
            assert not errors, errors
            section.screenshot(path=str(output / f"{name}.png"), animations="disabled")

        try:
            page = new_page()
            form = page.locator('#newsletter form')
            email = page.get_by_role("textbox", name=labels["email"], exact=True)
            checkbox = page.get_by_role("checkbox", name=labels["consent"], exact=True)
            submit = form.get_by_role("button", name=labels["button"], exact=True)
            expect(checkbox).not_to_be_checked()
            expect(email).to_have_attribute("autocomplete", "email")
            expect(email).to_have_attribute("maxlength", "254")
            assert not form.evaluate("form => form.checkValidity()")
            submit.click()
            expect(email).to_be_focused()
            assert requests == []
            capture(page, "initial-native-validation")

            # The separate contact form has no delivery backend: keep the draft and say so.
            contact = page.locator("#contact")
            cache_before = row(database, "select count(*) from cache", ())
            contact.get_by_placeholder("Your Name (optional)", exact=True).fill("Reader")
            contact.get_by_placeholder("Your Email (optional)", exact=True).fill("reader@example.test")
            contact.get_by_placeholder("Your Message", exact=True).fill("Please keep my message until it can be sent.")
            action(page, contact.get_by_role("button", name="Submit", exact=True), "registerFeedback")
            expect(contact.locator("#contact-error")).to_have_text(labels["contact_unavailable"])
            expect(contact.locator("#contact-error")).to_have_attribute("role", "alert")
            expect(contact.get_by_placeholder("Your Name (optional)", exact=True)).to_have_value("Reader")
            expect(contact.get_by_placeholder("Your Email (optional)", exact=True)).to_have_value("reader@example.test")
            expect(contact.get_by_placeholder("Your Message", exact=True)).to_have_value("Please keep my message until it can be sent.")
            expect(page.locator("#newsletter-status")).to_have_count(0)
            assert row(database, "select count(*) from newsletters", ()) == (1,)
            assert row(database, "select count(*) from cache", ()) == cache_before
            assert requests == [] and not errors
            contact.screenshot(path=str(output / "contact-unavailable.png"), animations="disabled")

            edit_email(page, "reader@example.test")
            # Native consent validation must still work after a Livewire update.
            submit.click()
            expect(checkbox).to_be_focused()
            assert requests == []
            submit_server_probe(page, form)
            expect(page.locator("#newsletter-consent-error")).to_have_text(labels["required_consent"])
            expect(checkbox).to_have_attribute("aria-invalid", "true")
            assert row(database, "select count(*) from newsletters", ()) == (1,)
            capture(page, "consent-required")

            edit_email(page, "not-an-email")
            consent(page)
            submit_server_probe(page, form)
            expect(page.locator("#newsletter-email-error")).to_have_text(labels["invalid"])
            expect(email).to_have_attribute("aria-invalid", "true")
            assert row(database, "select count(*) from newsletters", ()) == (1,)
            capture(page, "invalid-address")

            edit_email(page, "Reader@Example.Test")
            with page.expect_response(lambda response: call_response(response, "registerNewsletter")) as pending:
                submit.focus()
                submit.press("Enter")
            assert pending.value.status == 200
            expect(page.locator("#newsletter-status")).to_have_text(labels["success"])
            expect(email).to_have_value("")
            expect(checkbox).not_to_be_checked()
            expect(page.locator("#newsletter-status")).to_have_attribute("role", "status")
            assert row(database, "select count(*) from newsletters", ()) == (2,)
            saved = row(database, "select * from newsletters where email = ?", ("reader@example.test",))
            assert saved is not None
            assert row(database, "select active from newsletters where email = ?", ("reader@example.test",)) == (1,)
            capture(page, "accepted")

            for address, name in (("READER@example.test", "duplicate"), ("inactive@example.test", "inactive-preserved")):
                edit_email(page, address)
                consent(page)
                action(page, submit, "registerNewsletter")
                expect(page.locator("#newsletter-status")).to_have_text(labels["success"])
                assert row(database, "select count(*) from newsletters", ()) == (2,)
                assert row(database, "select * from newsletters where email = ?", ("reader@example.test",)) == saved
                assert row(database, "select * from newsletters where email = ?", ("Inactive@Example.Test",)) == inactive
                capture(page, name)

            # A fresh anonymous session must share the source-IP quota, not reset it.
            other = new_page()
            edit_email(other, "sixth@example.test")
            consent(other)
            action(other, other.locator('#newsletter button[type="submit"]'), "registerNewsletter")
            expect(other.locator("#newsletter-error")).to_have_text(labels["throttled"])
            expect(other.locator("#newsletter-status")).to_have_count(0)
            expect(other.locator("#newsletter-email")).to_have_value("sixth@example.test")
            assert row(database, "select count(*) from newsletters", ()) == (2,)
            assert row(database, "select count(*) from cache", ())[0] > 0
            assert requests == [200] * 6, requests
            capture(other, "throttled-new-session")
            assert not errors, errors
        finally:
            for context in contexts:
                context.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    identity = source_identity()
    results = []
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale in COPY:
                for size, viewport in (("desktop", {"width": 1280, "height": 900}), ("mobile", {"width": 390, "height": 844})):
                    case = {"locale": locale, "viewport": size, "passed": False}
                    output = args.output / f"{locale}-{size}"
                    output.mkdir(exist_ok=True)
                    try:
                        journey(browser, locale, viewport, output)
                        assert source_identity() == identity, "Source or assets changed during the run"
                        case["passed"] = True
                    except Exception as error:
                        case["error"] = str(error)
                        # Record only source locations, never frame locals or request data.
                        case["locations"] = [
                            {"file": str(Path(frame.filename).resolve().relative_to(ROOT)),
                             "line": frame.lineno, "function": frame.name}
                            for frame in traceback.extract_tb(error.__traceback__)
                            if Path(frame.filename).resolve().is_relative_to(ROOT)
                        ]
                    results.append(case)
                    print(json.dumps(case), flush=True)
                    (args.output / "results.json").write_text(json.dumps({**identity, "cache": "database", "cases": results}, indent=2))
        finally:
            browser.close()
    if len(results) != 4 or not all(case["passed"] for case in results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
