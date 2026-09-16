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

### Implemented — Platform Customer 360 (browsing, scoped create, nav consolidation)

This is primarily a functional/UX conformance gap, not evidence of a new data leak in the existing separate-resource implementation.

Implementation risk is **High** because the change touches authorization, tenant context, subscriptions, licensing and billing.

Required implementation:

1. Add Customer View / Customer 360 page. ✅
2. Add Overview. ✅
3. Embed/reuse Users. ✅ (browse + Add User)
4. Embed/reuse Customer Modules. ✅ (browse + Add Module)
5. Embed/reuse Customer Subscription. ✅ (browse + Add Subscription)
6. Embed/reuse License administration. ✅ (browse + Add License)
7. Embed/reuse Billing & Payments. ✅ (browse + Add Billing Record)
8. Embed/reuse customer Audit Logs. ✅ (browse only — system-generated, no manual create)
9. Derive all child `customer_id` values from the selected parent Customer. ✅
10. Remove/hide duplicate customer-specific platform navigation after parity is verified. ✅
11. Preserve separate platform master areas. ✅
12. Preserve customer-facing My Company. ✅
13. Add browser/security regression tests. ✅ (Feature tests + Playwright)

All 13 items are implemented as of 25 August 2026. Each tab (except Audit Logs) has an "Add X" header action that creates a new child record with `customer_id` fixed to the selected Customer 360 parent — see Security Acceptance below for how. Row actions still link out to each resource's own existing View/Edit page for further edits.

Item 10: `UserResource`, `CustomerModuleResource`, `CustomerSubscriptionResource`, `LicenseResource` and `BillingRecordResource` now set a new `BaseResource::$consolidatedViaCustomer360 = true` flag (mirroring the existing `$customerFacingViaMyCompany` mechanism, but hiding the standalone top-level nav entry for platform users too, not just tenant users). The resources' own routes/pages/`can()`/`getEloquentQuery()` are unchanged and fully functional — only the duplicate sidebar entry is hidden; Customer 360's embedded tables and "Add X" actions link straight to them. `AuditLogResource` deliberately does **not** set this flag — its top-level "Platform Audit Logs" nav stays, per "Platform-wide master/administration remains separate" above; Customer 360's Audit Logs tab is additive, not a replacement.

**Extended 25 August 2026 to Locations** (not named in the original design review's tab list, but the same over-broad-platform-list problem applied): `LocationResource` gained a Customer 360 "Locations" tab (browse + "Add Location", identical mechanism to the five resources above) and sets `$consolidatedViaCustomer360 = true`. Unlike the five resources above, `LocationResource` does **not** set `$customerFacingViaMyCompany` — tenant users keep their own existing standalone "Locations" nav unchanged, same as other operational stock resources (Categories, Products, Stock Movements) rather than being folded into My Company; their data was already correctly isolated per customer via the `BelongsToCustomer` model trait (global scope + forced `customer_id` on create/update), independent of any Filament-layer flag. The only tenant-facing change is removing the always-forced, single-option `customer_id` picker from their form (`->visible(fn () => auth()->user()?->is_platform_user)`, the same precedent `BillingRecordResource` already used for its own `customer_id` field). This required a fix to `BaseResource::shouldRegisterNavigation()` itself: the platform-only consolidation check had been placed ahead of the platform-user branch, so it silently hid nav from tenant users too for every resource that set it — harmless for the five resources above (which also set `$customerFacingViaMyCompany`, hiding tenant nav anyway) but wrong for Locations. Fixed and covered by `tests/Feature/MyCompanyClusterTest.php::test_location_navigation_stays_for_tenant_users_but_hides_for_platform_users`.

### Security Acceptance

Customer A profile must never mutate/read Customer B child data. **Verified** — `App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable` constrains every embedded tab's query to `where('customer_id', $customer->getKey())` on top of the wrapped resource's own query; covered by `tests/Feature/CustomerProfileTest.php::test_each_tab_shows_only_the_selected_customers_rows`.

Customer 360 child forms must not accept an arbitrary browser-selected customer ownership. **Verified** — the same trait's `customerScopedCreateAction()` replaces each wrapped resource's own free `customer_id` Select with a `Hidden` field fixed to the selected Customer, and `mutateFormDataUsing()` additionally force-overwrites `customer_id` server-side regardless of what's submitted, as defence in depth against a tampered hidden-field value. Covered by `test_create_action_forces_the_selected_customers_id_and_ignores_a_tampered_value` (a deliberately tampered `customer_id` targeting a different customer is silently discarded) and a real-browser Playwright assertion that no "Customer" field is rendered in the modal at all.

Datamation Management must remain read-only. Unaffected by this change — Customer 360's create actions require the acting platform user to actually hold the wrapped resource's own `manage *` permission (`BaseResource::can()`'s existing write-action branch), same as using that resource's standalone create page directly.

Customer roles must not access Platform Customer 360. **Verified** — `CustomerResource::canAccessCustomer360()` requires `auth()->user()->is_platform_user` in addition to `can('view', $record)`, layered in front of every Customer 360 page precisely because `can('view')` alone legitimately passes for a tenant viewing their own record (required by My Company's `Overview::canAccess()`); covered by `test_non_platform_user_is_denied_even_for_their_own_customer`.

### Status

**Documentation:** ✅ Approved
**Implementation:** ✅ Implemented (embedded browsing, scoped create actions, standalone nav consolidated)
**Security regression tests:** ✅ Added (`tests/Feature/CustomerProfileTest.php`, `tests/Feature/MyCompanyClusterTest.php`, `tests/playwright/role-qa.spec.js`)
**Browser QA:** ✅ Passing (Playwright: browse all tabs, Add User end-to-end, non-platform denial, standalone nav gone + routes still functional)
**Conformance:** ✅ Closed

**Known pre-existing gap, not introduced by this change (flagged, not fixed):** `BaseResource::usageLimitReached()` keys its "Limit Rule" check off the *acting* user's own `customer_id`, which is always null for a platform user — so a platform-initiated create (via Customer 360's Add actions, or via any of these resources' own standalone create pages, which already had this gap) never enforces a customer's subscription usage limit (e.g. `max_users`). Pre-existing in `BaseResource`, orthogonal to this feature; left for a separate fix.

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

---

## 13. UI/UX Production-Readiness Audit — 26 August 2026

System-wide sweep of every Filament resource (29) x every role (7) x
List/Create page, plus a targeted code-pattern review of status badges,
FK-select labels, destructive-action confirmations, and label wording,
per the DMIMS UIUX & Design System Specification's principles (Filament's
stock theme/components, no bespoke design system). Scope: representative
automated coverage (every resource, every role, desktop + mobile + tablet
nav shell), not manual per-screen review of every permutation.

**Method:**
- New `tests/playwright/uiux-audit.spec.js` (reuses `tests/playwright/
  qa-helpers.js`, extracted from `role-qa.spec.js` to avoid re-executing
  its top-level tests on import): for each of the 7 roles, hits every
  resource's List and Create route, asserts status is 200/403/404 (a 500
  or unauthorized 200 fails the sweep — this is a security check baked
  into the UI sweep), checks for horizontal overflow at desktop, and
  checks the nav shell renders without overflow at mobile (390x844) and
  tablet (768x1024).
- Grep-based review of `app/Filament/Resources/**/*.php` and
  `app/Filament/Clusters/MyCompany/Pages/**/*.php` for: destructive
  actions missing `->requiresConfirmation()`, status/enum columns missing
  `->badge()`, divergent badge-color-to-meaning mappings, FK `Select`
  fields relying on Filament's auto-generated label.

**✅ Implemented (26 August 2026):**
- **Status column inconsistency (Medium):** 10 of 17 status-bearing
  resources rendered `status` as plain unstyled text while 7 already used
  `->badge()->color()` (Backup, BarcodeRegistry, Box, BillingRecord,
  Export, Import, Location). Added badge/color to the missing 10
  (Category, CustomerSubscription, Customer, DocumentType, License,
  LocationType, Module, StockAlert, SubscriptionPlan, User), reusing each
  resource's own existing status vocabulary and the color convention
  already established elsewhere (success=active/good, warning=pending/
  transitional, danger=expired/suspended/critical, gray=inactive/
  cancelled/archived).
- **Internal field name leaking into labels (Medium, Rule 10):**
  Filament's default label generator turns `customer_id` into the literal
  label "Customer id" (confirmed in `vendor/filament/forms/.../Field.php`
  — `ucfirst()`, not `ucwords()`, and no `_id` stripping). This affected
  every `customer_id` Select across 19 resource forms, plus 17 further FK
  Select fields (`module_id`, `subscription_plan_id`, `category_id`,
  `default_location_id`, `department_id`, `parent_id`,
  `location_type_id`, `current_location_id`, `current_box_id`,
  `document_type_id`, `from_location_id`, `to_location_id`,
  `from_box_id`, `to_box_id`, `product_id`, `location_id`). Added explicit
  `->label(...)` to all of them (e.g. "Customer", "Department", "From
  Location"). Label-only change; no relationship, query, or authorization
  logic touched.
- Verified via automated sweep, `vendor/bin/pint`, `vendor/bin/phpstan
  analyse`, `php artisan test` (210 passed), `npm run build`, and the full
  Playwright suite (`role-qa.spec.js`, `website-qa.spec.js`,
  `uiux-audit.spec.js`).

**⚠️ Open Low — deferred, not fixed this pass:**
- **Create-action verb inconsistency:** Customer 360 embedded tables use
  "Add User"/"Add Location" (`HasCustomerScopedEmbeddedTable`), while
  Export/Import/BarcodeScanner use "New Export"/"New Import"/"New
  Document"/"New Box", and most other resources fall back to Filament's
  default `CreateAction` label. Cosmetic only; "Add" and "New" are
  near-synonyms and existing Playwright locators (`role-qa.spec.js`)
  assert the exact "Add User"/"Add Location" text, so a mass relabel
  risks churn for no material usability gain. Revisit only as part of a
  deliberate, documented copy pass.
- **Raw numeric FK inputs instead of pickers:** a handful of fields store
  a foreign key as a plain numeric `TextInput` rather than a searchable
  `Select` (`StockAdjustmentApprovalResource::stock_movement_id`,
  `LicenseLogResource::license_id`, `NotificationResource::user_id`,
  `StockAlertResource::product_id`/`location_id`,
  `SupportAccessLogResource::support_user_id`/`target_user_id`). Users
  must type a database ID rather than search by name. Converting these to
  relationship Selects is a real UX improvement but requires confirming
  each model's relationship methods and tenant-scoping behavior per
  field — out of scope for a label/badge pass; flagged for a follow-up
  ticket, not fixed here to avoid an unreviewed change to data-entry
  behavior on audit-trail tables.
- Mobile/PWA nav-parity-with-desktop-authorization gap noted in §12 of
  the prior pass remains open; this sweep's mobile/tablet check covers
  nav-shell rendering only (no overflow, sidebar/topbar visible), not a
  full parity audit.

**Not in scope / already covered:** Excel/PDF export logic, Filament
theme/viteTheme registration, branded error pages, JSON textareas on
Subscription/License forms, Enabled Modules blank-field bug — all fixed
in prior passes (see recent commit history) and re-verified clean by this
sweep, not re-audited from scratch.

---

## 14. Row-Action Modal Crash — Barcode Registries Preview/Print — 26 August 2026

**✅ Implemented (26 August 2026):**
- **Critical, fixed:** `BarcodeRegistryResource`'s `preview` row action (label
  "Preview / Print") and the `batchPrint` bulk action both crashed with a 500
  ("Call to a member function `makeGetUtility()` on null") whenever the
  modal's "Label size" `->live()` select was changed. Root cause:
  `->modalContent()` took a `Get $get` parameter; Filament resolves
  `Get $get` via `$this->getSchemaComponent()->makeGetUtility()`, but a
  `modalContent()` closure is not itself bound to a schema component, so
  `getSchemaComponent()` returns `null` on that specific code path. Found by
  the user hitting it live in production use (not caught by the automated
  `uiux-audit.spec.js` sweep, which only exercises List/Create page loads,
  not row-action modals — a real coverage gap, noted below). Fixed by
  switching both closures to the `array $data` parameter
  (`Action::getData()`, which does not require a schema component) and
  reading `$data['size']` instead of `$get('size')`. Reproduced the crash
  and verified the fix with a live Playwright run against the deployed site
  (open Preview/Print, change the size select, confirm no 500 and the modal
  re-renders). Commit `4339a16`.
- **Verification sweep (read-only, no code changes needed):**
  - Grepped every `modalContent()` and every `Get $get` usage across
    `app/Filament` — confirmed this was the only occurrence of the unsafe
    pattern; every other `Get $get` usage is inside an actual schema field
    closure (`->options()`, `->content()` on a Placeholder,
    `->unique(modifyRuleUsing:)`), which is safe because the field itself is
    the bound schema component.
  - Live-clicked every custom row/header action across the app that opens a
    modal (Box: Transfer, Move Out, Timeline; DocumentFile: Transfer, Move
    Out, Timeline; StockMovement: Receive In, Stock Out, Transfer, Adjust;
    BillingRecord: Record Payment, Issue, Cancel; BarcodeRegistry: Batch
    Generate, including its own `->live()` "Record type" select; Backup: Run
    Database Backup; Export: New Export; Import: New Import) — as
    Datamation Super Admin, as a tenant Company Admin, and (a targeted
    sweep) as all 7 QA roles. Zero crashes, zero console/network errors, and
    role-based visibility was correct throughout (each role only saw the
    actions its permissions allow).
  - Checked both real customers present in the environment: `Datamation
    Inventory Demo` (QA sandbox data) and `Madhan Inc` (a real account with
    one live billing record) — confirmed the Cancel/Record Payment/Issue
    action-visibility logic behaved correctly for the real record's actual
    status (`issued`/`paid` → only "Cancel" visible) and all 6 Customer 360
    tabs loaded cleanly for that customer. No destructive actions were
    opened/submitted against the real customer's data.
  - Confirmed structurally that action Select options which query related
    records (`Location::query()`, `Box::query()`, `Product::query()` inside
    Box/DocumentFile/StockMovement transfer actions) cannot leak across
    tenants: all three models use the `BelongsToCustomer` global-scope
    trait, so scoping is automatic for any query, not per-action logic that
    could be individually missed. Empirically confirmed too — as a tenant
    Company Admin, Box Transfer's "To Location" options only listed that
    tenant's own locations.
  - Final full-suite confirmation re-run after the fix: `vendor/bin/pint`,
    `vendor/bin/phpstan analyse`, `php artisan test` (210 passed), `npm run
    build`, and the full Playwright suite (`role-qa.spec.js`,
    `website-qa.spec.js`, `uiux-audit.spec.js` — 32 passed) against the live
    deployed site, not just locally.

**✅ Coverage gap closed (26 August 2026, follow-up):**
- `tests/playwright/uiux-audit.spec.js` only exercises resource List/Create
  page loads; it does not click into row-action modals, which is why this
  bug shipped past the original UI/UX audit pass (§13) undetected. Added
  `tests/playwright/row-actions.spec.js` to the standing `npm run qa` suite:
  as Super Admin, it opens (never submits) every custom row/header action
  modal app-wide — Box (Transfer, Move Out, Timeline), DocumentFile
  (Transfer, Move Out, Timeline), StockMovement (Receive In, Stock Out,
  Transfer, Adjust), BillingRecord (Record Payment, Issue, Cancel),
  BarcodeRegistry (Preview/Print, Lost/Damaged, Batch Generate), Backup (Run
  Database Backup), Export (New Export), Import (New Import) — and, for any
  modal exposing a `->live()`-updating select, also changes it before
  closing, which is the exact interaction that crashed Preview/Print.
  Header actions (no record needed) always run; row actions requiring an
  existing record skip gracefully when the QA seed hasn't created one yet,
  keeping the spec robust across seed states. Verified passing both locally
  (33/33 full suite, no regressions) and live against the deployed site.

---

## 15. Demo-Readiness Fixes — 7 September 2026

Marketing tested DMIMS ahead of a customer demo and filed 4 High-priority
issues in a ticket doc; a follow-up review widened scope to the full demo
workflow. All items below are implemented and verified
(`php artisan test`: 221 passed; `vendor/bin/pint --test`: passed;
`vendor/bin/phpstan analyse` (Larastan, level 5): no errors).

**✅ Implemented (7 September 2026):**
- **Box had no detail/view page** — only List/Create/Edit, no way to open a
  single box and see what's inside it or its history. Added a `view` page
  with record sub-navigation tabs: Overview, Documents Inside, Box Movement
  Log, Box Audit Log (`BoxResource::getRecordSubNavigation()`/`getPages()`,
  new `Pages\{ViewBox,Documents,MovementLog,AuditLog}`).
- **Document File had the same gap** — added a `view` page with Overview,
  Movement Log, and Audit Log tabs
  (`DocumentFileResource`, new `Pages\{ViewDocumentFile,MovementLog,AuditLog}`).
  Both detail pages share two new traits: `Concerns\HasScopedEmbeddedTable`
  (embeds another resource's table scoped to an arbitrary parent record)
  and `Concerns\HasAuditLogTab` (one row per audit event, "Changes" column
  summarizing `old_values`/`new_values` as `field: old → new`).
- **Customer → Location had no working Delete action** on the table (row
  actions were incomplete) and its Edit action navigated away from the
  Customer 360 Locations tab to a standalone page. Added a working Delete
  (with FK-violation handling) and an in-modal Edit. `Location::delete()`
  is now overridden with a `hasLinkedInventory()` guard blocking deletion
  while boxes, sub-locations, or product stock are still linked —
  previously unenforced because `Location` uses `SoftDeletes`, so the
  database's FK `RESTRICT` constraint never actually fired on delete.
- **Create Document File required `current_box_id`**, preventing a file
  from being registered before it was physically boxed. Made optional;
  `CreateDocumentFile::afterCreate()` now guards against a null box before
  calling `DocumentMovementService::receiveInFile()` (a latent TypeError
  otherwise). The Current Box field and the Transfer/Return box pickers now
  match by `box_barcode` as well as `box_number` (new
  `Box::searchByNumberOrBarcode()`), so a barcode scanner works directly in
  those fields.
- **"Add Document Mode" (new):** the Scan Center gained a "Target Box"
  field; while set, scanning a Document File barcode assigns that file into
  the target box via the existing `DocumentMovementService` instead of just
  looking it up. Guarded for tenant isolation (file and box must share the
  same `customer_id`) since platform users' queries aren't customer-scoped.
- Scan Center's "unknown barcode" prompt gained a "New Location"
  quick-create button (previously only "New Document"/"New Box").
- Location hierarchy dropdowns (Box's Current Location, Location's Parent
  Location, Box Transfer/Return pickers) now show the full ancestry path
  ("Room 1 > Area A > Shelf-A01") instead of a bare name, with `->preload()`
  added so options list without typing first — backed by a cached, single-
  query `Location::ancestryPathMap()` (invalidated on any Location write).
- `DocumentMovementLogResource`'s columns were expanded/relabeled to match
  the ticket's required set (Date & Time, Movement Type, From/To Location,
  From/To Box, Destination, Operator via a new `AuditLog::user()`/
  `DocumentMovementLog::performedBy()` relation, Reference/Tracking No) —
  this resource is used both standalone and embedded in the new Box/
  Document File Movement Log tabs.
- Cosmetic: "Borrowed by" relabeled to "Receiver" on the Document File Move
  Out action.

**Verified already working, no fix needed:** batch barcode printing (via
`BarcodeRegistryResource`'s bulk action), Box-level transfer/move-out/return
actions (already at parity with Document File), external dispatch with
receiver + optional return date (already `borrowed_by`/`due_date`, just
relabeled above), and Box's Current Location (already a displayed name, not
a raw ID).

**⚠️ Open — not reproduced, no fix shipped:** the original "Location edit
malfunction" ticket item could not be reproduced from source — no defect
was found in the plain `EditAction`. The in-modal edit UX change above is
the best diagnosis shipped for this item; if the reporter still sees a
distinct error live, it needs a follow-up fix driven from reproduction
evidence, not a further guess against source alone.

**Security review (same pass) — found and fixed before reaching the demo:**
- **High:** "Add Document Mode" wrote to Document Files/Boxes without
  checking `DocumentFileResource::can('update')`/`BoxResource::can('update')`
  — a role with only `manage inventory` could reassign a file it couldn't
  even see in Document Files, bypassing license/module gating too. Fixed:
  both checks now required before the write.
- **High:** `DocumentMovementService`'s transfer/return/receive-in methods
  had no same-customer check on the target box/location — for a platform
  user (whose Box/Location queries aren't customer-scoped), the Transfer/
  Return action's own dropdown could legitimately offer another customer's
  box/location. Fixed with a single `assertSameCustomer()` guard at the
  service layer (one chokepoint, not four duplicated call-site checks).
- **Medium:** the new Audit Log tabs gated on the record's view permission,
  not `view audit logs` (Security & Access Control Matrix §14 restricts
  audit logs to SA/Management/Company Admin) — Viewer/Supervisor/Stock/
  Document roles could see the full audit trail for any box/file they could
  view. Fixed: `canAccess()` now also requires `AuditLogResource::can('view')`.
- **Medium:** `HasAuditLogTab`'s query had no explicit `customer_id` filter
  of its own (`AuditLog` has no `BelongsToCustomer` scope); safe today only
  transitively via the parent record. Fixed as defense-in-depth.
- **Low:** `Location::ancestryPathMap()`'s cache was unkeyed (would serve
  one user's snapshot to the next request under a long-lived worker). Fixed
  by keying on the acting user's scope.
- **Low:** the new Location in-modal edit action was the only edit path
  skipping `ForcesOwnCustomerId`'s server-side re-assertion — not currently
  exploitable, fixed for consistency.
- **Low:** "Documents Inside" tab's `date_added` column was an N+1 (one
  query per row); replaced with a single correlated subquery.

**Regression tests:** `tests/Feature/DemoReadinessFixesTest.php` (11 tests)
— unboxed file creation, Location delete guard, Box/Document File tab
rendering, scan-to-box assignment, the cross-tenant scan guard, Add
Document Mode permission gating, Audit Log tab access control, and the
`DocumentMovementService` cross-customer transfer guard. Full suite:
221/221 passing.

## 16. Demo-Readiness Correction Pass — 8 September 2026

An external review of commit `5cfff19` (§15 above) against the original
demo scenarios found real gaps in the scan → act workflow and movement-
tracking integrity that §15 missed. Every claim was independently
re-verified against source (two read-only audits) before fixing anything —
all confirmed, one with a minor nuance (Box's audit log did already get a
generic `current_file_count` entry; it just didn't identify which file).

**✅ Fixed (8 September 2026):**
- **Dispatched-file scan bug** — scanning a moved-out file into a box via
  Add Document Mode was indistinguishable from a never-boxed file (both
  have `current_box_id = null`) and was logged as a fresh `'create'`
  intake instead of a `'return'` — `returned_at` never set, stale
  `destination`/`due_date` left in place. `BarcodeScanner::assignScannedFileToBox()`
  now checks `current_status === 'moved_out'` first and routes to
  `DocumentMovementService::returnFile()`.
- **Scans now land somewhere actionable** — `ScannerService::recordUrl()`
  always linked to `edit`, ignoring the `view` pages added in §15, and
  those view pages had zero header actions. Fixed both: `recordUrl()` now
  prefers `view` when the resource has one; Transfer/Move Out/Return/
  Timeline were extracted into reusable methods and added as header
  actions on the Box/Document File View and Edit pages (same
  authorization as the List page's row actions — verified with a Viewer
  role test).
- **Edit forms no longer bypass movement tracking** — `current_location_id`/
  `current_box_id` were plain editable fields on Edit with no logging;
  correcting placement there left `DocumentMovementLog` and box file
  counts silently wrong. Now locked on Edit (matching the existing
  `current_file_count` pattern) — Create is unaffected; corrections go
  through Transfer/Move Out/Return instead.
- **Box Audit Log now identifies the file** — `DocumentMovementService`
  writes an explicit `file_linked`/`file_unlinked` entry (with the file's
  barcode) on every box entry/exit, not just the incidental generic
  `current_file_count` delta.
- **Location gained its own Overview + Audit Log tab** — previously
  List/Create/Edit only; needed for the demo script's "create a shelf via
  scan, review its audit history" step.
- Scan Center's quick-create buttons now carry the scanned code into the
  destination Create form; a new "Scan Documents In" button on the Box
  view page pre-selects that box as the Add Document Mode target.

**Confirmed not bugs, unchanged by this pass:** Audit Log's single
"Changes" column (an explicit prior decision, not the ticket's literal
separate Field/Old/New columns); unboxed file creation logging no
movement event (correct — nothing moved).

**✅ Closed (10 September 2026):** the "Location edit malfunction" ticket item
— live-reproduced in the browser against Customer 360's Locations tab
(`/admin/customers/{id}/locations`): opened Edit on an existing location, the
modal pre-populated every field correctly, changed `location_name`, submitted,
got a "Location updated" success notification, the table reflected the new
name immediately, and re-opening Edit showed the change persisted. No defect
found — the in-modal edit UX shipped in this pass (replacing navigation to a
standalone page) is confirmed to fully resolve the reported symptom. Reserving
barcode labels for not-yet-existing records (pre-printing) remains deferred as
a larger, separate feature.

**Regression tests:** `tests/Feature/DemoCorrectionPassTest.php` (12
tests) — the dispatched-file return-workflow fix, working Transfer actions
on both View pages, Edit-form field locking (and other fields still
saving), Box Audit Log file identification, Location Audit Log tenant
isolation, RBAC on the new header actions, and safe handling of a
malicious barcode value through the quick-create query param. Full suite:
236/236 passing.

## 17. Review of a Parallel Codex Session's Barcode Work — 9 September 2026

A separate, uncommitted local checkout (`DMIMS-CODEX`, one commit behind
`main`) contained another AI agent's large, unreviewed rewrite of the
barcode/movement subsystem (label pre-printing/reservation, a global
`unique(barcode)` constraint, capacity-aware move validation, a
scan-to-destination picker, print/retire/replace actions). Reviewed in
full for genuinely portable fixes rather than adopted wholesale.

**✅ Fixed (9 September 2026):**
- **`Box.current_file_count` drift** — `DocumentMovementService::adjustBoxFileCount()`
  applied a `+1`/`-1` delta; any write path that ever bypassed the service
  left the count permanently wrong. Now recomputed from `files()->count()`
  on every move — self-healing.
- **Location hierarchy integrity** — nothing validated `parent_id` beyond
  the FK existing, so a Location could become its own ancestor (infinite
  loop risk in `getAncestryPathAttribute()`) or be reparented into another
  customer's tree. `Location::booted()` now cancels a `parent_id` change
  that would create either. New `tests/Feature/LocationHierarchyTest.php`.
- **Accessibility** — Box view page's "Add Document Mode" checkbox gained
  `role="switch"` / `aria-label`.

**Investigated, found already correct:** the Codex diff's rationale for
hardening `CreateRecord`/`EditRecord` claimed `current_location_id`/
`current_box_id` being `disabled()` on Edit (§16) was client-side only and
bypassable via a crafted Livewire request. Traced through
`vendor/filament/schemas/src/Components/Concerns/HasState.php::dehydrateState()`:
a non-dehydrated field's key is stripped from the state array server-side,
per the field's own definition, before `mutateFormDataBeforeSave()` ever
runs — not based on client-supplied state. No change needed.

**⚠️ New gap found, not yet fixed (needs a decision):**
`DocumentFileResource`'s `current_status` and `BoxResource`'s `status`
fields are freely editable on Edit — unlike their sibling
`current_box_id`/`current_location_id`. Setting `current_status` to
`'moved_out'` (or back to `'active'`) directly via Edit bypasses Move
Out/Return and leaves `current_box_id` stale, reproducing §16's
dispatched-file bug via a different path. Can't simply lock the field:
`damaged`/`missing`/`archived`/`closed` have no dedicated action and are
only reachable through this Select today. Needs either a narrower guard
(reject only the `moved_out`/`active` transitions on direct edit) or
dedicated status-change actions — deferred pending that decision.

**Deliberately not ported:** barcode pre-printing/reservation + global
uniqueness constraint (needs a duplicate-barcode data check and a
tenant-policy decision on global vs. per-customer uniqueness first — see
`docs/DMIMS Database Dictionary.md` for the current per-customer model);
box/location capacity enforcement during moves (`capacity_limit`/
`box_capacity` exist but nothing reads them at move time — real gap,
large change, best done incrementally); a scan-to-destination picker
built on non-public Livewire internals (`$wire.__instance`) — flagged as
a maintenance risk, not ported without a safer implementation.

**Regression tests:** `tests/Feature/LocationHierarchyTest.php` (2 new
tests). Full suite: 254/254 passing; Pint and Larastan (level 5) clean.

## 18. Fixing the Three Items Deferred in §17 — 9 September 2026

Planned and implemented all three items §17 deferred, after a research pass
(3 parallel Explore agents + 1 Plan agent) resolved the open decisions each
one needed.

**✅ Fixed — `current_status`/`status` desync guard:**
`DocumentFileResource`'s `current_status` and `BoxResource`'s `status`
Select fields gained a closure-based validation rule (same `Get $get`
convention already used on `file_barcode`'s uniqueness rule a few lines
above): on Edit, rejects setting the field to `'active'` when the record's
box/location is null, or to `'moved_out'` when it isn't — only when the
submitted value differs from the record's current value, so unrelated edits
and no-op resaves are unaffected. Every administrative value (`archived`/
`missing`/`damaged`/`closed`, `transferred`) stays freely editable, since no
dedicated action exists for them. 6 new tests in `DemoCorrectionPassTest.php`.

**✅ Fixed — barcode pre-printing/reservation (MVP):** Resolved the
uniqueness-policy question by precedent rather than by new decision: this
project already deliberately chose per-customer (not global) barcode
uniqueness (`2026_08_18_000001_scope_barcode_uniqueness_to_customer.php`,
"DBA review finding, Critical #2"), and `barcode_registry` already has
`unique(customer_id, barcode)` — no change needed there. The actual missing
capability was reservation itself: `BarcodeService::reserve()` pre-generates
N `'unused'` registry rows (no reference) for a customer+type, reusing the
same per-customer sequence counter and `lockForUpdate()` pattern as
`registerFor()`; `claim()` attaches one to a newly-created record whose
barcode was pre-filled from a reservation (no-op for a manually-typed
barcode). `ScannerService::scan()` gained a distinct `'unused'` result;
`BarcodeScanner::scan()` redirects it straight to the matching resource's
Create form. New "Reserve Labels" header action on Barcode Center. New
migration `2026_09_09_000000_add_barcode_reservation_support.php` widens
`barcode_registry.status` and `barcode_scan_logs.scan_result` (both via
Blueprint's native `enum()->change()`, not raw SQL, so it applies on both
the SQLite test driver and MySQL) and makes `reference_table`/`reference_id`
nullable; `down()` refuses to roll back while any `unused`/logged-`unused`
rows exist. Explicitly out of scope (unchanged from §17): the Print/Retire/
Replace action split and the scan-to-destination picker. 8 new tests across
`BarcodeScannerTest.php`, `BarcodeCenterTest.php`, `ScanCenterTest.php`.

**✅ Fixed — box/location capacity enforcement:** `DocumentMovementService`
gained `assertBoxHasCapacity()`/`assertLocationHasCapacity()`, styled like
the existing `assertSameCustomer()` guard (same exception type, same
before-the-transaction placement), called in every method that places a
file into a box or a box into a location (not the "move out" methods —
capacity only gates entries). `0`/`null` both mean unlimited, matching the
existing `capacity_percent`/`box_capacity_percent` accessors' falsy check —
no new policy invented. Bundled fix: every `DocumentMovementService` call
site in the Filament layer (`BoxResource`, `DocumentFileResource`,
`BarcodeScanner`, `ViewBox` — 6 sites total) now catches
`InvalidArgumentException` and shows a danger notification instead of a raw
500 — this also closes the pre-existing gap where `assertSameCustomer()`
failures had no handling at all (confirmed via grep: no call site anywhere
caught it before this pass). Creating a record directly into an
over-capacity box/location still creates the record — `current_box_id`/
`current_location_id` are cleared back to null (Box additionally flips to
`status = 'moved_out'`, mirroring `moveOutBox()`'s existing "exists, not
placed" representation) rather than left pointing at a box/location the
record was never actually logged into. 8 new tests in
`DocumentOperationsTest.php` and `DemoCorrectionPassTest.php`.

**Regression tests:** 22 new tests total across the 5 files named above.
Full suite: 273/273 passing; Pint and Larastan (level 5) clean. Migration
verified against both the SQLite test driver and the actual local dev
database — MySQL/staging behavior relies on Laravel 13's native
cross-driver `Blueprint::change()` and was not independently verified
against a live MySQL instance.

## 19. Location Chain Builder, Batch Generate, and Box View Redesign — 9 September 2026

Ported UI/UX from a reference implementation of this system the user shared
(screenshots + a full export of an older codebase) — see `CHANGELOG.md` for
the feature-level description. Two things worth recording here specifically:

- **Deliberate exception to the "no `infolist()` override" convention**:
  `BoxResource` now has a real `infolist()`. Every other resource's View page
  (`DocumentFileResource`, `LocationResource`, etc.) still falls back to
  Filament's default read-only form embed, as before — this is a single,
  scoped exception for Box only, not a new app-wide pattern. If a future
  change wants the same richer layout on `DocumentFileResource`'s View page,
  design it fresh (a `DocumentFile` sits in a `Box`, not directly in a
  `Location` — a different breadcrumb shape) rather than assuming this one
  generalizes.
- **`LocationType` is no longer empty by default**: `LocationTypesSeeder`
  (called from `RolesAndPermissionsSeeder`) seeds the 7-level hierarchy
  already documented in `docs/DMIMS Data Migration Strategy & Execution
  Guide.md` §13. Any environment seeded before this change needs
  `php artisan db:seed --class=RolesAndPermissionsSeeder --force` re-run
  (idempotent, safe to re-run) to pick up the new types.

**Regression tests:** 9 new tests across `LocationChainBuilderTest.php`,
`LocationBatchGenerateTest.php`, `BoxViewInfolistTest.php`. Full suite:
282/282 passing; Pint and Larastan (level 5) clean.

## 20. Box View — Inline RelationManager Tabs (Follow-up) — 9 September 2026

The user asked for a closer match to the reference screenshots on two specific
points: Box View's Documents/Movement/Audit tabs should render inline on one page
(not as separate sub-navigation pages), and the scan-toggle should be a header
button, not an in-page checkbox.

**✅ Fixed:**
- `BoxResource::getRecordSubNavigation()` (3 separate pages) replaced with
  `BoxResource::getRelations()` (Filament's native inline `RelationManager` tab
  strip — confirmed this is the same mechanism the reference project itself uses).
  `Box::movementLogs()`/`Box::auditLogs()` are new manually-scoped `hasMany`
  relations (`movable_type`/`auditable_type` are plain string columns in this
  schema, not a Laravel morph map, so `morphMany` doesn't apply directly).
  `app/Filament/Resources/BoxResource/Pages/{Documents,MovementLog,AuditLog}.php`
  were deleted — their table/column logic moved into the 3 new
  `BoxResource/RelationManagers/*` classes rather than kept as duplicated,
  now-orphaned pages.
- `ViewBox`'s "Add Document Mode" checkbox became a header `Action` labeled
  "Scan Mode: ON"/"Scan Mode: OFF" (icon + color matching the reference). The
  underlying scan-to-assign logic (`scanDocument()`) is untouched and fully
  functional — worth noting the reference project's own equivalent toggle was
  traced to dead code during the original UI/UX research pass; this codebase's
  version is a real, tested feature, not a cosmetic port of a non-functional one.

**Testing nuance recorded for future reference:** `RelationManager`'s
`CanAuthorizeAccess` trait (`hydrateCanAuthorizeAccess()`) only re-checks
authorization on Livewire *hydrate* (a follow-up request), not on the component's
initial `mount()` — by design, since a RelationManager is normally only reachable
through its parent page, which already gates tab visibility via
`canViewForRecord()` before the tab is ever rendered. Testing
`Livewire::test(SomeRelationManager::class, [...])->assertForbidden()` directly
therefore does **not** exercise that gate on first load. The correct test is
either a direct call to `SomeRelationManager::canViewForRecord($record, $pageClass)`,
or (better, end-to-end) asserting the tab is absent from the parent page's
rendered output for that role — both are now used together in
`DemoReadinessFixesTest::test_viewer_cannot_access_box_audit_log_tab()`.

**Regression tests:** 3 existing tests updated (page-class references →
relation-manager references in `DemoCorrectionPassTest.php` and
`DemoReadinessFixesTest.php`); no new test files. Full suite: 282/282 passing;
Pint and Larastan (level 5) clean.

## 21. UX Feedback Pass Against the Same Reference System — 10 September 2026

A second feedback round against the same reference screenshots/legacy export used in
§19-20, this time covering login/auth, Reports, Boxes/Document Files bulk actions and
Barcode Center — audited by direct code read against each requirement rather than
assumption, since §19-20 had already shipped much of what a naive re-read of the
screenshots would suggest was missing.

**✅ Implemented (10 September 2026):**
- **Login: Cloudflare Turnstile + background image.** New `App\Filament\Auth\Login`
  (replaces Filament's stock login page), `App\Services\TurnstileVerifier` (server-side
  `siteverify` call, fails closed on missing token/failed challenge/network error, no-op
  when `TURNSTILE_SITE_KEY`/`SECRET_KEY` are unset). CSP widened only for
  `challenges.cloudflare.com`. Background image applied via a `.fi-simple-layout` CSS rule
  (covers login, password reset, and any future Filament "simple layout" auth page, not
  just login). `tests/Feature/TurnstileLoginTest.php` (7 tests): verifier enabled/disabled
  state, success/failure/network-error paths, and a real `Livewire::test(Login::class)`
  authentication round-trip proving a missing/failed token actually blocks login and a
  passing one doesn't.
- **Department management — root cause found and fixed.** `Department` had a model,
  migration, and `department_id` FK on `document_files`, but **no Filament resource ever
  existed to create one** — for any customer with none seeded, the "Department" dropdown
  on Document Files was empty with no way to populate it. This was reported as "the
  Department dropdown doesn't work"; the actual defect was a missing CRUD screen, not a
  query/scoping/persistence bug — `relationship('department', 'name')` was already correct
  and already tenant-scoped via `Department`'s own `BelongsToCustomer` trait. New
  `App\Filament\Resources\DepartmentResource` (reuses the existing `manage documents`/
  `view documents` permission — no new permission added). `DocumentFileResource`'s
  Department field now shows an inline link to create one when none exist. **Boxes do not
  have a Department field in this schema at all** (confirmed against both the current
  migration and the legacy reference export) — the original request's Boxes section
  mentioning a "Department dropdown" does not apply; flagged as a documentation/request
  mismatch rather than inventing a field with no underlying column.
- **Document Reports dashboard.** `Reports` page gained a live filters (date range/
  status/location)/KPIs (Total Documents, Dispatched Externally, Missing Documents,
  Tracked Boxes)/status-breakdown bar/two recent-records tables (with CSV export) section
  matching the reference screenshot, scoped to tenant users with Document Tracking access.
  Platform users keep the existing multi-report export form (no single customer_id to
  scope a cross-tenant dashboard's aggregates to — a deliberate scoping decision, not an
  oversight). Inventory/Platform/Billing reports are unchanged. `tests/Feature/DocumentReportsDashboardTest.php`
  (5 tests) — including a cross-tenant isolation regression that initially failed due to a
  test-authoring ordering bug (fixtures for "another customer" were created while already
  `actingAs()` the first tenant, so `BelongsToCustomer`'s `creating()` hook force-rewrote
  their `customer_id` — fixed by building cross-tenant fixtures before `actingAs()`, the
  same convention `DocumentTenantIsolationTest` already used), not a product defect.
- **Boxes/Document Files list — bulk actions, Actions grouping, row-click target.** Both
  gained row selection with bulk "Print Barcode" (new `HasBarcodeAction::bulkBarcodeAction()`)
  and "Delete" (new `BaseResource::deleteSelectedWithReport()` — per-record `can('delete')`
  + any model delete guard, reporting deleted/skipped/failed counts). Transfer/Move Out/
  Return/Timeline grouped into one "Actions" dropdown. **Root cause of "clicking Box
  Number opens Edit instead of View" found:** Filament's default `recordUrl` resolution
  (`ListRecords.php`) only checks for a registered `view`/`edit` *table action*, in that
  order — only `EditAction` was ever registered in `recordActions()`, so it always won.
  Fixed with an explicit `->recordUrl()` pointing at the `view` page; `EditAction` removed
  from the list (Edit stays reachable from inside View's header, already present from an
  earlier pass). Location column now truncated with a hover/focus tooltip.
- **Box delete guard.** `Box::delete()` now blocks deleting a box that still contains
  files (`hasLinkedDocuments()`), mirroring `Location::delete()`'s existing guard (§15) —
  same latent bug shape: `boxes` uses `SoftDeletes`, so the FK `RESTRICT` on
  `document_files.current_box_id` never actually fires.
- **Move Out / "Dispatch to External Party" fields.** Box and Document File Move Out
  modals now collect Recipient/Contact Name, Company/Vendor Name, Delivery Address,
  Courier Tracking Ref., Expected Return Date, Additional Notes. Only `destination`/
  `borrowed_by`/`due_date` (Document Files only) have dedicated columns; the rest compose
  into `remarks` (already flows into `DocumentMovementLog`/Timeline) rather than adding
  schema for a UI-only request.
- **Barcode Center.** Batch Generate/Reserve Labels record-type options now filtered to
  the customer's enabled modules + permissions instead of a hardcoded 4-type list. Status
  filter now includes `unused`. Batch Print's preview modal gained a working "Print
  Barcode Labels" button (`window.print()`) — previously Submit only marked labels printed
  with no way to actually trigger printing from the modal. Barcode labels (individual and
  bulk) now show the record's own descriptive title above the barcode value, matching the
  reference's "Legal Files 2026 / BOX-00001" layout.
- **Scan Center.** "Add Document Mode — Target Box" relabeled "Bulk Scan: ON/OFF — Target
  Box" to match requested terminology. The underlying behavior (stays on page, keeps
  target box selected, reuses `DocumentMovementService` including the dispatched-file
  return workflow) was already correct from §16-18 — label-only change.

**⚠️ Documented conflict, not resolved by picking one side silently:** the request asked
to "replace the nonworking Batch Generate... with Search Barcode" but also to "not remove
shared barcode-generation logic required by valid workflows." Batch Generate was found to
be working code (not broken), and the list's own existing search bar + type/status filters
already satisfy "Search Barcode" as a capability (searchable `barcode` column, customer/
permission-scoped query). Resolution: kept Batch Generate, did not build a duplicate
"Search Barcode" screen — an earlier draft of this change added a non-functional stub
action for this, caught and removed before shipping.

**❌ Not done this pass (real, acknowledged gaps):**
- Inventory/Platform/Billing report filters/KPIs/charts (Document Reports only, matching
  the one screenshot actually supplied). `ReportExportService`'s 19 report definitions are
  otherwise unchanged.
- A full visual redesign of Boxes/Document Files Create/Edit forms beyond the Department
  fix and a storage-location helper text on Box Assignment (Create Document File already
  included Box Assignment from an earlier pass, per §15/§19).
- Physical barcode printer/scanner hardware testing — not possible in this environment.
  Code128 SVG generation was verified to run and the label view renders; not verified
  against a real handheld scanner or label printer.
- A visual redesign of the Boxes/Document Files list beyond the bulk-actions/grouping/
  row-click/location-tooltip fixes above (column layout otherwise unchanged).

**Verification:** `vendor/bin/pint --test` clean; `vendor/bin/phpstan analyse` (Larastan,
level 5) clean; `php artisan test`: 296/296 passing (was 282); `npm run build` clean.
Browser/Playwright verification was **not** run this pass (not available — see completion
report) — the above is verified at the automated-test level only; UI claims (label text,
tooltip behavior, print button) are verified by code/test, not by an interactive browser
session.

## 22. License/Subscription Separation Confirmed; License Technical Debt Cleanup — 10 September 2026

User asked whether Customer License and Customer Subscription are the same concept and
could be combined. Investigated via two parallel research passes plus direct code
verification.

**Finding: they must stay separate.** `docs/DMIMS Architecture Decision Records (ADR).md`
ADR-005 ("Separate Subscription and License", Accepted) rules this explicitly:
"Subscription controls commercial entitlement. License controls technical access." The code
genuinely implements that split as two independent gates in `AccessControlService`: License
(`getEffectiveAccessMode()`/`modeFromLicense()`) drives real request-blocking
(`EnsureLicenseAllowsAccess` middleware) and view-only mode from `technical_access_mode` +
expiry/grace period; Subscription (`getEffectiveLimits()` + `CustomerSubscriptionObserver`)
drives usage caps and `CustomerModule` sync, plus a separate `EnsureSubscriptionActive`
existence gate. Merging them would conflate two intentionally independent lifecycles (e.g.
a license can be blocked during a billing dispute while the subscription itself stays
active). **No merge was made — this section documents technical debt found while confirming
that, not an architecture change.**

**✅ Fixed (10 September 2026):**
- **Deleted `app/Services/LicenseService.php`** — confirmed via repo-wide grep to have zero
  callers anywhere in `app/`, `routes/`, `tests/`, `database/` (only two stale comments in
  `AccessControlService.php` referenced it). It duplicated validity logic that
  `AccessControlService::modeFromLicense()` already implements and actually uses. The two
  stale comments were reworded to drop the reference.
- **Removed dead `license_logs` infrastructure** (`App\Models\LicenseLog`,
  `App\Filament\Resources\LicenseLogResource`, and the `license_logs` table via new
  migration `2026_09_10_000000_drop_license_logs_table.php`). Root cause: `License` already
  uses the `Auditable` trait (`app/Models/Concerns/Auditable.php`), which records every
  create/update/delete with old/new diffs into the platform-wide `audit_logs` table,
  visible via the existing `AuditLogResource` — "the authoritative audit trail for the
  platform" per that trait's own doc comment. `license_logs` was modeled scaffolding no
  observer ever wrote to (confirmed via grep — zero `LicenseLog::create` calls anywhere),
  unlike its sibling `subscription_logs`, which `CustomerSubscriptionObserver` populates on
  every change. Confirmed not protected by `2026_08_18_000010_enforce_immutable_log_tables`'s
  DB triggers (those only guard `stock_movements`/`document_movement_logs`/`audit_logs`).
- **Removed License's dead duplicate fields** — `max_users`, `max_products`,
  `max_document_files`, `max_boxes`, `enabled_modules`, `allowed_reports` dropped from the
  `licenses` table (new migration
  `2026_09_10_000001_drop_dead_limit_and_module_columns_from_licenses_table.php`),
  `App\Models\License`'s fillable/casts, and the corresponding `LicenseResource` form
  fields. These duplicated `CustomerSubscription`'s identically-named columns, but only
  `CustomerSubscription`'s copies were ever read (`AccessControlService::getEffectiveLimits()`
  reads `CustomerSubscription` exclusively; module access is synced from
  `CustomerSubscription` via `CustomerSubscriptionObserver`, never from `License`). Keeping
  them on the admin form was actively misleading — an admin filling in License's
  `max_users` would reasonably assume it does something, when nothing ever read it.
  **⚠️ Data-loss note:** the migration's `down()` restores column structure only, not data —
  any historical values in these 6 columns are permanently lost once `up()` runs. Confirmed
  low-risk in this codebase specifically (no `License` factory exists; `ReportExportService`'s
  License Summary export only touches `license_no`/`customer`/`status`/
  `technical_access_mode`/`valid_to`; `DemoScenariosSeeder`'s `License::firstOrCreate(...)`
  call doesn't set any of the 6 fields) — flagged here so this isn't run against an
  environment with real license data without confirming that first.
- **Repointed `tests/Feature/JsonRuleValidationTest.php`** from `LicenseResource`'s (now
  removed) `enabled_modules` field to `CustomerSubscriptionResource`'s identical field —
  the test guards a real, previously-fixed Filament bug (`->rule(static::jsonRule())`
  throwing a 500 via Filament's closure dependency-injection), not something specific to
  License; `CustomerSubscriptionResource` uses the exact same `BaseResource::jsonRule()`
  mechanism on its own `enabled_modules`/`allowed_reports` fields, so the regression stays
  covered.

**Regression tests:** new `tests/Feature/LicenseResourceFieldsTest.php` (2 tests: the 6
columns are confirmed gone from the schema; a `License` still creates successfully without
them). `tests/Feature/JsonRuleValidationTest.php` rewritten in place (2 tests, same count,
now targeting `CustomerSubscriptionResource`). Both new migrations verified via
`migrate --pretend`, applied, rolled back, and re-applied cleanly against the local SQLite
test database (round-trip confirmed) — not independently verified against a live
MySQL/MariaDB instance before this release, consistent with this project's existing
migration-verification caveats (see §18's closing note for precedent). Full suite:
299/299 passing (was 296); Pint and Larastan (level 5) clean; `npm run build` clean.

## 23. UI Feedback Pass 3: Actions Grouping, Barcode Print Redesign, Scan Center Removal — 10 September 2026

Third feedback round against the same reference screenshots. Cloudflare Turnstile was
already implemented (§21) — real Site/Secret keys were added to `.env` this pass, not
re-implemented. Scope: Locations create-flow consolidation, Actions-dropdown grouping and
bulk actions across Boxes/Document Files/Locations, a barcode-print redesign (remove "Mark
as printed", add a real Print trigger + label-size select + print-only-the-label CSS), and
full removal of the Scan Center page per an explicit user decision (documented tradeoff:
loses the generic "scan any barcode → jump to that record" lookup; View Box's own Scan Mode
already covers the "scan Document Files into a box" use case this removal was justified by).

**✅ Implemented (10 September 2026):**
- **Locations: "Add Location" + "Location Chain Builder" combined into one button.**
  `LocationResource::createChainAction()` relabeled "Add Location" (was "Location Chain
  Builder") — a chain of one row with no starting parent behaves exactly like the old plain
  single-location create; a deeper chain builds a full hierarchy in one submit. The
  separate plain `customerScopedCreateAction('Add Location')` removed from Customer 360's
  Locations tab; the standalone `/locations` list's default page-level Create button
  suppressed (`ListLocations::getHeaderActions()` returns `[]`) so it doesn't duplicate
  `table()`'s own header actions. "Batch Generate" unchanged, still separate.
- **Actions grouped into one dropdown, per resource:**
  - Locations: Edit, Print Barcode, Delete now one `ActionGroup` (was three separate
    buttons).
  - Boxes: View, Transfer, Move Out, Return, Timeline, Print Barcode now one `ActionGroup`
    (was a standalone View button + a partial group + a standalone Print Barcode button).
  - Document Files: same consolidation as Boxes.
- **Bulk actions added to Locations** (`bulkBarcodeAction()` + `deleteSelectedWithReport()`
  via `BulkActionGroup`), matching the existing Box/Document File pattern exactly — no new
  code, reused `HasBarcodeAction`/`BaseResource` as-is.
- **Barcode print redesign** (`App\Filament\Concerns\HasBarcodeAction`, plus
  `BarcodeRegistryResource`'s `preview`/`batchPrint` actions for consistency, since they
  share the same views): "Mark as printed" submit-then-confirm step removed
  (`modalSubmitAction(false)`); a label-size `Select` added to the single-record action
  (previously only the bulk/registry actions had one); a "Print" button inside the modal
  content triggers `window.print()` directly, client-side, no server round-trip.
  `printed_count` now increments the moment the label modal is opened (there is no longer a
  submit click to hang it on) rather than on a separate confirmation.
  `resources/views/filament/barcode-label.blade.php` /
  `batch-barcode-labels.blade.php` gained print-only-the-label CSS (the standard
  `body * { visibility: hidden }` / `.dmims-print-label, .dmims-print-label * { visibility:
  visible }` technique) so printing shows only the title/barcode graphic/code value, not the
  modal heading, size selector, or Print/Close buttons.
- **Scan Center removed entirely** — `App\Filament\Pages\BarcodeScanner`,
  `resources/views/filament/pages/barcode-scanner.blade.php`, and `tests/Feature/
  ScanCenterTest.php` deleted. `ScannerService::recordUrl()` (its only caller) removed as
  now-dead code. `BoxResource::scanDocumentsInAction()` ("Scan Documents In", the only
  other link to the page) removed from the Boxes list, `EditBox`, and `ViewBox`. Three test
  methods in `DemoReadinessFixesTest.php`/`DemoCorrectionPassTest.php` that exercised
  Scan-Center-specific behavior already redundant with existing `ViewBox`-based "Add
  Document Mode" tests were removed without porting (confirmed equivalent coverage exists);
  the one genuinely unique case (`test_platform_user_cannot_scan_assign_a_file_into_another_customers_box`)
  was ported to exercise the same guard via `ViewBox::scanDocument()` instead.
- **View Box's Scan Mode: refresh + continuous-focus fixed.** Previously
  `$this->record->refresh()` only updated the page's own `$record` property — the
  "Documents in this Box" tab is a separate child Livewire `RelationManager` component with
  its own table query, which does not re-query just because its parent refreshed. Now also
  dispatches Livewire's special `'$refresh'` event (re-renders every component on the page,
  parent and children) after a successful assignment, so the new file appears in the list
  immediately. The scan input also refocuses after every scan attempt (success or reject)
  via a dispatched `'barcode-scanned'` browser event + an Alpine listener, so an operator
  can keep firing a handheld scanner without touching the mouse between scans.
- **Stale comments/docs updated:** every "Carries the scanned code over from the Scan
  Center's ... quick-create link" comment on Box/Document File/Location/Product's barcode
  field default (`request()->query('...')`) reworded — the underlying pre-fill-from-query-
  param behavior is left in place (harmless, still useful for a reserved-but-unclaimed
  barcode's URL), only the now-inaccurate "via Scan Center" framing was removed.
  `docs/DEMO_SCENARIO_DATA.md`'s demo script updated (Scenario 1: "Box Center → Add
  Document Mode" → "Boxes → View → Scan Mode: ON"; Scenario 5: Scan Center quick-create →
  plain "Add Location").

**Not done this pass — explicit user decision, documented tradeoff:** Scan Center's
general-purpose "scan any barcode type → jump to that record" lookup (Product/Location/Box/
Document File) has no replacement. Anything needing that now goes through each resource's
own list/search page instead.

**Regression tests:** new `tests/Feature/BarcodePrintActionsTest.php` (6 tests) — the exact
"change the live label-size select inside the modal" interaction that crashed this app's
BarcodeRegistryResource before (§14) is re-exercised for Boxes, Document Files, Locations
(single + bulk), and BarcodeRegistryResource's own preview/batchPrint, confirming the
`array $data`-typed `modalContent()` closures (not `Get $get`) don't regress. One test file
edited in place (`CustomerProfileTest.php`'s generic Customer 360 tamper-resistance loop
dropped its now-mismatched `Locations` case — `createChain` already has its own dedicated
tamper-resistance test). 298/298 passing (was 292 immediately after Scan Center removal,
299 before it — net change: -1 for the file explicitly removed, ScanCenterTest's 4 tests
and DemoReadinessFixesTest/DemoCorrectionPassTest's redundant BarcodeScanner-based tests
removed without porting once confirmed redundant, +6 for the new print-action regression
coverage). Pint clean; Larastan (level 5) clean; `npm run build` clean (theme.css shrank
slightly — fewer Blade files for Tailwind's JIT scanner to scan, consistent with the
removed view).

**⚠️ Not independently verified this pass:** browser/Playwright verification (unavailable
this session, as in §21) — the print-only-the-label CSS, the Alpine refocus behavior, and
the actual visual layout of the grouped Actions dropdowns are verified at the code/
automated-test level only, not by an interactive browser session.

## 24. Barcode Print Buttons Still Silently Broken After §23 — Root-Caused via Live Browser — 11 September 2026

§23's browser verification gap turned out to be load-bearing: despite the iframe redesign
and passing automated tests, "Print Barcode" did nothing in a real browser on every surface
(Locations, Boxes, Document Files, Barcode Registries; single and bulk) — the automated
tests exercise the Livewire component's server-side action, not whether the client-side
`window.print()` call actually fires, so they couldn't catch this. Found and fixed via
Claude in Chrome browser automation (`http://dmims.test/admin`, Super Admin session).

**✅ Fixed (11 September 2026), two independent stacked bugs:**
- **`dmimsPrintLabel()` never executed.** It was defined by a `<script>` tag inside
  `barcode-label.blade.php`/`batch-barcode-labels.blade.php`, both rendered as Filament
  action-modal content. Modal content is injected into the DOM after the page has already
  loaded (opened on demand), and separately, Filament navigates between pages via
  Livewire's `wire:navigate` (an SPA-style body swap over AJAX) — neither path executes a
  `<script>` tag that arrives that way. Console showed
  `ReferenceError: dmimsPrintLabel is not defined` on every click, on both a fresh hard
  page load's modal and a `wire:navigate`'d page. Fixed by binding one delegated `click`
  listener on `document` (which `wire:navigate` never replaces, only the body's content)
  from a new `FilamentPanelProvider::renderHook(PanelsRenderHook::BODY_END, ...)`, guarded
  by `window.__dmimsPrintLabelBound` against double-binding; buttons now carry a
  `data-dmims-print` attribute instead of an inline `onclick`.
- **The fix above then collided with `App\Http\Middleware\InjectPwaScript`.** That
  middleware regex-matches the literal substrings `</head>` and `</body>` anywhere in the
  full HTML response body (not just real tags) to splice in PWA link/meta/script tags. The
  print handler's own JS string — which builds an iframe document via
  `iframe.contentDocument.write(...)` — contained those exact substrings as plain text
  (both in the string literal and, on a second miss, in an explanatory code comment),
  so the middleware spliced unrelated HTML (manifest link, `sw-register.js`, Livewire's own
  injected styles/scripts) into the middle of the script, corrupting it into invalid
  JavaScript (`SyntaxError: Invalid or unexpected token`). Fixed by building those closing
  tags via string concatenation (`'<' + '/head>'`, etc.) so the literal substring never
  appears in the response for the middleware to match — and rewording the comment to
  describe the tags without quoting them whole, for the same reason.

**Verified live, in the actual browser, on every named surface:** single-record print
(Locations, Boxes, Document Files, Barcode Registries) and bulk print (Locations, Boxes)
each opened the modal, rendered the correct label(s), and — on clicking Print — genuinely
invoked `window.print()` (observed as the OS print dialog opening, which never happened
before this fix), with zero `dmimsPrintLabel`/`SyntaxError` console errors afterward,
including through a true `wire:navigate` transition (not just a hard page load).

**Files:** `app/Providers/FilamentPanelProvider.php` (new render hook),
`resources/views/filament/barcode-label.blade.php`,
`resources/views/filament/batch-barcode-labels.blade.php` (dead per-modal `<script>` tags
removed, buttons switched to `data-dmims-print`). No test changes — this bug was invisible
to the existing automated coverage by nature (client-side script execution timing and a
cross-cutting middleware interaction), which is exactly why §23 flagged the missing browser
verification as a real gap rather than a formality.

**Regression tests:** unchanged — 300/300 passing; Pint clean; Larastan (level 5) clean;
`npm run build` clean.

**⚠️ Known pre-existing, unrelated bug noticed during this verification, not fixed:** every
table page's "select all" header checkbox throws
`ReferenceError: areRecordsPartiallySelected is not defined` in the console (Filament's own
Alpine-generated indeterminate-state expression) on Boxes, Document Files, Barcode
Registries, and Locations. Individual row checkboxes and bulk actions (including bulk
Print Barcode, verified above) still work correctly — this only affects the header
checkbox's visual indeterminate/checked state computation, not selection functionality
itself. Out of scope for this barcode-print fix; flagged here for a future pass.

## 25. Barcode Print: Page-Break-Safe Labels, Box Removed From Print Output — 11 September 2026

Follow-up user feedback on the §24 fix, once printing itself worked: a multi-label batch
print could split a single barcode's graphic across a page break, and the bordered box
Filament rendered around each label in the batch print grid was unwanted in the printed
output.

**✅ Fixed (11 September 2026), applied system-wide (Locations, Boxes, Document Files,
Barcode Registries; single and bulk):**
- `app/Providers/FilamentPanelProvider.php`'s print handler now injects
  `<style>.dmims-barcode-item{break-inside:avoid;page-break-inside:avoid}</style>` into the
  print iframe's `<head>`, so a label always prints whole and wraps to the next page instead
  of splitting mid-barcode.
- `resources/views/filament/batch-barcode-labels.blade.php`'s
  `rounded border border-gray-200 dark:border-gray-700` wrapper div around each label
  removed entirely, replaced with a plain `dmims-barcode-item` class (the CSS hook for the
  rule above); `barcode-label.blade.php`'s own `data-print-target` div also carries the
  class for the single-record print path.

Verified live in the browser: bulk Print Barcode modal on Boxes shows labels with no
bordered box; Print still invokes the OS print dialog with zero console errors.

**Regression tests:** unchanged — 300/300 passing (`BarcodePrintActionsTest` unaffected,
no CSS/DOM-structure assertions); Pint clean; Larastan (level 5) clean; `npm run build`

## 26. Global Barcode Scan Reinstated (Scoped Beyond §23's Removal) + Optional Create Fields + Structured External Dispatch — 11 September 2026

User request, informed by a reference legacy system (DMOIS) screenshot: scanning an
unregistered barcode from any page should offer quick-create shortcuts (New Box/New
Document/New Rack), scanning a registered one should jump straight to its record, the
resulting Create forms should treat every field (including the barcode) as optional,
and Box "Move Out"'s external-dispatch details should be individually queryable, not
buried in one `remarks` blob — mirroring a feature already in DMOIS.

Investigation found DMIMS had exactly this scan/toast/redirect behaviour before — a
dedicated "Scan Center" page — **deliberately removed** in §23 (19a6e74) on the
reasoning that Box's own Scan Mode covered scanning files into boxes. That reasoning
doesn't cover the "any page, any barcode type" case the user is asking for, so this
reinstates the behaviour globally instead of as a page, on top of the barcode
infrastructure §23/§25 already left in place (`ScannerService`, `BarcodeRegistry`,
`BarcodeScanLog`).

**✅ Implemented (11 September 2026):**
- `App\Livewire\BarcodeScannerListener` + `resources/views/livewire/barcode-scanner-listener.blade.php`,
  mounted globally via a new `FilamentPanelProvider` `PanelsRenderHook::BODY_END` hook
  (same slot as the existing print-label script, so it survives `wire:navigate`).
  Alpine buffers scanner-gun keystrokes only while no form field has focus (so it never
  competes with typing anywhere, including Box's own "Add Document Mode" scan input).
- `ScannerService::recordUrl()` (deleted in §23) restored — maps a resolved barcode to
  its resource's View URL (Product falls back to Edit — it has no View page).
- `file_barcode`/`title`/`current_status` (Document File), `box_barcode`/`box_number`/
  `current_location_id`/`status` (Box), `location_code`/`location_name` (Location) all
  lost their `->required()`. New migration
  `2026_09_11_000000_make_identity_fields_optional.php` drops the matching `NOT NULL`
  DB constraints (per-customer `unique()` rules unaffected — multiple `NULL`s are
  permitted in a unique index). `current_location_id`/`current_box_id` were already
  nullable from an earlier migration.
- Document File's `received_date` auto-fills to today when arriving via the
  scan-to-create redirect (`?file_barcode=` present).
- `BoxResource::moveOutBoxAction()`'s recipient/address/tracking-ref/expected-return
  fields now write to a new `document_movement_logs.metadata` JSON column (migration
  `2026_09_11_000001_add_metadata_to_document_movement_logs_table.php`) instead of a
  concatenated `remarks` string; a new "External Dispatch Details" infolist section on
  Box View reads them back individually. `remarks` still holds free-text notes only.

**Bugs caught by the qa-tester/security-reviewer subagent pass and fixed before
shipping:**
- `CreateBox::afterCreate()` unconditionally called
  `DocumentMovementService::receiveInBox($record, $record->current_location_id, ...)`,
  whose second parameter is non-nullable — since `current_location_id` is no longer
  required, an empty-shell Box create threw an uncaught `TypeError` instead of the
  `InvalidArgumentException` the surrounding `try/catch` expected. Fixed with the same
  early-return guard `CreateDocumentFile::afterCreate()` already had for its equivalent
  optional `current_box_id` case.
- `BarcodeScannerListener` is mounted on every panel page, including the guest-facing
  login/password-reset layout — a scan there would have hit `ScannerService::scan()`'s
  non-nullable `User $user` parameter. Added an `auth()->check()` guard, plus a 150-char
  clamp on the scanned string (matching `ViewBox`'s own scan input limit) and `e()`
  escaping on the two notification bodies that interpolate the raw barcode (Filament
  sanitizes notification HTML already, so this wasn't exploitable as XSS, but a
  hand-typed barcode containing markup rendered as a live, unescaped element).
- **Noted, not fixed (pre-existing, broader than this change):** the security review
  found the panel's `business-access` middleware group (subscription/license/user-active
  gates, `FilamentPanelProvider.php`) isn't registered as `isPersistent: true`, so it
  doesn't re-run on Livewire's own update route — a session whose access should have
  just been revoked can keep calling any Livewire action, this new scanner included,
  until its next full page load. This predates this change and affects every Filament
  action in the app, not just barcode scanning; flagged for a separate fix rather than
  folded into this diff.

**Regression tests:** 308/308 passing, plus 10 new (`BarcodeScannerListenerTest` —
found/unknown/blank-scan/logged-out-scan/optional-create-save for both Document File
and Box; `BarcodeScannerTest::test_record_url_maps_a_found_registry_to_its_view_route`;
`BoxViewInfolistTest::test_box_view_shows_structured_external_dispatch_details_after_move_out`).
Pint clean; PHPStan (project baseline level) clean on all changed files.

**Deliberately not ported from DMOIS:** its unfinished "barcode pool pre-generation"
page (labelled "soon" in its own nav) — DMIMS's lazier `BarcodeService::registerFor()`
register-on-first-print already covers the need without pre-generating unused stock.

**Bug caught by live browser verification, not by the test suite — fixed same day:**
manually logging in and running the actual scan → create → scan-again flow (Turnstile
required a human to solve the login challenge; the app itself was then driven end to
end) surfaced a real gap the test suite's mocked/direct-call assertions had missed:
a record created via the scan-to-create flow was **not** immediately scannable again.
Root cause — `claim()` only activates a *pre-reserved* barcode; a barcode typed in
fresh via the scan flow (never reserved) is, by the existing "register-on-first-print"
design, left with no `BarcodeRegistry` row until its label is printed, so the very next
scan of that same barcode came back `unknown` and re-showed the quick-create toast
instead of opening the record.
- Added `BarcodeService::registerExisting(Model $record): ?BarcodeRegistry` —
  registers a record's own already-set barcode value as-is (unlike `registerFor()`,
  which generates and overwrites with a brand-new one).
- `CreateDocumentFile`/`CreateBox`/`CreateLocation` now call it in `afterCreate()`
  when `claim()` was a no-op — but **only** when the record arrived via the scan flow.
  That flag (`$fromBarcodeScan`) has to be captured in `mount()`, the one point in the
  request lifecycle where `request()->query()` still reflects the page's own URL —
  `afterCreate()` runs during a later, separate Livewire AJAX request with no query
  string of its own, so re-reading `request()->filled('file_barcode')` there (the
  first attempt) silently never registered anything. Ordinary manual barcode entry
  (not via scan) deliberately still skips registration, unchanged.
- Verified live in the browser end-to-end: scan unknown barcode → toast → New Document
  → barcode/received-date prefilled, saved with every other field blank → scanned the
  same barcode again → redirected straight to the new record's View page.
- Regression tests added: `test_scan_to_create_document_file_is_scannable_again_immediately`,
  `test_manually_typed_barcode_outside_the_scan_flow_is_not_registered`. 310/310 total
  passing.

## 27. Six Issues From External Review of the Barcode-Scan Feature — 11 September 2026

A second reviewer went through §26's work against `docs/DMIMS_ISSUES.md` and the
broader feature discussion, and found six real gaps (five in code the barcode-scan
work itself touched, one pre-existing) not caught by the original test suite or
live-browser pass. All six fixed same day.

**1. Reserved Product labels fell through to "inactive"** — `BarcodeScannerListener`'s
`unused` branch matched `document_file`/`box`/`location` but had no `product` case,
so scanning a pre-reserved Product barcode landed on a dead-end "Barcode is inactive"
notification instead of Product's Create form. Fixed by adding the case (matching
`ProductResource::getUrl('create', ['barcode' => $barcode])`); PHPStan then correctly
flagged the explicit `'product' =>` arm as redundant once all three sibling types are
excluded (the DB enum only has 4 values), so it collapsed into the `default` arm
instead — same pattern the old, deleted `BarcodeScanner::scan()` used.

**2. `registerExisting()` had no type check** — could silently attach an existing
*unassigned* (`reference_id === null`) registry row to a new record without checking
that its `barcode_type` matched what was being created, e.g. a Product label's code
typed into a Document File's barcode field would get "claimed" as if it were a
Document File barcode. Fixed: `registerExisting()` now refuses (returns `null`, no
DB write) whenever an existing row for that barcode string has a *different* type —
the record keeps its typed-in barcode but stays unregistered rather than corrupting
the other type's entry.

**3. Box Transfer/Return's location picker didn't search by barcode** — it used a
static `Location::ancestryPathMap()` options array with `->searchable()`, which only
matches the *displayed* ancestry-path text, not the location's own `barcode` column.
A handheld scanner's input (the shelf's barcode) never matched anything. Added
`Location::searchByNameOrBarcode()` (same shape as the existing
`Box::searchByNumberOrBarcode()`) and switched both Selects to
`getSearchResultsUsing()`/`getOptionLabelUsing()`. `BoxResource::locationOptions()`
removed as dead code once nothing called it anymore.

**4. Capacity off-by-one on create** — creating a Document File/Box with its Box
Assignment/Current Location field preselected persisted `current_box_id`/
`current_location_id` on the row's very first INSERT, because Filament's own
`saveRelationships()` (which the Create page runs right after the insert, independent
of `mutateFormDataBeforeCreate()`) re-applies a `->relationship()` Select's value from
the live form state — before `afterCreate()`'s `receiveInFile()`/`receiveInBox()` call
ever ran its capacity check. With the field already saved, `$box->files()->count()`/
`$location->boxes()->count()` counted the record against itself: a box/location with
exactly one slot left was always wrongly rejected as "at capacity" for what should
have been its own first, legitimate occupant. Root-caused via a reproduction test
(`test_creating_a_document_file_into_a_box_with_exactly_one_slot_left_succeeds`) that
failed before the fix and passes after. Fix: `afterCreate()` now detaches the
just-auto-set `current_box_id`/`current_location_id` (plain `->update()`, not through
the relationship) immediately before calling `receiveInFile()`/`receiveInBox()`, so
the capacity check sees the container's *true* existing count, then lets that same
call reapply the assignment (with its own log entry) if there's room — the same shape
an ordinary Transfer already used correctly, since a transferred file/box was never
pre-attached before its own check ran.

**5. Capacity checks ran outside the transaction** — `assertBoxHasCapacity()`/
`assertLocationHasCapacity()` were called on a plain `findOrFail()`'d instance
*before* `DB::transaction()` opened, so two concurrent requests targeting the same
nearly-full box/location could both pass the check before either committed (TOCTOU).
Fixed: all six capacity-gated methods (`receiveInFile`, `transferFile`, `returnFile`,
`receiveInBox`, `transferBox`, `returnBox`) now fetch the target with
`lockForUpdate()` *inside* the transaction, serializing concurrent writers on that
row.

**6. Destination suitability wasn't enforced** — nothing stopped a box from being
received/transferred/returned into a `Location` marked `status = 'inactive'` or with
`can_store_boxes = false` (e.g. a stock-only shelf). `assertLocationHasCapacity()`
now checks both before the capacity count.

**Pre-existing, unrelated to the barcode-scan work itself, also closed while here:**
`business-access` middleware (user/company active, subscription, license gates) was
registered only as regular panel middleware, not `->persistentMiddleware()` — so it
never re-ran on Livewire's own `/livewire/update` route, only full page loads. A
session whose access was revoked mid-session kept working for every Livewire action
(including the barcode scanner) until its next navigation. Fixed with one additional
`->persistentMiddleware(['business-access'])` call in `FilamentPanelProvider`; all six
of the group's checks are idempotent reads/aborts, safe to re-run per-request.

**Also, while auditing the same print actions for #1-2:** `printed_count` was
incrementing inside `->modalContent()`, which Filament re-evaluates on every Livewire
render — including the label-size Select's own `->live()` updates — so changing the
size twice while a preview stayed open counted as three prints, not one. Moved to
`->mountUsing()` (fires exactly once, on modal open) across all four print actions
(`HasBarcodeAction::barcodeAction()`/`bulkBarcodeAction()`, `BarcodeRegistryResource`'s
`preview`/`batchPrint`). Added an optional "Copies" field alongside (feeds
`printed_count` and repeats the label N times in the actual printed output — printing
itself is fully client-side `window.print()`, so `printed_count` remains "modal
opens," an honest lower bound, not an exact physical-copy count) and a required
"Reason" field on "Lost/Damaged", logged to `audit_logs`.

**Regression tests:** 11 new (`test_scanning_a_reserved_product_barcode_redirects_to_product_create`,
`test_register_existing_does_not_attach_a_reservation_of_a_different_type`,
`test_search_by_name_or_barcode_matches_a_shelf_barcode`,
`test_creating_a_document_file_into_a_box_with_exactly_one_slot_left_succeeds`,
`test_creating_a_box_into_a_location_with_exactly_one_slot_left_succeeds`,
`test_receive_in_box_is_rejected_when_location_is_inactive`,
`test_receive_in_box_is_rejected_when_location_cannot_store_boxes`,
`test_business_access_is_registered_as_persistent_livewire_middleware`,
`test_replace_action_requires_a_reason`,
`test_print_barcode_action_only_increments_printed_count_once_despite_live_size_changes`,
`test_increment_printed_accepts_a_copies_count`). 321/321 total passing, Pint and
PHPStan clean across the whole codebase (not just changed files).

## 28. Critical — `orWhere()` Broke Out of Tenant Scoping in Two Barcode Search Helpers — 11 September 2026

**Critical, fixed same day.** A background security review of §27's commit caught a
cross-tenant information disclosure in the new `Location::searchByNameOrBarcode()`
(§27 item 3): a bare `->where('location_name', ...)->orWhere('barcode', ...)`
chained directly onto `static::query()` breaks OUT of `BelongsToCustomer`'s global
scope, which adds its own plain top-level `$builder->where('customer_id', $id)`.
SQL operator precedence turns the intended `customer_id = X AND (name LIKE ? OR
barcode LIKE ?)` into `customer_id = X AND name LIKE ? OR barcode LIKE ?` — the
`barcode` half is no longer inside the customer_id condition at all, so any tenant
user searching Box Transfer/Return's location picker by barcode could see (and
select as a transfer destination) another tenant's location.

Checking the pattern this was copied from — `Box::searchByNumberOrBarcode()`
(pre-existing, used by `DocumentFileResource`'s Box Assignment search) — found the
identical bug already live in production code, unrelated to today's work. Both
fixed by grouping the two conditions inside a nested `->where(function ($query) {
...})`, so the OR stays confined within one group that the global scope's AND wraps
correctly.

**Regression tests** added to `DocumentTenantIsolationTest.php` (the file that
already exists specifically to guard this class of bug, previously only exercising
the plain `Box::query()`/`Location::query()` path, not these two search helpers):
`test_search_by_number_or_barcode_does_not_leak_another_companys_box`,
`test_search_by_name_or_barcode_does_not_leak_another_companys_location`.

## 29. Added — Live camera barcode scanning — 12 September 2026

Every DMIMS scan input (`BarcodeScannerListener`, Box View's "Scan Mode") was
keyboard-wedge only — no way to scan with a phone's own camera. Added a
feature-detected "Scan with Camera" button (`html5-qrcode`, this project's
first production JS dependency) at both existing scan entry points — later
renamed "Scan Barcode" and fixed to not need a page refresh between scans;
see §30. **Zero backend changes**: `ScannerService::scan()`, `ViewBox::scanDocument()`, and
`BarcodeController` are all untouched — the camera decodes a barcode and
hands the plain string to the exact same `$wire.scan()` /
`scannedFileBarcode` + `scanDocument()` paths a physical scanner already
drives. Regression-verified live (not just by unit test) that the existing
keyboard-wedge path is unaffected: scanning `DOC-MA-000011` by typing it
into Box View's Scan Mode field + Enter still correctly added it to the box
(`Total Files` 8 → 9, "Added to box..." notification) after the camera
feature was wired in alongside it.

`Document File` View has no scan-mode equivalent today (keyboard-wedge
included) — pre-existing, unrelated to this change, intentionally left out
of scope (this feature extends *how* scanning happens, not *where* it's
available).

Requires a secure context — `navigator.mediaDevices.getUserMedia` does not
exist at all on a plain-HTTP origin (confirmed empirically: `dmims.test`
over HTTP showed `supported: false` and the button correctly stayed
hidden; enabling Herd's local TLS via `herd secure dmims` made it
`supported: true` and the button appeared). This is correct browser
behaviour, not a bug — production is HTTPS-only already (the PWA service
worker registration itself requires a secure context, per `docs/PWA.md`).

Also fixed along the way: `resources/css/filament/admin/theme.css`'s
Tailwind `@source` list only covered `app/Filament/**/*` and
`resources/views/filament/**/*` — the new views live in
`resources/views/livewire/` and `resources/views/components/`, neither
previously scanned, so their utility classes (`fixed`, `bottom-4`, etc.)
were silently compiled out. Both paths added. Caught by loading the actual
page rather than trusting `npm run build` succeeding — a clean build says
nothing about whether Tailwind found the classes a new file uses.

**Manual on-device QA still required** (cannot be automated — Playwright
cannot grant real camera permissions or simulate a live decode): actual
scan-to-decode speed/accuracy on a real iOS Safari and Android Chrome
device, and confirming a decoded barcode correctly triggers the existing
`found`/`unused`/`unknown`/`inactive` outcomes end-to-end in a live mobile
browser.

## 30. Login Rejection Reasons, Camera Scan Refresh Bug, Turnstile Explicit Render — 13 September 2026

Three fixes; full detail in `CHANGELOG.md` (Unreleased) and
`DEPLOYMENT_GUIDE.md`'s Deployment Lessons Learned items 9–10 — summarised
here for the audit trail:

- **Login rejection messages.** Every login rejection after a correct
  password (suspended/inactive/locked/pending/password-expired/archived
  account; cancelled/archived company; cancelled/revoked/blocked
  license) previously showed the same generic "These credentials do not
  match our records." as a wrong password. `AccessControlService::
  loginDenialReason(User $user): ?string` now returns the specific reason;
  `canLogin()` is unchanged in behaviour. `App\Filament\Auth\Login`
  surfaces it via `isUserAllowedToAccessPanel()`/
  `throwFailureValidationException()`, only reachable after the password
  has already been verified correct — not an account-enumeration path.
  Applies identically to platform (Super Admin) and customer users. New
  tests: `tests/Feature/LoginErrorMessagesTest.php`.
- **§29's "Scan with Camera" button renamed "Scan Barcode" and fixed.** It
  needed a full page refresh to relaunch after a scan and re-prompted for
  camera permission on every scan in the same session. Root cause:
  `resources/views/components/barcode-camera-button.blade.php` was missing
  `wire:ignore`, so every `$wire.scan()` call morphed the button's DOM out
  from under the running `Html5Qrcode` instance. Fixed with `wire:ignore` +
  instance reuse in `resources/js/barcode-camera.js`. Also gated behind
  `@auth` in `barcode-scanner-listener.blade.php` — it was previously
  rendering, unused, on the guest-facing login page.
- **Turnstile switched from implicit to explicit render.** Implicit
  auto-render raced with page parsing and could leave the widget
  permanently empty (no iframe, no token), causing intermittent silent
  login failures. `resources/views/filament/turnstile-widget.blade.php`
  now uses Cloudflare's documented explicit pattern
  (`?render=explicit` + `turnstile.render()` from Alpine `x-init`).

## 31. zxing-wasm Scanner Swap, Explicit Customer 360 Create/Add Gate, Dual-Hostname Access Restored — 16 September 2026

Full detail in `CHANGELOG.md` (Unreleased) and `DEPLOYMENT_GUIDE.md`'s
Deployment Lessons Learned #11–13; summarised here for the audit trail,
plus every item still pending manual/on-device verification.

- **Camera scanning moved off `html5-qrcode` onto self-hosted
  `zxing-wasm`.** `resources/js/barcode-camera.js` now drives raw
  `getUserMedia()` frames through `zxing-wasm/reader`'s `readBarcodes()`
  in a `requestAnimationFrame` loop instead of wrapping an `Html5Qrcode`
  instance. Both existing entry points (Box Scan Mode, the global
  scanner) are untouched — the component's public contract
  (`barcode-camera-decoded` CustomEvent) didn't change. `html5-qrcode`
  is fully removed from `package.json` (confirmed zero remaining
  consumers first). Rear-camera preference, duplicate-scan prevention,
  and the `wire:ignore` fix from §29/§30 all carry over. Camera cleanup
  is simpler than before: a fresh `getUserMedia()` call per open doesn't
  re-prompt once permission is granted for the page, so `closeScanner()`
  can always fully stop every track — no more "keep the instance alive
  so the next open reuses the same grant" workaround. The `.wasm` binary
  is self-hosted via Vite's `?url` import
  (`zxing-wasm/reader/zxing_reader.wasm?url`), emitted into
  `public/build/assets/` with the same content-hash versioning as every
  other build asset. `public/service-worker.js` needed no change — its
  existing `/build/` path-prefix rule already covers the `.wasm` file
  regardless of extension, and its `/admin` exclusion already keeps all
  customer-data pages out of the cache. New regression coverage:
  `tests/playwright/barcode-camera.spec.js` (open/error/close/reopen
  with a mocked `getUserMedia`, asserting the camera track is actually
  stopped on close — decode accuracy itself still needs a real device,
  same limitation §29 already documented).
- **"My Company > Users" had no Add User button at all** (found while
  verifying Company Admin's own create permissions weren't collaterally
  broken by the Customer 360 change above — they weren't, but the button
  to use that permission never existed). Fixed by adding a page-level
  header action (`CompanyUsers::getHeaderActions()`) linking to
  `UserResource`'s own hardened create page — not a bare `CreateAction`,
  which would have skipped that page's tenant-hop guard and
  platform-role-stripping (`CreateUser::mutateFormDataBeforeCreate()`/
  `afterCreate()`). **Deployed to the Herd-served copy and confirmed
  working live** on `dmims.datamationgroup.com` (2026-09-16). New tests:
  `MyCompanyClusterTest::test_users_tab_shows_add_user_action_for_company_admin`
  and `..._hides_..._for_company_supervisor`.
- **Customer 360 Create/Add gate made explicit, not incidental.**
  `HasCustomerScopedEmbeddedTable::customerScopedCreateAction()` (backs
  the Users/License/Modules/Subscription/Billing "Add" actions) and
  `CustomerResource::can()`'s own create path both now require
  `hasRole('Datamation Super Admin')` explicitly. Before this, "only
  Super Admin can create here" held only because Super Admin is
  currently the only platform role granted any `manage *` permission —
  a permission-assignment side effect, not a rule, so a future role
  grant could have silently reopened it. Company Admin's own
  same-company user management (outside Customer 360) was already
  correctly gated by `UserResource` and is untouched — this change did
  not touch, and was not meant to touch, any other module's Create/Add
  behaviour. New test: `tests/Feature/Customer360CreatePermissionTest.php`
  (Super Admin allowed; a view-only platform role denied; Company Admin
  denied even calling the action's `authorize()` closure directly,
  bypassing the UI).
- **`dmims.datamationgroup.com` (Cloudflare Tunnel) restored.** Working
  as of 2026-09-13 per nginx's own access logs; by 2026-09-16 an
  unrelated process (`AgentService`) had taken over port 8080, the
  tunnel's configured origin, silently breaking it. Moved the origin to
  port 80 (free at the `0.0.0.0` scope — Herd's own `dmims.test`/default
  vhosts only bind `127.0.0.1:80`) in both
  `~/.config/herd/config/valet/Nginx/dmims.datamationgroup.com.conf` and
  `~/.cloudflared/config.yml`. Narrowed `TRUSTED_PROXIES` from `*` to
  `127.0.0.1,192.168.6.113` (PHP-FPM is only ever reached over loopback
  per nginx's own fastcgi logs). No firewall rule existed for 8080
  before this and none was added for 80 — `cloudflared`'s hop to the
  origin is a same-machine local connection and the tunnel itself is
  outbound-only, so no inbound allow rule or router port-forwarding was
  ever required.
- **Turnstile site key rotated to the value specified for this work**
  (`0x4AAAAAAAKvq5_hJjbIITb0`) in both this repo's local `.env` and the
  Herd-served copy's `.env`; the Herd copy's secret already appears to
  be the newly-rotated one (a different, correctly-shaped value from
  the one previously in this repo's own `.env`, which has been cleared
  rather than left in place — see below).

**Security note, disclosed rather than buried:** during this work, an
existing (old, since-replaced) Turnstile secret value was inadvertently
echoed into a terminal/session transcript twice while inspecting `.env`
files. It has been cleared from this repo's `.env` rather than left in
place. Recommend rotating the Herd-copy secret once more as a
precaution, since it appeared in the same transcript.

**Follow-up (2026-09-16):** the Cloudflared Windows service runs in
Cloudflare's dashboard-managed tunnel mode, not config-file mode — so
the public-hostname-to-origin mapping lives in the Zero Trust dashboard,
and the local `~/.cloudflared/config.yml` edit above may not have been
the operative fix. Confirm the dashboard's Public Hostname setting for
`dmims.datamationgroup.com` points at port 80.

**Resolved since first written (2026-09-16):**
- Cloudflared Windows service restarted (with elevated access from the
  user) — confirmed clean restart via Windows Event Log.
- Deployed to the Herd-served copy (`D:\Users\Madhan Rao\Herd\dmims`)
  and to `main` via PR #68 — both now serving this work.
- Company Admin's "Add User" action confirmed working live on
  `dmims.datamationgroup.com`.
- Live camera barcode scanning confirmed working on iPhone Safari
  against `dmims.datamationgroup.com`.
- Confirmed in the Cloudflare Zero Trust dashboard (Tunnels > Routes):
  the `dmims.datamationgroup.com` route's service is
  `http://192.168.6.113` with no port specified, i.e. port 80 by
  default — matches this session's nginx/tunnel port change.
- Confirmed in the Cloudflare dashboard (Application Security >
  Turnstile > widget matching site key `0x4AAAAAAAKvq5_hJjbIITb0`):
  the hostname allowlist already includes both `dmims.test` and
  `dmims.datamationgroup.com` (alongside `localhost` and unrelated
  hostnames for other apps sharing the same widget). No change needed.
- Live camera barcode scanning confirmed working on Android Chrome
  against `dmims.datamationgroup.com`.
- **Root cause found and fixed for "a tenant account works on one
  hostname but not the other":** Herd's `dmims` site link pointed at a
  different codebase/database than `dmims.datamationgroup.com` — see
  `DEPLOYMENT_GUIDE.md` Deployment Lessons Learned #15. Re-linked to
  the correct installation; Madhan Inc.'s Company Admin now signs in
  successfully on `dmims.test`. Also cleaned up three throwaway test
  customers this session's own diagnostics had created in the
  now-orphaned working-repo database.
- **Resolved: `AgentService` / port 8080 conflict.** Identified the PID
  holding port 8080 as Windows service `MTAgentService`, binary
  `C:\Program Files\MiniTool ShadowMaker\AgentService.exe` — MiniTool
  ShadowMaker's backup agent, `StartMode: Auto`. Legitimate backup
  software, not malware; its local management web UI defaults to port
  8080, which is why it will always reclaim that port on every boot.
  No changes were made to the backup software itself — the conflict is
  resolved by the tunnel/nginx move to port 80 (§31), which needs
  nothing from port 8080 at all. No further action required.

**Resolved:**
- Live end-to-end browser verification (login, Turnstile, dashboard,
  Livewire, Customer 360 Add-User visibility) confirmed on
  `dmims.datamationgroup.com`; `APP_DEBUG` exposure found and fixed —
  see §33.
- Installed-PWA behaviour ("Add to Home Screen" / "Install app")
  confirmed working on both **iOS Safari and Android Chrome** by the
  user on real devices, against `dmims.datamationgroup.com`.

**Still pending:**
1. New Playwright coverage (`tests/playwright/barcode-camera.spec.js`)
   could not be run to green against a local `php artisan serve`
   instance in this session — every request past login 403'd
   ("Access Denied"). Confirmed **pre-existing and unrelated to this
   work**: the repo's own untouched `row-actions.spec.js` hits the
   identical 403 on the identical server. Root cause not found (all six
   `business-access` middleware checks pass individually via `tinker`
   for the QA seed user; a `subscription_active:*` cache-poisoning
   theory from `RefreshDatabase` feature tests sharing the file cache
   with the dev server was ruled out — `cache:clear` didn't fix it
   either). Needs investigation independent of this work; the new spec
   itself is believed correct pending that.

## 32. UserResource Email Field Missing Unique Validation — 16 September 2026

Found while investigating an unrelated live error-log entry (a failed
edit attempt against another user's record, from 2026-09-14, predating
this work — no data was corrupted, the DB's unique constraint correctly
rejected it). `UserResource::form()`'s `email` field had no `->unique()`
rule even though `users.email` is a globally-unique DB column — a
duplicate submission bypassed Filament's inline validation entirely and
hit the raw `QueryException`, which would render as an uncaught error
rather than a normal "already taken" message. Fixed with
`->unique(ignoreRecord: true)`, the same pattern already used by
`CustomerResource::form()`'s `company_code` field and the composite
unique fields covered by `DuplicateUniqueConstraintValidationTest.php`
(this change adds a case for the email field to that same suite). Not
part of the original 5-item request — investigated and fixed at the
user's explicit request after the pre-existing log entry was flagged.

## 33. Live End-to-End Browser Verification + APP_DEBUG Exposure Found and Fixed — 16 September 2026

Real browser verification (not just curl/log inspection) against both
`dmims.test` and `dmims.datamationgroup.com`, using a QA platform-admin
account already present in the live database:

- Login page, Turnstile widget, and PWA service worker registration all
  confirmed working on both hostnames (Turnstile auto-passed on the
  real-TLS external hostname; `dmims.test`'s self-signed cert triggered
  only a browser-profile trust warning, not an app issue — real device
  testing earlier in this session already confirmed it working
  normally).
- Real sign-in succeeded on `dmims.datamationgroup.com`; dashboard
  rendered with live data (3 customers, 3 subscriptions, 12 documents,
  5 boxes); Scan Barcode button present and its modal opened/closed
  cleanly (camera itself can't be exercised from this automated browser
  context — no device, no permission-prompt handling — already covered
  by real iPhone/Android testing earlier).
- Customer 360 → Madhan Inc → Users tab: confirmed "Add User" visible
  for the Super Admin account, live-testing §31/§32's Customer 360
  permission gate.
- One transient recurrence of the known view-compile race (§ "Windows
  view-compile race", `DEPLOYMENT_GUIDE.md` #14) during rapid automated
  navigation — self-healed once the compiled file existed; not expected
  under normal human browsing pace.
- **Found: `APP_DEBUG=true` on the installation both hostnames share**
  — any error on the publicly-reachable `dmims.datamationgroup.com`
  (e.g. a plain method-not-allowed) rendered Laravel's full debug page:
  stack trace, absolute file paths, Laravel/PHP version numbers,
  visible to any external visitor. Pre-existing, unrelated to this
  session's other changes, found by deliberately triggering an error
  during this verification pass. **Fixed**: set `APP_DEBUG=false`;
  confirmed the same trigger now shows Laravel's generic error page
  with no sensitive detail. This is an env-only change (`.env` is
  never committed) — applied directly on the live installation, with
  the user's explicit go-ahead given the trade-off (detailed on-screen
  errors are no longer available for local `dmims.test` debugging
  either, since both hostnames now share one app instance; use
  `storage/logs/laravel.log` instead).
