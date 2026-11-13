"""Localized family journeys on disposable SQLite; no external mail or accounts."""
import argparse
import hashlib
import json
from pathlib import Path
import sqlite3
import subprocess

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, action, application, login, row

# Independent reviewed copy, not expectations obtained from the app's translator.
COPY = {
    "en": {
        "index": "My Families", "empty": "You don't belong to any family yet.",
        "create": "Create Family", "submit": "Create", "cancel": "Cancel",
        "name": "Family Name", "description": "Description",
        "required": "The family name field is required.",
        "minimum": "The family name field must be at least 2 characters.",
        "one": "1 member", "two": "2 members", "settings": "Family Settings",
        "update": "Update", "updated": "Family updated successfully.",
        "code": "Invite Code", "copy": "Copy", "copied": "Copied!",
        "regenerate": "Regenerate", "regenerated": "Invite code regenerated.",
        "regenerate_confirm": "Regenerate the invite code? The old code will stop working.",
        "danger": "Danger Zone", "archive": "Archive", "archived": "Family archived.",
        "archive_confirm": "Archive this family? Members will no longer see it in their list.",
        "join": "Join Family", "placeholder": "Enter invite code",
        "size": "The invite code field must be 12 characters.", "invalid": "Invalid invite code.",
        "admin": "Admin", "member": "Member", "members": "Members",
        "promote": "Promote", "promoted": "Member promoted to admin.",
        "promote_confirm": "Promote this member to admin?",
        "remove": "Remove", "removed": "Member removed from family.",
        "remove_confirm": "Remove this member from the family?",
        "invitations": "Email invitations", "email": "Email address",
        "invitation_empty": "No email invitations yet.", "send": "Send invitation",
        "sent": "Invitation queued for email delivery.", "pending": "Pending",
        "cancel_invitation": "Cancel invitation", "cancelled": "Invitation cancelled.",
        "cancel_confirm": "Cancel this invitation? Its email link will no longer allow joining.",
        "accepted": "Invitation accepted.", "declined": "Invitation declined.", "expired": "Expired",
        "pagination": "Invitation pages", "page_two": "Go to page 2",
        "previous": "Previous", "next": "Next", "showing": "Showing 1 to 10 of 13 results",
    },
    "pt_BR": {
        "index": "Minhas famílias", "empty": "Você ainda não faz parte de nenhuma família.",
        "create": "Criar família", "submit": "Criar", "cancel": "Cancelar",
        "name": "Nome da família", "description": "Descrição",
        "required": "O campo nome da família é obrigatório.",
        "minimum": "O campo nome da família deve ter pelo menos 2 caracteres.",
        "one": "1 membro", "two": "2 membros", "settings": "Configurações da família",
        "update": "Atualizar", "updated": "Família atualizada com sucesso.",
        "code": "Código de convite", "copy": "Copiar", "copied": "Copiado!",
        "regenerate": "Gerar novo código", "regenerated": "Novo código de convite gerado.",
        "regenerate_confirm": "Gerar um novo código de convite? O código anterior deixará de funcionar.",
        "danger": "Zona de atenção", "archive": "Arquivar", "archived": "Família arquivada.",
        "archive_confirm": "Arquivar esta família? Ela não aparecerá mais na lista dos membros.",
        "join": "Entrar na família", "placeholder": "Digite o código de convite",
        "size": "O campo código de convite deve ser 12 caracteres.", "invalid": "Código de convite inválido.",
        "admin": "Administrador", "member": "Membro", "members": "Membros",
        "promote": "Promover", "promoted": "Membro promovido a administrador.",
        "promote_confirm": "Promover este membro a administrador?",
        "remove": "Remover", "removed": "Membro removido da família.",
        "remove_confirm": "Remover este membro da família?",
        "invitations": "Convites por e-mail", "email": "Endereço de e-mail",
        "invitation_empty": "Nenhum convite por e-mail ainda.", "send": "Enviar convite",
        "sent": "Convite adicionado à fila de envio por e-mail.", "pending": "Pendente",
        "cancel_invitation": "Cancelar convite", "cancelled": "Convite cancelado.",
        "cancel_confirm": "Cancelar este convite? O link enviado por e-mail não permitirá mais a entrada.",
        "accepted": "Convite aceito.", "declined": "Convite recusado.", "expired": "Expirado",
        "pagination": "Páginas de convites", "page_two": "Ir para a página 2",
        "previous": "Anterior", "next": "Próximo", "showing": "Mostrando 1 até 10 de 13 resultados",
    },
}


def confirmed(page, control, method, text, accept=True):
    messages = []
    page.once("dialog", lambda dialog: messages.append(dialog.message))
    action(page, control, method, confirmation=accept)
    assert messages == [text], f"Unexpected {method} confirmation: {messages}"


def journey(browser, locale, viewport, output):
    copy = COPY[locale]
    errors, geometry = [], []
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        owner_id, = row(database, "select id from users where email = ?", ("newcomer@example.test",))
        # The existing seed's newcomer has no locale; set only this run-owned fixture.
        with sqlite3.connect(database) as connection:
            connection.execute("update users set locale = ? where id = ?", (locale, owner_id))
        contexts, pages = [], []
        try:
            for email in ("newcomer@example.test", "member@example.test"):
                context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
                context.grant_permissions(["clipboard-read", "clipboard-write"], origin=origin)
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
                contexts.append(context)
                page = context.new_page()
                page.on("pageerror", lambda error: errors.append(str(error)))
                login(page, origin, email, password)
                pages.append(page)
            owner, member = pages

            def capture(page, name):
                page.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                bounds = page.evaluate("""() => ({
                    content: document.documentElement.scrollWidth, viewport: innerWidth,
                    outside: [...document.querySelectorAll('main a, main button, main input, main textarea, main h1')]
                        .filter(e => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden')
                        .filter(e => { const r = e.getBoundingClientRect(); return r.left < -1 || r.right > innerWidth + 1; })
                        .map(e => ({tag: e.tagName, text: (e.innerText || e.id).trim().slice(0, 80)}))
                })""")
                geometry.append({"screen": name, **bounds})
                (output / "geometry.json").write_text(json.dumps(geometry, indent=2))
                assert bounds["content"] <= bounds["viewport"] + 1 and not bounds["outside"], f"Layout overflow at {name}: {bounds}"
                assert not errors, f"JavaScript errors: {errors}"

            assert owner.goto(origin + "/families").status == 200
            expect(owner.get_by_role("heading", name=copy["index"], exact=True)).to_be_visible()
            expect(owner.get_by_text(copy["empty"], exact=True)).to_be_visible()
            capture(owner, "empty-index")
            owner.get_by_role("link", name=copy["create"], exact=True).first.click()
            expect(owner.get_by_role("heading", name=copy["create"], exact=True)).to_be_visible()
            form = owner.locator('form[wire\\:submit="create"]')
            expect(form.get_by_label(copy["name"], exact=True)).to_have_attribute("placeholder", copy["name"])
            expect(form.get_by_label(copy["description"], exact=True)).to_be_visible()
            expect(form.get_by_role("link", name=copy["cancel"], exact=True)).to_be_visible()
            action(owner, form.get_by_role("button", name=copy["submit"], exact=True), "create")
            expect(form.get_by_text(copy["required"], exact=True)).to_be_visible()
            assert row(database, "select count(*) from families where creator_id = ?", (owner_id,)) == (0,)
            capture(owner, "create-validation")
            form.locator("#name").fill("Família <amigos>")
            form.locator("#description").fill("My unchanged description")
            action(owner, form.get_by_role("button", name=copy["submit"], exact=True), "create")
            family_id, old_code, description = row(database, "select id, invite_code, description from families where creator_id = ?", (owner_id,))
            detail = f"{origin}/families/{family_id}"
            settings = detail + "/settings"
            owner.wait_for_url(detail)
            assert owner.url == detail and description == "My unchanged description"
            assert row(database, "select role from family_members where family_id = ? and user_id = ?", (family_id, owner_id)) == ("ADMIN",)
            expect(owner.get_by_role("heading", name="Família <amigos>", exact=True)).to_be_visible()
            expect(owner.get_by_text(copy["admin"], exact=True)).to_be_visible()
            expect(owner.get_by_text(copy["invitation_empty"], exact=True)).to_be_visible()
            action(owner, owner.get_by_role("button", name=copy["copy"], exact=True), "__dispatch")
            expect(owner.locator("#toast-parent").get_by_text(copy["copied"], exact=True)).to_be_visible()
            assert owner.evaluate("navigator.clipboard.readText()") == old_code
            capture(owner, "created-roster")
            assert owner.goto(origin + "/families").status == 200
            card = owner.locator(f'main a[href="{detail}"]')
            expect(card).to_contain_text(copy["one"])
            capture(owner, "one-member-index")

            assert owner.goto(settings).status == 200
            expect(owner.get_by_role("heading", name=copy["settings"], exact=True)).to_be_visible()
            expect(owner.get_by_role("heading", name=copy["danger"], exact=True)).to_be_visible()
            form = owner.locator('form[wire\\:submit="update"]')
            form.locator("#name").fill("x")
            action(owner, form.get_by_role("button", name=copy["update"], exact=True), "update")
            expect(form.get_by_text(copy["minimum"], exact=True)).to_be_visible()
            assert row(database, "select name from families where id = ?", (family_id,)) == ("Família <amigos>",)
            capture(owner, "settings-validation")
            form.locator("#name").fill("Família <atualizada>")
            action(owner, form.get_by_role("button", name=copy["update"], exact=True), "update")
            expect(owner.locator("#toast-parent").get_by_text(copy["updated"], exact=True)).to_be_visible()
            assert row(database, "select name, description from families where id = ?", (family_id,)) == ("Família <atualizada>", "My unchanged description")
            regenerate = owner.get_by_role("button", name=copy["regenerate"], exact=True)
            confirmed(owner, regenerate, "regenerateCode", copy["regenerate_confirm"], accept=False)
            assert row(database, "select invite_code from families where id = ?", (family_id,)) == (old_code,)
            confirmed(owner, regenerate, "regenerateCode", copy["regenerate_confirm"])
            new_code, = row(database, "select invite_code from families where id = ?", (family_id,))
            assert new_code != old_code and len(new_code) == 12
            expect(owner.locator("span.font-mono")).to_have_text(new_code)
            expect(owner.locator("#toast-parent").get_by_text(copy["regenerated"], exact=True)).to_be_visible()
            capture(owner, "saved-settings")

            assert member.goto(origin + "/families/join").status == 200
            expect(member.get_by_role("heading", name=copy["join"], exact=True)).to_be_visible()
            join = member.locator('form[wire\\:submit="join"]')
            expect(join.get_by_label(copy["code"], exact=True)).to_have_attribute("placeholder", copy["placeholder"])
            join.locator("#inviteCode").fill("short")
            action(member, join.get_by_role("button", name=copy["join"], exact=True), "join")
            expect(join.get_by_text(copy["size"], exact=True)).to_be_visible()
            capture(member, "join-validation")
            join.locator("#inviteCode").fill(old_code)
            action(member, join.get_by_role("button", name=copy["join"], exact=True), "join")
            expect(join.get_by_text(copy["invalid"], exact=True)).to_be_visible()
            assert row(database, "select id from family_members where family_id = ? and user_id = ?", (family_id, fixture["member"])) is None
            join.locator("#inviteCode").fill(new_code)
            action(member, join.get_by_role("button", name=copy["join"], exact=True), "join")
            member.wait_for_url(detail)
            expect(member.get_by_role("heading", name="Família <atualizada>", exact=True)).to_be_visible()
            expect(member.get_by_text(copy["member"], exact=True)).to_be_visible()
            expect(member.get_by_role("link", name=copy["settings"], exact=True)).to_have_count(0)
            member_id, role = row(database, "select id, role from family_members where family_id = ? and user_id = ?", (family_id, fixture["member"]))
            assert role == "MEMBER"
            capture(member, "joined-roster")
            assert member.goto(settings).status == 403
            assert owner.goto(origin + "/families").status == 200
            expect(card).to_contain_text(copy["two"])
            capture(owner, "two-member-index")
            assert owner.goto(detail).status == 200
            promote = owner.get_by_role("button", name=copy["promote"], exact=True)
            expect(promote).to_be_visible()
            capture(owner, "member-actions")
            confirmed(owner, promote, "promoteMember", copy["promote_confirm"], accept=False)
            assert row(database, "select role from family_members where id = ?", (member_id,)) == ("MEMBER",)
            confirmed(owner, promote, "promoteMember", copy["promote_confirm"])
            expect(promote).to_have_count(0)
            expect(owner.get_by_text(copy["admin"], exact=True)).to_have_count(2)
            expect(owner.locator("#toast-parent").get_by_text(copy["promoted"], exact=True)).to_be_visible()
            assert row(database, "select role from family_members where id = ?", (member_id,)) == ("ADMIN",)
            capture(owner, "promoted-member")
            remove = owner.get_by_role("button", name=copy["remove"], exact=True)
            confirmed(owner, remove, "removeMember", copy["remove_confirm"], accept=False)
            assert row(database, "select id from family_members where id = ?", (member_id,)) == (member_id,)
            confirmed(owner, remove, "removeMember", copy["remove_confirm"])
            expect(owner.get_by_text("member@example.test", exact=True)).to_have_count(0)
            expect(owner.locator("#toast-parent").get_by_text(copy["removed"], exact=True)).to_be_visible()
            assert row(database, "select id from family_members where id = ?", (member_id,)) is None

            invitations = owner.locator('section[aria-labelledby="email-invitations-heading"]')
            expect(invitations.get_by_role("heading", name=copy["invitations"], exact=True)).to_be_visible()
            invitations.get_by_label(copy["email"], exact=True).fill("Invitee@Example.test")
            action(owner, invitations.get_by_role("button", name=copy["send"], exact=True), "inviteByEmail")
            expect(invitations.get_by_text("invitee@example.test", exact=True)).to_be_visible()
            invitation = invitations.locator("article").filter(has=owner.get_by_text("invitee@example.test", exact=True))
            expect(invitation).to_have_count(1)
            expect(invitation.get_by_text(copy["pending"], exact=False)).to_be_visible()
            expect(owner.locator("#toast-parent").get_by_text(copy["sent"], exact=True)).to_be_visible()
            invitation_id, status = row(database, "select id, status from family_invitations where family_id = ? and email = ?", (family_id, "invitee@example.test"))
            assert status == "PENDING"
            cancel = invitation.get_by_role("button", name=copy["cancel_invitation"], exact=True)
            confirmed(owner, cancel, "cancelInvitation", copy["cancel_confirm"], accept=False)
            assert row(database, "select status from family_invitations where id = ?", (invitation_id,)) == ("PENDING",)
            capture(owner, "pending-invitation")
            confirmed(owner, cancel, "cancelInvitation", copy["cancel_confirm"])
            expect(invitation.get_by_text(copy["cancelled"], exact=True)).to_be_visible()
            assert row(database, "select status from family_invitations where id = ?", (invitation_id,)) == ("CANCELLED",)

            # Presentation fixtures only, inserted in the private database already owned by application().
            with sqlite3.connect(database) as connection:
                for number in range(1, 13):
                    status = {9: "ACCEPTED", 10: "DECLINED", 11: "CANCELLED"}.get(number, "PENDING")
                    offset = "-1 day" if number == 12 else "+7 days"
                    connection.execute("insert into family_invitations (family_id, inviter_id, email, generation, status, expires_at, created_at, updated_at) values (?, ?, ?, ?, ?, datetime('now', ?), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                                       (family_id, owner_id, f"person{number}@example.test", f"synthetic-generation-{number}", status, offset))
            owner.reload()
            expect(invitations.locator("article")).to_have_count(10)
            for key in ("accepted", "declined", "cancelled", "expired"):
                expect(invitations.get_by_text(copy[key], exact=True)).to_be_visible()
            navigation = invitations.get_by_role("navigation", name=copy["pagination"], exact=True)
            expect(navigation).to_be_visible()
            if viewport["width"] >= 640:
                expect(navigation.get_by_role("button", name=copy["page_two"], exact=True)).to_be_visible()
                expect(navigation.locator("p")).to_have_text(copy["showing"])
            else:
                expect(navigation.get_by_role("button", name=copy["next"], exact=False)).to_be_visible()
            capture(owner, "invitation-pagination")
            action(owner, navigation.locator('button[wire\\:click^="nextPage"]:visible').first, "nextPage")
            expect(invitations.locator("article")).to_have_count(3)
            expect(invitations.get_by_text("person1@example.test", exact=True)).to_be_visible()
            capture(owner, "invitation-page-two")
            action(owner, navigation.locator('button[wire\\:click^="previousPage"]:visible').first, "previousPage")
            expect(invitations.locator("article")).to_have_count(10)
            assert row(database, "select count(*) from family_invitations where family_id = ?", (family_id,)) == (13,)
            assert row(database, "select count(*) from family_members where family_id = ?", (family_id,)) == (1,)

            assert owner.goto(settings).status == 200
            archive = owner.get_by_role("button", name=copy["archive"], exact=True)
            confirmed(owner, archive, "archive", copy["archive_confirm"], accept=False)
            assert row(database, "select is_archived from families where id = ?", (family_id,)) == (0,)
            confirmed(owner, archive, "archive", copy["archive_confirm"])
            expect(owner.locator("#toast-parent").get_by_text(copy["archived"], exact=True)).to_be_visible()
            assert row(database, "select is_archived from families where id = ?", (family_id,)) == (1,)
            assert owner.url == settings
            assert owner.goto(origin + "/families").status == 200
            expect(owner.get_by_text(copy["empty"], exact=True)).to_be_visible()
            expect(owner.get_by_text("Família <atualizada>", exact=True)).to_have_count(0)
            capture(owner, "archived-index")
            failures = [item for item in geometry if item["content"] > item["viewport"] + 1 or item["outside"]]
            assert not failures, f"Layout overflow: {failures}"
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
