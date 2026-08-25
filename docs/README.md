# DMIMS Platform Customer 360 Documentation Update Pack

**Prepared:** 25 August 2026

This package updates the DMIMS documentation for the approved Platform Customer 360 / Customer Profile workflow.

## Full Replacement Markdown Files

- `docs/DMIMS Security & Access Control Matrix.md`
- `docs/DMIMS Business Rules & Functional Specification.md`
- `docs/DMIMS Master Functional Specification (MFS).md`
- `docs/DMIMS System Architecture Document (SAD).md`
- `docs/DMIMS Technical Design Document (TDD).md`
- `docs/DMIMS UIUX & Design System Specification.md`
- `docs/DMIMS Administrator Manual.md`
- `docs/DMIMS Database Dictionary.md`
- `docs/DMIMS Test Strategy, QA Plan & UAT Specification.md`
- `docs/DMIMS Architecture Decision Records (ADR).md`
- `docs/DMIMS Developer Getting Started Guide.md`
- `docs/DMIMS Developer Handover & Onboarding Guide.md`

## Merge-Safe Entries

The Customer 360 entries were merged in place, preserving all existing historical records:

- `docs/CONFORMANCE_GAP_ANALYSIS.md` — new "Platform Customer 360 Design Review — 25 August 2026" section added near the top.
- `CHANGELOG.md` — new `## [Unreleased]` entry added above the released version history.

These were merges rather than destructive replacements because the current Conformance Gap Analysis and Changelog contain detailed historical security/release records that must be preserved.

## Approved Platform Navigation

```text
Dashboard
Customers
Platform Users
Roles & Permissions
Module Catalogue
Subscription Plans
Reports & Analytics
Platform Audit Logs
Backup / Restore
System Settings
```

## Approved Customer 360

```text
Customers
→ Selected Customer
   ├── Overview
   ├── Users
   ├── Modules
   ├── Subscription
   ├── License
   ├── Billing & Payments
   └── Audit Logs
```

## Core Security Rule

```text
Customer 360 child customer_id
=
selected authorized parent Customer ID
```

Never trust a child customer ID submitted by the browser.

## Important

The current GitHub implementation does not yet have a full Platform Customer 360 ViewCustomer workspace.

Documentation is the approved target state.

Do not mark conformance complete until implementation, automated tests, security review and browser QA pass.
