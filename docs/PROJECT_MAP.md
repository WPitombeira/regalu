# Regalu — architecture and historical baseline audit

Inspected 2026-11-09, starting from `20f16a6`. This is an implementation inventory, not a production-readiness approval. The dependency refresh is recorded separately in [DEPENDENCY_UPGRADE.md](DEPENDENCY_UPGRADE.md).

> Historical snapshot: the application modernization subsequently repaired many findings below. Read [LARAVEL_13_MODERNIZATION.md](LARAVEL_13_MODERNIZATION.md) for current behavior, verification and remaining limits. This baseline is retained as audit evidence, not as the current defect list.

## Architecture

Regalu is a server-rendered Laravel application: web routes invoke thin controllers, Blade pages embed Livewire components, and Livewire implements most interactive behavior. There is no application API route file or separate SPA. Livewire provides Alpine; do not install an unrelated npm Livewire runtime.

| Layer | Location / responsibility |
|---|---|
| Bootstrap | `bootstrap/app.php`: web routes, console routes, `/up` health check |
| HTTP | `routes/web.php`, `app/Http/Controllers/`: page shells, authentication, password reset |
| Interactive UI | `app/Livewire/`, paired with `resources/views/livewire/` |
| Shared shell | `resources/views/components/layouts/app.blade.php`: navbar, toasts, footer |
| Presentation primitives | `resources/views/components/ui/`: icons, buttons, user menu |
| Assets | `vite.config.js`, `resources/css/app.css`, `resources/js/`; Tailwind's Vite integration |
| Domain / access | `app/Models/`, `app/Policies/WishlistPolicy.php` |
| Services | Draw algorithm, notification persistence, URL metadata fetch in `app/Services/` |
| Mail | `app/Mail/`, `resources/views/mail/`; invitations currently send synchronously |
| Locales | `lang/en/`, `lang/pt_BR/`; `__()` translation helper |
| Persistence | `database/migrations/`, factories, seeders; SQLite by default |
| Runtime | Octane with RoadRunner; conventional `artisan serve` also works |
| CI / deployment | `.github/workflows/laravel.yml`; legacy `.scripts/deploy.sh` |

Sessions, cache, and queue default to database drivers. Mail defaults to logging, not a production delivery service. Queue configuration exists, but the inspected application has no job-based invitation/reveal pipeline. `routes/console.php` does not implement Secret Santa reveal scheduling.

## Routes and feature state

The route file is the authoritative inventory; older product notes saying that family/wishlist/event routes are commented out are obsolete.

| Surface | Entry points | Current implementation / limitations |
|---|---|---|
| Landing | `/` → `HomeController` → `Home\\Index` | Hero, features, newsletter, contact UI. Newsletter/feedback methods live in a different, unmounted `Home` component; do not call these flows complete. |
| Authentication | `/login` GET/POST; `Home\\LoginForm`, `UserController` | Session authentication and registration backend exist. Login-page component spelling was fixed during this refresh. Login validation and registration UI bindings still need repair. |
| Password reset | `/forgot-password`, `/reset-password/{token}`, POST handlers | Link request and reset handlers exist. Full successful email/token/browser flow is not qualified by this refresh. |
| Account | `/dashboard`, `/settings`, `/profile` | Dashboard and mounted settings remain placeholders. The more complete `Profile\\Settings` component is not mounted by these settings routes. |
| Families | `/families`, `/families/create`, `/families/join`, detail/settings | Creation, membership, invite codes, roster, and settings code exist. Resource authorization is incomplete. |
| Wishlists | `/wishlists`, create/detail/settings; `/w/{wishlist}` | Lists, items, search/filter, sharing, buy state, archive/delete, and URL metadata code exist. `WishlistPolicy` protects controller view/update access. The purported public route still uses that policy and there is no PUBLIC privacy mode. |
| Secret Santa | `/secret-santa`, create/join/detail/participants/exclusions/draw; `/join-event/{code}` | Events, participants, email invitations, exclusions, draw generation/persistence exist. Organizer/participant checks and public-join authentication need work. |
| Notifications | `/notifications`, navbar bell, shared toast component | Persistent records and transient Livewire toasts are different systems. Stored notification UI exists, but domain actions largely dispatch toasts rather than calling `NotificationService`. |

Authentication middleware is not a substitute for membership or organizer authorization. Only wishlist policy coverage was found; family and event mutation boundaries need explicit policies/action-level checks.

## Domain model

- **User → Family**: many-to-many through `family_members`, including role and join time; each family also has a creator. The membership migration uniquely constrains family/user pairs.
- **User → Wishlist → WishlistItem**: owner, optional family, privacy, archive state, gift URL/image/price/priority/status, and optional buyer. `WishlistShare` adds explicit VIEW/EDIT sharing.
- **AmigoSecretoEvent → Participant / Exclusion / Draw**: organizer, optional family, dates/status/code; participants may initially be email-only. Exclusions link user pairs. Draw rows constrain event/drawer uniqueness. Participant/exclusion uniqueness still deserves database-level review.
- **User → Notification**: stored user-owned notifications can reference a related entity. `Newsletter` is a separate subscription model.

`Wishlist::isVisibleTo()` holds privacy rules. `visibleItemsFor()` is a separate projection helper that the detail component does not use; verify buyer-identity privacy through actual rendered/serialized payloads before making privacy guarantees.

## Implemented integrations versus product vision

Implemented: URL Open Graph/title fetching, synchronous Secret Santa invitation mail, local/database persistence, Livewire/Alpine UI, Octane/RoadRunner runtime.

Not implemented or not wired end-to-end: Apple/Google OAuth buttons, WhatsApp coordination, automatic reveal, assignment-email delivery after draws, queued invitation/reveal work, and a complete account/dashboard experience. Existing family/assignment mail classes are not proof that their corresponding delivery workflows run.

## Existing release blockers and follow-up order

These findings are from source inspection and baseline/browser checks; this upgrade does not silently rewrite business/security behavior.

### 1. Authorization and network safety — before any public release

- `app/Livewire/Family/FamilySettings.php:25`: update/archive/code regeneration lack membership/admin authorization.
- `app/Livewire/Family/FamilyRoster.php:16`: remove/promote checks administration of the current family but looks up the target membership globally, not through that family. Cross-family targets must be rejected.
- `app/Livewire/AmigoSecreto/ManageParticipants.php:23` and `ManageExclusions.php:24`: creation actions lack organizer checks. Exclusions validate existing users rather than membership in the event.
- Family/event controller routes require login but do not enforce the missing resource boundaries. The public event join route is outside the auth group while its component assumes a logged-in user.
- `app/Services/UrlMetaFetchService.php:20`: user URLs reach `file_get_contents()` without scheme, public-IP/DNS, redirect, response-size, or local-file protections. Treat this as an SSRF/local-resource access risk; implement controls at the actual connection boundary.

### 2. Authentication and functional wiring

- `app/Livewire/Home/LoginForm.php:58`: unrestricted `$this->validate()` also validates required registration properties during login.
- `resources/views/livewire/home/login-form.blade.php`: registration name binds `name` rather than `registerName`; backend tests that assign `registerName` directly do not cover that UI defect.
- Login's fallback redirect and authenticated navigation point at `/home`, but the actual dashboard route is `/dashboard`.
- Logout uses GET and does not perform full session invalidation/CSRF-token regeneration.
- Mount the intended settings/newsletter components deliberately rather than retaining parallel implementations.

### 3. Operational and UX debt

- Newsletter migration rollback drops `newsletter` instead of the created `newsletters` table.
- The legacy deploy script is not fail-fast and lacks a deliberate failure/maintenance recovery contract. Dependency commands were updated, but deployment was not executed or certified.
- `app/View/Components/ui/user/card.php` declares a class at a non-PSR-4 path; Composer already warned about it before this refresh.
- `primary-*` theme colors are used without configured primary tokens; mobile contact heading/form overlap is also present in the pre-upgrade browser baseline. Both remain outside this dependency-only change.
- PHPStan and Pint have substantial existing/style-preset debt; do not describe the repository as lint-clean.

## Tests and verification boundaries

Pest feature suites cover landing rendering, registration backend, password-reset requests, family management, wishlists, events/draws, notifications, profile behavior, and migrations. Unit tests cover draw permutations. `tests/Feature/UserTest.php` retains six TODO tests.

This refresh adds a login-page rendering regression test and Node tooling tests for the actual portable asset build/manifest and actual Octane file watcher. The final PHP suite passes, but broad authorization-negative tests, URL-fetch safety tests, complete browser sign-up/login/reset flows, and production mail delivery remain necessary follow-ups.

See the [qualification matrix](DEPENDENCY_UPGRADE.md#qualification) for executed commands and exact results.
