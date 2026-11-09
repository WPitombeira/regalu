# Dependency refresh — 2026-11-09

## Policy and scope

Use the newest released LTS where the project has an actual LTS channel (Node and the CI Ubuntu image). PHP and most application libraries do not offer a separate LTS distribution: use their latest supported stable, mutually compatible releases, not development branches or arbitrary major-version overrides. Laravel 13 is a supported stable release, not a claim that Laravel labels it LTS.

Work starts at `20f16a6` and is isolated on `upgrade/lts-20261109`. No commit, push, merge, deployment, or system-wide runtime replacement is included. The original checkout remains untouched. No application schema or business-policy changes were needed for the version migration.

The complete locked dependency graphs are in `composer.lock` and `package-lock.json`. `composer outdated --direct` is empty. The only npm direct-version exception is the intentionally ABI-matched portable Rolldown binding, explained below.

## Runtime and build targets

| Tool | Previous declaration / resolved version | Qualified target |
|---|---|---|
| PHP | `^8.5` | **8.5.12**, minimum and Composer platform pin |
| Composer | Not pinned in CI | **2.11.1**, pinned in CI |
| Node.js | `.nvmrc` 22 | **26.13.0 LTS**, `.nvmrc` and engines |
| npm | Not pinned | **12.2.2**, packageManager / engines / CI |
| CI OS | Floating `ubuntu-latest` | **Ubuntu 26.04 LTS**, explicit available runner label |
| RoadRunner server | No locked installer/bridge | **3.0.1** binary, installed and smoke-tested locally; download command pinned in README |
| Vite | 5.4.8 | **8.3.7** |
| Laravel Vite plugin | 1.0.5 | **3.2.1** |
| Tailwind CSS | 3.4.13 | **4.3.5** |
| Tailwind Vite integration | Absent | **4.3.5** |
| Tailwind Forms | 0.5.9 | **0.5.11** |
| Axios | 1.7.7 | **1.21.0** |
| Chokidar | 3.6.0 | **6.0.0** |

`.npmrc` enforces engine compatibility. Dependency installation/deployment uses lockfiles: `composer install` and `npm ci`, not `npm install` as a deployment-time resolver.

### Composer packages

| Package | Before | After |
|---|---|---|
| `laravel/framework` | 12.52.0 | **13.40.0** |
| `laravel/octane` | 2.13.5 | **2.23.0** |
| `laravel/tinker` | 2.11.1 | **3.0.2** |
| `livewire/livewire` | 3.7.10 | **4.4.8** |
| `spiral/roadrunner-cli` | Absent | **3.1.0** |
| `spiral/roadrunner-http` | Absent | **4.2.1** |
| `fakerphp/faker` | 1.24.1 | **1.24.1** (already current) |
| `larastan/larastan` | 3.9.2 | **3.14.0** |
| `laravel/pint` | 1.27.1 | **1.32.1** |
| `laravel/sail` | 1.53.0 | **1.69.0** |
| `mockery/mockery` | 1.6.12 | **1.6.15** |
| `nunomaduro/collision` | 8.9.1 | **8.9.5** |
| `pestphp/pest` | 3.8.5 | **5.3.5** |
| `pestphp/pest-plugin` | 3.0.0 | **5.0.0** |
| `pestphp/pest-plugin-laravel` | 3.2.0 | **5.0.1** |

PHPUnit resolves transitively to **13.4.5**. All transitive Composer dependencies were re-resolved together against PHP 8.5.12; no platform-check bypass was used.

### CI actions

Current release tags were resolved through GitHub's API and pinned to verified commit SHAs in `.github/workflows/laravel.yml`:

- `actions/checkout` **v7.0.1**.
- `actions/setup-node` **v7.1.0**.
- `shivammathur/setup-php` **2.40.0**.

CI checks the Composer manifest, installs both lockfiles, audits both dependency graphs, builds assets, runs tooling checks, migrates SQLite, and runs Pest. Remote CI has **not** run: this work has not been pushed.

## Compatibility changes

### Tailwind / assets

- Replace the old PostCSS/Tailwind chain with `@tailwindcss/vite` and CSS `@import` / `@plugin` / `@source` directives.
- Remove obsolete `postcss.config.js` and `tailwind.config.js`; this project had no custom theme extension to preserve.
- Remove direct PostCSS/Autoprefixer dependencies; Vite owns PostCSS transitively and Tailwind 4 performs its own prefixing.
- Remove the unrelated npm `livewire` (LiveScript router) and deprecated npm `tailwind` packages. These were not Laravel Livewire or Tailwind CSS.
- Migrate removed/renamed utilities across 18 Blade templates: `flex-shrink-0`, old shadow/radius scales, and forced-colors-safe outline behavior. Preserve the original border/placeholder/button defaults in CSS.
- Update Alpine's deprecated click-away spelling to `.outside`.
- Preserve the old newsletter width with `w-auto`: `w-100` was ignored by Tailwind 3 but becomes a fixed width in Tailwind 4. A real mobile browser assertion went RED on horizontal overflow before this fix, then GREEN afterward.
- Tailwind 4 raises the browser floor: Chrome/Edge 111+, Firefox 128+, Safari 16.4+. The Vite target records that floor. Qualification below uses Chromium; Safari/Firefox were not physically tested.

### Livewire

The application uses controller/Blade page shells, self-closing component tags, supported model bindings, and modern dispatch/listener APIs. No route-model rewrite or separate Alpine npm dependency was necessary.

Fix `<livewire:home.loginform />` to `<livewire:home.login-form />`; the old page returned an exception because the class is `Home\\LoginForm`. A new HTTP/rendering regression test covers the mounted form and its snapshot. Other existing authentication/UI bugs are documented in [PROJECT_MAP.md](PROJECT_MAP.md), not hidden by the version refresh.

### Vite native binary and the portable lane

On this older QEMU virtual CPU, importing Rolldown's native Linux binary terminates Node with `SIGILL` (exit 132). Tailwind Oxide and Lightning CSS imports succeed independently; this was isolated to Rolldown rather than guessed to be an application error.

Keep normal native `npm run build` / `npm run dev` for supported CPUs. Add explicit portable equivalents, `npm run build:portable` / `npm run dev:portable`, using Rolldown's official `NAPI_RS_FORCE_WASI=error` mechanism. The wrapper preloads only Rolldown under that flag and restores the previous environment before loading Vite/Tailwind. It is a Node script rather than a POSIX-only environment assignment.

The stable WASM binding is pinned to **1.2.18**, exactly matching Vite 8.3.7's locked Rolldown **1.2.18**. npm advertises binding 1.3.1 as newer, but substituting a different binding version is not a compatible upgrade. The wrapper explicitly rejects a version mismatch. Update this pin together with Vite/Rolldown; do not override ABI compatibility to make `npm outdated` look empty.

## Qualification

Executed locally with the isolated PHP 8.5.12 / Composer 2.11.1 / Node 26.13.0 / npm 12.2.2 toolchain on Linux:

| Gate | Actual result |
|---|---|
| Empty-vendor `composer install` from the final lock | **PASS**; package discovery and autoload generation complete |
| Fresh `npm ci` | **PASS** |
| `composer validate --strict` | **PASS** |
| `composer check-platform-reqs` | **PASS** |
| `composer audit --locked` | **PASS**, no advisories |
| `npm audit` | **PASS**, zero vulnerabilities |
| Native `npm run build` on this VM | **Environment limitation**: native Rolldown `SIGILL`; not reported as a passing native build |
| Portable real Vite build and manifest/chunk assertions | **PASS**, through `npm run test:tooling` |
| Real Octane PHP-glob file watcher with Chokidar 6 | **PASS**, through `npm run test:tooling` |
| Portable Vite development server | **PASS**, HTTP 200 for the Vite client, application JS, and transformed application CSS |
| Tooling tests | **2 passed** |
| Full `php artisan test --compact` | **66 passed, 185 assertions; 6 existing TODO tests** |
| All migrations on a fresh disposable SQLite file | **PASS**, all 15 migrations applied |
| New PHP test: scoped Pint, PHPStan, `php -l` | **PASS** |
| Node syntax / deployment shell syntax / tracked whitespace | **PASS** |
| Application PHPStan level 6 | **Not globally clean**: 78 findings before and after; zero newly introduced findings |
| Repository-wide Pint | **Not globally clean**: old Pint reported 86 files; latest Pint reports the same 94 failing paths on both the untouched baseline and upgraded tree. The new PHP test passes. No broad formatting rewrite was applied. |
| Browser / actual Octane + RoadRunner 3.0.1 `--watch` | **6 cases passed**: `/`, `/login`, `/forgot-password` at 1440×1000 and 390×844; HTTP 200, Livewire loaded, zero JS errors, zero horizontal overflow |
| Before/after visual comparison | Actual Laravel 12/Livewire 3/Tailwind 3 baseline rebuilt in isolated scratch; newsletter regression fixed; existing contact overlap distinguished from new regressions |
| Preview cleanup | HTTP/RPC listeners closed after the browser run |
| Remote CI, macOS/Windows, other browsers, deployment | **Not executed** |

The tests and browser checks do not prove complete authorization coverage, working sign-up/login UX, successful real email delivery, production performance, or release readiness. See [existing release blockers](PROJECT_MAP.md#existing-release-blockers-and-follow-up-order).

### Reproduction

With the pinned runtimes selected and a private local/testing environment configured:

```bash
composer install --prefer-dist --no-interaction --no-progress
npm ci
composer validate --strict
composer check-platform-reqs
composer audit --locked
npm audit
npm run test:tooling
php artisan test --compact
php vendor/bin/pint --test tests/Feature/Auth/LoginPageTest.php
php vendor/bin/phpstan analyse --no-progress tests/Feature/Auth/LoginPageTest.php
```

Do not run migrations or test database reset helpers against a real user database. Browser previews used only a disposable SQLite file, no real mail transport, and a loopback-only temporary server.

## Authoritative sources

Versions were verified from upstream release metadata rather than inferred from old documentation:

- [PHP support policy](https://www.php.net/supported-versions.php).
- [Node release/LTS table](https://nodejs.org/en/about/previous-releases).
- [Laravel 13 releases](https://laravel.com/docs/13.x/releases) and [upgrade guide](https://laravel.com/docs/13.x/upgrade).
- [Livewire 4 upgrade guide](https://livewire.laravel.com/docs/4.x/upgrading).
- [Tailwind upgrade guide](https://tailwindcss.com/docs/upgrade-guide).
- [Vite 8 migration](https://vite.dev/guide/migration).
- [Rolldown supported platforms and official WASM fallback](https://rolldown.rs/guide/getting-started).
- [Pest upgrade guide](https://pestphp.com/docs/upgrade-guide).
- [Laravel Octane](https://laravel.com/docs/13.x/octane) and [RoadRunner v3.0.1](https://github.com/roadrunner-server/roadrunner/releases/tag/v3.0.1).
- [Ubuntu release policy](https://ubuntu.com/about/release-cycle) and [available GitHub runner images](https://github.com/actions/runner-images).
- [Packagist package metadata](https://repo.packagist.org/packages.json), [npm registry](https://registry.npmjs.org/), and the respective GitHub release/tag APIs.
