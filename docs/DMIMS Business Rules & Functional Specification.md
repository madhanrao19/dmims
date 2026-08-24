# DMIMS Business Rules & Functional Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# Document Purpose

This document defines the business rules that govern DMIMS from a business perspective.

Whenever there is uncertainty during development, these rules take precedence over implementation assumptions.

---

# 1. Core Business Principles

DMIMS is built around:

1. Multi-tenant architecture
2. Complete customer data isolation
3. Immutable inventory and document history
4. Subscription-based commercial control
5. License-based technical access control
6. Full auditability
7. Least-privilege customer presentation
8. Platform/customer administrative separation

These principles must never be violated.

---

# 2. Customer Isolation

Every customer company operates independently inside the same DMIMS platform.

No customer may access another customer's information.

Every customer-owned record contains `customer_id`.

The authenticated user's customer context determines what customer-owned records may be accessed.

Never trust `customer_id` submitted by the browser.

## 2.1 Customer Data Scope Rule

### TENANT_STRICT

For customer-owned data:

```text
customer_id = authenticated user's customer_id
```

Never automatically include `customer_id IS NULL`.

This applies to customer users, operational records, customer subscriptions, licenses, billing, customer audit logs and other customer-owned records.

### TENANT_WITH_GLOBAL_DEFAULTS

`customer_id IS NULL` may be customer-visible only where the relevant table explicitly supports shared global/default reference records.

Global/default visibility is opt-in.

### PLATFORM_ONLY

Platform administration data must not be visible to customer users.

Examples include:

- Subscription Plans
- Platform Module catalogue
- Platform users
- Platform settings
- Backup / Restore
- Platform audit logs

---

# 3. User Management Rules

## Platform Users

Datamation employees.

Normally:

```text
customer_id = NULL
is_platform_user = true
```

Platform roles:

- Datamation Super Admin
- Datamation Management

`is_platform_user` must match actual platform-tier role membership.

## Company Users

Assigned to exactly one customer.

Cannot:

- Create platform users
- Assign platform roles
- View platform users
- Change another company's users
- Change customer ownership through crafted requests

Customer user listing is TENANT_STRICT.

---

# 4. Company Status Rules

Statuses:

- Trial
- Active
- Near Expiry
- Expired
- Suspended
- Cancelled
- Archived

Trial and Active allow normal access subject to subscription, license, module and permission.

Near Expiry remains operational but shows reminders.

Expired is controlled by subscription grace and license.

Suspended blocks operational actions and may permit view-only.

Cancelled and Archived are terminal/restricted states.

---

# 5. Role-Based Permissions

## Datamation Super Admin

Full system control.

## Datamation Management

Read-only platform analytics.

## Company Admin

Manages own company only.

## Company Supervisor

Operational oversight and limited administration.

## Stock Inventory User

Inventory only.

## Document Tracking User

Document Tracking only.

## Viewer

Read-only access to permitted operational modules.

## 5.1 Customer Navigation Rule

Customer navigation must reflect effective access.

Customer-facing administration is consolidated under:

**My Company**

Possible tabs:

- Company Profile
- Company Users
- Enabled Modules
- Subscription Summary
- License Status
- Billing
- Customer Audit Logs

Each tab remains independently role- and entitlement-controlled.

Customer users must not see:

- Multi-customer Customer Management
- Platform Users
- Roles & Permissions
- Module catalogue management
- Subscription Plans
- License Management
- Backup / Restore
- Platform Settings
- Platform Reports
- Platform Audit Logs

---

# 6. Access Control Rules

Before allowing any operation, evaluate:

User Status  
↓  
Customer / Platform Context  
↓  
Resource Scope  
↓  
Company Status  
↓  
Subscription Status  
↓  
License Status  
↓  
Module Enabled  
↓  
Permission Granted  
↓  
Report Entitlement or Usage Limit

Only when all mandatory checks succeed may the action continue.

---

# 7. Subscription Rules

A subscription defines commercial entitlement.

It controls:

- Plan
- Modules
- Limits
- Billing cycle
- Validity period
- Grace period
- Allowed reports

Subscription does not alone determine final technical access.

## Subscription Plans

Subscription Plans are platform master data.

Customers cannot browse or manage the platform Subscription Plan catalogue.

Authorized Company Admin/Supervisor users may only see their own effective subscription summary.

## Subscription Limits

May define:

- Maximum users
- Maximum products
- Maximum document files
- Maximum archive boxes
- Enabled modules
- Allowed reports

If a limit is reached, prevent creation of additional records while preserving permitted access to existing records.

---

# 8. License Rules

License determines technical system access.

Statuses:

- Active
- Suspended
- Expired
- Revoked

Technical modes:

- Full Access
- View Only
- Blocked

Customers do not receive standalone License Management.

Authorized Company Admin/Supervisor users may see a simplified own-customer License Status only.

Internal technical license configuration must not be exposed unnecessarily.

---

# 9. Effective Access Rule

Effective permission is the intersection of:

- Customer ownership
- User status
- Company status
- Resource scope
- Subscription
- License
- Enabled module
- Role permission
- Report entitlement
- Usage limits

Failure of any mandatory check denies the requested operation.

---

# 10. Module Rules

Each customer has independently enabled modules.

Examples:

- Stock Inventory
- Document Tracking
- Barcode Scanning
- Barcode Printing
- Reports
- Import / Export
- Audit
- Billing View

If disabled:

- Hide menu
- Block route
- Block direct URL
- Block service execution
- Block reports requiring the module

Customer module status may be shown read-only inside My Company.

Viewing own enabled modules never grants access to the platform Module catalogue.

---

# 11. Inventory Rules

Every product belongs to one customer.

SKU and barcode are unique within customer.

Negative stock is not permitted.

Every movement creates movement history and audit.

---

# 12. Stock Receive-In

Receive-In increases available inventory.

External source is allowed.

Destination must be an internal DMIMS location owned by the same customer.

---

# 13. Stock Out

Stock Out decreases inventory.

Destination may be external.

Available quantity cannot become negative.

---

# 14. Internal Stock Transfer

Both locations must belong to the same customer.

Total inventory remains unchanged.

---

# 15. Stock Adjustment

Requires:

- Reason
- User
- Date
- Audit record

Negative adjustment cannot reduce stock below zero.

---

# 16. Shared Location Rules

Locations are shared by Inventory and Document Tracking.

Products occupy locations.

Boxes occupy locations.

Files occupy boxes.

External destinations are never stored as fake DMIMS locations.

---

# 17. Archive Box Rules

Boxes may contain multiple files.

A box occupies one location.

Moving a box changes effective location of contained files without updating every file record.

---

# 18. Document File Rules

Files belong to one customer.

Files may belong to one box at a time.

Moving a file changes its current box.

Moving a box does not update each file record.

---

# 19. External Movement Rules

External locations are represented by structured movement fields, not fake location master records.

---

# 20. Barcode Rules

Every barcode is centrally registered.

Supported types:

- Product
- Location
- Box
- Document File

Unknown barcodes are logged.

A barcode from another customer must not reveal information.

---

# 21. Import Rules

Imports must:

- Validate all rows
- Preview before commit
- Reject duplicates
- Respect limits
- Respect module/role access
- Use exact tenant ownership
- Generate audit logs
- Roll back on failure where required

---

# 22. Export and Report Rules

Exports require:

- Required operational permission
- Required module
- License allowing export
- Customer ownership
- Allowed report entitlement where applicable

A generic `view reports` permission does not authorize every report.

Examples:

- Inventory reports require Inventory access.
- Document reports require Document Tracking access.
- Billing reports require Billing View and billing permission.
- Audit reports require audit permission and exact customer scope.

Every export creates an audit record.

---

# 23. Billing Rules

Billing is manual in Version 1.

Only Datamation Super Admin may:

- Create invoices
- Record payments
- Update balances
- Issue/cancel billing records

Company users may only view own billing when Billing View is enabled and their role permits it.

Billing is TENANT_STRICT.

---

# 24. Audit Rules

Audit critical actions including:

- Authentication
- User changes
- Role changes
- Customer changes
- Subscription changes
- License changes
- Billing/payment changes
- Inventory/document movements
- Barcode operations
- Imports/exports
- Backup/restore

Audit entries are immutable.

## Customer Audit Visibility

Company Admin may view only:

```text
customer_id = authenticated user's customer_id
```

Platform audit records with `customer_id = NULL` are never customer-visible.

Other customer audit records are never customer-visible.

---

# 25. Notification Rules

Customer notifications remain within customer boundary.

Platform notifications are visible only to authorized Datamation platform users.

---

# 26. Progressive Web App Rules

DMIMS remains online-first.

Offline mode shows information only and prevents operational transactions.

---

# 27. Security Rules

Developers must never:

- Trust browser-submitted `customer_id`
- Disable authorization
- Rely only on hidden UI
- Expose hidden routes
- Modify audit history
- Delete movement history
- Bypass access-control services
- Use generic `OR customer_id IS NULL` for TENANT_STRICT resources

---

# 28. Error Handling Rules

Provide clear messages.

Log unexpected failures.

Rollback incomplete transactions.

Never expose technical details or unauthorized resource existence.

---

# 29. Business Rule Hierarchy

Precedence:

1. System Security
2. Customer Isolation
3. Resource Scope
4. License
5. Subscription
6. Module Availability
7. User Permission
8. Business Validation
9. User Interface

Security always overrides convenience.

---

# 30. Summary

DMIMS protects customer data by strict tenant isolation, separates commercial entitlement from technical access, preserves immutable history, and presents customer users only with functions relevant to their company, role and purchased/enabled services.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Business Rules & Functional Specification |
| 1.1 | 24 August 2026 | Added strict tenant scope, My Company model, platform-only subscription/license administration and report-family authorization |
