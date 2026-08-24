# DMIMS Security & Access Control Matrix

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 24 August 2026 (§3 clarified same day — see Document History)

---

# Document Purpose

This document defines the complete authorization model for DMIMS.

It is the authoritative reference for:

- Filament Resource authorization
- Spatie permissions
- Filament navigation
- Middleware
- UI visibility
- Report authorization
- Tenant scoping
- QA testing
- UAT

---

# 1. Security Principles

DMIMS follows:

- Least Privilege Access
- Multi-Tenant Isolation
- Role-Based Access Control
- Defense in Depth
- Secure by Default
- Audit Everything
- No Direct Object Access
- Explicit Permission Checks

## 1.1 Customer-Facing Access Principle

A customer user must only see or access:

1. Records belonging to the authenticated user's own customer.
2. Modules enabled for that customer.
3. Functions permitted by the user's assigned role and permissions.
4. Reports permitted by the user's operational modules and report entitlement.
5. Functions allowed by the customer's subscription.
6. Functions allowed by the customer's current license/access mode.

Platform administration functions must not be visible or accessible to customer users.

Hiding a menu item is not authorization.

The same access decision must be enforced for:

- Navigation
- Dashboard widgets
- Global search
- Routes
- Direct URLs
- Filament Resources
- Relation Managers
- Select/dropdown queries
- Table actions
- Bulk actions
- Livewire requests
- Services
- Reports
- Imports
- Exports
- Downloads
- APIs
- Jobs
- Notifications
- Audit Logs

---

# 2. Role Hierarchy

Highest privilege:

Datamation Super Admin  
↓  
Datamation Management  
↓  
Company Admin  
↓  
Company Supervisor  
↓  
Stock Inventory User  
↓  
Document Tracking User  
↓  
Viewer

Higher roles inherit permissions only where explicitly defined.

## 2.1 Platform-Tier Roles

Only:

- Datamation Super Admin
- Datamation Management

are platform-tier roles.

Every other role is tenant-scoped.

`users.is_platform_user` must always match whether the user holds a platform-tier role.

---

# 3. Resource Scope Classification

Every DMIMS resource must use one explicit access-scope class.

## 3.1 PLATFORM_ONLY

Platform-owned resources.

Customer users must never receive direct navigation or resource access.

Examples:

- Multi-customer Customer directory
- Platform users
- Roles and permissions
- Module catalogue
- Location type catalogue
- Subscription Plans
- License administration (create, renew, suspend, revoke, reactivate,
  technical access mode, limits, module/report overrides — the standalone
  License Management resource, including internal technical fields such as
  server fingerprint, installation id and deployment configuration)
- Platform settings
- Backup / Restore
- Platform reports
- Platform audit logs

Only authorized Datamation platform roles may access these resources.

License administration is intentionally listed both here and, in a
narrower read-only form, under §3.2 — see the note there. This is the only
resource with a split classification; every other example belongs to
exactly one scope class.

## 3.2 TENANT_STRICT

Customer-owned resources.

For a customer user:

```text
customer_id = authenticated user's customer_id
```

Only exact tenant ownership is permitted.

`customer_id IS NULL` must NOT be included.

Examples:

- Customer users
- Customer modules
- Customer subscriptions
- Subscription logs
- License status/history (read-only: status, access mode, valid from/to,
  expiry warning, and the license log/audit trail — via Dashboard/My
  Company, not the administrative License Management resource, which is
  §3.1 PLATFORM_ONLY)
- Billing records
- Billing payments
- Billing logs
- Categories
- Products
- Locations
- Product location stocks
- Stock movements
- Stock alerts
- Boxes
- Document files
- Document movement logs
- Barcode registry
- Barcode scan logs
- Audit logs
- Customer notifications

## 3.3 TENANT_WITH_GLOBAL_DEFAULTS

Used only where the business model explicitly supports shared global reference records.

For a customer user:

```text
customer_id = authenticated user's customer_id
OR
customer_id IS NULL
```

Examples may include:

- Global/default Document Types
- Explicitly approved global Settings/reference values

This scope is opt-in and must never be used as the default tenant scope.

A nullable `customer_id` alone does not authorize customer visibility.

---

# 4. Role Definitions

## Datamation Super Admin

Full platform control.

Can manage:

- Whole system
- All customers
- All users
- Roles and permissions
- Modules
- Subscriptions
- Licenses
- Billing
- Payments
- Reports
- Audit logs
- Backup / restore
- Settings

Scope: all customers and platform resources.

## Datamation Management

Read-only platform analytics.

Can view permitted platform summaries and reports.

Cannot modify operational or administrative data.

## Company Admin

Customer administrator.

Can:

- Manage own company users
- Manage operational modules allowed by role and entitlement
- View own reports
- View own billing when Billing View is enabled
- View own subscription summary
- View own license status
- View own customer audit logs

Cannot access platform configuration.

## Company Supervisor

Operational oversight.

Can:

- Perform operational work where permitted
- View own company profile
- View limited user information / limited user updates
- View own subscription summary
- View own license status
- View own billing when enabled

Cannot access platform administration or customer audit logs unless explicitly approved later.

## Stock Inventory User

Inventory operations only.

## Document Tracking User

Document operations only.

## Viewer

Read-only access to permitted operational areas.

---

# 5. Customer / My Company Permissions

Customer users must not see a multi-customer Customer Management area.

Authorized customer administration is consolidated under:

**My Company**

Possible tabs:

- Company Profile
- Company Users
- Enabled Modules
- Subscription
- License Status
- Billing
- Audit Logs

Each tab is independently authorized.

| Function | SA | Mgmt | Company Admin | Supervisor | Stock | Document | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| Platform Customer List | ✓ | View | ✗ | ✗ | ✗ | ✗ | ✗ |
| Own Company Profile | ✓ | ✓ | View | View | ✗ | ✗ | ✗ |
| Own Company Users | ✓ | ✓ | Manage | View / Limited Edit | ✗ | ✗ | ✗ |
| Own Enabled Modules | ✓ | ✓ | View | View | ✗ | ✗ | ✗ |
| Own Subscription Summary | ✓ | ✓ | View | View | ✗ | ✗ | ✗ |
| Own License Status | ✓ | ✓ | View | View | ✗ | ✗ | ✗ |
| Own Billing | ✓ | ✓ | View* | View* | ✗ | ✗ | ✗ |
| Own Audit Logs | ✓ | Limited | View | ✗ | ✗ | ✗ | ✗ |

\* Only when Billing View is enabled.

---

# 6. User Management Permissions

| Permission | SA | Mgmt | Admin | Sup | Stock | Doc | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| View Users | ✓ | ✓ | Own | Own | ✗ | ✗ | ✗ |
| Create User | ✓ | ✗ | Own | ✗ | ✗ | ✗ | ✗ |
| Update User | ✓ | ✗ | Own | Limited | ✗ | ✗ | ✗ |
| Reset Password | ✓ | ✗ | Own | ✗ | ✗ | ✗ | ✗ |
| Delete User | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

Customer user queries must use exact customer ownership.

Platform users with `customer_id = NULL` must never appear in customer user management.

---

# 7. Subscription Permissions

## Subscription Plans

Subscription Plans are platform master data.

Only Datamation Super Admin may directly create, edit, activate, deactivate or browse Subscription Plans.

Customer users must not:

- See Subscription Plans in navigation
- Browse the platform plan catalogue
- Access Subscription Plan routes directly
- Enumerate plans through global search
- View platform plan configuration

## Customer Subscription

Company Admin and Company Supervisor may view only their own current subscription summary.

Customer-facing information may include:

- Current plan name
- Subscription status
- Valid from
- Valid to
- Usage limits
- Current usage
- Enabled modules
- Allowed report summary
- Billing cycle

Customer users cannot modify subscription configuration.

---

# 8. License Permissions

Only Datamation Super Admin may:

- Create licenses
- Renew licenses
- Suspend licenses
- Revoke licenses
- Reactivate licenses
- Change technical access mode
- Change license limits
- Change license module/report overrides

Customer users must not receive a standalone License Management resource or menu.

Company Admin and Company Supervisor may see only a simplified own-customer License Status through Dashboard or My Company.

Customer-facing license information should be limited to:

- Status
- Access mode
- Valid from
- Valid to
- Expiry warning

Internal technical fields such as server fingerprint, installation identifier, deployment configuration and administrative remarks must not be exposed unless explicitly required.

---

# 9. Billing Permissions

| Action | SA | Mgmt | Admin | Supervisor | Stock | Document | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| View Billing | ✓ | ✓ | Own* | Own* | ✗ | ✗ | ✗ |
| Create Invoice | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Update Payment | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Cancel Billing | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Export Billing | ✓ | ✓ | Own* | Own* | ✗ | ✗ | ✗ |

\* Billing View module required.

Customer billing data is TENANT_STRICT.

---

# 10. Inventory Permissions

| Action | SA | Mgmt | Admin | Supervisor | Stock | Document | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| View Products | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ |
| Create Product | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Update Product | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Delete Product | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Receive Stock | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Stock Out | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Transfer Stock | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Adjust Stock | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |

---

# 11. Document Tracking Permissions

| Action | SA | Mgmt | Admin | Supervisor | Stock | Document | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| View Files | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Receive File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Transfer File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Move Out File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Return File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Receive Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Transfer Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Move Out Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Return Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |

---

# 12. Barcode Permissions

| Action | SA | Mgmt | Admin | Supervisor | Stock | Document | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| Scan Barcode | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Print Barcode | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ |
| View Registry | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Barcode results remain customer-scoped.

---

# 13. Reports & Analytics Permissions

A generic `view reports` permission alone is insufficient.

For a customer user, a report is available only when all applicable checks pass:

1. User belongs to the customer.
2. Reports module is enabled.
3. Required operational module is enabled.
4. User has the required operational permission.
5. Report is permitted by effective `allowed_reports`.
6. License permits viewing/export.
7. Report query is tenant-scoped.
8. Export permission is satisfied.

| Report Family | Required Module | Required Capability |
|---|---|---|
| Inventory Reports | Stock Inventory | View Inventory |
| Document Reports | Document Tracking | View Documents |
| Billing Reports | Billing View | View Billing |
| Audit Reports | Audit entitlement | View Audit Logs |
| Platform Reports | Platform only | Datamation role |

| Report | SA | Mgmt | Admin | Supervisor | Stock | Document | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| Platform Customer Reports | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Subscription Platform Reports | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| License Platform Reports | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Inventory Reports | ✓ | ✓ | ✓* | ✓* | ✓* | ✗ | ✓* |
| Document Reports | ✓ | ✓ | ✓* | ✓* | ✗ | ✓* | ✓* |
| Billing Reports | ✓ | ✓ | Own** | Own** | ✗ | ✗ | ✗ |
| Audit Reports | ✓ | Limited | Own*** | ✗ | ✗ | ✗ | ✗ |

\* Required module and report entitlement must also be enabled.  
\** Billing View must be enabled.  
\*** Own-customer audit records only.

A report must not appear in the report selector when the user cannot execute it.

Backend generation must repeat the same authorization checks.

---

# 14. Audit Permissions

Audit logs are immutable.

Only AuditService may write audit records.

No user may edit or delete audit records.

## Datamation Super Admin

May view all platform and customer audit records.

## Datamation Management

May view summarized audit information where permitted.

## Company Admin

May view only:

```text
audit_logs.customer_id = authenticated_user.customer_id
```

Company Admin must NOT see:

- `customer_id = NULL` platform logs
- Another customer's logs
- Datamation administrative logs unrelated to their customer
- Platform backup / restore activity
- Platform settings activity
- Platform subscription-plan administration

## Other Customer Roles

Company Supervisor, Stock Inventory User, Document Tracking User and Viewer do not receive direct Audit Logs access unless explicitly approved later.

---

# 15. Import & Export Permissions

Imports require:

- Active subscription
- Active license
- Required module enabled
- Required operational permission
- Customer ownership
- Limit availability

Exports require:

- Required operational permission
- Required module
- License allowing export
- Report entitlement where applicable
- Exact tenant scope

Every import and export creates an audit record.

---

# 16. Customer Isolation Matrix

| User | Own Company | Other Company | Platform NULL Records |
|---|---:|---:|---:|
| Super Admin | ✓ | ✓ | ✓ |
| Management | ✓ | ✓ Read-only | ✓ Read-only |
| Company Admin | ✓ | ✗ | ✗ except approved global defaults |
| Supervisor | ✓ | ✗ | ✗ except approved global defaults |
| Stock User | ✓ | ✗ | ✗ except approved global defaults |
| Document User | ✓ | ✗ | ✗ except approved global defaults |
| Viewer | ✓ | ✗ | ✗ except approved global defaults |

---

# 17. Access Decision Flow

Authenticated?  
↓  
User Active?  
↓  
Platform or Customer Context?  
↓  
Resource Scope Allowed?  
↓  
Company Active?  
↓  
Subscription Valid?  
↓  
License Allows?  
↓  
Module Enabled?  
↓  
Permission Granted?  
↓  
Report Entitlement / Usage Limit?  
↓  
Perform Action  
↓  
Write Audit Log

Any failed mandatory check immediately denies access.

---

# 18. Security Best Practices

Developers must:

- Never trust client-submitted `customer_id`.
- Derive tenant context from authenticated user.
- Use authorization in addition to navigation hiding.
- Block direct URL access.
- Apply resource-specific query scopes.
- Protect relation managers, selects, global search and exports.
- Audit critical mutations.
- Fail closed.
- Prefer exact tenant matching for customer-owned data.

---

# 19. QA Verification Checklist

QA must verify:

- Each role sees only permitted menus.
- Customer users cannot see Subscription Plans.
- Customer users cannot access standalone License Management.
- Customer users cannot see platform users.
- Company Admin sees only own-customer audit logs.
- `customer_id = NULL` is excluded from TENANT_STRICT resources.
- Stock users cannot run Document reports.
- Document users cannot run Inventory reports.
- Billing reports require Billing View.
- Report selector contains only executable reports.
- Direct unauthorized report requests fail.
- Hidden platform URLs remain inaccessible.
- Global search does not reveal unauthorized platform records.
- Cross-customer direct IDs are blocked.
- Exports use the same authorization rules as on-screen reports.

---

# 20. Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Security & Access Control Matrix |
| 1.1 | 24 August 2026 | Added explicit resource-scope classifications, My Company boundary, strict audit/user isolation, platform-only Subscription Plans/License management and report-family authorization |
| 1.2 | 24 August 2026 | Resolved a self-contradiction where "Licenses" appeared under both §3.1 PLATFORM_ONLY and §3.2 TENANT_STRICT: split into License *administration* (§3.1, the standalone resource) and License *status/history* (§3.2, read-only). Added the Location type catalogue to §3.1 (implemented as `LocationTypeResource::$platformOnly`; see CONFORMANCE_GAP_ANALYSIS.md §10a H3) |
