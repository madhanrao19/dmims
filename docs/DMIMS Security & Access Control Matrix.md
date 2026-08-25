# DMIMS Security & Access Control Matrix

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.3  
**Updated:** 25 August 2026

---

# Document Purpose

This document defines the complete authorization model for DMIMS.

It is the authoritative reference for:

- Filament Resource authorization
- Spatie permissions
- Filament navigation
- Customer 360 / Customer Profile access
- Customer-facing My Company access
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
- Trusted Parent Context for Customer Administration

Hiding a menu item is not authorization.

Authorization must be enforced for:

- Navigation
- Dashboard widgets
- Global search
- Routes
- Direct URLs
- Filament Resources
- Relation Managers
- Embedded tables
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

`users.is_platform_user` must match actual platform-tier role membership.

---

# 3. Resource Scope Classification

Every DMIMS resource must use one explicit access-scope class.

## 3.1 PLATFORM_ONLY

Platform-owned resources.

Customer users must never receive direct navigation or administrative resource access.

Examples:

- Multi-customer Customer directory
- Platform users
- Roles and permissions
- Module catalogue
- Location type catalogue
- Subscription Plans
- Full License administration
- Platform settings
- Backup / Restore
- Platform reports
- Platform audit logs

Only authorized Datamation platform roles may access these resources.

## 3.2 TENANT_STRICT

Customer-owned resources.

For a customer user:

```text
customer_id = authenticated user's customer_id
```

For a Customer 360 child context opened by an authorized platform user:

```text
customer_id = selected Customer record ID
```

No child screen may accept a different customer ID from the browser.

Examples:

- Customer users
- Customer modules
- Customer subscriptions
- Subscription logs
- License status/history
- Billing records
- Billing payments
- Billing logs
- Categories
- Products
- Locations
- Stock movements
- Boxes
- Document files
- Barcode records
- Customer audit logs
- Customer notifications

## 3.3 TENANT_WITH_GLOBAL_DEFAULTS

Used only for explicitly approved shared reference records.

For a customer user:

```text
customer_id = authenticated user's customer_id
OR
customer_id IS NULL
```

Examples may include approved global/default Document Types or Settings.

This scope is opt-in.

---

# 4. Role Definitions

## Datamation Super Admin

Full platform control.

Can:

- View all customers
- Open any Customer 360 profile
- Manage the selected customer's users
- Manage the selected customer's enabled modules
- Manage the selected customer's subscription
- Manage the selected customer's license
- Manage the selected customer's billing/payments
- View the selected customer's audit logs
- Manage platform Subscription Plans
- Manage platform Module Catalogue
- Manage platform users
- Manage roles/permissions
- Access platform reports, audit, backup and settings

## Datamation Management

Read-only platform role.

May open Customer 360 in read-only mode where permitted.

Cannot perform customer mutations.

## Customer Roles

Customer roles do not access Platform Customer 360.

They use the tenant-facing **My Company** area and operational modules.

---

# 5. Platform Customer 360 / Customer Profile

## 5.1 Purpose

Customer 360 is the primary Datamation platform workspace for managing one selected customer.

Platform navigation should expose one primary:

**Customers**

entry for customer administration.

The Customers list displays all customers permitted to the platform role.

Selecting a customer opens a consolidated Customer Profile / Customer 360 workspace.

## 5.2 Required Customer 360 Tabs

Recommended tabs:

1. Overview
2. Users
3. Modules
4. Subscription
5. License
6. Billing & Payments
7. Audit Logs
8. Activity / Notifications where useful

## 5.3 Trusted Parent Customer Rule

Once a platform user opens:

```text
Customers → Customer A
```

every customer-owned child query and mutation must derive `customer_id` from Customer A's parent record.

Do not show a second customer selector inside a Customer 360 child tab unless there is a documented exceptional workflow.

A crafted request attempting to assign Customer B data while inside Customer A must be rejected or server-side overwritten with Customer A's ID.

## 5.4 Customer 360 Overview

Overview should provide customer health/status including where available:

- Company name/code
- Company status
- Contact information
- Current subscription plan/status
- Subscription expiry
- Current license/access mode
- License expiry
- Enabled modules
- Users used / effective user limit
- Products used / effective limit
- Files used / effective limit
- Boxes used / effective limit
- Outstanding billing
- Recent customer audit/activity
- Alerts or near-expiry conditions

## 5.5 Platform Navigation Consolidation

The following customer-specific management areas should not be separate primary sidebar destinations once Customer 360 is implemented:

- Customer Users
- Customer Modules
- Customer Subscriptions
- Customer Licenses
- Customer Billing
- Customer Payments

The underlying resources/models/services remain separate internally.

They are presented through the selected customer's Customer 360 workspace.

## 5.6 Platform Items That Remain Separate

These remain top-level platform administration because they are not specific to one selected customer:

- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit Logs
- Backup / Restore
- System Settings

Cross-customer subscription/license/billing summaries may remain under Reports & Analytics.

---

# 6. Customer-Facing My Company

Customer users must not see the Platform Customers list or Customer 360.

Authorized customer roles use:

**My Company**

Possible tabs:

- Company Profile
- Company Users
- Enabled Modules
- Subscription Summary
- License Status
- Billing
- Customer Audit Logs

Each tab remains independently authorized.

Customer My Company uses authenticated-user customer context.

Platform Customer 360 uses selected-parent customer context.

These are different presentation contexts over the same authoritative business data.

---

# 7. User Management Permissions

| Permission | SA | Mgmt | Company Admin | Supervisor | Stock | Doc | Viewer |
|---|---:|---:|---:|---:|---:|---:|---:|
| View Platform Users | ✓ | View | ✗ | ✗ | ✗ | ✗ | ✗ |
| View Customer Users in Customer 360 | ✓ | View | N/A | N/A | N/A | N/A | N/A |
| Create Customer User | ✓ | ✗ | Own Company | ✗ | ✗ | ✗ | ✗ |
| Update Customer User | ✓ | ✗ | Own Company | Limited Own | ✗ | ✗ | ✗ |
| Reset Password | ✓ | ✗ | Own Company | ✗ | ✗ | ✗ | ✗ |
| Delete User | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

Customer 360 user operations must be locked to the selected customer.

---

# 8. Module Permissions

## Platform Module Catalogue

Platform-only.

Defines modules available in DMIMS.

## Customer Modules

Customer-specific assignments are managed by Super Admin from:

```text
Customers → Selected Customer → Modules
```

Customer Admin/Supervisor may view their enabled modules through My Company where permitted.

Customer roles cannot change module assignments.

---

# 9. Subscription Permissions

## Subscription Plans

Platform-only template catalogue.

Remain a separate top-level Platform administration item.

## Customer Subscription

Super Admin manages the selected customer's subscription through:

```text
Customers → Selected Customer → Subscription
```

The child workflow must not require re-selecting the customer.

Datamation Management may view read-only where permitted.

Customer users see only their own effective summary.

---

# 10. License Permissions

Full License administration is platform-only.

Super Admin manages the selected customer's license through:

```text
Customers → Selected Customer → License
```

Customer users see simplified own License Status only.

Internal technical fields remain platform-only.

---

# 11. Billing & Payment Permissions

Super Admin manages the selected customer's billing/payment through:

```text
Customers → Selected Customer → Billing & Payments
```

Customer users may view own billing only when Billing View and role permission allow.

Customer users cannot mutate billing/payment.

---

# 12. Inventory Permissions

Existing inventory role matrix remains authoritative.

Customer 360 does not replace operational Inventory screens.

---

# 13. Document Tracking Permissions

Existing document role matrix remains authoritative.

Customer 360 does not replace operational Document Tracking screens.

---

# 14. Barcode Permissions

Existing barcode permissions remain authoritative.

Cross-customer barcode information must never be exposed.

---

# 15. Reports & Analytics

A generic `view reports` permission is insufficient.

Reports require the relevant module, permission, entitlement, tenant context and license mode.

Platform Reports & Analytics may provide cross-customer summaries.

Customer-specific management should route back to the selected Customer 360 profile when practical.

---

# 16. Audit Permissions

## Platform

Super Admin may view platform-wide audit information.

Datamation Management may view permitted summaries.

## Customer 360

When a customer is selected:

```text
audit_logs.customer_id = selected Customer ID
```

The Customer 360 Audit tab must not mix unrelated platform NULL logs or another customer's logs.

## Customer My Company

Company Admin may view only:

```text
audit_logs.customer_id = authenticated user's customer_id
```

---

# 17. Access Decision Flow

Authenticated?  
↓  
Platform or Customer Role?  
↓  
Requested Presentation Context?  
↓  
Resource Scope Allowed?  
↓  
Trusted Customer Context Resolved?  
↓  
Role Permission?  
↓  
Company / Subscription / License / Module rules?  
↓  
Perform Action  
↓  
Audit

For Customer 360, trusted customer context is the authorized parent Customer record.

For My Company, trusted customer context is the authenticated user's customer.

---

# 18. Security Best Practices

Developers must:

- Never trust client-submitted customer ID.
- Never allow a Customer 360 child form to silently switch customer.
- Reuse existing resources/services rather than duplicating logic.
- Authorize embedded tables/actions.
- Protect direct child routes.
- Protect global search.
- Audit critical changes.
- Fail closed.

---

# 19. QA Verification Checklist

Verify:

- Super Admin sees all Customers.
- Clicking a Customer opens Customer 360.
- Customer 360 child tabs show only selected-customer data.
- Creating a user inside Customer A automatically assigns Customer A.
- Crafted Customer B IDs are rejected/overwritten inside Customer A context.
- Customer Modules tab cannot modify another customer.
- Subscription tab is locked to selected customer.
- License tab is locked to selected customer.
- Billing/Payments are locked to selected customer.
- Audit tab contains only selected-customer audit events.
- Customer-specific standalone sidebar entries are removed/hidden for platform users as approved.
- Platform Users remains separate.
- Subscription Plans remains separate.
- Module Catalogue remains separate.
- Cross-customer Reports & Analytics remain available.
- Datamation Management Customer 360 is read-only.
- Customer roles cannot access Platform Customer 360.
- Existing My Company behaviour does not regress.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Security & Access Control Matrix |
| 1.1 | 24 August 2026 | Added strict tenant scope and My Company model |
| 1.2 | 24 August 2026 | Clarified split License administration/status scope and global defaults |
| 1.3 | 25 August 2026 | Added Platform Customer 360, trusted parent customer context and consolidated customer-specific platform management |
