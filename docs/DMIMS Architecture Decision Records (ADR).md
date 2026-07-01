# **DMIMS Architecture Decision Records (ADR)**

## **Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document records the major architectural and engineering decisions made during the design and development of DMIMS.

Its purpose is to explain **why** decisions were made, what alternatives were considered, and what consequences those decisions have.

This document should evolve throughout the lifetime of the project.

Whenever a significant architectural decision is made, a new ADR should be added.

---

# **ADR Format**

Each Architecture Decision Record follows this template.

## **ADR Number**

Unique identifier

Example

ADR-001

---

## **Title**

Short description

---

## **Status**

One of

* Proposed  
* Accepted  
* Deprecated  
* Superseded

---

## **Date**

Decision date

---

## **Context**

What problem existed?

---

## **Decision**

What was chosen?

---

## **Alternatives Considered**

What other options were evaluated?

---

## **Consequences**

Positive

Negative

Trade-offs

---

# **ADR-001**

## **Use Laravel as the Primary Framework**

Status

Accepted

---

### **Context**

DMIMS requires:

* Long-term maintainability  
* Large developer ecosystem  
* Enterprise authentication  
* Strong ORM  
* Mature package ecosystem

---

### **Decision**

Laravel is selected as the primary backend framework.

---

### **Alternatives Considered**

Symfony

ASP.NET Core

NestJS

Django

Spring Boot

---

### **Reasons**

Large ecosystem

Excellent documentation

Strong community

Long-term support

Filament compatibility

Rapid development

---

### **Consequences**

Positive

Fast development.

Large hiring pool.

Excellent package support.

Negative

Higher memory usage than micro-frameworks.

---

# **ADR-002**

## **Use Filament as the Administration Framework**

Status

Accepted

---

### **Context**

DMIMS is primarily an internal business application.

Rapid CRUD development is required.

---

### **Decision**

Filament will be used for all administration pages.

---

### **Alternatives**

Nova

Backpack

Voyager

Custom Blade

---

### **Reasons**

Modern

Fast

Laravel-native

Excellent tables and forms

Strong ecosystem

---

### **Consequences**

Rapid feature development.

Less frontend code.

---

# **ADR-003**

## **Multi-Tenant Architecture Using customer\_id**

Status

Accepted

---

### **Context**

Multiple customer companies share the same application.

Data isolation is mandatory.

---

### **Decision**

Every customer-owned table includes:

customer\_id

---

### **Alternatives**

Separate database per customer

Separate schema

Hybrid multi-tenancy

---

### **Reasons**

Simpler deployment

Lower operational cost

Shared reporting

Simpler upgrades

---

### **Consequences**

Positive

Single deployment.

Central management.

Negative

Developers must always enforce customer isolation.

---

# **ADR-004**

## **Never Trust customer\_id from Client**

Status

Accepted

---

### **Context**

Users could manipulate requests.

---

### **Decision**

customer\_id is always derived from the authenticated user.

---

### **Consequences**

Prevents cross-company access.

Reduces security risks.

---

# **ADR-005**

## **Separate Subscription and License**

Status

Accepted

---

### **Context**

Commercial entitlement and technical access are different concepts.

---

### **Decision**

Subscription

Commercial contract

License

Technical access

---

### **Alternatives**

Single combined table

---

### **Reasons**

Greater flexibility.

Supports payment disputes.

Supports legal suspension.

Supports technical overrides.

---

### **Consequences**

Slightly more complex implementation.

Much greater operational flexibility.

---

# **ADR-006**

## **Service-Oriented Business Logic**

Status

Accepted

---

### **Context**

Controllers become difficult to maintain when business rules grow.

---

### **Decision**

Business rules belong in Services.

Controllers remain thin.

---

### **Alternatives**

Fat Controllers

Fat Models

---

### **Consequences**

Better testing.

Reusable logic.

Cleaner architecture.

---

# **ADR-007**

## **Immutable Movement History**

Status

Accepted

---

### **Context**

Inventory history must remain trustworthy.

---

### **Decision**

Movement records are never updated or deleted.

Corrections generate new records.

---

### **Alternatives**

Update movement history

Delete incorrect records

---

### **Reasons**

Auditability

Compliance

Traceability

---

### **Consequences**

Complete historical integrity.

---

# **ADR-008**

## **Shared Location Model**

Status

Accepted

---

### **Context**

Inventory and document tracking both use physical locations.

---

### **Decision**

Single locations table.

Products occupy locations.

Boxes occupy locations.

Files occupy boxes.

---

### **Alternatives**

Stock locations

Document locations

---

### **Consequences**

Cleaner database.

Less duplicated logic.

---

# **ADR-009**

## **Manual Billing in Version 1**

Status

Accepted

---

### **Context**

Customers pay outside the application.

Online payments are unnecessary.

---

### **Decision**

Manual billing only.

Manual payment confirmation.

---

### **Alternatives**

Stripe

PayPal

Bank APIs

---

### **Consequences**

Simpler implementation.

Can integrate online payments later.

---

# **ADR-010**

## **Centralized AccessControlService**

Status

Accepted

---

### **Context**

Permission logic becomes duplicated across modules.

---

### **Decision**

Single AccessControlService.

---

### **Reasons**

Consistency.

Maintainability.

Reduced duplication.

---

### **Consequences**

One place to maintain authorization logic.

---

# **ADR-011**

## **Audit-First Design**

Status

Accepted

---

### **Context**

Business-critical systems require accountability.

---

### **Decision**

Every critical action generates an audit record.

---

### **Consequences**

Improved traceability.

Supports investigations.

---

# **ADR-012**

## **Progressive Web App Instead of Native Mobile App**

Status

Accepted

---

### **Context**

Customers need mobile access primarily for barcode scanning.

---

### **Decision**

Build a responsive PWA first.

Native apps remain a future enhancement.

---

### **Alternatives**

Android app

iOS app

Flutter

React Native

---

### **Consequences**

Lower development cost.

Single codebase.

Faster deployment.

---

# **ADR-013**

## **API-Ready Architecture**

Status

Accepted

---

### **Context**

Future integrations are expected.

---

### **Decision**

Keep business logic independent of UI.

Expose functionality through Services.

---

### **Consequences**

Future REST and GraphQL APIs can reuse existing business logic.

---

# **ADR-014**

## **Database Transactions for Multi-Step Operations**

Status

Accepted

---

### **Context**

Inventory and document operations update multiple tables.

---

### **Decision**

All multi-table operations execute inside database transactions.

---

### **Consequences**

Data integrity.

Automatic rollback on failure.

---

# **ADR-015**

## **Soft Deletes for Master Data**

Status

Accepted

---

### **Context**

Business records should remain recoverable.

---

### **Decision**

Master data uses soft deletes.

History tables remain immutable.

---

### **Consequences**

Recovery is possible.

Historical relationships remain valid.

---

# **ADR-016**

## **Database Queue Before Redis**

Status

Accepted

---

### **Context**

Version 1 aims to minimise infrastructure complexity.

---

### **Decision**

Use Laravel Database Queue initially.

Redis can be introduced later without changing business logic.

---

### **Consequences**

Simpler deployment.

Easy future migration.

---

# **ADR-017**

## **File Cache Before Redis**

Status

Accepted

---

### **Context**

Current workload does not justify Redis.

---

### **Decision**

Use file cache.

Prepare interfaces for future Redis support.

---

### **Consequences**

Lower operational complexity.

---

# **ADR-018**

## **Cloudflare Tunnel for Secure Publishing**

Status

Accepted

---

### **Context**

The production environment should avoid exposing inbound ports directly where possible.

---

### **Decision**

Publish the application through Cloudflare Tunnel.

---

### **Alternatives**

Direct public IP

Traditional reverse proxy

VPN-only access

---

### **Consequences**

Improved security.

Simplified TLS management.

---

# **ADR-019**

## **AI-Assisted Development is Supported**

Status

Accepted

---

### **Context**

Modern development increasingly uses AI coding assistants.

---

### **Decision**

AI-generated code is permitted.

Every change must still comply with:

* Coding standards  
* Security standards  
* Testing requirements  
* Documentation requirements  
* Human review

---

### **Consequences**

Higher productivity while maintaining engineering quality.

---

# **ADR-020**

## **Documentation is a Deliverable**

Status

Accepted

---

### **Context**

Knowledge should not exist only in developers' heads.

---

### **Decision**

Every significant architectural or functional change must include updates to the relevant documentation.

Documentation is part of the Definition of Done.

---

### **Consequences**

Improved onboarding.

Reduced knowledge loss.

Simpler maintenance.

---

# **Future ADRs**

Future decisions should be recorded for topics such as:

* Redis adoption  
* Elasticsearch integration  
* Object storage (S3-compatible)  
* Multi-language support  
* Multi-currency support  
* Event-driven architecture  
* WebSocket notifications  
* OCR integration  
* RFID support  
* AI document classification  
* AI inventory forecasting  
* High Availability deployment  
* Blue/Green deployment  
* Kubernetes adoption  
* Public REST API  
* GraphQL support

---

# **Decision Review Process**

Every new ADR should:

1. Define the problem.  
2. Evaluate alternatives.  
3. Explain the chosen solution.  
4. Record trade-offs.  
5. Be reviewed by the technical lead.  
6. Be version-controlled with the source code.

Deprecated or superseded ADRs should remain in the document to preserve historical context.

---

# **Summary**

Architecture Decision Records provide the historical reasoning behind DMIMS.

They prevent accidental reversal of deliberate design choices, improve onboarding, support future maintenance, and help ensure architectural consistency as the system evolves.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Architecture Decision Records |

