# DMIMS Technical Design Document (TDD)

**Datamation Inventory Management System**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# Document Purpose

Defines the technical implementation standards for DMIMS.

---

# 1. Technical Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 |
| Admin Panel | Filament 5 |
| Language | PHP 8.4+ |
| Database | MariaDB / MySQL compatible |
| Authentication | Laravel + Filament |
| Permissions | Spatie Laravel Permission |
| Frontend | Blade + Tailwind CSS + Alpine.js |
| Build Tool | Vite |
| Queue | Database Queue |
| Cache | File Cache, Redis-ready |
| Server | Ubuntu 24.04 + Apache + PHP-FPM |

---

# 2. Directory Structure

Use existing DMIMS Laravel structure under:

- app/Filament
- app/Http
- app/Models
- app/Services
- app/Traits
- app/Observers
- app/Jobs
- resources
- routes
- tests
- docs

Reuse existing architecture.

---

# 3. Laravel Design Rules

Controllers remain thin.

Business logic belongs in services.

Models hold relationships/scopes/casts.

Middleware handles cross-cutting validation.

Filament resources must enforce authorization server-side.

Never rely only on UI visibility.

## 3.1 Resource Scope Classification

Each resource/page must explicitly identify one logical access scope:

- PLATFORM_ONLY
- TENANT_STRICT
- TENANT_WITH_GLOBAL_DEFAULTS

### TENANT_STRICT

For non-platform users:

```php
$query->where('customer_id', auth()->user()->customer_id);
```

Do not append `orWhereNull('customer_id')`.

### TENANT_WITH_GLOBAL_DEFAULTS

Only explicitly approved resources may use:

```php
$query->where(function ($query) use ($user) {
    $query
        ->where('customer_id', $user->customer_id)
        ->orWhereNull('customer_id');
});
```

### PLATFORM_ONLY

Customer users fail authorization even if they possess a similarly named generic permission.

Platform-only status must be enforced server-side.

---

# 4. Required Models

Core:

- Customer
- User
- Module
- CustomerModule
- SubscriptionPlan
- CustomerSubscription
- SubscriptionLog
- License
- LicenseLog
- BillingRecord
- BillingPayment
- BillingLog
- Setting
- AuditLog
- Notification

Inventory:

- Category
- Product
- ProductLocationStock
- StockMovement
- StockAlert
- Location
- LocationType

Document:

- Box
- DocumentType
- DocumentFile
- DocumentMovementLog

Barcode:

- BarcodeRegistry
- BarcodeScanLog

---

# 5. Required Services

Use existing:

- AccessControlService
- ModuleAccessService
- LicenseService
- BillingService
- PaymentService
- BarcodeService
- ScannerService
- StockMovementService
- DocumentMovementService
- AuditService
- NotificationService
- ImportService
- ReportExportService
- BackupService

Subscription lifecycle may remain split across AccessControlService and subscription observer architecture.

---

# 6. Required Middleware

Applicable middleware includes:

- Auth
- User active
- Company assigned/active
- Business access
- Subscription validation
- License validation
- Module validation
- Activity logging

Middleware order must ensure authentication/session exists before business-access checks.

---

# 7. Filament Resources

Resources must not infer customer visibility from permission names alone.

## 7.1 Platform-Only Resources

Examples:

- SubscriptionPlanResource
- ModuleResource
- Platform settings
- BackupResource
- Other platform administration

Customer users must not register navigation or pass direct authorization.

## 7.2 Strict Tenant Resources

Examples:

- UserResource customer user view
- CustomerModuleResource
- CustomerSubscriptionResource
- License customer record
- BillingRecordResource
- AuditLogResource
- Operational resources

Query ownership must be exact.

## 7.3 Customer-Facing Presentation

Customer users should not receive separate platform-style navigation entries for customer modules/subscriptions/licenses.

Where permitted, relevant information should be composed into:

**My Company**

Panels:

- Profile
- Users
- Enabled Modules
- Subscription Summary
- License Status
- Billing
- Audit

Every panel independently authorizes access.

Do not duplicate business logic.

---

# 8. Naming Standards

Use Laravel conventions:

- plural snake_case tables
- PascalCase models
- `Service` suffix
- explicit permission names
- explicit scope semantics

---

# 9. Database Transactions

Use transactions for critical multi-table operations including inventory/document movement, billing updates, renewals and barcode registration.

---

# 10. Error Handling

Throw typed exceptions where appropriate.

Log unexpected errors.

Return user-safe messages.

Do not leak existence of another tenant's records.

---

# 11. Logging

Log system errors, failed login, queue failures, import/export failures and unexpected exceptions.

Audit critical business actions separately.

---

# 12. Validation Standards

Validate server-side:

- Required fields
- Length
- Uniqueness
- Foreign keys
- Dates
- Numbers
- Files
- Barcode format
- Customer ownership
- Role and platform boundary

---

# 13. Database Relationships

All customer-owned relationships must maintain same-customer integrity.

Relationship/select queries must not enumerate records from another customer or platform-only resources.

---

# 14. Queue Jobs

Jobs carrying customer work must derive/use trusted customer ownership created server-side.

Do not trust user-supplied `customer_id` embedded in job payloads.

---

# 15. Performance Guidelines

Use eager loading, pagination, indexes, caching and streaming/queues for large workloads.

Authorization queries should be efficient and indexed.

---

# 16. Security Guidelines

Use:

- CSRF
- Session security
- Rate limiting
- Resource authorization
- Middleware
- Validation
- Parameterized queries
- Secure uploads
- HTTPS

Never expose secrets.

Never use navigation visibility as sole security.

---

# 17. Testing Requirements

Each module requires feature, unit, permission, validation, transaction and regression tests.

## Mandatory Access-Control Regression Tests

- TENANT_STRICT excludes `customer_id = NULL`.
- Customer Admin cannot enumerate platform users.
- Company Admin audit query excludes platform logs.
- Platform-only resources are inaccessible to customer roles.
- Stock role cannot run Document reports.
- Document role cannot run Inventory reports.
- Billing reports fail when Billing View is disabled.
- Direct report generation fails for unauthorized report code.
- Global search cannot leak platform/other-tenant records.

---

# 18. ReportExportService Design

Each report definition must declare sufficient authorization metadata.

Recommended fields:

```text
group
platform_only
required_permission
required_module
required_report_entitlement
```

`view reports` controls entry to Reports, not all report content.

`availableTo()` and generation must enforce the same effective authorization.

Never rely only on filtering the dropdown.

---

# 19. Development Workflow

Follow:

Understand  
↓  
Discover  
↓  
Prioritize  
↓  
Implement root cause  
↓  
Test  
↓  
Security review  
↓  
Documentation update  
↓  
Release

---

# 20. Definition of Done

A change is complete only when:

- Business requirement implemented
- Customer isolation enforced
- Resource authorization implemented
- Tests pass
- Static analysis passes
- UI correct
- Docs updated
- No Critical/High gaps remain in scope

---

# 21. Future Enhancements

Future APIs, mobile, SSO, AI, GraphQL and integration layers must reuse the same access-control model.

---

# 22. Technical Design Summary

DMIMS uses explicit resource scope, defense in depth, service-oriented business logic and strict platform/customer separation.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Technical Design Document |
| 1.1 | 24 August 2026 | Added explicit resource scopes, customer My Company composition and report authorization metadata |
