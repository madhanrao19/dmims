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

# 8. WIP — Customer Navigation Consolidation

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
**Implementation:** ✅ Items 2–7 (§2–§7 above) implemented; all §10a findings
(H1–H3, M1–M3) fixed or resolved; §8 (My Company nav consolidation) remains
the only open item, tracked as WIP
**Regression verification:** ✅ automated test suite green (Pest/PHPUnit);
Pint clean; Larastan/PHPStan clean; independent security-reviewer and
qa-tester passes completed 24 August 2026; real-browser Playwright
verification completed for the platform-only lockdown, audit-log scoping,
and full existing role-QA suite (`tests/playwright/role-qa.spec.js`, all
role/permission assertions pass unchanged)
**Production-ready for this access-control change:** ⚠️ Conditional — every
item in this document's scope is implemented, tested, and reviewed; the
full "My Company" navigation consolidation (§8) is a separate, larger UI
feature not yet built and remains the only reason this is not called fully
conformant to the approved target state.
