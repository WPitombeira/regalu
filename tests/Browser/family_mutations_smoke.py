"""Real family settings, code admission and roster actions on disposable SQLite."""
import argparse
import hashlib
import json
from pathlib import Path
import sqlite3
import subprocess

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, action, application, call_response, login, row


def journey(browser, locale, viewport, output):
    errors = []
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        family_id, old_code = row(database, "select id, invite_code from families where creator_id = ? and name = ?", (fixture["member"], "My family"))
        newcomer_id, = row(database, "select id from users where email = ?", ("newcomer@example.test",))
        # Only synthetic, run-owned fixtures: roster buttons require an ADMIN row.
        with sqlite3.connect(database) as connection:
            connection.execute("insert into family_members (family_id, user_id, role, joined_at, created_at, updated_at) values (?, ?, 'ADMIN', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)", (family_id, fixture["member"]))
            connection.execute("update users set locale = ? where id = ?", (locale, newcomer_id))
        contexts = []
        try:
            pages = []
            for email in ("member@example.test", "newcomer@example.test"):
                context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
                contexts.append(context)
                page = context.new_page()
                page.on("pageerror", lambda error: errors.append(str(error)))
                login(page, origin, email, password)
                pages.append(page)
            owner, newcomer = pages
            detail = f"{origin}/families/{family_id}"
            settings = detail + "/settings"
            membership = (family_id, newcomer_id)

            def capture(page, name):
                assert not errors, f"JavaScript errors: {errors}"
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")

            assert owner.goto(settings).status == 200
            form = owner.locator('form[wire\\:submit="update"]')
            form.locator("#name").fill("Family smoke")
            form.locator("#description").fill("Saved through the real family form")
            action(owner, form.locator('button[type="submit"]'), "update")
            assert row(database, "select name, description from families where id = ?", (family_id,)) == ("Family smoke", "Saved through the real family form")
            assert owner.goto(detail).status == 200
            expect(owner.get_by_role("heading", name="Family smoke", exact=True)).to_be_visible()
            expect(owner.get_by_text("Saved through the real family form", exact=True)).to_be_visible()
            capture(owner, "updated-family")

            assert newcomer.goto(origin + "/families/join").status == 200
            join_form = newcomer.locator('form[wire\\:submit="join"]')
            join_form.locator("#inviteCode").fill(old_code)
            assert owner.goto(settings).status == 200
            action(owner, owner.locator('button[wire\\:click="regenerateCode"]'), "regenerateCode", confirmation=True)
            new_code, = row(database, "select invite_code from families where id = ?", (family_id,))
            assert new_code != old_code and len(new_code) == 12
            expect(owner.locator("span.font-mono")).to_have_text(new_code)
            action(newcomer, join_form.locator('button[type="submit"]'), "join")
            expect(join_form.locator(".text-red-500")).to_be_visible()
            assert row(database, "select id from family_members where family_id = ? and user_id = ?", membership) is None
            capture(newcomer, "old-code-refused")

            join_form.locator("#inviteCode").fill(new_code)
            action(newcomer, join_form.locator('button[type="submit"]'), "join")
            newcomer.wait_for_url(detail)
            expect(newcomer.get_by_role("heading", name="Family smoke", exact=True)).to_be_visible()
            member_id, role = row(database, "select id, role from family_members where family_id = ? and user_id = ?", membership)
            assert role == "MEMBER"
            capture(newcomer, "joined-family")

            assert owner.goto(detail).status == 200
            promote = owner.locator(f'button[wire\\:click="promoteMember({member_id})"]')
            expect(promote).to_be_visible()
            action(owner, promote, "promoteMember", confirmation=True)
            expect(promote).to_have_count(0)
            assert row(database, "select role from family_members where id = ?", (member_id,)) == ("ADMIN",)
            capture(owner, "promoted-member")
            remove = owner.locator(f'button[wire\\:click="removeMember({member_id})"]')
            action(owner, remove, "removeMember", confirmation=True)
            expect(remove).to_have_count(0)
            expect(owner.get_by_text("newcomer@example.test", exact=True)).to_have_count(0)
            assert row(database, "select id from family_members where id = ?", (member_id,)) is None
            capture(owner, "removed-member")

            # Mount before archival: the subsequent real Livewire request must deny admission.
            assert newcomer.goto(origin + "/families/join").status == 200
            join_form.locator("#inviteCode").fill(new_code)
            assert owner.goto(settings).status == 200
            action(owner, owner.locator('button[wire\\:click="archive"]'), "archive", confirmation=True)
            assert row(database, "select is_archived from families where id = ?", (family_id,)) == (1,)
            with newcomer.expect_response(lambda response: call_response(response, "join")) as pending:
                join_form.locator('button[type="submit"]').click()
            assert pending.value.status == 403, f"Archived family join: HTTP {pending.value.status}"
            assert row(database, "select id from family_members where family_id = ? and user_id = ?", membership) is None
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
    tree = subprocess.check_output(["git", "rev-parse", "HEAD^{tree}"], cwd=ROOT, text=True).strip()
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
                        "source": source, "tree": tree, "worktree_dirty": dirty,
                        "asset_manifest_sha256": manifest, "cases": results,
                    }, indent=2))
        finally:
            browser.close()
    if len(results) != 4 or not all(case["passed"] for case in results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
