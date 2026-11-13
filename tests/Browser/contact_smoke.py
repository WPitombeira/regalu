"""Contact availability presentation on disposable Laravel fixtures, not delivery."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
from urllib.parse import urlsplit

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, application, row


COPY = {
    "en": {
        "heading": "Contact",
        "notice": "Contact delivery is unavailable. Please do not enter a message; this form cannot send it.",
        "name": "Your name (optional)", "email": "Your email (optional)", "message": "Your message",
        "button": "Sending unavailable",
    },
    "pt_BR": {
        "heading": "Contato",
        "notice": "O envio de mensagens está indisponível. Não preencha uma mensagem; este formulário não pode enviá-la.",
        "name": "Seu nome (opcional)", "email": "Seu e-mail (opcional)", "message": "Sua mensagem",
        "button": "Envio indisponível",
    },
}


def provenance():
    def git(*args):
        return subprocess.check_output(["git", *args], cwd=ROOT).decode().strip()

    paths = sorted(set(git("ls-files", "-z", "--cached", "--others", "--exclude-standard").strip("\0").split("\0")))
    sources = {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in paths}
    build = ROOT / "public/build"
    manifest = build / "manifest.json"
    assets = {"manifest.json"}
    for entry in json.loads(manifest.read_text()).values():
        assets.add(entry["file"])
        assets.update(entry.get("css", []))
        assets.update(entry.get("assets", []))
    assert not (ROOT / "public/hot").exists(), "A production asset build is required"
    assert all((build / path).resolve().is_relative_to(build.resolve()) for path in assets)
    return {
        "source": git("rev-parse", "HEAD"), "tree": git("rev-parse", "HEAD^{tree}"),
        "worktree_dirty": bool(git("status", "--porcelain")), "source_sha256": sources,
        "asset_sha256": {path: hashlib.sha256((build / path).read_bytes()).hexdigest() for path in sorted(assets)},
    }


def domain_counts(database):
    return {table: row(database, f"select count(*) from {table}", ())[0]
            for table in ("newsletters", "notifications", "jobs")}


def inspect_case(browser, origin, database, case, output):
    copy = COPY[case["locale"]]
    context = browser.new_context(
        viewport={"width": case["width"], "height": 900}, locale=case["locale"].replace("_", "-"),
        color_scheme=case["scheme"], java_script_enabled=case["javascript"],
    )
    errors, http_errors, posts, loaded_css = [], [], [], []
    context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
    page = context.new_page()
    page.set_default_timeout(10000)
    page.on("pageerror", lambda error: errors.append(type(error).__name__))
    page.on("request", lambda request: posts.append(urlsplit(request.url).path) if request.method == "POST" else None)

    def response_seen(response):
        if response.url.startswith(origin + "/"):
            if response.status >= 400:
                http_errors.append({"path": urlsplit(response.url).path, "status": response.status})
            if response.request.resource_type == "stylesheet" and response.status == 200:
                loaded_css.append(urlsplit(response.url).path)

    page.on("response", response_seen)
    try:
        case["stage"] = "public navigation"
        before = domain_counts(database)
        assert page.goto(origin + "/").status == 200
        page.wait_for_load_state("networkidle")
        section = page.locator("#contact")
        expect(section).to_have_count(1)
        expect(section).to_have_attribute("aria-labelledby", "contact-heading")
        expect(section.get_by_role("heading", name=copy["heading"], exact=True)).to_be_visible()
        expect(section.locator("#contact-unavailable")).to_have_text(copy["notice"])
        expect(section.locator("form")).to_have_attribute("aria-describedby", "contact-unavailable")
        expect(section.locator("fieldset[disabled]")).to_have_count(1)
        for field in ("name", "email", "message"):
            control = section.get_by_label(copy[field], exact=True)
            expect(control).to_have_count(1)
            expect(control).to_have_attribute("id", "feedback_" + field)
            expect(control).to_be_visible()
            expect(control).to_be_disabled()
            expect(control).to_have_value("")
            expect(section.locator(f'label[for="feedback_{field}"]')).to_be_visible()
        button = section.get_by_role("button", name=copy["button"], exact=True)
        expect(button).to_be_disabled()
        expect(section.get_by_role("alert")).to_have_count(0)
        assert any(path.startswith("/build/") for path in loaded_css), "Built CSS was not loaded"

        case["stage"] = "contact geometry"
        section.scroll_into_view_if_needed()
        geometry = section.evaluate("""section => {
            const rect = element => {
                const r = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return {left:r.left, right:r.right, top:r.top, bottom:r.bottom, width:r.width, height:r.height,
                    color:style.color, background:style.backgroundColor};
            };
            const selectors = ['#contact-heading', '#contact-unavailable', 'form',
                'label[for="feedback_name"]', '#feedback_name', 'label[for="feedback_email"]', '#feedback_email',
                'label[for="feedback_message"]', '#feedback_message', 'button'];
            return {section:rect(section), viewport:innerWidth, contactScroll:section.scrollWidth,
                contactClient:section.clientWidth, pageScroll:document.documentElement.scrollWidth,
                nodes:Object.fromEntries(selectors.map(selector => [selector, rect(section.querySelector(selector))]))};
        }""")
        case["geometry"] = geometry
        # Record and fail at this checkpoint; do not hide local overflow with a later summary.
        output.with_suffix(".json").write_text(json.dumps(case, indent=2))
        section.screenshot(path=str(output.with_suffix(".png")), animations="disabled")
        case["screenshot"] = output.with_suffix(".png").name
        bounds, nodes = geometry["section"], geometry["nodes"]
        assert bounds["left"] >= -1 and bounds["right"] <= geometry["viewport"] + 1
        assert geometry["contactScroll"] <= geometry["contactClient"] + 1
        for node in nodes.values():
            assert node["width"] > 0 and node["height"] > 0
            assert node["left"] >= bounds["left"] - 1 and node["right"] <= bounds["right"] + 1
            assert node["top"] >= bounds["top"] - 1 and node["bottom"] <= bounds["bottom"] + 1
        assert nodes["#contact-heading"]["bottom"] <= nodes["#contact-unavailable"]["top"]
        assert nodes["#contact-unavailable"]["bottom"] <= nodes["form"]["top"]
        previous = nodes["#contact-unavailable"]["bottom"]
        for field in ("name", "email", "message"):
            label, control = nodes[f'label[for="feedback_{field}"]'], nodes["#feedback_" + field]
            assert previous <= label["top"] and label["bottom"] <= control["top"]
            previous = control["bottom"]
        assert previous <= nodes["button"]["top"] and nodes["button"]["height"] >= 44
        case["page_overflow_outside_scope"] = geometry["pageScroll"] > geometry["viewport"] + 1

        case["stage"] = "native disabled interaction"
        button.scroll_into_view_if_needed()
        box = button.bounding_box()
        assert box is not None
        page.mouse.click(box["x"] + box["width"] / 2, box["y"] + box["height"] / 2)
        # Bounded negative observation, not a claim about arbitrary future requests.
        page.wait_for_timeout(350)
        case["post_observation_ms"] = 350
        case["posts"] = posts
        case["domain_counts_before"] = before
        case["domain_counts_after"] = domain_counts(database)
        case["javascript_errors"] = errors
        case["http_errors"] = http_errors
        case["loaded_css"] = loaded_css
        assert not posts and not errors and not http_errors
        assert case["domain_counts_after"] == before
        expect(button).to_be_disabled()
        case["stage"] = "complete"
        case["passed"] = True
    except Exception as error:
        # Do not publish response bodies, snapshots, credentials, or bootstrap traces.
        case["error_type"] = type(error).__name__
    finally:
        context.close()
        output.with_suffix(".json").write_text(json.dumps(case, indent=2))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    identity = provenance()
    report = {**identity, "cases": [], "identity_unchanged": False}
    matrix = [(locale, scheme, width, True) for locale in COPY for scheme in ("light", "dark") for width in (320, 390, 1280)]
    matrix += [(locale, "dark", 390, False) for locale in COPY]
    report["expected_cases"] = len(matrix)
    report_path = args.output / "results.json"
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for locale in COPY:
                with application(locale, "seed-dashboard.php") as (origin, _password, _fixture, database):
                    for language, scheme, width, javascript in matrix:
                        if language != locale:
                            continue
                        case = {"locale": locale, "scheme": scheme, "width": width, "javascript": javascript, "passed": False}
                        name = f"{locale}-{scheme}-{width}-{'js' if javascript else 'nojs'}"
                        inspect_case(browser, origin, database, case, args.output / name)
                        report["cases"].append(case)
                        report_path.write_text(json.dumps(report, indent=2))
                        print(json.dumps({key: case[key] for key in ("locale", "scheme", "width", "javascript", "passed", "stage")}), flush=True)
        finally:
            browser.close()
            report["identity_unchanged"] = provenance() == identity
            report_path.write_text(json.dumps(report, indent=2))
    if not report["identity_unchanged"] or len(report["cases"]) != len(matrix) or not all(case["passed"] for case in report["cases"]):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
