# DMIMS Architecture Decision Records (ADR)

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# Document Purpose

This document records major architectural decisions.

Existing accepted decisions remain in force.

---

# ADR-001 — Use Laravel as Primary Framework

**Status:** Accepted

Laravel is the primary backend framework.

---

# ADR-002 — Use Filament as Administration Framework

**Status:** Accepted

Filament is used for business administration interfaces.

---

# ADR-003 — Multi-Tenant Architecture Using customer_id

**Status:** Accepted

Every customer-owned table includes `customer_id`.

Shared deployment is retained.

---

# ADR-004 — Never Trust customer_id from Client

**Status:** Accepted

Trusted customer ownership is always derived server-side from authenticated context.

---

# ADR-005 — Separate Subscription and License

**Status:** Accepted

Subscription = commercial entitlement.

License = technical access.

---

# ADR-006 — Service-Oriented Business Logic

**Status:** Accepted

Business logic belongs in reusable services rather than controllers/resources.

---

# ADR-007 — Immutable Movement History

**Status:** Accepted

Movement history is never edited/deleted; corrections create new records.

---

# ADR-008 — Shared Location Model

**Status:** Accepted

Inventory and Document Tracking share one location hierarchy.

---

# ADR-009 — Manual Billing in Version 1

**Status:** Accepted

Billing/payment is manual and controlled by Datamation administration.

---

# ADR-010 — Explicit Resource Scope Classification and Customer-Facing My Company Boundary

**Status:** Accepted  
**Date:** 24 August 2026

## Context

DMIMS uses `customer_id` multi-tenancy.

Some tables legitimately contain global/default records with `customer_id = NULL`.

Applying:

```text
customer_id = current customer
OR customer_id IS NULL
```

as a generic tenant query is unsafe.

It can expose platform-level records such as platform users or platform audit logs to customer roles.

DMIMS also contains platform resources such as Subscription Plans and License Management that customers do not need as standalone administration functions.

Report authorization must reflect operational module, permission and entitlement.

## Decision

DMIMS uses three resource-scope classifications:

1. PLATFORM_ONLY
2. TENANT_STRICT
3. TENANT_WITH_GLOBAL_DEFAULTS

### TENANT_STRICT

Default for customer-owned data.

```text
customer_id = authenticated user's customer_id
```

No implicit NULL/global records.

### TENANT_WITH_GLOBAL_DEFAULTS

Opt-in only for resources whose documented business rules explicitly support global/default records.

### PLATFORM_ONLY

Customer roles cannot directly access platform administration resources.

Customer-facing company administration is consolidated under:

**My Company**

Possible panels:

- Profile
- Users
- Enabled Modules
- Subscription
- License Status
- Billing
- Audit

Subscription Plans and full License Management remain platform administration.

Customers may see only own effective subscription summary and simplified license status where permitted.

Reports are authorized by:

- Report family
- Required module
- Required permission
- Entitlement
- Tenant ownership
- License mode

## Alternatives Considered

1. Generic tenant scope with NULL fallback.
2. Hide sensitive resources only from navigation.
3. Duplicate customer versions of platform resources.
4. Explicit resource-scope classifications.

## Rationale

Explicit scope provides:

- Least privilege
- Clear tenant boundaries
- Defense in depth
- Reduced data-leak risk
- Cleaner customer UI
- Better report authorization
- No duplication of authoritative business data

## Consequences

Positive:

- Platform records cannot accidentally appear in tenant resources.
- Audit visibility is deterministic.
- Platform users cannot appear in customer user lists.
- Customer navigation is simpler.
- Report authorization matches operational permissions.

Trade-offs:

- Existing resource scoping must be reviewed.
- Regression tests are required.
- Navigation/report logic must be refactored.
- Documentation and UAT must stay synchronized.

## Security Requirement

Menu hiding is never sufficient.

Enforce the same decision in:

- Resource queries
- Authorization
- Direct URLs
- Global search
- Relationships
- Select fields
- Actions
- Reports
- Exports
- APIs
- Jobs

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial ADR collection |
| 1.1 | 24 August 2026 | Added ADR-010 explicit resource scope and My Company architecture |
