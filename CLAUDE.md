# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Regalu is a wishlist management app with family/friends sharing and Secret Santa features. Built with Laravel 12, Livewire 3, TailwindCSS, and Laravel Octane (RoadRunner). PHP ^8.5 required.

## Common Commands

### Development
```bash
php artisan octane:start --watch   # Dev server with Octane (preferred)
php artisan serve                  # Traditional dev server
npm run dev                        # Vite HMR for frontend assets
npm run build                      # Production asset build
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
- Auth routes: `/dashboard`, `/settings`, `/logout`
- Wishlist and Secret Santa routes are planned but commented out

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
- Uses `lang/en/messages.php` for i18n via `__()` helper

### Models
- `User` — standard auth model with `oauth_tokens` field (nullable, for future OAuth)
- `Newsletter` — email subscriptions with `active` flag

### Infrastructure
- Database: SQLite (default), session/cache/queue all use database driver
- Octane: RoadRunner server with file watching
- CI: GitHub Actions runs on push/PR to `master` — PHP 8.5, SQLite, Pest tests
