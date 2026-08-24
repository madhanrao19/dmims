# DMIMS Customer Access-Control Documentation Update Pack

**Prepared:** 24 August 2026

This package contains full standalone updated Markdown documents for the approved customer-facing access-control model.

## Files

- `docs/DMIMS Security & Access Control Matrix.md`
- `docs/DMIMS Business Rules & Functional Specification.md`
- `docs/DMIMS Master Functional Specification (MFS).md`
- `docs/DMIMS System Architecture Document (SAD).md`
- `docs/DMIMS Technical Design Document (TDD).md`
- `docs/DMIMS UIUX & Design System Specification.md`
- `docs/DMIMS Administrator Manual.md`
- `docs/DMIMS Database Dictionary.md`
- `docs/DMIMS Test Strategy, QA Plan & UAT Specification.md`
- `docs/DMIMS API & Service Integration Specification.md`
- `docs/DMIMS Architecture Decision Records (ADR).md`
- `docs/CONFORMANCE_GAP_ANALYSIS.md`
- `CHANGELOG_UNRELEASED_ENTRY.md`

## Approved Access Model

Customer access is determined by:

```text
OWN CUSTOMER
AND
ROLE PERMISSION
AND
ENABLED MODULE
AND
SUBSCRIPTION ENTITLEMENT
AND
LICENSE ACCESS
AND
RESOURCE-SPECIFIC AUTHORIZATION
```

Resource scope classes:

- `PLATFORM_ONLY`
- `TENANT_STRICT`
- `TENANT_WITH_GLOBAL_DEFAULTS`

For customer-owned data:

```text
TENANT_STRICT
= customer_id = authenticated_user.customer_id
```

`customer_id IS NULL` is never automatically customer-visible.

## Customer-Facing Administration

Use:

**My Company**

with role-controlled panels:

- Profile
- Users
- Enabled Modules
- Subscription
- License Status
- Billing
- Audit Logs

## Important

These documents define the approved target state.

`CONFORMANCE_GAP_ANALYSIS.md` correctly marks the corresponding implementation work as pending.

`CHANGELOG_UNRELEASED_ENTRY.md` is intentionally merge-ready rather than a replacement file, so existing release history is preserved.

Do not claim production conformance until code, tests and browser-level role QA pass.

## Governance

Existing Engineering Constitution, Project Governance and Definition of Done remain authoritative and do not need replacement for this change.
