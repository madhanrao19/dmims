# DMIMS System Architecture Document (SAD)

**Datamation Inventory Management System**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# 1. Purpose

Defines the architecture of DMIMS.

---

# 2. Architecture Overview

Browser / PWA  
↓  
Laravel / Filament  
↓  
Authentication  
↓  
Authorization  
↓  
Platform / Customer Presentation Context  
↓  
Trusted Customer Context  
↓  
Resource Scope  
↓  
Services  
↓  
Models  
↓  
MariaDB  
↓  
Audit

---

# 3. Presentation Boundaries

## Platform Boundary

For Datamation platform roles.

Includes:

- Dashboard
- Customers / Customer 360
- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit
- Backup/Restore
- Settings

## Customer Boundary

For customer roles.

Includes:

- My Company
- Authorized operational modules
- Authorized reports
- Own customer data

---

# 4. Customer 360 Architecture

## 4.1 Parent Resource

`CustomerResource` is the platform parent for customer-specific administration.

Target page structure:

```text
CustomerResource
├── ListCustomers
├── CreateCustomer
├── ViewCustomer / Customer360
└── EditCustomer
```

## 4.2 Child Domains

ViewCustomer composes existing customer-owned domains:

- Users
- Customer Modules
- Customer Subscriptions
- Licenses
- Billing Records
- Billing Payments
- Audit Logs
- Notifications/Activity

## 4.3 No Data-Layer Merge

Customer 360 is presentation orchestration.

Do not combine these tables into one table.

Do not create duplicate Customer360 business tables merely for display.

## 4.4 Trusted Parent Context

Every child operation uses the selected `Customer` record as trusted context.

Example:

```text
/customers/{customer}/users
```

or embedded tab state must resolve Customer through authorized server-side route/model binding.

Child `customer_id` is derived from parent Customer.

---

# 5. Multi-Tenant Architecture

### PLATFORM_ONLY

Platform master/admin resources.

### TENANT_STRICT

Customer-owned data.

For customer user:

```text
customer_id = authenticated user's customer_id
```

For Customer 360:

```text
customer_id = selected authorized Customer ID
```

### TENANT_WITH_GLOBAL_DEFAULTS

Explicit opt-in only.

---

# 6. Request Lifecycle — Customer 360

Platform User  
↓  
Authenticate  
↓  
Authorize Platform Role  
↓  
Resolve Customer record  
↓  
Authorize selected Customer  
↓  
Resolve child tab/action  
↓  
Apply selected Customer scope  
↓  
Apply action permission/business rules  
↓  
Service  
↓  
Database transaction if required  
↓  
Audit  
↓  
Response

---

# 7. Layered Architecture

Customer 360 remains in Presentation Layer.

It must reuse:

- BaseResource authorization
- Existing Eloquent relationships
- Existing Services
- Existing Observers
- Existing report/audit logic
- Existing tenant-scope protections

---

# 8. Customer Model Relationships

Customer is the natural aggregate navigation root for Customer 360.

Required/desired relationships include:

- users()
- departments()
- customerModules()
- subscriptions()
- licenses()
- billingRecords()
- billingPayments() where useful
- auditLogs()
- notifications()
- locations()

Relationships may be added to the model without changing database schema where foreign keys already exist.

---

# 9. Customer 360 UI Composition

Preferred implementation options:

1. Filament ViewRecord with Tabs/Sections and embedded tables
2. Relation Managers
3. Reusable embedded resource tables/pages

Choose the option that best reuses existing resources and authorization.

Do not copy large table/form definitions into new parallel implementations.

---

# 10. Platform Navigation Architecture

Primary customer management navigation becomes:

```text
Customers
```

Customer-specific resources become contextual to selected Customer.

Remain top-level:

- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit
- Backup / Restore
- System Settings

---

# 11. My Company Architecture

Customer My Company remains a separate tenant-facing presentation composition.

Do not merge My Company and Platform Customer 360 into one authorization path.

They may share reusable components/services but resolve customer context differently.

---

# 12. Security Architecture

Protect:

- Parent Customer route
- Every embedded child table
- Every row action
- Every create/update action
- Global search
- Deep links
- Exports
- Cross-customer relation options

A child action cannot override parent customer context.

---

# 13. Reports Architecture

Reports & Analytics remain separate.

Customer 360 can pass an authorized selected-customer filter to reusable report services where supported.

Never implement separate report SQL inside Customer 360.

---

# 14. Audit Architecture

Customer 360 Audit is a customer-filtered view of authoritative audit logs.

Platform Audit remains separate.

---

# 15. Database Transactions

Existing transaction requirements remain unchanged.

Customer 360 must call existing services for mutations.

---

# 16. Performance

Customer 360 Overview should avoid N+1 queries.

Use aggregate queries/eager loading/caching where appropriate.

Do not load full users/audit/billing history into Overview.

Use paginated child tabs.

---

# 17. PWA / Responsive

Customer 360 should remain usable on desktop/tablet.

Customer operational PWA functionality remains unchanged.

---

# 18. Production Architecture

No infrastructure change is required solely for Customer 360.

---

# 19. Design Principles

- Customer-centric administration
- Trusted parent context
- Reuse existing architecture
- No duplicated business logic
- Defense in depth
- Least privilege
- Clear platform/customer separation

---

# 20. Architecture Decision

See ADR-011 for the accepted Platform Customer 360 decision.

---

# 21. Summary

DMIMS uses Customer as the platform administration aggregate root while retaining separate underlying domain models/services.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial SAD |
| 1.1 | 24 August 2026 | Added explicit resource scope/My Company |
| 1.2 | 25 August 2026 | Added Platform Customer 360 aggregate/presentation architecture |
