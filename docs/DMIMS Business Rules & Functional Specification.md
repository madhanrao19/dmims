# DMIMS Business Rules & Functional Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 25 August 2026

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
9. Customer-centric platform administration

---

# 2. Customer Isolation

Every customer operates independently inside the same platform.

No customer may access another customer's information.

Never trust `customer_id` submitted by the browser.

## 2.1 Scope Rules

### TENANT_STRICT

For customer users:

```text
customer_id = authenticated user's customer_id
```

For an authorized platform Customer 360 workflow:

```text
customer_id = selected Customer parent ID
```

### TENANT_WITH_GLOBAL_DEFAULTS

Allowed only for explicitly documented shared/default resources.

### PLATFORM_ONLY

Platform administration data is not customer-visible.

---

# 3. User Management Rules

Platform users are Datamation staff.

Company users belong to exactly one customer.

Company Admin can manage only own-customer users.

Datamation Super Admin may manage a customer's users from that customer's Customer 360 profile.

Inside Customer 360, `customer_id` is derived from the selected parent Customer and must not be re-selected from an arbitrary customer dropdown.

---

# 4. Company Status Rules

Statuses remain:

- Trial
- Active
- Near Expiry
- Expired
- Suspended
- Cancelled
- Archived

Status behaviour remains subject to subscription/license/access rules.

---

# 5. Role-Based Permissions

## Datamation Super Admin

Full platform and Customer 360 management.

## Datamation Management

Read-only platform analytics and read-only Customer 360 where permitted.

## Customer Roles

Tenant-scoped and use My Company plus operational modules.

---

# 6. Platform Customer Management Rule

## 6.1 Customer-Centric Administration

The primary platform workflow for customer administration is:

```text
Customers
→ Select Customer
→ Customer 360 / Customer Profile
```

Customer-specific administration is grouped under that selected Customer.

Required areas:

- Overview
- Users
- Modules
- Subscription
- License
- Billing & Payments
- Audit Logs
- Activity/Notifications where useful

## 6.2 No Duplicate Customer Selection

Once a customer is selected, customer-owned child operations must derive customer ownership from the selected parent record.

Do not ask Super Admin to select the customer again in:

- User creation
- Module assignment
- Subscription management
- License management
- Billing/payment management
- Customer audit review

## 6.3 Underlying Data Remains Separate

Customer 360 is a presentation/workflow consolidation only.

Do not merge database tables or duplicate business logic.

Keep existing models/services/resources authoritative.

## 6.4 Platform Navigation Consolidation

Customer-specific management should not require separate primary sidebar entries for:

- Customer Users
- Customer Modules
- Customer Subscriptions
- Customer Licenses
- Customer Billing
- Customer Payments

Keep separate top-level platform items for:

- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit Logs
- Backup / Restore
- System Settings

---

# 7. Customer My Company Rule

Customer roles never use Platform Customer 360.

They use:

**My Company**

with independently authorized own-company tabs.

My Company uses authenticated-user tenant context.

---

# 8. Subscription Rules

Subscription Plans remain platform templates.

Customer-specific subscription management occurs inside selected Customer 360.

Customer users see own summary only.

---

# 9. License Rules

Full License administration remains platform-only.

Customer-specific License administration occurs inside selected Customer 360.

Customer users see simplified own License Status only.

---

# 10. Module Rules

Platform Module Catalogue remains separate.

Customer module assignment occurs inside selected Customer 360.

Customer users may view enabled modules where permitted but cannot change assignments.

---

# 11. Billing Rules

Billing/payment mutation is Super Admin only.

Customer-specific billing management occurs inside selected Customer 360.

Customer users may view own billing only if permitted.

---

# 12. Audit Rules

Audit history is immutable.

Customer 360 Audit tab uses:

```text
customer_id = selected Customer ID
```

My Company Audit uses:

```text
customer_id = authenticated user's customer_id
```

Do not mix platform NULL audit events into customer-specific timelines unless explicitly designed as a clearly separate platform activity stream.

---

# 13. Customer 360 Overview Rules

Overview should summarize where available:

- Company status
- Contact details
- Current subscription
- Current license/access mode
- Enabled modules
- Effective usage limits
- Current usage
- Outstanding billing
- Recent customer activity
- Expiry/operational alerts

Overview data must be derived from authoritative models/services.

---

# 14. Reports & Analytics Rules

Cross-customer Reports & Analytics remain a separate platform capability.

Customer 360 may link to customer-filtered reports but must not duplicate report generation logic.

Customer-facing report permissions remain module/role/entitlement aware.

---

# 15. Operational Modules

Inventory, Document Tracking, Barcode, Import/Export and other operational rules remain unchanged and tenant-scoped.

Customer 360 does not replace operational workflows.

---

# 16. Security Rules

Developers must never:

- Trust browser-submitted `customer_id`
- Depend only on hidden navigation
- Allow child tabs to change parent customer
- Duplicate authorization/business logic
- Expose cross-customer relationship options
- Weaken existing My Company isolation

---

# 17. Business Rule Hierarchy

1. System Security
2. Customer Isolation
3. Trusted Customer Context
4. Resource Scope
5. License
6. Subscription
7. Module Availability
8. User Permission
9. Business Validation
10. User Interface

---

# 18. Summary

DMIMS uses customer-centric platform administration while preserving strict multi-tenant isolation.

Super Admin manages each customer from one Customer 360 profile.

Customer users remain limited to My Company and authorized operational modules.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Business Rules |
| 1.1 | 24 August 2026 | Added strict tenant scope and My Company |
| 1.2 | 25 August 2026 | Added Platform Customer 360 and trusted parent-customer administration rule |
