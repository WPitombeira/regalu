"""Localized wishlist forms with real authentication and disposable SQLite only."""
import argparse
import hashlib
import json
from pathlib import Path
import sqlite3
import subprocess

from playwright.sync_api import expect, sync_playwright
from event_history_smoke import ROOT, action, application, call_response, login, row


LABELS = {
    "en": {
        "create_title": "Create Wishlist", "name": "Wishlist Name", "description": "Description",
        "type": "Type", "privacy": "Privacy", "family": "Family (optional)", "none": "None",
        "types": ["Generic", "Christmas", "Birthday", "Wedding"],
        "privacies": ["Private", "Family", "Specific People"],
        "name_placeholder": "My Birthday Wishlist", "description_placeholder": "What is this wishlist for?",
        "cancel": "Cancel", "create": "Create", "settings": "Settings", "edit": "Edit Wishlist", "save": "Save",
        "sharing": "Share Management", "email": "Email address to share with",
        "email_placeholder": "Enter user email to share...", "add": "Add",
        "empty": "No users shared with yet.", "view": "VIEW", "remove": "Remove access for Browser Friend",
        "danger": "Danger Zone", "archive": "Archive", "delete": "Delete",
        "confirm_archive": "Are you sure you want to archive this wishlist?",
        "confirm_delete": "Are you sure you want to permanently delete this wishlist? This action cannot be undone.",
        "required": "The wishlist name field is required.",
        "invalid_email": "The email address field must be a valid email address.",
        "missing": "User not found with that email.", "self": "You cannot share a wishlist with yourself.",
        "duplicate": "This user already has access.", "updated": "Wishlist updated successfully.",
        "added": "User added successfully.", "removed": "User removed.",
    },
    "pt_BR": {
        "create_title": "Criar lista de desejos", "name": "Nome da lista", "description": "Descrição",
        "type": "Tipo", "privacy": "Privacidade", "family": "Família (opcional)", "none": "Nenhuma",
        "types": ["Geral", "Natal", "Aniversário", "Casamento"],
        "privacies": ["Privada", "Família", "Pessoas específicas"],
        "name_placeholder": "Minha lista de aniversário", "description_placeholder": "Para que é esta lista?",
        "cancel": "Cancelar", "create": "Criar", "settings": "Configurações", "edit": "Editar lista", "save": "Salvar",
        "sharing": "Gerenciar compartilhamento", "email": "E-mail da pessoa com quem compartilhar",
        "email_placeholder": "Digite o e-mail para compartilhar...", "add": "Adicionar",
        "empty": "Esta lista ainda não foi compartilhada.", "view": "Visualização", "remove": "Remover acesso de Browser Friend",
        "danger": "Ações irreversíveis", "archive": "Arquivar", "delete": "Excluir",
        "confirm_archive": "Tem certeza de que deseja arquivar esta lista?",
        "confirm_delete": "Tem certeza de que deseja excluir esta lista permanentemente? Esta ação não pode ser desfeita.",
        "required": "O campo nome da lista é obrigatório.",
        "invalid_email": "O campo e-mail deve ser um endereço de e-mail válido.",
        "missing": "Nenhuma pessoa encontrada com esse e-mail.", "self": "Você não pode compartilhar uma lista consigo mesmo.",
        "duplicate": "Esta pessoa já tem acesso.", "updated": "Lista atualizada com sucesso.",
        "added": "Pessoa adicionada com sucesso.", "removed": "Pessoa removida.",
    },
}


def inputs_digest():
    """Bind the exercised source bytes, including new files, without printing their contents."""
    paths = subprocess.check_output(["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=ROOT).split(b"\0")
    digest = hashlib.sha256()
    for path in sorted(set(filter(None, paths))):
        content = (ROOT / path.decode()).read_bytes()
        digest.update(path + b"\0" + hashlib.sha256(content).digest())
    return digest.hexdigest()


def journey(browser, locale, viewport, output):
    labels = LABELS[locale]
    errors = []
    with application(locale, "seed-dashboard.php") as (origin, password, fixture, database):
        # Controlled synthetic data in the just-created, run-owned database only.
        with sqlite3.connect(database) as connection:
            family = connection.execute("select id from families where creator_id = ?", (fixture["member"],)).fetchone()[0]
            connection.execute("insert into family_members (family_id, user_id, role) values (?, ?, 'MEMBER')", (family, fixture["member"]))
            connection.execute("update users set name = 'Browser Friend' where id = ?", (fixture["buyer"],))
        contexts = []
        try:
            pages = []
            for email in ("member@example.test", "buyer@example.test"):
                context = browser.new_context(viewport=viewport, locale=locale.replace("_", "-"), color_scheme="dark")
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(origin + "/") else route.abort())
                contexts.append(context)
                page = context.new_page()
                page.on("pageerror", lambda error: errors.append(str(error)))
                login(page, origin, email, password)
                pages.append(page)
            owner, friend = pages

            def capture(name):
                # Assert toast copy at the action boundary, then capture the unobscured form.
                expect(owner.locator('#toast-parent [role="alert"]:visible')).to_have_count(0, timeout=5000)
                owner.screenshot(path=str(output / f"{name}.png"), full_page=True, animations="disabled")
                widths = owner.evaluate("({content: document.documentElement.scrollWidth, viewport: innerWidth})")
                assert widths["content"] <= widths["viewport"] + 1, f"{name}: overflow {widths}"
                for control in owner.locator('main input, main select, main textarea, main button').all():
                    if control.is_visible():
                        box = control.bounding_box()
                        assert box and box["x"] >= 0 and box["x"] + box["width"] <= viewport["width"] + 1, f"{name}: clipped control {box}"
                assert not errors, f"JavaScript errors: {errors}"

            def options():
                expect(owner.get_by_label(labels["type"], exact=True).locator("option")).to_have_text(labels["types"])
                expect(owner.get_by_label(labels["privacy"], exact=True).locator("option")).to_have_text(labels["privacies"])

            assert owner.goto(origin + "/wishlists/create").status == 200
            expect(owner.get_by_role("heading", name=labels["create_title"], exact=True)).to_be_visible()
            expect(owner.get_by_label(labels["name"], exact=True)).to_have_attribute("placeholder", labels["name_placeholder"])
            expect(owner.get_by_label(labels["description"], exact=True)).to_have_attribute("placeholder", labels["description_placeholder"])
            expect(owner.get_by_label(labels["family"], exact=True).locator("option")).to_have_text([labels["none"], "My family"])
            expect(owner.get_by_role("link", name=labels["cancel"], exact=True)).to_be_visible()
            expect(owner.get_by_label(labels["privacy"], exact=True)).to_have_value("PRIVATE")
            options()
            capture("create")
            action(owner, owner.get_by_role("button", name=labels["create"], exact=True), "create")
            expect(owner.get_by_text(labels["required"], exact=True)).to_be_visible()
            owner.get_by_label(labels["name"], exact=True).fill("Untranslated birthday list")
            owner.get_by_label(labels["description"], exact=True).fill("User-authored description")
            owner.get_by_label(labels["type"], exact=True).select_option("BIRTHDAY")
            owner.get_by_label(labels["family"], exact=True).select_option(str(family))
            action(owner, owner.get_by_role("button", name=labels["create"], exact=True), "create")
            owner.wait_for_url("**/wishlists/*")
            expect(owner.get_by_role("heading", name="Untranslated birthday list", exact=True)).to_be_visible()
            wishlist = row(database, "select id, privacy, type, family_id from wishlists where name = ? and user_id = ?", ("Untranslated birthday list", fixture["member"]))
            assert wishlist and wishlist[1:] == ("PRIVATE", "BIRTHDAY", family)
            detail = f"{origin}/wishlists/{wishlist[0]}"
            settings = detail + "/settings"
            assert friend.goto(detail).status == 403
            assert owner.goto(settings).status == 200
            expect(owner.get_by_role("heading", name="Untranslated birthday list - " + labels["settings"], exact=True)).to_be_visible()
            for key in ("edit", "sharing", "danger"):
                expect(owner.get_by_role("heading", name=labels[key], exact=True)).to_be_visible()
            expect(owner.get_by_text(labels["empty"], exact=True)).to_be_visible()
            share_email = owner.get_by_label(labels["email"], exact=True)
            expect(share_email).to_have_attribute("placeholder", labels["email_placeholder"])
            options()
            capture("settings-empty")
            for email, error_key in (("invalid-email", "invalid_email"), ("missing@example.test", "missing"), ("member@example.test", "self")):
                share_email.fill(email)
                action(owner, owner.get_by_role("button", name=labels["add"], exact=True), "addShare")
                expect(owner.get_by_text(labels[error_key], exact=True)).to_be_visible()
                assert row(database, "select count(*) from wishlist_shares where wishlist_id = ?", (wishlist[0],)) == (0,)
            capture("sharing-error")
            # Clear the deferred email before the main form validates all fields.
            share_email.fill("")
            owner.get_by_label(labels["name"], exact=True).fill("Updated birthday list")
            owner.get_by_label(labels["privacy"], exact=True).select_option("SPECIFIC")
            action(owner, owner.get_by_role("button", name=labels["save"], exact=True), "update")
            expect(owner.get_by_role("heading", name="Updated birthday list - " + labels["settings"], exact=True)).to_be_visible()
            expect(owner.get_by_text(labels["updated"], exact=True)).to_be_visible()
            assert row(database, "select name, privacy from wishlists where id = ?", (wishlist[0],)) == ("Updated birthday list", "SPECIFIC")
            share_email.fill("buyer@example.test")
            action(owner, owner.get_by_role("button", name=labels["add"], exact=True), "addShare")
            expect(owner.get_by_role("button", name=labels["remove"], exact=True)).to_be_visible()
            expect(owner.get_by_text(labels["added"], exact=True)).to_be_visible()
            expect(owner.locator('span.uppercase').filter(has_text=labels["view"])).to_be_visible()
            assert row(database, "select shared_with_user_id, access_level from wishlist_shares where wishlist_id = ?", (wishlist[0],)) == (fixture["buyer"], "VIEW")
            capture("settings-shared")
            assert friend.goto(detail).status == 200
            expect(friend.get_by_role("heading", name="Updated birthday list", exact=True)).to_be_visible()
            assert friend.goto(settings).status == 403
            share_email.fill("buyer@example.test")
            action(owner, owner.get_by_role("button", name=labels["add"], exact=True), "addShare")
            expect(owner.get_by_text(labels["duplicate"], exact=True)).to_be_visible()
            remove = owner.get_by_role("button", name=labels["remove"], exact=True)
            remove.focus()
            with owner.expect_response(lambda response: call_response(response, "removeShare")) as pending:
                remove.press("Enter")
            assert pending.value.status == 200
            expect(owner.get_by_text(labels["empty"], exact=True)).to_be_visible()
            expect(owner.get_by_text(labels["removed"], exact=True)).to_be_visible()
            assert row(database, "select count(*) from wishlist_shares where wishlist_id = ?", (wishlist[0],)) == (0,)
            assert friend.goto(detail).status == 403

            for method in ("archive", "delete"):
                button = owner.get_by_role("button", name=labels[method], exact=True)
                dialogs = []

                def dismiss(dialog):
                    dialogs.append(dialog.message)
                    dialog.dismiss()

                owner.once("dialog", dismiss)
                button.click()
                assert dialogs == [labels["confirm_" + method]]
                assert row(database, "select is_archived from wishlists where id = ?", (wishlist[0],)) == (0,)
            capture("settings-revoked")
            action(owner, owner.get_by_role("button", name=labels["archive"], exact=True), "archive", confirmation=True)
            owner.wait_for_url("**/wishlists")
            assert row(database, "select is_archived from wishlists where id = ?", (wishlist[0],)) == (1,)
            assert owner.goto(settings).status == 200
            action(owner, owner.get_by_role("button", name=labels["delete"], exact=True), "delete", confirmation=True)
            owner.wait_for_url("**/wishlists")
            assert row(database, "select id from wishlists where id = ?", (wishlist[0],)) is None
            assert not errors
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
    inputs = inputs_digest()
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
                    assert inputs_digest() == inputs, "Source changed during browser qualification"
                    (args.output / "results.json").write_text(json.dumps({"source": source, "worktree_dirty": dirty, "inputs_sha256": inputs, "asset_manifest_sha256": manifest, "cases": results}, indent=2))
        finally:
            browser.close()
    if len(results) != 4 or not all(case["passed"] for case in results):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
