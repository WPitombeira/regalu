# Regalu

Wishlist sharing, family groups, and Amigo Secreto (Secret Santa), built with Laravel, Livewire, and Blade.

## Start here

- [Laravel 13 modernization, verification, and rollout notes](docs/LARAVEL_13_MODERNIZATION.md)
- [Architecture and historical baseline inventory](docs/PROJECT_MAP.md)
- [Dependency versions, upgrade decisions, and verification](docs/DEPENDENCY_UPGRADE.md)
- [Product vision (historical)](docs/PRODUCT_DEFINITION.md)

## Requirements

- PHP **8.5.12+** within the 8.x line, with Laravel's required extensions plus SQLite and sockets for RoadRunner.
- Composer **2.11.1**.
- Node.js **26.13.0 LTS** (`.nvmrc`) and npm **12.2.2** (`packageManager`).
- SQLite by default. No external database, Redis, or mail service is needed for local tests.

Use the current Node LTS where an LTS channel exists; libraries without an LTS channel use their newest compatible stable release. Install the checked-in locks rather than resolving new versions during deployment.

## Local development

```bash
nvm install
nvm use
npm install --global npm@12.2.2
composer install
npm ci
```

Create a private local `.env` with `APP_ENV=local`, `DB_CONNECTION=sqlite`, and `MAIL_MAILER=log`. Review `.env.example` rather than blindly reusing service credentials. Do not use a production database or production mail account for local development/tests.

```bash
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
php artisan serve
```

For HMR, run `npm run dev` in another terminal. On older virtual CPUs where Vite's native Rolldown binding raises `SIGILL`, use `npm run build:portable` and `npm run dev:portable`; these use the official, lockfile-pinned WASM binding without forcing Tailwind's native modules into WASM mode.

### Octane / RoadRunner

The required PHP bridge and installer are in `composer.lock`. Download the qualified server binary separately; it is intentionally not tracked in Git:

```bash
php vendor/bin/rr get-binary --filter=3.0.1 --no-config --no-interaction
php artisan octane:start --server=roadrunner --watch
```

Use `--poll` as well as `--watch` when native file events are unavailable on a network-mounted workspace. Keep `vendor/`, `node_modules/`, and SQLite databases on a local filesystem.

## Verification

```bash
composer validate --strict
composer check-platform-reqs
composer audit --locked
npm audit
npm run build                   # or npm run build:portable on older virtual CPUs
npm run test:tooling
php artisan test
composer test:coverage          # PCOV/Xdebug required; minimum 80%, Clover/JUnit reports
php vendor/bin/phpstan analyse
php vendor/bin/pint --test
```

PHPStan and repository-wide Pint now pass, and CI enforces at least 80% line coverage. Tests use an isolated SQLite database and array/sync services; if `.env.testing` does not exist locally, create a comment-only file rather than copying production settings. See the [modernization report](docs/LARAVEL_13_MODERNIZATION.md) for actual results and remaining product/concurrency limits.

Invitations now use the `mail` queue. Run a supervised `php artisan queue:work --queue=mail,default --tries=3 --timeout=30` worker with an asynchronous queue connection. JSON sessions require a coordinated reauthentication/compatibility rollout; read the report before deploying. No successful remote pipeline or production deployment is implied by local tests.