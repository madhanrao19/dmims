# DMIMS Architecture Decision Records (ADR)

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# ADR-001 — Use Laravel

**Status:** Accepted

Laravel is the primary backend framework.

---

# ADR-002 — Use Filament

**Status:** Accepted

Filament is the primary administration UI framework.

---

# ADR-003 — customer_id Multi-Tenancy

**Status:** Accepted

Customer-owned data uses customer_id isolation.

---

# ADR-004 — Never Trust customer_id from Client

**Status:** Accepted

Trusted customer context is server-derived.

---

# ADR-005 — Separate Subscription and License

**Status:** Accepted

Subscription controls commercial entitlement.

License controls technical access.

---

# ADR-006 — Service-Oriented Business Logic

**Status:** Accepted

Reuse services and avoid duplicated business logic.

---

# ADR-007 — Immutable Movement History

**Status:** Accepted

Corrections create new history rather than mutating old history.

---

# ADR-008 — Shared Location Model

**Status:** Accepted

Inventory/Documents share one location model.

---

# ADR-009 — Manual Billing v1

**Status:** Accepted

Billing/payment is manual in Version 1.

---

# ADR-010 — Explicit Resource Scope and My Company Boundary

**Status:** Accepted  
**Date:** 24 August 2026

DMIMS uses:

- PLATFORM_ONLY
- TENANT_STRICT
- TENANT_WITH_GLOBAL_DEFAULTS

Customer-facing administration is consolidated under My Company.

---

# ADR-011 — Platform Customer 360 as Customer Administration Aggregate

**Status:** Accepted  
**Date:** 25 August 2026

## Context

Datamation Super Admin currently manages customer-related information through multiple platform resources/menus.

A customer administrator may need to move between:

- Customers
- Users
- Customer Modules
- Customer Subscriptions
- Licenses
- Billing
- Payments
- Audit Logs

This creates navigation overhead and repeated customer selection.

It also increases the chance of operating on the wrong customer when multiple generic customer selectors are presented.

The Customer model is already the natural owner/parent for many of these records.

## Decision

The Platform will use **Customers** as the primary customer-management entry.

Selecting a customer opens a consolidated:

**Customer 360 / Customer Profile**

workspace.

Customer-specific platform administration is presented contextually beneath the selected Customer.

Required areas:

- Overview
- Users
- Modules
- Subscription
- License
- Billing & Payments
- Audit Logs

The selected Customer is the trusted parent context.

Child customer_id is derived server-side from the parent Customer.

## Underlying Architecture

Existing domain tables, models, resources and services remain separate.

Customer 360 is a presentation/orchestration layer, not a data-model merge.

## Platform Navigation

Customer-specific resources should no longer require separate primary sidebar destinations after Customer 360 is complete.

Remain separate:

- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit
- Backup / Restore
- System Settings

## Customer-Facing Boundary

Customer roles continue using My Company.

Platform Customer 360 remains inaccessible to customer roles.

## Alternatives Considered

1. Keep all customer-specific resources as separate top-level navigation.
2. Merge customer-related database tables.
3. Build a separate duplicate Customer Management module.
4. Use Customer as parent aggregate and compose existing resources.

Option 4 is selected.

## Benefits

- Faster Super Admin workflow
- Clear customer context
- Less navigation clutter
- Fewer repeated customer selectors
- Reduced wrong-customer operational risk
- Better customer health overview
- Reuses existing architecture

## Trade-Offs

- CustomerResource requires a new View/Customer 360 page.
- Embedded resource authorization must be carefully implemented.
- Customer model may need additional Eloquent relationships.
- Navigation changes require browser regression testing.
- Overview queries require performance review.

## Security Requirements

- Parent Customer authorization first.
- Every child query fixed to parent Customer.
- Child forms cannot choose another customer.
- Direct child IDs from another customer denied.
- Existing resource permissions reused.
- Datamation Management remains read-only.
- Customer roles denied Platform Customer 360.
- Every mutation audited.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial ADR collection |
| 1.1 | 24 August 2026 | Added ADR-010 |
| 1.2 | 25 August 2026 | Added ADR-011 Platform Customer 360 |
