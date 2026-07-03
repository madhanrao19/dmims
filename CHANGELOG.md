# Changelog

All notable changes to DMIMS (Datamation Inventory Management System) are
documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/),
and the project aims to follow [Semantic Versioning](https://semver.org/).

## [2.1.11] - 2026-07-03

### Security
- **API rate limiting.** `/api/v1/*` had no rate limiting at all. Added a
  named `api` limiter (`AppServiceProvider::boot()`) — 60 requests/minute per
  authenticated user (falling back to IP), configurable via
  `API_RATE_LIMIT_PER_MINUTE` — and applied `throttle:api` to the `v1` route
  group. Added `ApiV1Test::test_exceeding_the_rate_limit_returns_429`.

## [2.1.10] - 2026-07-03

### Security
- **API tokens now default to a restricted ability and an expiration.**
  `dmims:issue-api-token` previously issued full-access (`['*']`) tokens that
  never expired. It now defaults to a single `api:read` ability (all current
  `/api/v1/*` endpoints are read-only) and an explicit `expires_at` (365 days,
  configurable via `SANCTUM_TOKEN_EXPIRATION`, blank disables it). Sanctum's
  ability-check middleware (`abilities`/`ability`, not previously registered)
  is now aliased in `bootstrap/app.php`, and `routes/api.php` requires
  `abilities:api:read`. `sanctum:prune-expired --hours=24` is scheduled daily.
  **Existing tokens are unaffected**: Sanctum's global `sanctum.expiration`
  config is deliberately left `null` (it ANDs an age check based on
  `created_at` independently of a token's own `expires_at` — setting it
  globally would have retroactively invalidated already-issued tokens
  regardless of their `expires_at`, verified directly against Sanctum's
  `Guard::isValidAccessToken()`). The new expiration only applies via each new
  token's own `expires_at`, going forward. Old tokens issued before this
  change default to Sanctum's own `['*']` ability, which still passes the new
  ability check. Added `ApiTokenAbilityTest` covering ability enforcement,
  backward compatibility, and the command's new defaults.

## [2.1.9] - 2026-07-03

### Fixed
- **Billing/payment numbering race condition.** `BillingService::generateInvoiceNo()`
  and `PaymentService::generatePaymentNo()` used `count()+1` — the exact pattern
  already replaced elsewhere in the codebase with `SequenceGenerator` (a
  row-locked counter) because it collides under concurrent writes. Both now use
  `SequenceGenerator`, matching `StockMovementService`/`DocumentMovementService`.
  A one-time migration seeds the counters from existing data (the max of the
  row count and the highest numeric suffix parsed out of existing
  `invoice_no`/`payment_no` values, per year) so the switch doesn't collide with
  or reuse numbers already issued. Added regression tests for gapless
  sequencing and for the seeding migration's collision avoidance.

## [2.1.8] - 2026-07-02

### Added
- **`LICENSE` file** at the repo root, matching the existing proprietary
  declaration in `composer.json` and the README's "© Datamation Group" notice.

### Changed
- **`composer.json`** no longer overrides `config.audit.block-insecure` to
  `false`; Composer's own default (`true`) now applies, so `composer
  install`/`update`/`require` refuses to install a package with a known
  security advisory — matching what CI's `composer audit` step already
  enforces for the build, but now also enforced for local/dev installs.
  `composer audit` remains clean (no advisories).

## [2.1.7] - 2026-07-02

### Changed
- **Removed remaining "dmims-code" naming** from deployment tooling — a leftover
  from a pre-rename local folder/repo name. `deploy-ubuntu-24.sh`'s `REPO_DIR`
  default and usage examples now use `/var/www/dmims` (matching
  `DEPLOYMENT_GUIDE.md` throughout, instead of the inconsistent
  `/var/www/dmims-code`). `DEPLOYMENT_GUIDE.md`'s Windows SCP examples now use a
  generic `C:\path\to\dmims\` placeholder instead of a personal
  `d:\Dev\IMS\Source Code\dmims-code\` path. `composer.json`/`package.json`
  project identity (`datamation/dmims` / `dmims`) was already clean. No other
  "dmims-code" references exist anywhere in the tracked codebase.

## [2.1.6] - 2026-07-02

### Fixed
- **Deployment blocker on MySQL/MariaDB: index name too long.** The auto-generated
  unique index on `product_location_stocks (customer_id, product_id, location_id)`
  was 65 characters — one over MySQL/MariaDB's 64-char identifier limit — so
  `php artisan migrate` would abort on a real MySQL/MariaDB deploy (SQLite, used by
  the test suite, has no such limit, so it was never caught). Gave it an explicit
  short name (`product_location_stocks_cpl_unique`) and also named the
  `document_movement_logs` composite index (which was exactly at the 64 limit) as
  `doc_movement_logs_movable_index`. Verified: after the fix the longest generated
  index name is 60 chars and the longest foreign-key name is 52 — all within limit.

### Deployment readiness (verified, no code change)
- Dry-ran the full deploy sequence: `composer install --no-dev` resolves (no dev
  deps used at runtime), `migrate:fresh` + `RolesAndPermissionsSeeder` +
  `dmims:create-admin` run without Faker, and `config:cache` / `route:cache` /
  `view:cache` / `storage:link` / `filament:assets` all succeed. PWA icons are
  served from the committed `public/icons/` (not build output), so `npm run build`
  in the deploy script is sufficient. No TEXT/JSON column defaults (MySQL-safe).

## [2.1.5] - 2026-07-01

### Changed
- **Applied the previously-deferred dependency upgrades that validate cleanly.**
  - `phpunit/phpunit` **11 → 13.2** (dev). Installs without conflict; all 104
    tests pass unchanged, no config changes needed.
  - `sharp` **0.34 → 0.35.3** (dev, PWA icon generation). Validated by running
    `npm run icons:generate` — all icons (192/512/apple-touch) generate correctly.
  - npm minors bumped in the lockfile: `vite` 8.0 → **8.1.2**, `tailwindcss` /
    `@tailwindcss/vite` 4.3.1 → **4.3.2** (within existing constraints). Validated
    by CI's `npm ci` + `npm run build:assets` (the Vite build's build-time font
    fetch is only reachable in CI, not the local sandbox).

### Not upgraded (blocked)
- `openspout/openspout` **stays on 4.x**: `filament/actions` (Filament 5) requires
  `openspout ^4.23`, so 5.x is not installable while on Filament 5. Revisit when
  Filament relaxes that constraint.

## [2.1.4] - 2026-07-01

### Changed
- **Dependency refresh (within-constraint updates).** Ran `composer update`; 28
  packages updated, including `laravel/framework` 13.15 → 13.18, `filament/filament`
  → 5.6.7, `spatie/laravel-permission` 8.0 → 8.1, and `laravel/pint` → 1.29.3.
  No composer or npm security advisories (`composer audit` / `npm audit` clean).
  All 104 tests pass on the updated stack.

### Deferred (documented, not applied)
- Major upgrades that fall outside the current version constraints and would need
  an intentional, separately-tested change are **not** included here:
  `phpunit/phpunit` 11 → 13, `openspout/openspout` 4 → 5, and the npm `sharp`
  0.34 → 0.35 major. None are security fixes.
- npm minor/patch bumps (`vite` 8.0 → 8.1, `tailwindcss` / `@tailwindcss/vite`
  4.3.1 → 4.3.2) are deferred: they are not security fixes and the Vite build
  cannot be verified in the current sandbox (the network policy blocks the
  build-time `fonts.bunny.net` fetch). Apply them in a normal dev/CI environment
  where `npm run build` can be validated.

## [2.1.3] - 2026-07-01

### Fixed
- **Boxes were unreachable by the roles meant to manage them.** `BoxResource`
  required the `manage inventory` permission despite being a document-tracking
  resource (Document Tracking nav group, gated on the `document_tracking`
  module). The Document Tracking User (`manage documents`) and Viewer
  (`view documents`) roles were therefore locked out of Boxes. It now uses the
  `manage documents` permission, matching the sibling document resources. Added
  `RbacViewOnlyTest` cases for box access.

### Security
- **Billing money-path service guards (defence-in-depth).** `BillingService::issue`
  now rejects non-draft invoices, `BillingService::cancel` rejects
  already-cancelled invoices, and `PaymentService::recordPayment` rejects payments
  against cancelled invoices — previously these were only guarded in the Filament
  UI. Added `BillingServiceTest` cases.

### Removed
- Dead `RecentlyViewed` model (unwired — `record()` was never called and the
  model had no references, UI, factory or tests). The `recently_viewed` table is
  left in place; see `docs/CONFORMANCE_GAP_ANALYSIS.md` for the unused-schema
  report.

## [2.1.2] - 2026-07-01

### Fixed
- **Expired licenses could retain full access (licensing enforcement gap).**
  `AccessControlService::modeFromLicense()` gated its date-based expiry check on
  the license `status` column (`&& ! in_array($status, ['active','trial','near_expiry'])`),
  so it never fired while a license was still marked `active` — and nothing
  transitions `status` to `expired` automatically. A lapsed license therefore
  kept `MODE_FULL`. The date is now authoritative: once `valid_to` (+ grace
  period, inclusive to end-of-day) is past, access degrades to `view_only`
  regardless of the `status` column, matching `LicenseService::isLicenseValid()`.
  Added `AccessControlTest` cases for expired-but-active, valid-through-today and
  within-grace-period licenses.

### Changed
- Billing form inputs now enforce non-negative money: invoice `amount`/`tax_amount`
  use `minValue(0)` and the Record Payment amount uses `minValue(0.01)`.

## [2.1.1] - 2026-07-01

### Security
- **Tenant write-protection hardened.** `customer_id` is mass-assignable on the
  operational models, and the `BelongsToCustomer` creating hook previously only
  filled it when empty — so a crafted create could plant a record in another
  tenant. The hook now *always* binds a non-platform user's records to their own
  `customer_id`, overriding any supplied value. Platform users and
  unauthenticated contexts (seeders, queued jobs, console) are unchanged. Added
  `TenantScopeTest::test_customer_user_cannot_write_into_another_tenant`.

### Changed
- `InjectPwaScript::handle()` now declares its `Response` return type (the PWA
  injection remains guarded to `text/html`, so downloads are untouched).

## [2.1.0] - 2026-07-01

### Changed
- **Root project files aligned with the `/docs` governance system and the
  tested Ubuntu deployment.** Removed contradictions between `README.md`,
  `DEPLOYMENT_GUIDE.md`, `SECURITY.md` and `/docs`, and made
  **Ubuntu 24.04 + Apache + PHP 8.4 + MariaDB + Cloudflare Tunnel** the single
  documented production stack (previously "MySQL"; MariaDB is used via the
  MySQL-compatible `mysql` driver).
- **`CLAUDE.md` trimmed to a concise pointer** to the governance documents in
  `/docs` (Engineering Constitution, Project Governance, Definition of Done,
  Conformance Gap Analysis) instead of duplicating them.
- **`SECURITY.md`** replaced the GitHub stub template with a real security
  policy: supported versions (2.x), private vulnerability reporting, the
  seven-layer defence-in-depth model, and production security notes.
- **`.env.example`** now uses safe local defaults (`APP_NAME=DMIMS`, SQLite,
  `SESSION_SECURE_COOKIE=false`, empty `TRUSTED_PROXIES`, `MAIL_MAILER=log`)
  with explicit `PRODUCTION:` notes for MariaDB, HTTPS cookies, trusted proxies
  and SMTP mail.
- **`deploy-ubuntu-24.sh`** installs `mariadb-server` (was `mysql-server`),
  documents the required install order, splits PHP-dependency install from asset
  build so `composer install` always precedes any `php artisan` call, and sets
  `APP_NAME` / `SESSION_SAME_SITE` in the generated `.env`.
- Added a **Deployment Lessons Learned** section to `DEPLOYMENT_GUIDE.md`
  (composer before artisan; `vendor/autoload.php` must exist on the server;
  `SESSION_SECURE_COOKIE` false for local HTTP / true for HTTPS-only; short
  explicit MySQL/MariaDB index names; `AssignRequestContext` must not use
  `withHeaders()`; publish Filament assets every deploy; make PHP 8.4 default).
- `composer.json` / `package.json` project identity updated from the Laravel
  skeleton to DMIMS.

### Fixed
- **Report/export/backup downloads could fatal on every request.**
  `AssignRequestContext` (registered globally) called
  `$response->withHeaders(...)`, which only exists on Illuminate responses — on
  the `StreamedResponse` / `BinaryFileResponse` returned by downloads it would
  throw. Now sets the correlation-ID header via `$response->headers->set(...)`,
  which works on every Symfony response type.

## [2.0.0] - 2026-06-15

### Changed
- **Upgraded Filament 3 → 5 and Laravel 12 → 13; now requires PHP 8.4.**
  Performed in two stages using Filament's official automated upgrade tools
  (v3→v4 then v4→v5): every resource/page/widget migrated to the Schema-based
  forms, the unified `Filament\Actions` namespace, `recordActions()`, and the v5
  APIs. Also bumped Spatie Permission → 8, Tinker → 3; pinned
  `config.platform.php` to 8.4.22. All 59 tests pass on Filament 5.6 /
  Laravel 13.15 / PHP 8.4; 0 composer advisories.
- Raised the PHPUnit `memory_limit` to 512M (Filament 4/5 are heavier at boot).

> **Note:** PHP 8.4 is now the minimum. Production already targets 8.4; ensure
> the runtime is 8.4+ before deploying this release.

## [1.1.0] - 2026-06-14

### Added
- **Role-based view-only access** (Security & Access Control Matrix). Each area
  now has a `manage X` and a `view X` permission; reads are allowed on either,
  writes only on `manage X`. The Management and Viewer roles get genuine
  read-only access instead of no access. Adds `RbacViewOnlyTest`.
- **PDF and Excel report output.** `ReportExportService` now renders every named
  report as CSV, **XLSX** (openspout) or **PDF** (dompdf); the Reports page has a
  format selector.
- **Scannable Code128 barcode label images** (picqer) now render in the barcode
  label modal (previously the value-only fallback).

### Changed
- Added `barryvdh/laravel-dompdf`, `openspout/openspout` and
  `picqer/php-barcode-generator`. These resolve to PHP 8.3-compatible versions,
  so PDF/Excel/barcode images now work on the development box too (no longer
  gated to PHP 8.4 production only).

### Fixed
- **PWA was not installable** — the manifest referenced icons under
  `/build/icons/` that did not exist (the directory is git-ignored and the
  source SVGs were missing), and `favicon.ico` was empty. Added a tracked brand
  icon set under `public/icons/` (192/512 PNG + SVG, apple-touch, mask),
  repointed the manifest and `InjectPwaScript` middleware at `/icons/`, set the
  Filament panel favicon, refreshed the service-worker precache (v3), and added
  `PwaTest` asserting installability and that the PWA tags are injected.
  Verified live: manifest, service worker, offline page and icons all serve 200.

## [1.0.0] - 2026-06-14

First consolidated release. The codebase was audited against the requirements
documents (PRD, SAD, Security & Access Control Matrix, Database Dictionary,
TDD), brought to full conformance, hardened for production, placed under version
control, and updated to current dependencies. Test suite: **52 passing**.

### Added

**Security & access control**
- `AccessControlService` (TDD §12) combining user, company, subscription,
  license, module and permission state (`canLogin`, `canView`, `canExport`,
  `canPerformOperationalAction`, `getEffectiveAccessMode`, `getEffectiveLimits`).
- License validation layer: `EnsureLicenseAllowsAccess` middleware and
  `licenses.technical_access_mode` (full / view_only / blocked); view-only mode
  enforced in resource authorisation.
- Tenant isolation hardened with a `BelongsToCustomer` global scope (auto-scopes
  reads and auto-fills `customer_id`) across operational models.
- Model-level audit trail via the `Auditable` trait (create/update/delete with
  old/new values; sensitive fields excluded).
- Seven documented roles (Datamation Super Admin, Datamation Management, Company
  Admin, Company Supervisor, Stock Inventory User, Document Tracking User,
  Viewer) with matrix-aligned permissions, via `RolesAndPermissionsSeeder`.
- `php artisan dmims:create-admin` command (replaces the ad-hoc script).

**Modules & features**
- **Billing** module: `billing_records`, `billing_payments`, `billing_logs`;
  `BillingService` (invoices `INV-YYYY-####`, total = amount + tax, issue/cancel,
  payment-status recalculation) and `PaymentService` (manual payments
  `PAY-YYYY-####`); gated Filament resource with Record Payment / Issue / Cancel.
- **Notifications**: `dmims:generate-notifications` (low stock, subscription /
  license expiry, overdue billing) scheduled hourly; export-completed and
  import-failed alerts; idempotent generation.
- **Barcode**: formatted generation (`PRD/LOC/BOX/DOC-COMPANYCODE-000001`),
  central registry, `ScannerService` (scan-to-open + logging), Barcode Scanner
  page, and Generate/Print actions on the barcodable resources.
- **Reporting**: `ReportExportService` with 14 named platform / inventory /
  document reports and a gated Reports page (CSV now; PDF/Excel when the
  converter library is installed on production).
- **Inventory operations**: guided Receive-In / Stock Out / Transfer / Adjust.
- **Document operations**: guided file & box Receive-In / Transfer / Move-Out /
  Return (File → Box → Location; external destinations stored as text).
- Real **Backup** (driver-aware: `mysqldump` / SQLite copy) with download &
  restore, plus a scheduled nightly backup (`dmims:backup-database`).
- Real **CSV Import** (per-row validation, in-file + database duplicate
  detection, error-file download) and **CSV Export**.
- `subscription_logs` append-only history.
- Branded Filament panel (Indigo/Slate palette, Inter font, dark mode,
  collapsible sidebar) and a role-aware dashboard.
- `docs/CONFORMANCE_GAP_ANALYSIS.md` requirements audit and **52 automated tests**.

### Changed
- **Upgraded Laravel 11 → 12** (12.62) and refreshed all dependencies to the
  latest within their supported majors.
- Aligned module codes to the Database Dictionary (`stock_inventory`,
  `document_tracking`, `barcode_scanning`, `barcode_printing`, `reports`,
  `billing_view`).
- Migrated deprecated Filament v2 components (`BelongsToSelect`, `MultiSelect`)
  to the v3 `Select` API.
- Made `boxes.current_location_id` and `document_files.current_box_id` nullable
  so moved-out items (which have left the system) are representable.
- Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, MySQL connection,
  `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `TRUSTED_PROXIES`.

### Fixed
- **User model mass assignment** — non-existent `#[Fillable]`/`#[Hidden]`
  attributes left the model fully guarded (admin user create/edit threw) and
  exposed the password hash; replaced with conventional `$fillable`/`$hidden`.
- **`BarcodeRegistry` table-name mismatch** — model resolved to
  `barcode_registries` but the table is `barcode_registry`; every query errored.
- **Enum mismatches** that would be rejected by MySQL — Backup status (`completed`
  vs `success`), StockAlert `status`/`alert_type`, free-text `movement_type`.
- `TextInput::numericStep()` (not a Filament 3 method) → `step()`.
- Dead/broken services that wrote non-existent columns (Notification, Barcode,
  Export, Import, Backup) rewritten against the real schema.
- `DatabaseSeeder` referenced non-existent columns and could not run; corrected
  and made idempotent; roles/permissions split into a production-safe seeder.
- Module gating now enforced on direct access, not just navigation visibility.
- Added the previously missing row Edit actions on several resources.

### Security
- Patched **CVE-2026-48019** (Laravel CRLF injection in the default email rule)
  by upgrading to Laravel 12.62. No composer advisories remain.
- Fixed a critical npm advisory in `shell-quote` (override to `^1.8.4`); no npm
  vulnerabilities remain.
- Enforced all seven SAD access-control layers; trimmed per-request audit noise
  in favour of model-level auditing.

### Removed
- Throwaway artifacts: `composer.phar`, `composer-setup.php`, `*.patch`,
  `syntax_check*.txt`, `phpunit_*.txt`, route dumps, `finish_changes.ps1`,
  `test-results.xml`, `create-admin.php`.
- The unused `composer-unused/composer-unused-plugin` dev dependency (also
  unblocked dependency updates).
- `PR_DESCRIPTION.md` and `COMMIT_INSTRUCTIONS.md` (transient process notes).

### Notes
- The project was previously not under version control; it is now a git
  repository with a complete history of this work.
- PDF/Excel report rendering and scannable Code128 label images activate
  automatically when `picqer/php-barcode-generator` and a PDF library are
  installed on the PHP 8.4 production server (they cannot resolve on the PHP 8.3
  development box); CSV reports and barcode values work everywhere.
