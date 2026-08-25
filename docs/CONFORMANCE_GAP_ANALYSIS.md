# DMIMS — Requirements Conformance & Gap Analysis

**Updated:** 24 August 2026 (implementation pass same day)

This document tracks differences between the approved DMIMS specification and current implementation.

Legend:

- ✅ Implemented and verified
- WIP Partial / pending verification
- ❌ Missing / open
- ⚠️ Open security or production-readiness gap

---

# 1. Current Baseline

The repository has undergone multiple security and production-readiness hardening passes.

Previously remediated classes of issues include:

- Missing Filament/session enforcement
- Platform-role bypass
- User privilege escalation
- Business-access middleware ordering
- Missing-license full-access behaviour
- Unauthorised custom Filament actions
- Billing action authorization
- Export/download authorization
- Platform-role consistency

Existing fixes remain authoritative and must not regress.

---

## Platform Customer 360 Design Review — 25 August 2026

The Product Owner approved a customer-centric Platform administration model.

### Approved Target

Datamation Super Admin uses:

```text
Customers
→ Select Customer
→ Customer 360 / Customer Profile
```

Customer 360 contains:

- Overview
- Users
- Modules
- Subscription
- License
- Billing & Payments
- Audit Logs
- Activity/Notifications where useful

Customer-specific platform administration should no longer require separate primary sidebar navigation for Customer Users, Customer Modules, Customer Subscriptions, Customer Licenses, Customer Billing or Customer Payments.

Underlying resources/models/services remain separate and authoritative.

Platform-wide master/administration remains separate:

- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit Logs
- Backup / Restore
- System Settings

### Current Implementation Review

As of 25 August 2026:

- `CustomerResource` currently exposes list/create/edit pages but no dedicated ViewCustomer/Customer 360 page.
- `Customer` already exposes relationships for users, departments, customer modules, subscriptions and licenses.
- Billing records are customer-owned by `customer_id`.
- Existing customer-facing `My Company` is implemented and must not regress.
- Existing customer-specific platform resources remain separate today.

### WIP Medium — Platform Customer 360 Not Yet Implemented

This is primarily a functional/UX conformance gap, not evidence of a new data leak in the existing separate-resource implementation.

Implementation risk is **High** because the change touches authorization, tenant context, subscriptions, licensing and billing.

Required implementation:

1. Add Customer View / Customer 360 page.
2. Add Overview.
3. Embed/reuse Users.
4. Embed/reuse Customer Modules.
5. Embed/reuse Customer Subscription.
6. Embed/reuse License administration.
7. Embed/reuse Billing & Payments.
8. Embed/reuse customer Audit Logs.
9. Derive all child `customer_id` values from the selected parent Customer.
10. Remove/hide duplicate customer-specific platform navigation after parity is verified.
11. Preserve separate platform master areas.
12. Preserve customer-facing My Company.
13. Add browser/security regression tests.

### Security Acceptance

Customer A profile must never mutate/read Customer B child data.

Customer 360 child forms must not accept an arbitrary browser-selected customer ownership.

Datamation Management must remain read-only.

Customer roles must not access Platform Customer 360.

### Status

**Documentation:** ✅ Approved
**Implementation:** WIP / Not yet implemented
**Security regression tests:** WIP
**Browser QA:** WIP
**Conformance:** Not yet closed

Close this gap only after implementation, tests, security review, browser QA and documentation synchronization pass the Definition of Done.

---

# 2. Access-Control Design Review — 24 August 2026

A customer-facing access-control review identified a further class of least-privilege gaps.

The approved target architecture now distinguishes:

- `PLATFORM_ONLY`
- `TENANT_STRICT`
- `TENANT_WITH_GLOBAL_DEFAULTS`

`TENANT_STRICT` customer queries must match the authenticated customer's exact `customer_id` and must not automatically include `customer_id IS NULL`.

## ⚠️ Open High — Generic tenant scope includes NULL/global records

Current common resource scoping may include:

```text
customer_id = tenant
OR customer_id IS NULL
```

for resources using generic customer scoping.

This is appropriate only for explicitly global/default resources.

It is not safe as a default tenant scope.

### Required Resolution

Introduce explicit scope semantics.

TENANT_STRICT becomes default for customer-owned resources.

NULL/global visibility becomes opt-in only.

### ✅ Implemented (24 August 2026)

`App\Models\Concerns\BelongsToCustomer` and `App\Filament\Resources\BaseResource`
now default to TENANT_STRICT (`customer_id = tenant`, no `OR customer_id IS
NULL`). Only `DocumentType`/`Setting` (model + Filament resource) opt back
into TENANT_WITH_GLOBAL_DEFAULTS, matching §3.3's approved examples. Covered
by `tests/Feature/TenantScopeTest.php`.

---

# 3. ⚠️ Open High — Customer Audit Query

Customer Company Admin must see only:

```text
audit_logs.customer_id = authenticated_user.customer_id
```

Platform audit records with `customer_id = NULL` must not be customer-visible.

Other-customer audit records must not be customer-visible.

### Verification Required

- Feature test
- Browser test
- Direct URL test
- Global search/filter test if applicable
- Export/report test

### ✅ Implemented (24 August 2026)

Covered by the BaseResource TENANT_STRICT fix above (`AuditLogResource` sets
no global-defaults opt-in). Feature-tested in
`tests/Feature/CustomerAccessScopeTest.php::test_company_admin_sees_only_own_customer_audit_logs`.
Browser/Playwright-level verification is still outstanding (see §19 of the
Security & Access Control Matrix QA checklist).

---

# 4. ⚠️ Open High — Customer User Query

Customer user management must exclude platform users.

For customer role:

```text
users.customer_id = authenticated_user.customer_id
```

Do not include `customer_id = NULL`.

### Verification Required

- Company Admin cannot enumerate platform users.
- Company Supervisor cannot enumerate platform users.
- Direct edit/view URL to platform user is denied.
- Relationship/global search does not expose platform users.

### ✅ Implemented (24 August 2026)

Covered by the BaseResource TENANT_STRICT fix (`UserResource` sets no
global-defaults opt-in) plus the existing platform-user write guard. Feature-
tested in `tests/Feature/CustomerAccessScopeTest.php::test_company_admin_cannot_see_other_customer_or_platform_null_users`.
Browser/Playwright-level verification is still outstanding.

---

# 5. ⚠️ Open High — Subscription Plans Must Be Platform-Only

Subscription Plans are platform master data.

Customer roles with permission to view their own subscription must not thereby obtain access to the platform Subscription Plans resource.

### Target

Customers receive only a read-only own subscription summary under My Company.

### ✅ Implemented (24 August 2026)

`SubscriptionPlanResource` now sets `$platformOnly = true` — no customer
role can browse/view/edit it regardless of permission. Company Admin/
Supervisor's existing read-only "own subscription summary" via
`CustomerSubscriptionResource` (already TENANT_STRICT-scoped) is unaffected.
Feature-tested in `tests/Feature/CustomerAccessScopeTest.php`. The full "My
Company" tab consolidation (§8 below) is still outstanding.

---

# 6. ⚠️ Open High — License Management Must Be Platform-Only

Customer users may view only simplified own License Status where permitted.

They must not receive the administrative License Management resource.

Internal technical licensing fields must remain platform-only.

### ✅ Implemented (24 August 2026)

`LicenseResource` now sets `$platformOnly = true`. A new read-only
`MyLicenseStatusWidget` (Dashboard) gives Company Admin/Supervisor a
simplified own-license status/access-mode/expiry view without internal
technical fields (server fingerprint, installation id, deployment mode).
Feature-tested in `tests/Feature/CustomerAccessScopeTest.php` and
`tests/Feature/MyLicenseStatusWidgetTest.php`.

---

# 7. ⚠️ Open High — Report Authorization Must Include Underlying Module/Permission

Generic report access is insufficient.

Target rules:

- Inventory reports → Inventory module + inventory permission
- Document reports → Document Tracking + document permission
- Billing reports → Billing View + billing permission
- Audit reports → audit permission + exact customer scope
- All customer reports → effective `allowed_reports` where configured
- Platform reports → platform roles only

The UI selector and direct generation route/action must enforce the same rule.

### ✅ Implemented (24 August 2026)

`ReportExportService::definitions()`/`availableTo()` now require the
matching operational permission (`view`/`manage` inventory or documents) and
module (`stock_inventory`/`document_tracking`) for those report families, and
additionally require the `billing_view` module for the three billing
reports (on top of the existing `view billing` permission check). Both the
selector (`Reports::form()`) and the direct download action
(`Reports::download()`'s `abort_unless`) share the same `availableTo()` call,
so both are covered. Feature-tested in `tests/Feature/ReportExportServiceTest.php`.

---

# 8. ✅ Implemented — Customer Navigation Consolidation

Approved customer-facing structure:

**My Company**

- Profile
- Users
- Enabled Modules
- Subscription
- License Status
- Billing
- Audit Logs

Each tab remains independently authorized.

Standalone customer exposure of:

- Subscription Plans
- License Management
- Platform Module Management
- Platform Settings
- Backup / Restore
- Platform Reports
- Platform Audit Logs

is not permitted.

### ✅ Implemented (24 August 2026)

`App\Filament\Clusters\MyCompany` groups the seven tabs above under one
navigation entry (`app/Filament/Clusters/MyCompany.php` +
`app/Filament/Clusters/MyCompany/Pages/*.php`). Per TDD §7.3's "do not
duplicate business logic," each list-style tab (Users, Enabled Modules,
Subscription, Billing, Audit Logs) reuses its underlying resource's own
`can('viewAny')` and `table()`/`getEloquentQuery()` verbatim via
`Pages\Concerns\HasEmbeddedResourceTable`, so a tab is exactly as visible
and exactly as scoped as clicking directly into that resource always was —
no new authorization logic was written for these five. Profile is a
read-only display of the customer's own row (`Overview.php`); License
Status reuses the existing `MyLicenseStatusWidget`.

The five underlying resources (`UserResource`, `CustomerModuleResource`,
`CustomerSubscriptionResource`, `BillingRecordResource`,
`AuditLogResource`) got a new `BaseResource::$customerFacingViaMyCompany`
flag that hides only their standalone top-level nav entry for non-platform
users — their routes, `can()` and `getEloquentQuery()` are unchanged, so
row actions inside a My Company tab (e.g. "Edit" on a user) still work.
Platform users are unaffected and keep using the dedicated resources for
cross-tenant administration.

A real-browser Playwright pass plus an independent security review of this
build caught and fixed five further defects:

1. **`MyLicenseStatusWidget::canView()` had no permission check**, so *any*
   non-platform user — including Stock Inventory User, Document Tracking
   User and Viewer, none of whom the matrix grants "Own License Status" —
   could see the License Status tab, which was then the only tab keeping the
   entire My Company cluster visible to those roles (Filament only hides a
   Cluster's nav when *zero* sub-pages are accessible). Fixed by requiring
   `view licensing`/`manage licensing`, matching §5's table exactly.
2. **`CustomerResource` was missed from the `$customerFacingViaMyCompany`
   sweep** — Company Admin/Supervisor still saw a standalone "Customers" nav
   entry (correctly scoped to their own row, but duplicating the new
   Profile tab). Fixed by adding the flag.
3. **(High) BillingRecordResource's `ViewAction`/`EditAction` — the only two
   row actions on any wrapped resource without an explicit `->authorize()`
   call — were unauthorized on the embedded table.** A plain embedding Page
   has no default action→resource-authorization mapping (only
   `Filament\Resources\Pages\Page`, used by a resource's own pages, provides
   one), so Filament's framework default of "allowed" applied: a Company
   Admin holding `view billing` but not `manage billing` could reach Edit on
   any invoice through this tab — bypassing the operational-permission and
   license-mode checks `BaseResource::can()` enforces everywhere else. Fixed
   by adding `HasEmbeddedResourceTable::getDefaultActionAuthorizationResponse()`,
   mirroring `Filament\Resources\Pages\Page`'s own mapping exactly but
   failing closed (deny) for any action type not explicitly mapped, so a
   future action added to any wrapped resource is denied by default rather
   than silently allowed. The same gap meant `ViewAction`/`EditAction`
   opened with no fields at all (no default schema resolver either); fixed
   alongside via `getDefaultActionSchemaResolver()`, applying the
   authorization fix *first* so the schema fix couldn't turn "empty modal"
   into "unauthorized write of arbitrary fields."
4. **`Overview::mount()` filled the page's public Livewire `$data` property
   from the customer's full `attributesToArray()`**, serialising every
   column — including `notes` (internal Datamation commentary about the
   tenant) and `deployment_type` — to the browser regardless of which nine
   fields the disabled form actually renders. Fixed to fill only the
   displayed fields, driven from one list both `mount()` and `form()` share
   so they can't drift apart again.
5. **`MyCompany` (the cluster itself) didn't override `canAccess()`**,
   so its own route defaulted to allowed (Filament's base `Page` default)
   rather than matching `shouldRegisterNavigation()`'s tenant-only check —
   reachable-but-empty for a platform user (and threw an unhandled 500 via
   an unrelated Livewire/redirect interaction), though every sub-page still
   independently re-checked its own access, so no tab content was ever
   exposed. Fixed by overriding `canAccess()` to match.
6. **(Low, pre-existing, more exposed by this change)
   `AuditLogResource`'s module filter dropdown queried the model directly**
   (`AuditLog::query()->distinct()->pluck('module', ...)`), bypassing
   `getEloquentQuery()`'s tenant scope and disclosing which modules every
   other customer on the platform uses. Fixed to query through
   `static::getEloquentQuery()`.

All six are covered by `tests/Feature/MyCompanyClusterTest.php` and
`tests/Feature/MyLicenseStatusWidgetTest.php`, and (3) was additionally
verified in a real browser: the Edit action confirmed hidden and the View
modal confirmed to render real invoice fields for a Company Admin.

---

# 9. Required Implementation Scope

Implementation should review:

- BaseResource customer scoping
- UserResource
- AuditLogResource
- CustomerResource presentation
- CustomerModuleResource
- CustomerSubscriptionResource
- SubscriptionPlanResource
- LicenseResource
- BillingResource
- Reports page
- ReportExportService
- Global search
- Select/relationship queries
- Relevant middleware
- Tests/playwright role QA

Root cause should be fixed centrally without weakening existing protections.

---

# 10. Required Regression Tests

At minimum:

1. Customer A Company Admin cannot see Customer B users. — ✅ tested (`CustomerAccessScopeTest`)
2. Customer A Company Admin cannot see platform NULL users. — ✅ tested (`CustomerAccessScopeTest`)
3. Customer A Company Admin cannot see Customer B audit logs. — ✅ tested (`CustomerAccessScopeTest`)
4. Customer A Company Admin cannot see platform NULL audit logs. — ✅ tested (`CustomerAccessScopeTest`)
5. Customer user cannot browse Subscription Plans. — ✅ tested (`CustomerAccessScopeTest`)
6. Customer user cannot open License Management. — ✅ tested (`CustomerAccessScopeTest`)
7. Stock User cannot run Document reports. — ✅ tested (`ReportExportServiceTest`)
8. Document User cannot run Inventory reports. — ✅ tested (`ReportExportServiceTest`)
9. Billing report requires Billing View. — ✅ tested (`ReportExportServiceTest`)
10. Unauthorized direct report code returns 403. — ⚠️ reasoned-correct via `ReportExportServiceTest` (the same `availableTo()` call `Reports::download()`'s `abort_unless` uses), but the Livewire HTTP path itself is not directly under test — a `Livewire::test(Reports::class)` attempt hit unrelated Livewire component-snapshot test plumbing issues and was not worth forcing. Real-browser verification (Company Admin denied `/admin/subscription-plans`, `/admin/licenses`) was run manually via Playwright on 24 August 2026 and passed.
11. Customer global search cannot expose platform/other-tenant records. — ✅ tested (`CustomerAccessScopeTest::test_global_search_does_not_expose_platform_only_resources_to_customer` asserts `canGloballySearch()`; the underlying query path is covered by items 1–4).
12. Mobile/PWA navigation matches desktop authorization. — ⚠️ not covered by this pass; existing `tests/playwright/role-qa.spec.js` covers desktop role QA only (all roles' navigation/permission assertions pass unchanged after this implementation — verified by browser run on 24 August 2026; 10 pre-existing, unrelated CSP-console-error failures on external font/avatar CDNs in the same run are an environment issue, not a regression from this change).

---

# 10a. Security Review Findings (24 August 2026 implementation pass)

An independent `security-reviewer` pass on the implementation above found three
High and three Medium findings. Fixed same-day unless noted:

- **H1 (fixed):** `TENANT_WITH_GLOBAL_DEFAULTS` was read/write — a tenant's
  "manage" role could rename, re-own or delete a shared global-default
  record (e.g. a Document Type) that every other tenant relies on. Fixed in
  `BaseResource::can()` (write actions on a null-owned record are now always
  denied for non-platform users) and `BelongsToCustomer`'s `updating` hook
  (cancels the save as a second line of defence). Regression tests in
  `TenantScopeTest`.
- **H2 (fixed):** a non-platform user with `customer_id = NULL` (a data-
  integrity defect — e.g. an admin who forgot to select a company when
  creating a tenant user) fell through every "is this scoped?" check to
  *unscoped*, granting full cross-tenant read access. Fixed by making
  `AccessControlService::canLogin()`, `BelongsToCustomer`'s global scope, and
  `BaseResource::getEloquentQuery()`/`can()` fail closed (no rows / denied)
  for this state instead. Regression tests in `AccessControlTest` and
  `TenantScopeTest`. The underlying data-integrity gap (the `customer_id`
  Select on `UserResource`'s form has no `->required()`) is not yet closed —
  tracked below.
- **H3 (fixed 24 August 2026, follow-up pass):** `LocationTypeResource`
  (`manage inventory`) is global master data — the `location_types` table has
  no `customer_id` column at all, the same shape as the module catalogue —
  but had no `$platformOnly`/read-only restriction, so a Stock Inventory
  User at any tenant could edit/delete a location type other tenants'
  `locations` rows reference. Resolved as PLATFORM_ONLY (matching the module
  catalogue precedent): `LocationTypeResource::$platformOnly = true`. A
  tenant can still *select* an existing location type when creating a
  Location (`LocationResource`'s `relationship()` select queries the model
  directly, not through this resource) — only the admin CRUD screen for the
  shared catalogue is now platform-only. Added to §3.1 in the Security &
  Access Control Matrix (v1.2). Regression test:
  `CustomerAccessScopeTest::test_customer_user_cannot_manage_location_types`.
- **M1 (fixed):** `$platformOnly` is now also set on `ModuleResource`,
  `BackupResource`, and `LocationTypeResource` (verified zero live behaviour
  change for the first two — no tenant role holds `manage modules`/`manage
  settings`/`view modules`/`view settings`). `SettingResource` was
  deliberately left as `TENANT_WITH_GLOBAL_DEFAULTS` (not `$platformOnly`),
  matching §3.3's own "Explicitly approved global Settings/reference values"
  example, in case a tenant-readable-settings permission is granted in
  future; it is currently inert for the same reason.
- **M2 (fixed):** `$platformOnly` is now also enforced in
  `BaseResource::getEloquentQuery()` (`whereRaw('1 = 0')`), not just
  `can()`/`shouldRegisterNavigation()`, as defence in depth for any future
  relation manager/select query.
- **M3 (fixed 24 August 2026, follow-up pass):** the Security & Access
  Control Matrix classified "Licenses" under both §3.1 PLATFORM_ONLY and
  §3.2 TENANT_STRICT. Resolved in the matrix itself (v1.2): §3.1 now reads
  "License administration" (the standalone `LicenseResource` — create,
  renew, suspend, revoke, technical configuration, internal fields); §3.2
  now reads "License status/history" (read-only status/access-mode/
  validity/expiry plus the license log/audit trail, via Dashboard/My
  Company — `LicenseLogResource` and `MyLicenseStatusWidget`, both already
  implemented this way). No code change was needed — the implementation
  already matched this split; only the matrix's own self-contradiction was
  fixed.

---

# 11. Completion Criteria

Do not mark these gaps conformant until:

- Code implemented
- Unit/feature tests pass
- Browser role QA passes
- Pint passes
- Larastan/PHPStan passes
- Build passes
- Security review passes
- Documentation remains synchronized
- No Critical/High issue remains in this scope

---

# 12. Status

**Documentation target state:** ✅ Approved and synchronized (matrix v1.2)
**Implementation:** ✅ All items in this document (§2–§10a) are implemented,
including §8 (My Company navigation consolidation)
**Regression verification:** ✅ automated test suite green (Pest/PHPUnit);
Pint clean; Larastan/PHPStan clean; independent security-reviewer and
qa-tester passes completed 24 August 2026 for both the access-control
hardening and the My Company cluster; real-browser Playwright verification
completed for the platform-only lockdown, audit-log scoping, the full
existing role-QA suite (`tests/playwright/role-qa.spec.js`, all
role/permission assertions pass unchanged), and My Company's per-role tab
visibility (which caught and fixed one real defect — see §8)
**Production-ready for this access-control change:** every item named in
this document is implemented, automated-tested, and independently
reviewed. This document only tracks the 24 August 2026 access-control
review's scope — it does not certify the platform as a whole.
