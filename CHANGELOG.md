# Changelog

All notable changes to DMIMS (Datamation Inventory Management System) are
documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/),
and the project aims to follow [Semantic Versioning](https://semver.org/).

## [2.1.25] - 2026-08-18

### Fixed
- **`dmims:create-admin` created a "platform administrator" who could view
  everything but write nothing, silently (High).** The command only set
  `is_platform_user = true`, never assigning a role. Per
  `BaseResource::can()`, `is_platform_user` alone only grants read access —
  every write action (create/edit/delete) additionally requires a real
  Spatie permission, which only comes from an assigned role. Reported by a
  user whose staging `dm_it@datamationgroup.com` account couldn't add or
  manage users, locations, etc. Root-caused: not staging-specific, the
  command itself never assigned one. Now assigns `Datamation Super Admin`
  by default when that role exists; if `RolesAndPermissionsSeeder` hasn't
  run yet, warns clearly with the exact remediation command instead of
  silently leaving a read-only "admin". Regression test added
  (`CreateAdminUserCommandTest`) covering both paths.

**If you already created an admin with the old behaviour** (e.g. the
`dm_it@datamationgroup.com` account on staging), fix it in place — no need
to recreate the user:

```bash
php artisan tinker --execute="App\Models\User::where('email','dm_it@datamationgroup.com')->first()->assignRole('Datamation Super Admin')"
```

## [2.1.24] - 2026-08-18

### Fixed — closing the last deferred findings from the full-app review
- **`BillingRecordResource` didn't set `$applyCustomerScope = true` (Medium).**
  The one resource inconsistent with the pattern used everywhere else; not
  currently exploitable since `BillingRecord` already has the model-level
  `BelongsToCustomer` trait, but this closes the defense-in-depth gap.
- **`License`/`CustomerSubscription`'s `enabled_modules`/`allowed_reports`
  textareas accepted any free text, no JSON validation (Medium).** Since
  these gate real module/report access, malformed JSON here could silently
  fail open or closed depending on how the consumer decodes it. Added a
  shared `BaseResource::jsonRule()` validation closure (valid JSON or
  blank; used by both fields on both resources) — functionally verified via
  tinker against blank, valid, and invalid input.
- **`CustomerResource` had no `->unique()` validation on `company_code`
  (Low).** The DB already enforces uniqueness; this was a UX gap (raw
  constraint error instead of a clean form message). Added
  `->unique(ignoreRecord: true)`.

Full suite (127/127), Pint, and Larastan verified green. No findings remain
open from the full-app review — see `docs/CONFORMANCE_GAP_ANALYSIS.md` for
the complete history.

## [2.1.23] - 2026-08-18

### Fixed — database integrity (remaining Low DBA findings)
- **`stock_movements`, `document_movement_logs`, `audit_logs` had no DB-level
  immutability (Low).** Documented as append-only, but only application
  discipline enforced it (confirmed no update/delete call exists anywhere
  in app code or tests for these three models). Added BEFORE UPDATE /
  BEFORE DELETE triggers (MySQL/MariaDB `SIGNAL`, SQLite `RAISE(ABORT, ...)`)
  that reject any write. Functionally verified via tinker that both an
  UPDATE and a DELETE are now rejected with a clear error. `TRUNCATE`
  bypasses per-row triggers on both drivers, so this doesn't interfere with
  `migrate:fresh` or test database resets.
- **`2026_06_14_000003_make_placement_columns_nullable`'s `down()` wasn't
  safely reversible once data exists (Low).** Once any box/file has
  actually been moved out, re-applying `NOT NULL` would fail with a raw DB
  constraint error. `down()` now checks for existing NULL rows first and
  fails fast with a clear, actionable message instead — there's no safe
  automatic choice of location/box to backfill those rows with, so this
  intentionally stays a guarded one-way migration rather than pretending to
  auto-resolve a business decision.
- New migration `2026_08_18_000010_enforce_immutable_log_tables`. Verified
  migrate → rollback → re-migrate clean, full suite (127/127), Pint, and
  Larastan.

No DBA findings remain open. See `docs/CONFORMANCE_GAP_ANALYSIS.md` for the
full review history.

## [2.1.22] - 2026-08-18

### Fixed — database integrity (remaining High/Medium DBA findings)
- **`created_by`/`updated_by` unconstrained on 9 master tables (High).**
  `customers`, `departments`, `customer_modules`, `customer_subscriptions`,
  `licenses`, `locations`, `barcode_registry`, `categories`, `products` —
  unlike every sibling table using the same column pair. Added FKs to
  `users`, guarded (nulls any value already pointing at a nonexistent user
  first, logged).
- **`users.department_id` had no FK/index (High).** Added both, same guard
  pattern.
- **`subscription_logs` cascade-deleted with its parent while the
  structurally identical `license_logs` restricts (High).** Changed
  `subscription_logs.customer_subscription_id` to restrict, consistent with
  `license_logs` and the billing audit-trail fix in 2.1.21.
- **`users` table missing `status`/`last_login_at` indexes (Medium).** Added
  (purely additive).
- **`document_types.type_code` had no uniqueness constraint (Medium).**
  Added `unique(['customer_id', 'type_code'])` — tenant-scoped, matching
  every other tenant-owned lookup model. Guarded: checks for existing
  duplicates first; skips and logs instead of failing if any are found.
- **No DB-level "one active license/subscription per customer" constraint
  (Medium).** Added a generated column (non-null only when
  `status = 'active'`) with a unique index on `licenses` and
  `customer_subscriptions` — MySQL/MariaDB/SQLite all exclude NULLs from a
  unique index, so this enforces "at most one active row per customer"
  without touching non-active rows. Guarded: skips and logs if a customer
  already has more than one active row.
- New migrations `2026_08_18_00000{4-9}_*`. All verified migrate → rollback
  → re-migrate clean, plus a full fresh-migrate via the test suite's
  `RefreshDatabase` (127/127 passing).
- Remaining Low findings not fixed (immutable log tables rely on app-layer
  discipline only, not a DB-level immutability mechanism; one earlier
  migration's `down()` isn't safely reversible once data exists) — see
  `docs/CONFORMANCE_GAP_ANALYSIS.md`.

## [2.1.21] - 2026-08-18

### Fixed — security & database integrity (full-app review: search/filter/upload/download + DBA schema pass)
- **`Department` model had no tenant scope (Medium).** Unlike every other
  tenant-owned lookup model, `Department` didn't use `BelongsToCustomer` —
  the `department_id` Select in `DocumentFileResource`/`UserResource` and
  the matching table filter showed every tenant's department names, and
  nothing stopped a tenant user assigning a department belonging to another
  company. Added the trait (same one-line pattern as `Category`/`Tag`).
- **Import CSV upload had no size limit (Low).** `ImportResource`'s
  `FileUpload::make('file')` restricted MIME type but not size; the file is
  read synchronously in-request. Added `->maxSize(5120)`.
- **Database schema hardening (DBA review, Critical):**
  - `users.customer_id` had no FK constraint or index despite being the
    column every tenant global scope depends on. Added both; guarded by
    first nulling any `customer_id` that already points at a nonexistent
    customer (logged), so the constraint can't fail against existing data.
  - `boxes.box_barcode`/`box_number` and `document_files.file_barcode` were
    globally unique instead of unique-per-tenant — cross-tenant collisions
    and a barcode-existence leak. Rescoped to `unique(['customer_id', ...])`;
    mathematically safe against existing data (global uniqueness implies
    per-tenant uniqueness).
  - `billing_records` had no soft delete, yet `billing_payments`/
    `billing_logs` cascade-deleted off it — a hard delete on a billing
    record silently destroyed the "immutable, append-only" payment/log
    history. Added `deleted_at` to `billing_records` (model + migration) and
    changed both child FKs from cascade to restrict.
  - New migrations: `2026_08_18_000001_scope_barcode_uniqueness_to_customer`,
    `2026_08_18_000002_add_customer_fk_and_index_to_users`,
    `2026_08_18_000003_protect_billing_audit_trail`. All three verified
    migrate + rollback + re-migrate clean against the local SQLite dev DB,
    and via the test suite's `RefreshDatabase` (fresh migrate from zero).
- Remaining DBA findings not fixed this pass (High: unconstrained
  `created_by`/`updated_by` on 9 tables, inconsistent cascade behaviour
  between `subscription_logs`/`license_logs`, `users.department_id` missing
  FK/index; Medium/Low: no DB-level "one active license per customer"
  constraint, `document_types.type_code` uniqueness, one migration's
  `down()` not safely reversible once data exists) — see
  `docs/CONFORMANCE_GAP_ANALYSIS.md` for the full list.

## [2.1.20] - 2026-08-18

### Fixed — security (CRUD/tenant-isolation review across all Filament resources)
- **`customer_id` was never re-derived on update, only on create (Critical).**
  `BelongsToCustomer`'s model-boot hook only forced `customer_id` back to the
  authenticated tenant user's own company in `creating()`. Any tenant user
  editing a record they already owned (Box, Category, DocumentFile,
  DocumentMovementLog, Location, Product, ProductLocationStock,
  StockMovement, StockAlert, StockAdjustmentApproval) could submit a
  different `customer_id` and silently reassign the record into another
  tenant. Added a matching `updating()` hook. `User` doesn't use this trait
  (no tenant-scoped query on the model), so `UserResource`'s
  `CreateUser`/`EditUser` pages got the same guard directly, alongside the
  existing `is_platform_user` protection.
- **`StockMovementService::record()` never checked the product's tenant
  (Critical).** It deliberately bypasses global scopes to resolve
  `customer_id` from the product, but never verified the acting user's
  tenant matched — a Stock Inventory User could move stock for a product
  belonging to a different company. Now throws `AuthorizationException` on
  a tenant mismatch for non-platform actors.
- **Five resources let stock quantities/movement history be edited outside
  the service/observer layer that keeps them consistent (Critical).**
  `ProductLocationStockResource` and `StockMovementResource` exposed
  generic create/edit forms that bypassed `StockMovementService` and
  `StockMovementObserver` (which only handles `created()`, not `updated()`),
  letting quantities be overwritten or movement history rewritten with no
  audit trail and no negative-stock guard. `DocumentMovementLogResource`,
  `LicenseLogResource`, and `StockAdjustmentApprovalResource` similarly
  exposed manual CRUD on what are meant to be system-written audit/approval
  trails (`StockAdjustmentApprovalResource` in particular was found to be
  fully disconnected — nothing in the app reads or writes it besides the
  resource itself). All five are now list-only (`ProductLocationStock`,
  `StockMovement` keep their existing header-action-driven receive/out/
  transfer/adjust flow, which already routed through the service layer
  correctly). `SupportAccessLogResource` kept `create` (the only mechanism
  that logs support access at all) but dropped `edit` (the backdating
  vector on existing entries).
- **`Setting` model had no tenant scope (High).** Every other tenant-scoped
  model uses `BelongsToCustomer`; `Setting` relied solely on
  `SettingResource`'s query-time scoping. Added the trait for defense in
  depth.
- **Raw numeric foreign-key inputs accepted any integer, with no existence
  check (High).** `NotificationResource.user_id`, `StockAlertResource.
  product_id`/`location_id`, and `SupportAccessLogResource.support_user_id`/
  `target_user_id` now validate with `->exists(...)`.
- `tests/Feature/ResourceFormRenderTest.php`'s floor assertion updated (20 →
  15) to match the now-smaller, intentional set of resources exposing a
  create page.

## [2.1.19] - 2026-08-05

### Fixed — billing (found during a go-live verification pass)
- **`PaymentService::recordPayment()` had no guard against overpayment,
  negative/zero amounts, or paying an already fully-paid invoice.** Only the
  cancelled-invoice case was guarded. A direct service call, API
  integration, or a future Filament change would have accepted a negative
  payment, an amount exceeding the outstanding balance, or a duplicate
  payment on a settled invoice. Now rejects all three with a
  `RuntimeException`, matching the existing cancelled-invoice guard's style.
  `BillingRecordResource`'s payment form gained a matching `maxValue()`
  (outstanding balance) alongside its existing `minValue(0.01)`, so
  overpayment fails as inline form validation rather than a thrown
  exception in the common case. Regression tests added
  (`test_cannot_pay_an_already_fully_paid_invoice`,
  `test_negative_or_zero_payment_amount_is_rejected`,
  `test_overpayment_beyond_outstanding_balance_is_rejected`).

## [2.1.18] - 2026-08-04

### Fixed — security (external production-readiness review)
- **Unlicensed customer defaulted to full technical access (Critical).**
  `AccessControlService::modeFromLicense()` returned `full` access whenever a
  customer had no `License` row at all — and nothing in the app ever
  auto-creates one, so a customer stayed fully unrestricted for as long as
  Datamation staff hadn't issued a license (indefinitely, if forgotten). This
  contradicted `LicenseService::isLicenseValid()`, which already treats a
  missing license as invalid, and the Security & Access Control Matrix's
  "License Allows?" access-decision gate. A missing license now degrades to
  `view_only` (same behaviour as a suspended/expired license per Business
  Rules & Functional Specification §8) — login and read/export still work,
  operational writes do not, until a license is issued. Regression test
  updated (`AccessControlTest::test_missing_license_degrades_to_view_only`,
  previously asserted the opposite). Traced every other call site that relies
  on `AccessControlService`/`BaseResource::can()` for non-platform tenant
  users without a seeded license and added one where the fix would otherwise
  have broken a working test (`RbacViewOnlyTest`, `UserResourcePrivilegeEscalationTest`)
  or the demo/QA tenant (`DatabaseSeeder` now seeds a `full`-mode license for
  the `DEMO` customer, so `QASampleUsersSeeder` and the role-based Playwright
  suite keep working — that tenant is meant to represent a fully licensed
  customer). **Operational impact:** any live customer currently relying on
  this gap (subscribed but never issued a license) will lose write access on
  deploy until a license is issued — this is the intended fix, not a
  regression; flag it before rollout.

### Fixed — code quality
- Removed a stray `console.log('DMIMS app entry')` from
  `resources/js/app.js`, left over from scaffolding and prohibited by
  `docs/DEFINITION_OF_DONE.md`.

### Documentation
- **Local dev setup was missing `php artisan filament:assets`.** A fresh
  `composer install` never publishes Filament's vendor CSS/JS to `public/`
  (production deploys already ran this via `deploy-ubuntu-24.sh` /
  `DEPLOYMENT_GUIDE.md`; local setup docs didn't). Symptom: the admin panel
  loads with no visible page errors, but every interactive element (buttons,
  selects, modals) silently does nothing, because Filament's Alpine.js
  components (`filamentFormButton`, `selectFormComponent`, etc.) never
  register — only visible via browser console errors. Found while running
  the role-based Playwright suite against a fresh clone. Added the missing
  step and a troubleshooting entry to `README.md` and
  `docs/DMIMS Developer Getting Started Guide.md`.

## [2.1.17] - 2026-07-30

### Removed
- **`favorites` and `recently_viewed` tables**, `Favorite` model, `Favoritable`
  trait (and its use in `Box`/`DocumentFile`). Both were unused scaffolding
  flagged in the v2.1.3 cleanup report and left in place pending a decision;
  `recently_viewed` was orphaned since `RecentlyViewed` was removed in v2.1.3,
  and `favorites`/`Favoritable` were mixed into two models but never called
  from any UI, route, or test. Dropped via a reversible migration
  (`2026_07_30_000000_drop_favorites_and_recently_viewed_tables.php`) — its
  `down()` recreates both tables to their original schema. Confirmed zero
  code references before dropping; full suite (122/122), `migrate` +
  `migrate:rollback` + re-`migrate`, and `pint` all verified clean.
- Also this session: five dead classes with zero references anywhere
  (`CompanyContextService`, `LocationService`, `SubscriptionService`,
  `UserSecurityService`, `EnsurePermission` middleware) and one stale
  commented-out import — see `docs/CONFORMANCE_GAP_ANALYSIS.md`'s Services
  table for what superseded them.

### Security
- Patched `dompdf/dompdf` (<3.1.6: chroot bypass, local file read via SVG
  data-URI, BMP resource-consumption DoS, font-face file-existence oracle)
  and `guzzlehttp/guzzle` (<7.14.2/<7.15.1: Referer/cookie/proxy-header
  leakage) transitive dependencies. Neither is a direct `composer.json`
  requirement; updated within existing constraints. `composer audit` and
  GitHub Dependabot both report zero open advisories.

## [2.1.16] - 2026-07-30

### Fixed — security & access control (found via full production-readiness re-audit)
- **UserResource privilege escalation (Critical).** The Users form exposed an
  unrestricted `is_platform_user` toggle and an unscoped `roles` Select to
  anyone holding `manage users` — including the tenant-scoped Company Admin
  role. A Company Admin could self-service escalate any user in their company
  to platform-wide read access (`is_platform_user = true`) and/or assign
  `Datamation Super Admin` / `Datamation Management`, defeating tenant
  isolation entirely. The `is_platform_user` toggle is now hidden from
  non-platform actors, the `roles` Select excludes the two platform-only
  roles for them, and `UserResource::stripDisallowedRoles()` + a
  `mutateFormDataBeforeCreate` guard enforce this server-side regardless of
  what the client submits.
- **Same-tenant platform-user credential takeover (Critical).** A platform
  user can share a tenant's `customer_id` — `DatabaseSeeder`'s own
  `admin@example.com` does exactly this — so `BaseResource`'s
  `customer_id`-based query scope alone let a same-tenant Company Admin open
  that platform user's edit page. Combined with the new password field
  (below), this was a full platform-takeover path that never touched
  `is_platform_user` or `roles`. `UserResource::can()` now denies every write
  action (`update`, `delete`, …) on a record where `is_platform_user = true`
  to any actor who is not themselves a platform user, independent of
  `customer_id`. Reads are unaffected.
- **Subscription/license/company/user-active checks were not enforced over
  HTTP (High).** `SetCompanyContext`, `EnsureUserIsActive`,
  `EnsureCompanyAssigned`, `EnsureCompanyActive`, `EnsureSubscriptionActive`
  and `EnsureLicenseAllowsAccess` were registered via
  `$middleware->append()` in `bootstrap/app.php` — the *global* middleware
  stack, which runs before any route-group middleware, including the
  Filament panel's own `StartSession` and `routes/api.php`'s `auth:sanctum`.
  Every one of these checks reads `auth()->user()`, which was always `null`
  at that point, so they silently no-op'd on every request — the exact
  "implemented in code but not enforced over HTTP" bug class v2.1.15 was
  meant to close. Most severely, a customer with a lapsed subscription (but a
  non-blocked license — subscription and license are orthogonal checks) kept
  full application access indefinitely. Moved to a named `business-access`
  middleware group, attached inside `FilamentPanelProvider` (after
  `StartSession`/`AuthenticateSession`) and `routes/api.php` (after
  `auth:sanctum`), so `auth()->user()` is populated when they run.

### Fixed — functionality
- **User creation was completely broken (Critical).** `UserResource`'s form
  had no `password` field at all — the `users.password` column is `NOT NULL`
  with no default, so every attempt to create a user via the Filament admin
  UI (any role holding `manage users`) threw a database integrity-constraint
  violation. Added a password field, required on create, optional on edit
  (`dehydrated` only when filled, so leaving it blank preserves the existing
  hash — regression-tested for both paths).

### Added
- `bootstrap/app.php`: named `business-access` middleware group (see above).
- Regression tests: `BusinessAccessMiddlewareTest`,
  `UserResourcePrivilegeEscalationTest`, `UserResourcePasswordFieldTest`.

### Changed
- `npm audit fix` applied (postcss, concurrently's `shell-quote` — both
  dev-only build tooling, no production dependency affected): 3 high
  severity advisories → 0.

### Known gaps (documented, not blocking)
- Password policy is `min:8` with no complexity requirement, matching the
  existing `dmims:create-admin-user` console command — consistent with
  current behaviour, but weaker than the "Strong password policy" named in
  the Master Functional Specification. Not changed in this pass (would touch
  the console command and profile password-change form too); tracked for a
  dedicated pass.
- The `business-access` group's `SetCompanyContext` calls the `session()`
  helper on the (stateless, Sanctum-guarded) API route group. Tests pass
  under `SESSION_DRIVER=array` (phpunit.xml); the write cost against
  `SESSION_DRIVER=database` in production has not been separately profiled.
- Pre-existing, unrelated to this pass: `database/seeders/QASampleUsersSeeder.php`
  fails `vendor/bin/pint --test` (import ordering / strict-types fixers).

## [2.1.15] - 2026-07-08

### Fixed — security & access control (found via role-based Playwright QA)
- **Panel middleware stack restored (Critical).** `FilamentPanelProvider` never
  called `->middleware([...])`; Filament panels ship with NO default route
  middleware, so `/admin` ran without `StartSession`, cookie encryption or
  CSRF protection — login could not persist a session at all over HTTP. Added
  the standard Filament middleware stack and switched `authMiddleware` to
  Filament's `Authenticate`.
- **`User` now implements `FilamentUser` (Critical).** Without
  `canAccessPanel()`, Filament denies every login outside `local` env —
  production would have been a total lockout once the middleware fix landed.
  Delegates to the existing `AccessControlService::canLogin()` (active user,
  active company, license not blocked).
- **Panel authorization re-wired to the layered engine (Critical).** Filament
  v5 authorizes pages/actions via `getAuthorizationResponse()` (Gate policies),
  so the documented `BaseResource::can()` seven-layer engine never ran for
  panel requests — and `ResourcePolicy::viewAny/create` expected a `$model`
  argument Laravel never passes, denying ALL list/create pages to every
  non-platform user. `BaseResource` now overrides `getAuthorizationResponse()`
  to route through `can()`, which also gained a record-level tenant-ownership
  check.
- **Platform write bypass removed (High).** `Gate::before` allowed every
  platform user all abilities, giving the view-only Datamation Management role
  full write access (Security & Access Control Matrix violation). Removed;
  platform users keep platform-wide read scope but writes now require the
  manage permission (Datamation Super Admin holds all permissions via its
  role, so it is unaffected).
- **Customer enumeration closed (High).** `Customer` had no tenant scope, so
  any customer-scoped user could enumerate every company on the platform
  (e.g. via the `customer_id` Select on 19 resource forms). A global scope now
  limits non-platform users to their own company.
- **User menu 500 fixed (High).** `AppServiceProvider` registered a
  `NavigationItem` as a user-menu item; Filament v5 requires `MenuItem` —
  every authenticated `/admin` page threw a `TypeError`.

### Removed
- **`ResourcePolicy` and its 27 model→policy mappings.** Its class-level
  `viewAny`/`create` methods expected a `$model` argument Laravel never passes
  (permanently denying), while its record-level methods carried
  `is_platform_user` allow-shortcuts contradicting the tightened model. It was
  unreachable from the panel (BaseResource authorizes centrally); with no
  policy registered the Gate now default-denies model abilities — fail closed.
- **`package.json` npm-init pollution.** Removed ~35 transitive packages that
  had been promoted to `dependencies`, npm-init boilerplate (including an
  incorrect `"license": "ISC"` on a proprietary project), and a duplicate qa
  script; restored the `shell-quote` security override that had been dropped.
  Lockfile regenerated (`npm install`: 0 vulnerabilities; build verified).

### Added
- Role-based Playwright QA suite (`tests/playwright/role-qa.spec.js`): per-role
  login/logout, dashboard, restricted URLs, tenant-scoped select options, one
  CRUD + validation flow, mobile viewport smoke. `QASampleUsersSeeder`
  (local/testing only) seeds one QA user per documented role.
- `playwright.config.js`: env-overridable `baseURL`, serial workers (single
  threaded `php artisan serve`).

## [2.1.14] - 2026-07-03

### Added
- **Claude Code workflow tooling under `.claude/`** (committed, team-shared) to run
  Claude Code as a disciplined system rather than a chatbot:
  - Slash commands: `/roast` (adversarial 5-role review ending in a
    GREEN LIGHT / RESHAPE / KILL verdict), `/selfcheck` (completion checklist that
    runs `php artisan test` + `pint` and grades against `docs/DEFINITION_OF_DONE.md`),
    and `/handoff` (compact, copy-pasteable session handoff).
  - Sub-agents: `security-reviewer`, `qa-tester`, and `docs-writer`, each tailored to
    the DMIMS stack and `/docs` governance (tenant isolation, the seven access-control
    layers, the test/lint/deploy-smoke commands, doc-sync rules).
  - `CLAUDE.md` gains an explicit "Operating rules (every task)" checklist alongside the
    existing governance pointer.
  - `.claude/skills/` documented as the home for future reusable skills.
  `/.claude/settings.local.json` remains git-ignored (per-developer settings).
## [2.1.13] - 2026-07-03

### Changed
- **Docs sync follow-up for the Sanctum/rate-limiting hardening (v2.1.10/v2.1.11).**
  `README.md`'s Scheduled tasks list now includes the daily
  `sanctum:prune-expired --hours=24` job, and the API bullet notes that issued
  tokens default to a restricted `api:read` ability, a 365-day expiration, and
  a 60/min rate limit. `DEPLOYMENT_GUIDE.md` documents the two new optional
  env vars (`SANCTUM_TOKEN_EXPIRATION`, `API_RATE_LIMIT_PER_MINUTE`) — both
  already shipped with safe defaults in `.env.example`, so no deployment
  behavior changed, just visibility for anyone wanting to override them.
  `deploy-ubuntu-24.sh` needed no change: it copies `.env.example` wholesale
  and only overrides the keys it explicitly cares about, so the new vars
  already carried through with their defaults.

## [2.1.12] - 2026-07-03

### Changed
- **`DEPLOYMENT_GUIDE.md` Part 13 (backup strategy) — credential handling fixed.**
  The optional OS-level cron backup script documented an inline
  `mysqldump -u ... -p'password'` invocation (password visible in
  `ps`/shell history), though the guide already flagged this as a concern.
  It now creates a chmod-600 `/root/.my.cnf` and drops the inline `-u`/`-p`
  flags entirely. Also clarified that the app's own `dmims:backup-database`
  (already scheduled nightly, encrypted, integrity-verified) is the primary
  backup mechanism — this OS-level script is an optional supplement. No code
  changed; the app's real `BackupService` already used the safer `MYSQL_PWD`
  env var, not an inline flag.

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
