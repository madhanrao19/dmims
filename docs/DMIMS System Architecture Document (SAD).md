# **DMIMS System Architecture Document (SAD)**

**Datamation Inventory Management System**

Version 1.0

---

# **1\. Purpose**

This document describes the complete architecture of the Datamation Inventory Management System (DMIMS).

It explains how every module fits together, how data flows through the system, and the architectural principles developers must follow.

This document complements the Developer Blueprint by explaining **how the system is built**, rather than **what features it contains**.

---

# **2\. Architecture Overview**

DMIMS is a multi-tenant Laravel web application built using a layered architecture.

Users

↓

Browser

↓

Laravel Routes

↓

Middleware

↓

Controllers / Filament Resources

↓

Services

↓

Models

↓

Database

Every request follows this same flow.

Business logic must never bypass the Service layer.

---

# **3\. High-Level System Architecture**

                   DMIMS Platform

                  \+------------------+  
                  |  Browser / PWA   |  
                  \+---------+--------+  
                            |  
                    HTTPS / Cloudflare  
                            |  
                  \+---------v--------+  
                  |      Nginx       |  
                  \+---------+--------+  
                            |  
                     PHP-FPM / Laravel  
                            |  
         \+------------------+------------------+  
         |                                     |  
    Filament Admin                     Public Web  
         |                                     |  
         \+------------------+------------------+  
                            |  
                      Application Layer  
                            |  
     \+------------------------------------------------------+  
     |                  Service Layer                       |  
     \+------------------------------------------------------+  
     | Company Context Service                             |  
     | Access Control Service                              |  
     | Subscription Service                               |  
     | License Service                                    |  
     | Billing Service                                    |  
     | Payment Service                                    |  
     | Barcode Service                                    |  
     | Scanner Service                                    |  
     | Stock Movement Service                             |  
     | Document Movement Service                          |  
     | Notification Service                               |  
     | Audit Service                                      |  
     | Report Export Service                              |  
     \+------------------------------------------------------+  
                            |  
                     Eloquent Models  
                            |  
                     MySQL / MariaDB

---

# **4\. Technology Architecture**

Backend

Laravel 12

Admin Framework

Filament 4

Programming Language

PHP 8.3+

Database

MySQL 8 / MariaDB

Frontend

Blade

Tailwind CSS

Alpine.js

Build Tool

Vite

Authentication

Laravel Authentication

Filament Authentication

Spatie Permission

Deployment

Ubuntu 24.04 LTS

Nginx

PHP-FPM

Supervisor

Cron

Cloudflare Tunnel

---

# **5\. Multi-Tenant Architecture**

DMIMS is a shared application serving multiple customer companies.

Every customer owns only their own data.

DMIMS

├── Customer A  
│      Products  
│      Boxes  
│      Files  
│      Billing  
│  
├── Customer B  
│      Products  
│      Boxes  
│      Files  
│  
├── Customer C  
│      Products  
│      Boxes  
│      Files  
│  
└── Datamation Platform

Every customer-owned table includes:

customer\_id

The authenticated user's customer\_id determines what records may be accessed.

---

# **6\. Request Lifecycle**

Every HTTP request follows this sequence.

Browser

↓

Route

↓

Authentication

↓

Authorization

↓

Company Context

↓

Subscription Check

↓

License Check

↓

Module Check

↓

Permission Check

↓

Business Service

↓

Database

↓

Audit Log

↓

Response

No business action should bypass these checks.

---

# **7\. Layered Architecture**

## **Presentation Layer**

Responsibilities

* Filament Resources  
* Forms  
* Tables  
* Widgets  
* Pages  
* Blade Views

Must not contain business logic.

---

## **Controller Layer**

Responsibilities

* Receive request  
* Validate request  
* Call Service  
* Return response

Controllers should remain thin.

---

## **Service Layer**

Contains all business rules.

Examples

AccessControlService

SubscriptionService

LicenseService

BillingService

BarcodeService

MovementService

NotificationService

AuditService

Services may call other services but should avoid circular dependencies.

---

## **Model Layer**

Represents database tables.

Models should contain:

* Relationships  
* Accessors  
* Mutators  
* Scopes

Heavy business logic belongs in Services.

---

## **Database Layer**

Stores all persistent data.

Responsibilities

* Constraints  
* Foreign keys  
* Indexes  
* Transactions  
* Soft deletes  
* Immutable history tables

---

# **8\. Core Services**

## **CompanyContextService**

Determines the active customer.

Provides customer isolation.

---

## **AccessControlService**

Central authorization engine.

Checks:

* User Status  
* Company Status  
* Subscription  
* License  
* Modules  
* Permissions  
* Limits

No module should implement these checks independently.

---

## **SubscriptionService**

Responsible for

* Subscription lookup  
* Plan limits  
* Enabled modules  
* Grace periods  
* Renewal

---

## **LicenseService**

Responsible for

* Technical access  
* Suspension  
* Revocation  
* Expiry  
* Access mode

---

## **BillingService**

Handles

Invoices

Outstanding balances

Manual billing

Payment tracking

---

## **StockMovementService**

Handles

Receive-In

Stock Out

Transfer

Adjustment

Transactions

Audit

---

## **DocumentMovementService**

Handles

File movement

Box movement

External transfers

Returns

Movement history

---

## **BarcodeService**

Handles

Barcode generation

Registration

Printing

Lookup

Validation

---

## **NotificationService**

Creates notifications.

Routes notifications to appropriate users.

---

## **AuditService**

Logs all important actions.

No module writes directly into audit\_logs.

---

# **9\. Security Architecture**

Every request must pass through:

Authentication

↓

Authorization

↓

Customer Isolation

↓

Subscription Validation

↓

License Validation

↓

Module Validation

↓

Permission Validation

↓

Business Logic

↓

Audit Logging

---

# **10\. Customer Isolation**

Isolation is enforced at multiple layers.

Database

↓

Model

↓

Policy

↓

Middleware

↓

Service

↓

UI

Even if one layer fails, another layer must prevent unauthorized access.

---

# **11\. Module Architecture**

Modules are independently enabled.

Examples

Stock Inventory

Document Tracking

Barcode

Reports

Import/Export

Audit

Billing

When disabled

* Menu hidden  
* Route blocked  
* Direct URL blocked  
* Service blocked

---

# **12\. Movement Architecture**

Three movement models exist.

Internal Transfer

DMIMS

↓

DMIMS

External Receive-In

External

↓

DMIMS

External Move-Out

DMIMS

↓

External

No fake locations should ever be created.

---

# **13\. Barcode Architecture**

Barcode

↓

Barcode Registry

↓

Determine Type

↓

Verify Customer

↓

Verify Permission

↓

Execute Action

↓

Log Scan

Supported types

Product

Location

Box

Document File

---

# **14\. Audit Architecture**

Every significant business action generates an immutable audit record.

Examples

Login

Logout

Create

Update

Delete

Transfer

Payment

Subscription

License

Import

Export

Backup

Restore

Audit records must never be modified.

---

# **15\. Database Transactions**

Database transactions are mandatory for

Stock movements

Document movements

Billing updates

Subscription renewals

License renewals

Barcode registration

Any process involving multiple table updates

---

# **16\. Background Processing**

Future queue jobs

Notification delivery

Large imports

Large exports

Scheduled reports

Backup

Cleanup

Email sending

---

# **17\. Progressive Web App Architecture**

Components

Manifest

Service Worker

Offline Page

Responsive Layout

Install Prompt

Current Version

Online-first architecture.

Future versions may support offline synchronization.

---

# **18\. Production Architecture**

Internet

↓

Cloudflare

↓

Cloudflare Tunnel

↓

Ubuntu Server

↓

Nginx

↓

PHP-FPM

↓

Laravel

↓

MySQL

↓

Storage

↓

Backups

---

# **19\. Design Principles**

The project follows these principles.

Single Responsibility Principle

Separation of Concerns

Service-Oriented Business Logic

Multi-Tenant by Design

Security by Default

Audit Everything

Immutable History

Least Privilege Access

Fail Securely

Scalable Architecture

---

# **20\. Future Architecture**

The architecture has been designed to support future enhancements without major redesign.

Potential additions include:

* REST API  
* GraphQL API  
* Mobile applications  
* Offline synchronization  
* RFID integration  
* QR code support  
* OCR document indexing  
* AI-powered document classification  
* AI inventory forecasting  
* Microsoft 365 integration  
* SAP integration  
* Power BI integration  
* Webhooks  
* Event-driven architecture  
* Redis caching  
* Horizontal scaling  
* Multi-language support  
* Multi-currency support

---

# **21\. Architecture Principles Summary**

Developers should always remember:

* Never trust browser-submitted customer\_id.  
* Keep controllers thin.  
* Put business rules in Services.  
* Use database transactions for multi-table updates.  
* Audit all critical actions.  
* Enforce customer isolation at every layer.  
* Treat movement logs as immutable.  
* Build reusable components instead of duplicating logic.  
* Design for future scalability.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial System Architecture Document |

