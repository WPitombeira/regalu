# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Regalu is a wishlist management app with family/friends sharing and Secret Santa features. Built with Laravel 13, Livewire 4, Tailwind CSS 4, Vite 8, and Laravel Octane (RoadRunner 3). PHP ^8.5.12 and Node 26 LTS are required; `.nvmrc`, `composer.lock`, and `package-lock.json` pin the qualified versions.

Read `docs/LARAVEL_13_MODERNIZATION.md` for current behavior, verification, queue-worker requirements and JSON-session rollout decisions, and `docs/DEPENDENCY_UPGRADE.md` for the version matrix. `docs/PROJECT_MAP.md` is the historical pre-modernization audit; the modernization report supersedes its repaired findings. The product definition is a historical vision, not the current implementation inventory.

## Common Commands

### Development
```bash
php artisan octane:start --watch   # Dev server with Octane (preferred)
php artisan serve                  # Traditional dev server
npm run dev                        # Vite HMR for frontend assets
npm run build                      # Production asset build
npm run dev:portable               # Official Rolldown WASM fallback for older virtual CPUs
npm run build:portable             # Same production build using the portable bundler
npm run test:tooling               # Build/manifest and real Octane watcher smoke tests
```

### Testing (Pest)
```bash
php artisan test                                    # Run all tests
composer test:coverage                              # Enforce 80% with PCOV/Xdebug and reports
./vendor/bin/pest                                   # Run via Pest directly
./vendor/bin/pest tests/Feature/IndexPageTest.php   # Run single file
./vendor/bin/pest --filter="renders the hero"       # Run by test name
./vendor/bin/pest --group=user                      # Run by group
```

### Code Quality
```bash
./vendor/bin/phpstan analyse    # Static analysis (level 6, Larastan)
./vendor/bin/pint               # Code formatting (Laravel defaults)
./vendor/bin/pint --test        # Check formatting without fixing
```

## Architecture

### Routing & Auth
- Routes in `routes/web.php` — no API routes exist
- Custom authentication (not Breeze/Jetstream) in `UserController`
- Guest routes: `/`, `/login` (GET+POST)
- Auth routes use `auth` and `auth.session`, including share links; logout is POST-only
- Family, wishlist, and Secret Santa routes are active; password-reset routes are also implemented
- Controller authorization uses Laravel 13 `#[Authorize]`; Livewire components must reauthorize fresh resource state on actions and rendering

### Livewire Components
The app uses a hybrid controller + Livewire approach:
- Controllers render pages (`HomeController`, `DashboardController`)
- Livewire components handle interactive UI (`app/Livewire/`)
- `Home/LoginForm` — login/register form with Livewire validation
- `Notifications` — toast notification system via Livewire events
- `Dashboard`, `Profile/Settings` — mounted authenticated area components; `UserSettings` is legacy

### Views
- Layout: `resources/views/components/layouts/app.blade.php`
- UI components: `resources/views/components/ui/` (navbar, footer, icons, buttons)
- Livewire views: `resources/views/livewire/`
- Uses `lang/en/` and `lang/pt_BR/` for i18n via the `__()` helper

### Models
- `User` — standard auth model with `oauth_tokens` field (nullable, for future OAuth)
- `Newsletter` — email subscriptions with `active` flag
- `Family`, `FamilyMember` — groups and memberships/roles
- `Wishlist`, `WishlistItem`, `WishlistShare` — lists, gifts, and per-user sharing
- `AmigoSecretoEvent`, `AmigoSecretoParticipant`, `AmigoSecretoExclusion`, `AmigoSecretoDraw` — event/draw lifecycle
- `Notification` — persistent user notifications, separate from toast events

### Infrastructure
- Database: SQLite (default), session/cache/queue all use database driver
- Octane: RoadRunner server with file watching
- CI: separate PHP quality, assets, and test jobs on push/PR to `master`; pinned actions/runtimes, audits, Pint/Larastan, native/portable builds, 80% PCOV coverage and disposable release cache/migration/rollback smoke
- Invitation jobs use Laravel 13 queue routing to `mail`, after-commit dispatch and bounded retry/timeout attributes. Keep supervised workers listening on `mail,default`
- Assignment jobs use the same `mail` queue. Secret Santa recipient secrecy, explicit wishlist consent and scheduled reveals are documented in `docs/SECRET_SANTA_PRIVACY.md`; keep the Laravel scheduler running every minute. Never expose raw recipient/list identifiers in anonymous assignment payloads or grant hosts other participants' pairings.
- Secret Santa completion/archive actions and metadata-only history are documented in `docs/SECRET_SANTA_EVENT_HISTORY.md`. Completion closes event wishlist access and purchases; archival also closes normal detail/assignment access. Keep history separate from organizer-only read-only management views.
