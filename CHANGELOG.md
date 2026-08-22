# Changelog

All notable changes to DMIMS (Datamation Inventory Management System) are
documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/),
and the project aims to follow [Semantic Versioning](https://semver.org/).

## [2.1.36] - 2026-08-23

### Fixed — Critical: tenant Company Admin could hold platform-wide access

Live incident: a Datamation Super Admin created a Company Admin user for a
new tenant ("Madhan Inc") via the Filament `UserResource` create form and
set `is_platform_user=true` on it at the same time — the form's toggle is
visible to platform actors with no check against the assigned role.
`BaseResource::can()` and `shouldRegisterNavigation()` both key off
`is_platform_user` alone to skip tenant scoping entirely, so the account
ended up with unrestricted platform-wide read access (Customers, Users,
Backups, Audit Logs, Subscription Plans, Licenses, etc.) despite a
tenant-scoped role and a real `customer_id`.

- **`UserResource::enforcePlatformRoleConsistency()`** (new): re-derives
  `is_platform_user` from whether the user actually holds a platform-tier
  role (`Datamation Super Admin` / `Datamation Management`), called from
  both `CreateUser::afterCreate()` and `EditUser::afterSave()` after role
  sync. A security review caught a regression in the first version of this
  fix — `EditUser::afterSave()` restoring a limited actor's pre-save role
  snapshot *after* stripping disallowed roles could put a platform role
  back and re-promote an already-mismatched record; reordered so the
  snapshot restore happens first, `stripDisallowedRoles()` always has the
  last word on the role set, and the consistency check reads a role list
  that's already been sanitized.
- **`dmims:fix-platform-role-consistency`** (new console command): audits
  every user (including soft-deleted, via `withoutGlobalScopes()`) for this
  mismatch and corrects it via the same method the live form uses;
  `--dry-run` lists what would change before applying. Used to remediate the
  live incident.
- **`dmims:create-admin`**: now fails closed (deletes the user, returns
  `FAILURE`) instead of warning and creating a role-less
  `is_platform_user=true` account when the Super Admin role hasn't been
  seeded yet — that state is invalid and the new consistency check would
  otherwise silently demote it later with no operator-visible reason. Uses
  `forceDelete()`, not `delete()` — `User` has `SoftDeletes`, and a soft
  delete would leave the row (and its unique email) behind, permanently
  blocking a retry once the role is seeded.

Verified: composer validate/audit, pint, phpstan (clean), 155 tests
(4 new, covering the create path, the edit-path reorder regression
specifically, the reverse direction, and the command's dry-run/apply
behavior). Two independent security-reviewer passes — the first caught the
edit-path reorder bug and the `delete()`/soft-delete bug above before
either shipped; the second confirmed both closed by reverting and
re-running the regression tests, not just re-reading the diff.

## [2.1.35] - 2026-08-22

### Fixed — resolved 203 of 215 PHPStan/Larastan baseline findings

Root-cause fixes, not suppressions (`phpstan-baseline.neon`: 215 → 12
entries). Two real bugs found along the way, not just lint:

- **`AppServiceProvider`**: `env('API_RATE_LIMIT_PER_MINUTE')` was called
  inside `boot()`, which silently falls back to the hardcoded default once
  `php artisan config:cache` runs in production — ignoring any custom `.env`
  value. Moved to `config('app.api_rate_limit_per_minute')` (new key in
  `config/app.php`), with a defensive `?: 60` fallback at the read site so a
  stale or missing config cache can't zero out the rate limit and lock out
  the API entirely (caught in security review before merge).
- **`ImportService`**: a stale `@return` docblock on `importableTypes()` was
  missing the `unique` key every real entry has, which made PHPStan think
  the duplicate-row-detection block was dead code. Fixed the docblock and
  removed one genuinely-unreachable null check in `validateRow()`.

The remaining fixes were mostly missing PHPDoc type annotations cascading
from two trait event closures (`Auditable`, `BelongsToCustomer`) typed as
base `Model` instead of the composing class — zero runtime effect. 12
findings were deliberately left in the baseline (mostly `nullsafe.neverNull`
"false positives" verified against migration nullability, not blindly
trusted) with reasoning recorded in the PR. Independently re-verified by a
security-reviewer pass over the full diff — no regressions.

## [2.1.34] - 2026-08-22

### Changed — dependency updates (composer, npm, CI actions)

Routine dependency maintenance, verified with the full local check suite
(Pint, Larastan, 152 tests, asset build) before merge:

- composer: `nunomaduro/collision` → 8.9.5, `phpunit/phpunit` → 13.3.1,
  `spatie/laravel-permission` → 8.3.0 (lockfile only, no `composer.json`
  range changes needed).
- npm: `tailwindcss` + `@tailwindcss/vite` → 4.3.3, `vite` → 8.2.2,
  `laravel-vite-plugin` → 3.2.0 (lockfile only).
- `.github/workflows/ci.yml`: `actions/setup-node` v6 → v7.

## [2.1.33] - 2026-08-19

### Changed — deploy-ubuntu-24.sh: single script for staging and production

`deploy-ubuntu-24.sh` and `DEPLOYMENT_GUIDE.md` now cover staging and
production deploys with one script instead of documenting production only:

- New required `--env staging|production` flag; sets `APP_ENV` accordingly.
- `RolesAndPermissionsSeeder` now runs automatically (previously required a
  manual step after the script finished) — safe on every environment, no
  demo data.
- New `--seed-qa-users` flag seeds `QASampleUsersSeeder`, **only accepted with
  `--env staging`** — refused at argument parsing and again inside the
  seeding step if somehow reached with `--env production`. The bare
  `php artisan db:seed` (which creates a demo customer and an
  `admin@example.com`/`password` login) is never invoked by the script.
- New `--admin-email`/`--admin-password` flags create the first platform
  admin via `dmims:create-admin`; idempotent, so re-running the script for a
  code update doesn't fail if the account already exists.
- `clone_repo` now updates an existing `--repo-dir` in place (`git fetch` +
  `git pull --ff-only`) instead of only supporting a first-time clone, so the
  same command redeploys an update.

## [2.1.32] - 2026-08-19

### Fixed — module review of Users, Products, Document Files, Billing, Backups

A structured review (security-reviewer + QA pass, per the project's risk-tiered
review workflow) of these five modules and their List/View/Create/Edit/Delete/
Search/Filter/custom-action/validation/authorization/tenant-isolation/audit
workflows found and fixed:

**Critical**
- **Billing's `recordPayment`/`issue`/`cancel` actions had no `->authorize()`
  at all** (`BillingRecordResource.php`) — unlike every other custom action in
  the app. Company Admin/Supervisor hold `view billing` (to see their own
  invoices, per Security & Access Control Matrix §9) but not `manage billing`
  (Super-Admin-only); with no authorize closure, any user who could see the
  Billing list could record a fabricated payment, issue a draft, or cancel any
  invoice — actions the matrix reserves for Super Admin alone. Fixed by adding
  `->authorize(fn ($record) => static::can('update', $record))` to all three,
  matching the pattern already used everywhere else.

**High**
- **`BackupResource`/`ImportResource`/`ExportResource` downloads used `can('view', ...)`**,
  which `BaseResource::can()` grants unconditionally to *any* platform user
  regardless of `$permission` (platform users get unrestricted read access by
  design). That meant `Datamation Management` — a view-only oversight role
  holding zero permissions — could decrypt and download a full database
  backup. Found by the QA pass, not the security review. Changed to
  `can('update', ...)`, matching `runBackup`/`restore`, so all three now
  correctly require `manage settings`.
- **Reports: `view reports` alone let any role download Billing Summary /
  Payment Summary / Outstanding Balance** (`ReportExportService`), even though
  the Security & Access Control Matrix §9 restricts billing data to SA/
  Management/Company Admin/Company Supervisor — Stock Inventory User, Document
  Tracking User, and Viewer all hold `view reports` and could pull full
  billing/payment exports. Fixed by adding an optional `permission` key to
  report definitions, checked in `availableTo()` for non-platform users.
- **13 resources' Create pages had no server-side re-assertion of `customer_id`**
  (Billing, Boxes, Categories, Customer Modules, Customer Subscriptions,
  Document Files, Licenses, Locations, Notifications, Products, Settings,
  Stock Alerts, Support Access Logs) — the `customer_id` Select is only ever
  hidden via `->visible()` for a tenant actor, never disabled or excluded from
  dehydration. In practice every affected model already carries `BelongsToCustomer`
  (whose `creating`/`updating` hooks force `customer_id` unconditionally) or,
  for the three that don't (License, CustomerSubscription, CustomerModule),
  only Super Admin can reach the form at all today — so this was not a live
  exploit, but a missing second layer. Closed once via a new shared
  `App\Filament\Resources\Pages\CreateRecord` base (mirroring the existing
  `ListRecords`/`EditRecord` pattern) plus the same hook added to the shared
  `EditRecord` base, rather than duplicating the fix in every resource.

**Medium**
- `CategoryResource` had no `$deletePermission` split — Stock Inventory User
  and Company Supervisor (who hold `manage inventory` but not `delete inventory`)
  could delete a Category even though they can't delete a Product. Added the
  same `delete inventory` split Product already has.
- `BackupService::restoreDatabase()` skipped the checksum tamper-check entirely
  when `checksum` was null (`if ($backup->checksum && ...)`) instead of
  failing closed. Every backup written through the normal path always has a
  checksum, so a null one means the row didn't go through it — now refused.

**Investigated and confirmed not exploitable** (verified with a new regression
test, not just re-reading the code): a security-reviewer finding claimed
`Box`/`Location` carry no tenant scoping, so the Transfer/Return action
dropdowns in `DocumentFileResource`/`BoxResource` could leak another
company's boxes/locations. Both models do carry `BelongsToCustomer`, whose
global scope applies to any `Box::query()`/`Location::query()` call —
confirmed with `tests/Feature/DocumentTenantIsolationTest.php`, which fails
if that scope is ever removed.

Regression tests added: `BillingActionAuthorizationTest`,
`DocumentTenantIsolationTest`, plus additions to `BackupServiceTest` and
`ReportExportServiceTest`. Full suite (152/152), Pint, Larastan, and
`npm run build` all verified clean.

## [2.1.31] - 2026-08-19

### Fixed — deleting a record with restricted history crashed instead of failing gracefully

Found live-testing: deleting a Customer Subscription that already had
`SubscriptionLog` history threw a raw, unstyled `QueryException` 500 page
(`SQLSTATE[23000]: FOREIGN KEY constraint failed`) instead of an in-app
notification. `subscription_logs.customer_subscription_id` — and, identically,
`license_logs.license_id` — deliberately `restrictOnDelete()` their parent so
the audit trail can't be silently wiped by deleting the record it documents
(see `2026_08_18_000006_restrict_subscription_logs_cascade.php`); Filament's
built-in `DeleteAction` has no exception handling around the delete call, so
that intentional block surfaced as a crash rather than a message.

Fixed once in `App\Filament\Resources\Pages\EditRecord` — the shared base
every resource's Delete button already routes through (see 2.1.29) — by
wrapping the delete in a try/catch for SQLSTATE `23000` and using Filament's
own failure-notification mechanism instead of a custom one. Because it's the
single shared class, this closes the same gap for every resource with a
restricting child (License, Customer Subscription, and any future one) in
one change, not per-resource. Audited every other place `DeleteAction::make()`
is wired directly (Backups, Exports, Imports) — none of those tables have any
foreign key restricting deletes, so they were never exposed to this.

Regression test added (`DeleteRestrictedByLogTest`). Full suite (146/146),
Pint, and Larastan verified clean.

## [2.1.30] - 2026-08-19

### Fixed — Critical: creating a License or Customer Subscription always 500'd

Found while live-testing the Super Admin workflow: submitting the "Create
License" (or "Create Customer Subscription") form always threw a 500
(`BindingResolutionException: ... [$attribute] was unresolvable`) the
instant `enabled_modules`/`allowed_reports` were validated, regardless of
what was typed. `BaseResource::jsonRule()` returns a raw
`function (string $attribute, mixed $value, Closure $fail)` closure —
Laravel's own signature for an inline validation-rule callback — passed
straight to Filament's `->rule()`. Filament's `evaluate()` dependency-injects
*any* closure passed there by parameter name; it has no way to know this
particular closure is meant as a raw Laravel rule callback rather than
something to resolve, and blew up trying to resolve `$attribute`. Fixed by
wrapping the rule in a zero-arg outer closure
(`fn (): \Closure => function (...) {...}`) so `evaluate()` calls it with no
arguments and gets the real rule closure back untouched — a one-line change
in `jsonRule()` itself fixes both call sites (License and Customer
Subscription) rather than patching each `->rule()` call. Regression test
added (`JsonRuleValidationTest`, both the invalid-JSON-rejected and
valid-JSON-succeeds paths). Full suite (145/145), Pint, and Larastan
verified clean.

## [2.1.29] - 2026-08-19

### Fixed — Critical: Create/Delete buttons missing app-wide + custom-action authorization gap

**Critical/High (found via a full council audit of every resource's Create/
Edit/Delete/Print/Download actions)**

- **No List page anywhere had a working Create button, no Edit page anywhere
  had a working Delete button.** Filament never auto-adds `getHeaderActions()`
  — every page class must declare it explicitly, and this app never did,
  across its entire history. Root-caused against Filament's own
  `make:filament-resource` generator templates, not a regression from the
  5.6.8→5.7.6 bump. Fixed via two new shared base classes,
  `App\Filament\Resources\Pages\{ListRecords,EditRecord}`, each declaring
  `getHeaderActions()` (`CreateAction`/`DeleteAction`, which auto-authorize
  against the resource's own `can()`), and swapping the base-class import in
  19 resources' `ListRecords`/`EditRecord` subclasses to point at them.
  `BarcodeRegistryResource` (no create page) and `SupportAccessLogResource`
  (no edit page, by design — see 2.1.x history) each got only the one import
  swap that applies to them.
- **Custom table actions (`Action`/`BulkAction` registered via
  `->headerActions()`/`->recordActions()`/`->bulkActions()`) were never
  authorized at all** — only Filament's built-in `CreateAction`/`EditAction`/
  `DeleteAction` auto-check `can()`; hand-rolled actions need an explicit
  `->authorize()`, which was missing everywhere. Most severe:
  `BackupResource`'s `restore` action could overwrite the live production
  database, reachable by any platform user regardless of role (only a
  confirmation modal stood in the way). Also unguarded: `BackupResource::
  runBackup`/`download`; `ImportResource::newImport`/`downloadErrors`;
  `ExportResource::newExport`/`download`; `BarcodeRegistryResource::
  batchGenerate`/`preview`/`replace`/`batchPrint`; `StockMovementResource::
  receiveIn`/`stockOut`/`transfer`/`adjust`; `BoxResource`/
  `DocumentFileResource`'s transfer/moveOut/return actions; and the shared
  `HasBarcodeAction` trait's `barcode` row action (Product/Box/DocumentFile).
  Fixed by adding `->authorize()` closures mirroring `BaseResource::can()`'s
  read/write split — mutating actions require `can('create')`/`can('update',
  $record)`, pure-read actions require `can('view', $record)` — so a
  platform "view-only" role or tenant "Viewer" role can no longer trigger
  them.

Found and fixed via a 3-agent council audit spanning all ~28 resources.
Regression test added (`BarcodeCenterTest::
test_platform_user_without_manage_barcode_cannot_run_mutating_actions`); the
pre-existing `BarcodeCenterTest` fixtures were updated to grant the
`manage barcode` permission they'd always needed but never had (the
vulnerability had been masking that gap). Full suite (143/143), Pint, and
Larastan verified green.

## [2.1.28] - 2026-08-19

### Added
- **Company Supervisor's "Update User: Limited"** (Security & Access
  Control Matrix §6), resolved by product decision rather than left
  ambiguous: Supervisor may edit an existing user's operational fields
  (name, phone, job_title, department_id, employee_id) but not create or
  delete a user, and not touch identity/security/privilege fields (email,
  username, password, status, roles, customer_id) — enforced twice: UI-level
  `->disabled()` on those fields, and server-side in
  `EditUser::mutateFormDataBeforeSave()`/`afterSave()` (roles is a
  relationship, restored from a pre-save snapshot since Filament syncs it
  automatically before `afterSave()` runs).
- New `BaseResource::$limitedUpdatePermission` — a weaker alternative to
  `$permission` for the update action only, so a resource can express
  "may edit, not create/delete" without granting full manage rights. New
  `update users limited` permission, held only by Company Supervisor.

Regression test added (`UserResourcePrivilegeEscalationTest::test_company_supervisor_has_limited_update_access`)
covering both the allowed operational-field path and a crafted-request
attempt at the locked fields (including a role-escalation attempt via the
roles relationship). Full suite (142/142), Pint, and Larastan verified
green.

**No findings remain open** from the full-app doc-conformance audit.

## [2.1.27] - 2026-08-19

### Fixed — remaining Medium/Low findings + one newly-discovered Critical

**Critical (found while verifying finding #17, not in the original audit)**
- **`EnsureCompanyActive` middleware had the exact same bug as
  `AccessControlService::companyActive()`** — only allowed company
  `status='active'`, re-blocking every request for trial/suspended
  companies on every page load even after the earlier login fix. This
  middleware runs on every admin/API request via the `business-access`
  group; the earlier fix only covered the login gate, not this one. Same
  fix applied: only `cancelled`/`archived` block.
- **Usage-limit enforcement was completely unimplemented.**
  `AccessControlService::getEffectiveLimits()` had zero call sites
  anywhere — Business Rules §7's "Limit Rule" (block creation once a
  subscription's max_users/max_products/max_document_files/max_boxes is
  reached) was computed but never enforced. Added
  `BaseResource::$usageLimitKey` (mirrors `$deletePermission`) and wired it
  into `UserResource`, `ProductResource`, `DocumentFileResource`,
  `BoxResource`.

**Corrections to the original audit** (verified, not assumed)
- Finding #17 ("8-step Access Decision Flow not fully re-validated per
  request") was largely wrong — `EnsureUserIsActive`/`EnsureCompanyActive`/
  `EnsureSubscriptionActive` already re-check per request via the
  `business-access` middleware group. Only usage limits were genuinely
  missing (now fixed above).
- 2 of the 5 "missing notification triggers" were false positives — Import
  Failure and Export Completion were already implemented directly in
  `ImportService`/`ExportService`, just not in the scheduled
  `GenerateNotifications` command the earlier audit only checked. Real gap
  was Payment updates and Overdue returns, both fixed below.

**Medium**
- Barcode Registry unreachable for Document Tracking User: added dedicated
  `manage barcode`/`view barcode` permissions (previously folded into
  `manage inventory`, which Document Tracking User never held) matching
  the doc's near-universal View Registry grant.
- "Payment updates" notification: `PaymentService::recordPayment()` now
  fires one, event-driven (not scanned for).
- "Overdue returns" notification: new `GenerateNotifications::overdueReturns()`
  — a moved-out file past its due_date.
- 5 of 19 documented reports were missing entirely (no `build()` case):
  added Outstanding Balance, Module Usage, Files by Box, Boxes by
  Location, External Movement to `ReportExportService`.
- Documented dead-letter/failed-job handling in `DEPLOYMENT_GUIDE.md`
  (`queue:failed` / `queue:retry` / `queue:flush`).
- `bootstrap/app.php`'s exception handling: explicit `dontFlash` list
  (extends Laravel's password default to API-token/2FA fields) and a
  comment recording the deliberate decision not to add an external
  error-tracking sink.

**Low**
- Updated composer/npm dependencies within existing major versions
  (Filament 5.6→5.7, Laravel 13.18→13.26, Sanctum, Pint, PHPUnit,
  spatie/laravel-permission, Vite, Tailwind, Playwright, etc.). Left
  `openspout/openspout` (4→5) alone — a major bump, evaluated separately
  rather than bundled into routine hygiene.

**Deliberately deferred item resolved**
- Added a Content-Security-Policy header — same-origin scripts/styles/
  connections, no framing. Not nonce-based (Filament 5's Livewire/Alpine
  stack needs inline script/style support throughout), but genuinely
  restrictive compared to no CSP at all. **Verified live**, not guessed:
  logged into a disposable admin panel instance, exercised login,
  dashboard, and a CRUD create page with Livewire's AJAX search, checked
  the browser console — zero CSP violations.

**Still deliberately unimplemented** — Company Supervisor's "Update User:
Limited" (§6): the doc doesn't specify which fields are restricted, so this
stays `view users`-only rather than a fabricated field-level rule.

Regression tests added for every fix (usage limits, company-status access
across all 7 statuses, both new notification triggers, a
build-every-defined-report test that would have caught the 5 missing
reports, updated CSP assertion). Full suite (141/141), Pint, and Larastan
verified green throughout — including catching and fixing a genuine test
bug of my own (a `CustomerSubscriptionObserver` side effect that disables
unlisted `CustomerModule` rows, which broke my first attempt at the
usage-limit test in a way that had nothing to do with the feature itself).

No findings remain open from the full-app doc-conformance audit except the
one deliberately-ambiguous item above.

## [2.1.26] - 2026-08-19

### Fixed — full-app doc-conformance audit (Critical + High findings)

A 3-agent council audit cross-checked every module and role against
`docs/DMIMS Business Rules & Functional Specification.md`,
`docs/DMIMS Master Functional Specification (MFS).md`, and
`docs/DMIMS Security & Access Control Matrix.md`. 12 Critical/High findings
fixed; Medium/Low findings and two ambiguous doc gaps are tracked in
`docs/CONFORMANCE_GAP_ANALYSIS.md`.

**Critical**
- **Trial customers could not log in at all.**
  `AccessControlService::companyActive()` only accepted `status='active'`,
  but new customers default to `'trial'` and Business Rules §4 says Trial
  gets normal access. Now only `cancelled`/`archived` (the two genuinely
  terminal statuses) block login; `expired`/`suspended` correctly defer to
  the license layer's grace/view-only handling, per the same rule.
- **Every request 403'd for trial/expired_grace subscriptions.**
  `EnsureSubscriptionActive` only allowed `active`/`near_expiry`. Business
  Rules §7 is explicit that "a subscription does not directly determine
  whether the customer can technically use the system" — that's the
  license's job. Now only `cancelled` blocks.
- **`StockAlert` was a fully dead feature.** Table and admin resource
  existed; nothing ever wrote to it. `dmims:generate-notifications` now
  opens/updates a `StockAlert` row alongside the existing generic
  `Notification`, and closes it when stock recovers above the reorder
  level.
- **Company Admin could delete users** (doc: SA-only). Added a
  `BaseResource::$deletePermission` concept — a resource can require a
  stricter permission for delete specifically instead of reusing `$permission`
  for every write. `UserResource` now requires the new `delete users`
  permission (SA only) for delete actions.
- **Company Admin could create/edit/cancel billing records** (doc: SA-only;
  Admin gets View + Export only). Removed `manage billing` from Company
  Admin's seeded permissions, replaced with `view billing`.
- **Audit logs were visible to Supervisor/Stock/Document/Viewer** (doc: only
  SA/Management/Company-Admin-own-company). `AuditLogResource` was gated on
  the generic `view reports` permission every role holds; now gated on a
  new `view audit logs` permission held only by those three.
- **Company Supervisor and Stock Inventory User could delete Products**
  (doc: Admin/SA only). `ProductResource` now requires the new
  `delete inventory` permission for delete actions.

**High**
- **Box/File creation bypassed the guided Receive-In workflow.**
  `DocumentMovementService::receiveInFile()`/`receiveInBox()` were dead
  code — nothing called them, so a new box/file's first event was never
  logged and the containing box's `current_file_count` was never
  incremented. `CreateBox`/`CreateDocumentFile` now call them from
  `afterCreate()`.
- **Company Admin/Supervisor had no way to view their own company
  record** (doc: "View Customers: Own Company"). `CustomerResource` was
  Super-Admin-only. Added `view customers` to both roles and a
  primary-key-based tenant scope on `CustomerResource` (Customer has no
  `customer_id` column of its own, so `BaseResource`'s standard scoping
  mechanism doesn't apply — scoped by `whereKey($user->customer_id)`
  instead, plus a matching `can()` override for direct-URL access).
- **Company Supervisor had zero user-management access** (doc: View =
  Own). Added `view users` to Company Supervisor. ("Update User: Limited"
  is left unimplemented — the doc doesn't specify which fields are
  restricted; flagged rather than guessed, see gap analysis.)
- **No security headers anywhere** (documented as required in the Ops
  guide and `DEFINITION_OF_DONE.md`). Added `SecurityHeaders` middleware:
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy` unconditionally; `Strict-Transport-Security` only
  when the request is actually HTTPS (this server is intentionally
  reachable via plain HTTP on the LAN too — see `SESSION_SECURE_COOKIE`'s
  comment in `DEPLOYMENT_GUIDE.md`). No CSP — a naive policy is a common
  way to silently break Livewire/Alpine's inline scripts; left as a
  deliberate follow-up rather than shipped unverified.
- **`LOG_LEVEL=debug` had no production override documented.** Added to
  `DEPLOYMENT_GUIDE.md`'s environment-config checklist and commented in
  `.env.example`.

Regression tests added: `AccessControlTest::test_can_login_across_company_statuses`,
`BusinessAccessMiddlewareTest::test_user_with_trial_or_expired_grace_subscription_is_not_blocked`,
`NotificationGenerationTest` (StockAlert open/close assertions),
`DocumentOperationsTest::test_creating_a_box_logs_a_movement` /
`test_creating_a_document_file_logs_a_movement_and_increments_box_count`,
`SecurityHeadersTest`. Full suite (136/136), Pint, and Larastan verified
green throughout.

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
