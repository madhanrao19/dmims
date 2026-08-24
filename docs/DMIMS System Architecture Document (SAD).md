# DMIMS System Architecture Document (SAD)

**Datamation Inventory Management System**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# 1. Purpose

This document describes the architecture of DMIMS and the rules developers must follow.

---

# 2. Architecture Overview

DMIMS is a multi-tenant Laravel application.

Browser / PWA  
↓  
Laravel / Filament  
↓  
Authentication  
↓  
Authorization  
↓  
Customer / Platform Context  
↓  
Resource Scope  
↓  
Subscription / License / Module / Permission  
↓  
Service Layer  
↓  
Eloquent Models  
↓  
MariaDB  
↓  
Audit

Business actions must not bypass this flow.

---

# 3. High-Level System Architecture

DMIMS contains two presentation boundaries:

## Platform Boundary

For authorized Datamation roles.

Includes:

- Platform customer administration
- Platform users
- Roles/permissions
- Module catalogue
- Subscription Plans
- License management
- Billing administration
- Platform reports
- Platform audit
- Backup/restore
- Settings

## Customer Boundary

For customer roles.

Includes only:

- Own company presentation
- Enabled operational modules
- Role-permitted reports
- Role-permitted billing/audit summaries
- Own customer data

---

# 4. Technology Architecture

- Laravel 13
- Filament 5
- PHP 8.4+
- MariaDB
- Blade / Tailwind / Alpine
- Vite
- Spatie Permission
- Ubuntu 24.04
- Apache / PHP-FPM
- Supervisor
- Cloudflare Tunnel

---

# 5. Multi-Tenant Architecture

Every customer-owned table includes `customer_id`.

The authenticated user's customer context determines customer-owned records.

Never trust request-provided `customer_id`.

## 5.1 Tenant Scope Architecture

DMIMS uses three explicit scope classifications.

### PLATFORM_ONLY

Accessible only to authorized platform roles.

### TENANT_STRICT

For customer users:

```text
customer_id = authenticated user's customer_id
```

No implicit `OR customer_id IS NULL`.

This is the default for customer-owned data.

### TENANT_WITH_GLOBAL_DEFAULTS

Only for explicitly approved shared reference data:

```text
customer_id = authenticated user's customer_id
OR customer_id IS NULL
```

This is opt-in.

## Architectural Rule

A generic reusable tenant scope must never automatically append `OR customer_id IS NULL` for every customer resource.

Global/default records must be deliberately declared.

---

# 6. Request Lifecycle

Browser  
↓  
Route  
↓  
Authentication  
↓  
Role / Permission Validation  
↓  
Platform / Customer Context  
↓  
Resource Scope Validation  
↓  
Company Status  
↓  
Subscription  
↓  
License  
↓  
Module  
↓  
Business Permission  
↓  
Service  
↓  
Database  
↓  
Audit  
↓  
Response

---

# 7. Layered Architecture

## Presentation Layer

Filament Resources, Pages, Widgets, Forms and Tables.

Presentation components do not define security on their own.

## Service Layer

Business rules and cross-module operations.

## Model Layer

Relationships, casts and scopes.

## Database Layer

Constraints, indexes, transactions, soft deletes and immutable history.

---

# 8. Core Services

Core security/business services include:

- AccessControlService
- ModuleAccessService
- LicenseService
- BillingService
- PaymentService
- BarcodeService
- ScannerService
- StockMovementService
- DocumentMovementService
- NotificationService
- AuditService
- ReportExportService
- ImportService
- BackupService

No module should duplicate the access-control decision independently.

---

# 9. Security Architecture

Every request must pass appropriate layers.

Authorization applies before data reaches presentation.

## Customer Presentation Boundary

The same access model controls:

Navigation  
↓  
Dashboard  
↓  
Global Search  
↓  
Resource Queries  
↓  
Relations  
↓  
Actions  
↓  
Reports / Exports  
↓  
API  
↓  
Jobs

The absence of a menu item is not sufficient protection.

---

# 10. Customer Isolation

Isolation is enforced at:

- Query/model layer
- Filament resource authorization
- Middleware
- Service layer
- Relationship/select queries
- Report/export layer
- API
- UI

TENANT_STRICT is the default for customer-owned data.

---

# 11. Module Architecture

Disabled modules are:

- Hidden
- Route blocked
- Direct URL blocked
- Service blocked
- Report blocked

## 11.1 Customer Administration Architecture

Customer-facing company administration uses a consolidated:

**My Company**

interface.

Possible panels:

- Profile
- Users
- Enabled Modules
- Subscription
- License Status
- Billing
- Audit

This is a presentation composition over existing authoritative resources/services.

Do not duplicate business data or business logic.

Platform resources remain separate for authorized Datamation users.

---

# 12. Movement Architecture

Three models:

- Internal Transfer
- External Receive-In
- External Move-Out

External locations are not fake DMIMS locations.

---

# 13. Barcode Architecture

Barcode  
↓  
Registry  
↓  
Type  
↓  
Customer Validation  
↓  
Module/Permission Validation  
↓  
Allowed Action  
↓  
Scan Log

---

# 14. Audit Architecture

Audit records are immutable.

Platform audit events and customer audit events are distinct by customer ownership.

For customer audit access:

```text
audit_logs.customer_id = authenticated user's customer_id
```

Platform `NULL` audit rows are excluded.

---

# 15. Database Transactions

Mandatory for multi-table operations and all critical movements, billing, renewal and barcode registration operations.

---

# 16. Background Processing

Future/background jobs must preserve authenticated/derived tenant context and must never accept arbitrary customer ownership from untrusted payloads.

---

# 17. PWA Architecture

Online-first.

Role/module navigation remains identical in security semantics across browser and installed PWA.

---

# 18. Production Architecture

Internet  
↓  
Cloudflare  
↓  
Cloudflare Tunnel  
↓  
Ubuntu  
↓  
Apache / PHP-FPM  
↓  
Laravel  
↓  
MariaDB  
↓  
Storage / Backup

---

# 19. Design Principles

- Single Responsibility
- Separation of Concerns
- Multi-Tenant by Design
- Security by Default
- Least Privilege
- Fail Closed
- Audit Everything
- Immutable History
- Reuse existing architecture

---

# 20. Future Architecture

Future APIs, mobile apps, reporting integrations, Power BI, AI, webhooks and external services must inherit the same `PLATFORM_ONLY`, `TENANT_STRICT` and `TENANT_WITH_GLOBAL_DEFAULTS` model.

---

# 21. Architecture Principles Summary

Never trust browser customer_id.

Use exact tenant scope for customer-owned records.

Do not expose platform resources to customers.

Never rely only on hidden navigation.

Authorize reports by underlying module and permission.

Keep documentation synchronized.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial System Architecture Document |
| 1.1 | 24 August 2026 | Added explicit resource scope architecture and My Company presentation boundary |
