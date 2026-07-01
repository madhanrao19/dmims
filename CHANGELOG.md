# Changelog

All notable changes to DMIMS (Datamation Inventory Management System) are
documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/),
and the project aims to follow [Semantic Versioning](https://semver.org/).

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
