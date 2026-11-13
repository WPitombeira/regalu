"""Authenticated dashboard/inbox journeys on disposable Laravel SQLite fixtures."""
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
        contexts = []
        try:
            pages = []
            for email in ("member@example.test", "buyer@example.test", "newcomer@example.test"):
                context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
                contexts.append(context)
                page = context.new_page()
                page.on("pageerror", lambda error: errors.append(str(error)))
                login(page, origin, email, password)
                pages.append(page)
            member, buyer, newcomer = pages
            copy = fixture["copy"]
            # Literal expectations must not inherit the application's fallback locale.
            labels = {
                "en": {
                    "title": "Notifications", "mark_all": "Mark all as read",
                    "empty": "No notifications yet.", "status": "Draws Complete", "unread": "Unread",
                    "bell_empty": "Notifications, no unread notifications",
                    "bell_two": "Notifications, 2 unread notifications",
                },
                "pt_BR": {
                    "title": "Notificações", "mark_all": "Marcar todas como lidas",
                    "empty": "Nenhuma notificação ainda.", "status": "Sorteio concluído", "unread": "Não lida",
                    "bell_empty": "Notificações, nenhuma não lida",
                    "bell_two": "Notificações, 2 não lidas",
                },
            }[locale]
            dashboard = member.get_by_test_id("dashboard")

            def capture(page, name):
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                widths = page.evaluate("({content: document.documentElement.scrollWidth, viewport: innerWidth})")
                assert widths["content"] <= widths["viewport"] + 1, f"{name}: horizontal overflow {widths}"
                assert not errors, f"JavaScript errors: {errors}"

            def private_free(page):
                content = page.content()
                for private in fixture["private"] + ["Foreign notice sentinel", "deduplication_key", "related_entity_id", "target_user_id", "bought_by_user_id"]:
                    assert private not in content, f"Private dashboard/inbox data disclosed: {private}"

            def notice(scope, kind):
                return scope.locator(f'button[wire\\:click="markAsRead({fixture["notices"][kind]})"]')

            expect(newcomer.get_by_role("heading", name=copy["dashboard"]["title"], exact=True)).to_be_visible()
            for key in ("empty_events", "empty_notifications", "empty_wishlists", "empty_families"):
                expect(newcomer.get_by_text(copy["dashboard"][key], exact=True)).to_be_visible()
            for key in ("create_event", "start_wishlists", "start_families"):
                expect(newcomer.get_by_role("link", name=copy["dashboard"][key], exact=True)).to_be_visible()
            empty_trigger = newcomer.get_by_role("button", name=labels["bell_empty"], exact=True)
            empty_trigger.click()
            expect(empty_trigger.locator("..").get_by_text(labels["empty"], exact=True)).to_be_visible()
            newcomer.keyboard.press("Escape")
            expect(empty_trigger).to_be_focused()
            capture(newcomer, "empty-dashboard")

            expect(dashboard.get_by_role("heading", name=copy["dashboard"]["check_email"], exact=True)).to_be_visible()
            expect(dashboard.get_by_role("link", name="My gift ideas", exact=True)).to_be_visible()
            expect(dashboard.get_by_role("link", name="My family", exact=True)).to_be_visible()
            expect(dashboard.get_by_text(labels["status"], exact=True)).to_be_visible()
            assignment = dashboard.locator(f'a[href="{origin}/secret-santa/{fixture["event"]}/assignment"]')
            expect(assignment).to_be_visible()
            private_free(member)
            capture(member, "active-dashboard")

            # A real buyer mutation creates a generic persisted owner notice.
            assert buyer.goto(f'{origin}/wishlists/{fixture["wishlist"]}').status == 200
            action(buyer, buyer.locator(f'button[wire\\:click="markAsBought({fixture["item"]})"]'), "markAsBought")
            expect(buyer.get_by_text(copy["bought"], exact=True)).to_be_visible()
            assert row(database, "select status from wishlist_items where id = ?", (fixture["item"],)) == ("BOUGHT",)
            purchase = row(database, "select id from notifications where user_id = ? and kind = 'wishlist_purchase'", (fixture["member"],))
            assert purchase is not None
            fixture["notices"]["wishlist_purchase"] = purchase[0]
            member.reload()
            expect(notice(dashboard, "wishlist_purchase")).to_be_visible()
            private_free(member)
            capture(member, "purchase-notice")
            action(member, notice(dashboard, "wishlist_purchase"), "markAsRead")
            member.wait_for_url(f'**/wishlists/{fixture["wishlist"]}')
            assert row(database, "select is_read from notifications where id = ?", purchase) == (1,)
            expect(member.locator('button[wire\\:click^="markAsBought"]')).to_have_count(0)

            assert member.goto(origin + "/dashboard").status == 200
            # Opening invitation information is not acceptance or membership.
            action(member, notice(dashboard, "family_invitation"), "markAsRead")
            expect(notice(dashboard, "family_invitation")).to_be_enabled()
            expect(notice(dashboard, "family_invitation").locator("..").get_by_text(copy["inbox"]["unread"], exact=True)).to_have_count(0)
            assert member.url == origin + "/dashboard"
            assert row(database, "select status from family_invitations where id = ?", (fixture["family_invitation"],)) == ("PENDING",)
            assert row(database, "select count(*) from family_members where user_id = ?", (fixture["member"],)) == (0,)

            # Bell controls must remain reachable on narrow screens and by keyboard.
            # Reload to read persisted counts rather than wait for the independent bell poll.
            member.reload()
            trigger = member.get_by_role("button", name=labels["bell_two"], exact=True)
            bell = trigger.locator("..")
            trigger.click()
            panel = bell.locator('[x-show="open"]')
            expect(panel).to_be_visible()
            expect(panel).to_have_css("opacity", "1")
            expect(panel.get_by_role("heading", name=labels["title"], exact=True)).to_be_visible()
            expect(panel.get_by_role("button", name=labels["mark_all"], exact=True)).to_be_visible()
            box = panel.bounding_box()
            assert box and box["x"] >= 0 and box["x"] + box["width"] <= viewport["width"], f"Bell clipped: {box}"
            capture(member, "notification-bell")
            member.keyboard.press("Escape")
            expect(panel).to_be_hidden()
            expect(trigger).to_be_focused()
            trigger.press("Enter")
            expect(panel).to_be_visible()
            assignment_notice = notice(bell, "assignment_available")
            expect(assignment_notice.get_by_text(labels["unread"], exact=True)).to_have_count(1)
            assignment_notice.focus()
            expect(assignment_notice).to_be_focused()
            with member.expect_response(lambda response: call_response(response, "markAsRead")) as pending:
                assignment_notice.press("Enter")
            assert pending.value.status == 200, f"Keyboard markAsRead: HTTP {pending.value.status}"
            member.wait_for_url(f'**/secret-santa/{fixture["event"]}/assignment')
            assert row(database, "select is_read from notifications where id = ?", (fixture["notices"]["assignment_available"],)) == (1,)
            expect(member.get_by_text("Anonymous gift idea", exact=True)).to_be_visible()
            assert "Private Recipient Sentinel" not in member.content()

            assert member.goto(origin + "/notifications").status == 200
            expect(member.get_by_role("heading", name=labels["title"], exact=True)).to_be_visible()
            listing = member.locator('div[wire\\:snapshot]').filter(has=member.locator('[wire\\:key^="notice-"]')).last
            expect(listing.locator('[wire\\:key^="notice-"]')).to_have_count(15)
            expect(listing.get_by_role("button", name=labels["mark_all"], exact=True)).to_be_visible()
            expect(notice(listing, "event_invitation").get_by_text(labels["unread"], exact=True)).to_have_count(1)
            private_free(member)
            action(member, member.locator('button[wire\\:click^="nextPage"]:visible').first, "nextPage")
            expect(listing.locator('[wire\\:key^="notice-"]')).to_have_count(2)
            action(member, member.locator('button[wire\\:click^="previousPage"]:visible').first, "previousPage")
            expect(listing.locator('[wire\\:key^="notice-"]')).to_have_count(15)

            # Controlled revocation in the run-owned DB while a real component is mounted.
            with sqlite3.connect(database) as connection:
                connection.execute("update amigo_secreto_events set is_archived = 1 where id = ?", (fixture["event"],))
                connection.execute("update family_invitations set status = 'CANCELLED' where id = ?", (fixture["family_invitation"],))
                connection.execute("update amigo_secreto_participants set status = 'DECLINED' where id = ?", (fixture["event_invitation"],))
            action(member, notice(listing, "assignment_available"), "markAsRead")
            expect(notice(listing, "assignment_available")).to_be_enabled()
            expect(listing.get_by_text(copy["inbox"]["unavailable"], exact=True)).to_have_count(3)
            assert member.url == origin + "/notifications"
            action(member, listing.locator('button[wire\\:click="markAllRead"]'), "markAllRead")
            expect(listing.locator('[wire\\:key^="notice-"] span.bg-blue-500')).to_have_count(0)
            assert row(database, "select count(*) from notifications where user_id = ? and is_read = 0", (fixture["member"],)) == (0,)
            assert row(database, "select is_read from notifications where id = ?", (fixture["foreign"],)) == (0,)
            private_free(member)
            capture(member, "read-inbox")
            assert member.goto(origin + "/dashboard").status == 200
            expect(member.locator("#dashboard-assignments, #dashboard-invitations")).to_have_count(0)
            expect(dashboard.get_by_text(copy["inbox"]["unavailable"], exact=True)).to_have_count(3)
            capture(member, "revoked-dashboard")
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
