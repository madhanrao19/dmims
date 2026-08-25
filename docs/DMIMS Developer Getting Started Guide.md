# DMIMS Developer Getting Started Guide

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 25 August 2026  
**Technology:** Laravel 13 + Filament 5 + MariaDB + PHP 8.4+

---

# 1. Project Overview

DMIMS is a multi-tenant platform for:

- Customer administration
- Inventory
- Document tracking
- Barcode workflows
- Subscription/license control
- Billing
- Reports
- Audit
- PWA

Platform customer administration is customer-centric through the approved Customer 360 architecture.

---

# 2. Mandatory Documents

Before development read:

1. Engineering Constitution
2. Project Governance
3. DEFINITION_OF_DONE
4. CONFORMANCE_GAP_ANALYSIS
5. Security & Access Control Matrix
6. MFS
7. SAD
8. TDD
9. Business Rules
10. Database Dictionary
11. UI/UX Specification
12. Test Strategy

---

# 3. Core Rules

- Never trust request customer_id.
- TENANT_STRICT is default for customer-owned data.
- Reuse AccessControlService/BaseResource architecture.
- Audit critical mutations.
- Keep movement/audit history immutable.
- Do not duplicate business logic.

---

# 4. Platform Customer 360 Concept

Developers must understand two distinct customer contexts.

## Customer User Context

Customer role:

```text
customer_id = authenticated user's customer_id
```

Presentation:

**My Company**

## Platform Customer 360 Context

Authorized platform role:

```text
Customers
→ selected Customer
→ child customer-owned data
```

Child ownership:

```text
customer_id = selected Customer ID
```

The browser must not choose a different child customer.

---

# 5. Customer 360 Target Components

Platform Customers:

- ListCustomers
- CreateCustomer
- ViewCustomer / Customer360
- EditCustomer

Customer 360:

- Overview
- Users
- Modules
- Subscription
- License
- Billing & Payments
- Audit

Reuse existing resources/services.

---

# 6. Navigation Rule

After Customer 360 implementation, customer-specific platform administration is contextual under Customers.

Platform-wide master areas remain separate.

---

# 7. Local Setup

Use current repository README/deployment setup.

Typical:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan filament:assets
npm run build
php artisan test
```

---

# 8. Important Code Areas

- `app/Filament/Resources`
- `app/Filament/Clusters`
- `app/Models`
- `app/Services`
- `app/Models/Concerns`
- `tests/Feature`
- `tests/playwright`
- `docs`

---

# 9. Customer Model

Customer is the natural platform administration parent.

Review existing relationships before adding new ones.

Add Eloquent relationships only when schema supports them.

---

# 10. Development Workflow

Understand  
↓  
Inspect docs/code  
↓  
Classify risk  
↓  
Plan root cause  
↓  
Implement  
↓  
Test  
↓  
Security review  
↓  
Update docs  
↓  
PR

---

# 11. Customer 360 Development Safety

Never:

- Copy customer_id from form request
- Add generic customer selector to child tabs
- Duplicate resource permissions
- Bypass embedded-action authorization
- Load all child history into Overview
- Merge domain tables merely for UI convenience

Always:

- Resolve parent Customer server-side
- Reuse child relationships/resources/services
- Paginate heavy tables
- Audit mutations
- Test A/B customer isolation

---

# 12. Testing

For Customer 360 run:

- Feature tests
- Permission tests
- Tenant tests
- Browser/Playwright tests
- Static analysis
- Frontend build

---

# 13. First Milestone

A successful Customer 360 implementation proves:

- Super Admin sees all Customers
- Opens Customer A profile
- Manages A users/modules/subscription/license/billing without re-selecting customer
- Audit shows A only
- Customer B cannot be affected from A context
- Management is read-only
- Customer roles cannot access Customer 360
- My Company remains correct

---

# 14. Definition of Done

Follow repository DEFINITION_OF_DONE.

No Customer 360 task is complete while its Conformance Gap remains open.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Getting Started Guide |
| 1.1 | 25 August 2026 | Added Customer 360 architecture and implementation safety guidance |
