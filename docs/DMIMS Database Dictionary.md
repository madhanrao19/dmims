# DMIMS Database Dictionary

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# Document Purpose

This document describes DMIMS database ownership and application access semantics.

Schema field-level definitions remain governed by actual Laravel migrations.

---

# Database Design Principles

DMIMS follows:

- Multi-tenant architecture
- Customer isolation
- Immutable movement history
- Soft deletes for master data
- Foreign key integrity
- Audit-first design
- Subscription/license separation
- Explicit access-scope classification

---

# Data Access Scope Classification

## PLATFORM_ONLY

Platform-owned/master data.

Examples:

- roles
- permissions
- modules
- subscription_plans
- platform settings

Customer users cannot directly browse these tables as platform administration resources.

## TENANT_STRICT

Customer-owned records.

Customer query rule:

```text
customer_id = authenticated user's customer_id
```

Examples:

- customer users
- customer_modules
- customer_subscriptions
- subscription_logs
- licenses
- license_logs
- billing_records
- billing_payments
- billing_logs
- locations
- categories
- products
- product_location_stocks
- stock_movements
- stock_alerts
- boxes
- document_files
- document_movement_logs
- barcode_registry
- barcode_scan_logs
- audit_logs
- customer notifications

`customer_id IS NULL` is not part of a customer query for these resources.

## TENANT_WITH_GLOBAL_DEFAULTS

Tables explicitly designed to contain global and customer-specific reference records.

Examples:

- document_types where `customer_id` may be NULL
- settings where approved global values are customer-readable

NULL does not automatically mean customer-visible.

---

# CUSTOMERS

Purpose: stores customer companies.

Platform Customer Management is PLATFORM_ONLY.

Customer users may view own company through My Company where authorized.

---

# USERS

Purpose: stores platform and customer users.

Platform users:

```text
customer_id = NULL
is_platform_user = true
```

Customer users:

```text
customer_id = owning customer
is_platform_user = false
```

## Access Rules

When a customer administrator lists users:

```text
users.customer_id = authenticated_user.customer_id
```

Platform users must never be included.

---

# MODULES

Platform module catalogue.

Scope: PLATFORM_ONLY.

Customers may see only their own enabled module assignments, not the module management resource.

---

# CUSTOMER_MODULES

Purpose: customer module assignments.

Scope: TENANT_STRICT.

---

# SUBSCRIPTION_PLANS

Purpose: reusable platform plan templates.

Scope: PLATFORM_ONLY.

Customer users do not browse this table/resource.

---

# CUSTOMER_SUBSCRIPTIONS

Purpose: actual subscription assigned to customer.

Scope: TENANT_STRICT.

Customers may see own summary where permitted.

---

# SUBSCRIPTION_LOGS

Scope: TENANT_STRICT.

Immutable history.

---

# LICENSES

Scope: TENANT_STRICT as data ownership.

Full management is platform-only.

Customer-facing presentation is simplified License Status.

---

# LICENSE_LOGS

Scope: TENANT_STRICT.

Not directly customer-editable.

---

# BILLING_RECORDS

Scope: TENANT_STRICT.

Customer users may only view own records when Billing View is enabled.

Only Datamation Super Admin modifies billing.

---

# BILLING_PAYMENTS / BILLING_LOGS

Scope: TENANT_STRICT.

Manual platform-controlled payment administration.

---

# LOCATIONS

Scope: TENANT_STRICT.

Shared by Inventory and Documents.

---

# CATEGORIES / PRODUCTS / PRODUCT_LOCATION_STOCKS

Scope: TENANT_STRICT.

Unique keys and quantities remain scoped by customer.

---

# STOCK_MOVEMENTS / STOCK_ALERTS

Scope: TENANT_STRICT.

Movement history immutable.

---

# BOXES / DOCUMENT_FILES / DOCUMENT_MOVEMENT_LOGS

Scope: TENANT_STRICT.

Movement history immutable.

---

# DOCUMENT_TYPES

May support global defaults and customer-specific records.

Scope: TENANT_WITH_GLOBAL_DEFAULTS only if actual implementation/business rule supports `customer_id = NULL` global records.

---

# BARCODE_REGISTRY / BARCODE_SCAN_LOGS

Scope: TENANT_STRICT.

Cross-customer barcode lookup must not reveal information.

---

# AUDIT_LOGS

Purpose: immutable audit trail.

## Ownership Meaning

`customer_id` populated:

Customer-related audit event.

`customer_id = NULL`:

Platform-level audit event.

## Customer Access Rule

Company Admin may query only:

```text
customer_id = authenticated user's customer_id
```

Platform NULL logs must never be included in customer audit queries.

Other-customer logs must never be included.

---

# NOTIFICATIONS

Customer notifications:

TENANT_STRICT.

Platform notifications:

Platform-only.

---

# SETTINGS

May contain platform and customer-specific settings.

Customer visibility of NULL/global settings must be explicitly approved per setting group.

Do not expose all global settings by default.

---

# Foreign Key Standards

Maintain referential integrity.

History tables should not cascade delete.

Tenant-owned relationships must preserve same-customer integrity.

---

# Index Standards

Index customer ownership and frequently queried authorization/filter columns.

---

# Database Design Summary

Database nullability is not an authorization rule.

Application access is determined by explicit resource-scope classification plus role/module/subscription/license authorization.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Database Dictionary |
| 1.1 | 24 August 2026 | Added explicit platform/tenant scope semantics and strict audit/user access rules |
