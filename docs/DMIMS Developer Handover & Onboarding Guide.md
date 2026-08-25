# DMIMS Developer Handover & Onboarding Guide

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 25 August 2026

---

# 1. Project Overview

DMIMS is a multi-tenant enterprise platform.

Key concepts:

- Strict customer isolation
- Platform/customer role boundary
- My Company for customer roles
- Customer 360 for platform customer administration
- Subscription/license separation
- Manual billing
- Immutable audit/movement history

---

# 2. Read Before Coding

Mandatory order:

1. Engineering Constitution
2. Governance
3. Definition of Done
4. Conformance Gap
5. Security Matrix
6. MFS
7. SAD
8. TDD
9. Business Rules
10. Database Dictionary
11. Test Strategy
12. UI/UX

---

# 3. Two Customer Administration Experiences

## Customer Roles — My Company

Customer derived from authenticated user.

Own-company only.

## Datamation Platform — Customer 360

Customer selected from Platform Customers list.

All child records/actions fixed to selected parent customer.

Do not confuse the two contexts.

---

# 4. Customer 360 Target

Customers  
↓  
Select Customer  
↓  
Overview | Users | Modules | Subscription | License | Billing & Payments | Audit

Underlying models/services remain separate.

---

# 5. Important Existing Architecture

Understand:

- CustomerResource
- BaseResource
- BelongsToCustomer
- AccessControlService
- ModuleAccessService
- LicenseService
- BillingService
- PaymentService
- AuditService
- CustomerSubscriptionObserver
- Existing My Company cluster
- Role-based Playwright tests

---

# 6. Customer 360 Implementation Expectations

Use Customer as parent aggregate.

Reuse existing child resource/table logic.

Do not create parallel authorization.

Do not allow customer switching inside child tabs.

Management role remains read-only.

---

# 7. Common Pitfalls

- Generic Customer select inside Customer 360
- Child action uses submitted customer_id
- Embedded action loses resource authorization
- Customer audit filter queries platform data
- Overview creates N+1 queries
- Hiding sidebar item but leaving unauthorized route
- Breaking customer My Company while changing platform navigation

---

# 8. Required Tests

Every Customer 360 change should test:

- Parent access
- Customer A/B isolation
- Child create/update ownership
- Embedded actions
- Management read-only
- Customer role denied
- Navigation
- My Company regression

---

# 9. Handover Status Rule

If Customer 360 is not yet implemented, it must remain an open item in CONFORMANCE_GAP_ANALYSIS.

Do not describe it as complete based on documentation alone.

---

# 10. Pull Request Expectations

Include:

- Scope
- Screenshots
- Files changed
- Security impact
- Tenant-isolation impact
- Tests
- Performance impact
- Documentation updated
- Conformance status

---

# 11. Success Criteria

A developer is ready to own Customer 360 work when they can explain:

- Why customer_id is never trusted from browser
- Difference between My Company and Customer 360
- Why Customer is the parent context
- Why database tables remain separate
- How embedded Filament authorization is preserved
- How A/B tenant regression tests prove safety

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Handover Guide |
| 1.1 | 25 August 2026 | Added Platform Customer 360 onboarding and implementation requirements |
