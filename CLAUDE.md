# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Regalu is a wishlist management app with family/friends sharing and Secret Santa features. Built with Laravel 13, Livewire 4, Tailwind CSS 4, Vite 8, and Laravel Octane (RoadRunner 3). PHP ^8.5.12 and Node 26 LTS are required; `.nvmrc`, `composer.lock`, and `package-lock.json` pin the qualified versions.

Read `docs/PROJECT_MAP.md` for the current implementation and known release blockers, and `docs/DEPENDENCY_UPGRADE.md` for the version matrix, compatibility decisions, and qualification limits. The product definition is a historical vision, not the current implementation inventory.

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
- Auth routes include `/dashboard`, `/settings`, `/profile`, `/logout`, and notifications
- Family, wishlist, and Secret Santa routes are active; password-reset routes are also implemented
- Do not mistake authentication middleware for complete resource authorization: known family/event policy gaps are recorded in `docs/PROJECT_MAP.md`

### Livewire Components
The app uses a hybrid controller + Livewire approach:
- Controllers render pages (`HomeController`, `DashboardController`)
- Livewire components handle interactive UI (`app/Livewire/`)
- `Home/LoginForm` — login/register form with Livewire validation
- `Notifications` — toast notification system via Livewire events
- `Dashboard`, `UserSettings` — authenticated area components

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
- CI: GitHub Actions runs on push/PR to `master` with pinned PHP/Node/npm/Composer, lockfile installs, dependency audits, build/tooling checks, SQLite migrations, and Pest 5 tests
