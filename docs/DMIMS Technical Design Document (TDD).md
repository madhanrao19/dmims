# **DMIMS Technical Design Document (TDD)**

**Datamation Inventory Management System**

Version 1.0

---

# **Document Purpose**

This document defines the technical implementation of the Datamation Inventory Management System (DMIMS).

It provides developers with the complete software design including:

* Application architecture  
* Directory structure  
* Models  
* Services  
* Middleware  
* Policies  
* Filament Resources  
* Database relationships  
* Coding standards  
* Development conventions

The objective is to ensure every developer builds the system consistently and according to best practices.

---

# **1\. Technical Stack**

| Layer | Technology |
| ----- | ----- |
| Backend | Laravel 12 |
| Admin Panel | Filament 4 |
| Language | PHP 8.3+ |
| Database | MySQL 8 / MariaDB |
| Authentication | Laravel \+ Filament |
| Permissions | Spatie Laravel Permission |
| Frontend | Blade \+ Tailwind CSS \+ Alpine.js |
| Build Tool | Vite |
| Queue | Database Queue |
| Cache | File Cache (Redis-ready) |
| Web Server | Nginx |
| Runtime | PHP-FPM |
| Operating System | Ubuntu 24.04 LTS |

---

# **2\. Project Directory Structure**

app/  
├── Actions/  
├── Console/  
├── Enums/  
├── Events/  
├── Exceptions/  
├── Filament/  
│   ├── Resources/  
│   ├── Pages/  
│   ├── Widgets/  
│   └── Clusters/  
├── Helpers/  
├── Http/  
│   ├── Controllers/  
│   ├── Middleware/  
│   ├── Requests/  
│   └── Resources/  
├── Jobs/  
├── Mail/  
├── Models/  
├── Notifications/  
├── Observers/  
├── Policies/  
├── Providers/  
├── Services/  
├── Traits/  
└── ViewModels/

---

# **3\. Laravel Design Rules**

## **Controllers**

Controllers should only:

* Validate requests  
* Call services  
* Return responses

Do not place business logic inside controllers.

---

## **Models**

Models contain:

* Relationships  
* Scopes  
* Accessors  
* Mutators  
* Casts

Avoid complex business rules.

---

## **Services**

All business logic belongs inside Services.

Examples:

* Product creation  
* Stock transfer  
* Barcode generation  
* Billing updates  
* Subscription validation

---

## **Middleware**

Middleware performs cross-cutting checks.

Examples:

* Authentication  
* Company context  
* Active subscription  
* Active license  
* Module enabled  
* Audit logging

---

## **Policies**

Every CRUD operation must be protected using Laravel Policies.

Never rely only on hidden buttons.

---

# **4\. Required Models**

## **Core Models**

Customer

User

Module

CustomerModule

SubscriptionPlan

CustomerSubscription

SubscriptionLog

License

LicenseLog

BillingRecord

BillingPayment

BillingLog

Setting

AuditLog

Notification

---

## **Inventory Models**

Category

Product

ProductLocationStock

StockMovement

StockAlert

Location

LocationType

---

## **Document Models**

Box

DocumentType

DocumentFile

DocumentMovementLog

---

## **Barcode Models**

BarcodeRegistry

BarcodeScanLog

---

# **5\. Required Services**

The following services must exist.

## **CompanyContextService**

Purpose

Determines the active company.

Responsibilities

* Resolve customer  
* Apply customer scope  
* Prevent cross-company access

---

## **AccessControlService**

Central security service.

Responsibilities

* Login validation  
* View permission  
* Export permission  
* Operational permission  
* Effective limits  
* Effective access mode

No module should duplicate these checks.

---

## **SubscriptionService**

Responsibilities

* Current subscription  
* Plan limits  
* Grace period  
* Enabled modules  
* Renewal

---

## **LicenseService**

Responsibilities

* Current license  
* Suspension  
* Revocation  
* Expiry  
* Technical access mode

---

## **BillingService**

Responsibilities

* Invoice creation  
* Outstanding balance  
* Manual billing  
* Billing status

---

## **PaymentService**

Responsibilities

* Payment recording  
* Partial payments  
* Balance calculation  
* Payment history

---

## **BarcodeService**

Responsibilities

* Barcode generation  
* Registration  
* Validation  
* Printing  
* Lookup

---

## **ScannerService**

Responsibilities

* Scan processing  
* Type detection  
* Permission validation  
* Action routing

---

## **StockMovementService**

Responsibilities

Receive-In

Transfer

Stock Out

Adjustment

Returns

Inventory validation

Transactions

Audit

---

## **DocumentMovementService**

Responsibilities

Receive

Transfer

Move-Out

Return

History

Transactions

Audit

---

## **LocationService**

Responsibilities

Manage shared locations.

Validate location hierarchy.

Prevent invalid parent relationships.

---

## **NotificationService**

Responsibilities

System notifications.

Customer notifications.

Email notifications (future).

---

## **AuditService**

Responsibilities

Write immutable audit records.

Centralize audit creation.

---

## **ImportService**

Responsibilities

CSV import.

Excel import.

Validation.

Error reporting.

---

## **ReportExportService**

Responsibilities

CSV

Excel

PDF

Print View

---

## **BackupService**

Responsibilities

Database backup.

Storage backup.

Restore support.

---

# **6\. Required Middleware**

EnsureAuthenticated

EnsureUserIsActive

EnsureCompanyAssigned

EnsureCompanyActive

EnsureSubscriptionValid

EnsureLicenseAllowsAccess

EnsureModuleEnabled

SetCompanyContext

LogUserActivity

All middleware should be registered using Laravel's middleware aliases.

---

# **7\. Filament Resources**

Every module should have a corresponding Filament Resource.

Examples

CustomerResource

UserResource

ModuleResource

SubscriptionPlanResource

CustomerSubscriptionResource

LicenseResource

BillingRecordResource

PaymentResource

CategoryResource

LocationResource

ProductResource

StockMovementResource

BoxResource

DocumentFileResource

BarcodeRegistryResource

AuditLogResource

NotificationResource

---

# **8\. Naming Standards**

## **Database Tables**

Plural snake\_case

Examples

products

document\_files

billing\_records

---

## **Models**

Singular PascalCase

Example

Product

DocumentFile

BillingRecord

---

## **Services**

Suffix with Service

ProductService

BarcodeService

AuditService

---

## **Controllers**

Suffix with Controller

---

## **Policies**

Suffix with Policy

---

## **Requests**

Suffix with Request

---

## **Jobs**

Suffix with Job

---

# **9\. Database Transactions**

Mandatory for:

Stock Receive-In

Stock Transfer

Stock Out

Stock Adjustment

File Transfer

Box Transfer

Subscription Renewal

License Renewal

Billing Update

Barcode Registration

If any operation fails, the transaction must roll back completely.

---

# **10\. Error Handling**

All services should:

Throw typed exceptions.

Avoid returning false.

Log unexpected errors.

Provide user-friendly messages.

Never expose stack traces in production.

---

# **11\. Logging**

Log:

System errors

Failed logins

Queue failures

Import failures

Export failures

Unhandled exceptions

Use Laravel logging channels.

---

# **12\. Validation Standards**

Always validate:

Required fields

Maximum length

Unique constraints

Foreign keys

Dates

Numeric ranges

File uploads

Barcode format

Never trust frontend validation alone.

---

# **13\. Database Relationships**

Examples

Customer

hasMany Users

hasMany Products

hasMany Boxes

hasMany Files

hasMany BillingRecords

hasMany Licenses

hasMany Subscriptions

---

Product

belongsTo Category

belongsTo Customer

belongsTo Default Location

hasMany StockMovements

---

Location

belongsTo Customer

belongsTo Parent Location

hasMany Child Locations

hasMany Product Stocks

hasMany Boxes

---

Box

belongsTo Customer

belongsTo Current Location

hasMany Document Files

hasMany Movement Logs

---

DocumentFile

belongsTo Customer

belongsTo Current Box

belongsTo Document Type

hasMany Movement Logs

---

# **14\. Queue Jobs (Future)**

Email notifications

Large imports

Large exports

Scheduled reports

Barcode generation

Backup

Cleanup

AI processing

---

# **15\. Performance Guidelines**

Always:

Use eager loading.

Paginate tables.

Index frequently queried columns.

Cache static reference data.

Avoid N+1 queries.

Use database transactions efficiently.

Keep queries simple.

---

# **16\. Security Guidelines**

Never expose:

.env

APP\_KEY

Database credentials

API secrets

Use:

CSRF protection

Rate limiting

Policies

Middleware

Input validation

Parameterized queries

Secure file uploads

HTTPS in production

---

# **17\. Testing Requirements**

Each module should include:

Feature Tests

Unit Tests

Policy Tests

Permission Tests

Validation Tests

Transaction Tests

Regression Tests

Target minimum coverage:

80%

---

# **18\. Code Quality**

Required tools

Laravel Pint

PHPStan

PHP CS Fixer (optional)

Composer Audit

Static analysis should pass before merging.

---

# **19\. Development Workflow**

Developer creates feature branch.

↓

Implement feature.

↓

Run tests.

↓

Run Pint.

↓

Review code.

↓

Submit Pull Request.

↓

Code Review.

↓

Merge into main.

---

# **20\. Definition of Done**

A feature is considered complete only when:

✓ Business requirements implemented

✓ Customer isolation enforced

✓ Policies implemented

✓ Validation completed

✓ Audit logging added

✓ Transactions added

✓ Tests written

✓ UI completed

✓ Documentation updated

✓ Code reviewed

✓ No critical warnings

---

# **21\. Future Technical Enhancements**

The design supports future additions without significant restructuring:

* REST API  
* GraphQL API  
* Mobile application  
* Offline synchronization  
* RFID support  
* QR code support  
* OCR integration  
* AI document classification  
* AI inventory forecasting  
* Event-driven architecture  
* Redis cache  
* Elasticsearch  
* Multi-language  
* Multi-currency  
* SSO (Azure AD, Google)  
* Webhooks  
* Public API for customers

---

# **22\. Technical Design Summary**

The DMIMS architecture follows these engineering principles:

* Service-oriented business logic  
* Multi-tenant architecture  
* Security by default  
* Immutable audit history  
* Thin controllers  
* Reusable services  
* Database transaction integrity  
* Policy-based authorization  
* Modular feature design  
* Future-ready scalability

This document serves as the primary implementation reference for all DMIMS developers.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Technical Design Document |

