# **DMIMS Developer Handover & Onboarding Guide**

## **Datamation Inventory Management System (DMIMS)**

**Version:** 1.0

---

# **Document Purpose**

This guide enables a new developer to become productive on the DMIMS project as quickly as possible.

It explains:

* How the project is organised  
* How to set up the development environment  
* How to understand the codebase  
* Development workflow  
* Common pitfalls  
* Coding expectations  
* First development tasks

A developer should be able to contribute confidently after completing this guide.

---

# **1\. Project Overview**

DMIMS (Datamation Inventory Management System) is a multi-tenant enterprise inventory and document tracking platform.

The application is designed around these principles:

* Multi-company architecture  
* Customer data isolation  
* Service-oriented business logic  
* Immutable audit history  
* Subscription management  
* License management  
* Barcode-driven workflows  
* Progressive Web App (PWA)

---

# **2\. Technology Stack**

| Layer | Technology |
| ----- | ----- |
| Backend | Laravel 12 |
| Admin Panel | Filament 4 |
| Language | PHP 8.3+ |
| Database | MariaDB / MySQL |
| Frontend | Blade \+ Tailwind \+ Alpine.js |
| Build Tool | Vite |
| Queue | Laravel Database Queue |
| Deployment | Ubuntu 24.04 \+ Nginx |

---

# **3\. Documents You Must Read**

Read these documents in order:

1. Developer Getting Started Guide  
2. Developer Blueprint  
3. System Architecture Document  
4. Technical Design Document  
5. Business Rules Specification  
6. Security & Access Control Matrix  
7. Database Dictionary  
8. API & Integration Specification  
9. Development Standards & Coding Guidelines

Do not begin feature development until these documents are understood.

---

# **4\. Repository Structure**

app/  
config/  
database/  
public/  
resources/  
routes/  
storage/  
tests/  
vendor/

Important folders:

app/Models  
app/Services  
app/Filament  
app/Http  
app/Policies  
database/migrations  
database/seeders  
tests

---

# **5\. Important Services**

Before changing anything, understand these services:

AccessControlService

SubscriptionService

LicenseService

StockMovementService

DocumentMovementService

BarcodeService

AuditService

NotificationService

These services contain the business logic.

---

# **6\. Core Business Concepts**

Every developer must understand the following concepts before writing code.

## **Customer**

A customer is a company using DMIMS.

Every customer owns its own data.

---

## **Customer Isolation**

Every customer-owned record includes:

customer\_id

Never trust customer\_id from the browser.

Always derive it from the authenticated user.

---

## **Subscription**

Commercial entitlement.

Controls:

* Plan  
* Modules  
* Limits  
* Billing cycle

---

## **License**

Technical access.

Controls:

* Full access  
* View only  
* Blocked

---

## **AccessControlService**

Combines:

User

↓

Company

↓

Subscription

↓

License

↓

Module

↓

Permission

↓

Limits

Never duplicate this logic elsewhere.

---

# **7\. Development Workflow**

Every task should follow this sequence.

Understand requirement

↓

Review related documentation

↓

Review existing implementation

↓

Design solution

↓

Implement

↓

Test

↓

Update documentation

↓

Create Pull Request

---

# **8\. Before Writing Code**

Ask yourself:

Does this feature already exist?

Can I reuse an existing service?

Does it affect customer isolation?

Does it require audit logging?

Does it require database transactions?

Does it require permissions?

Does it require tests?

---

# **9\. Coding Workflow**

Step 1

Create feature branch.

Step 2

Implement smallest working solution.

Step 3

Reuse existing architecture.

Step 4

Avoid duplication.

Step 5

Write tests.

Step 6

Run formatter.

Step 7

Submit Pull Request.

---

# **10\. Development Checklist**

Before committing:

✓ Builds successfully

✓ No PHP warnings

✓ No JavaScript errors

✓ Tests pass

✓ Migrations succeed

✓ Seeder succeeds

✓ Documentation updated

✓ Audit logging implemented

✓ Transactions implemented

---

# **11\. Common Pitfalls**

Never:

* Trust customer\_id from requests.  
* Bypass AccessControlService.  
* Put business logic in controllers.  
* Update movement history.  
* Delete audit logs.  
* Duplicate business rules.

Always:

* Reuse Services.  
* Use Form Requests.  
* Use Policies.  
* Use database transactions.  
* Write audit records.

---

# **12\. First Week Plan**

## **Day 1**

* Clone repository.  
* Install dependencies.  
* Configure environment.  
* Run migrations.  
* Run seeders.  
* Log in to the application.

---

## **Day 2**

Read:

* Blueprint  
* Architecture  
* Database Dictionary

Explore:

* Filament Resources  
* Models  
* Services

---

## **Day 3**

Debug existing workflows.

Understand:

* Customer isolation  
* Inventory workflow  
* Document workflow

---

## **Day 4**

Implement a small bug fix under supervision.

Write tests.

Submit first Pull Request.

---

## **Day 5**

Review feedback.

Learn coding standards.

Understand deployment process.

---

# **13\. First Month Plan**

Week 1

Understand architecture.

Week 2

Complete first feature.

Week 3

Participate in code reviews.

Week 4

Own a small module enhancement independently.

---

# **14\. Debugging Tips**

Useful Artisan commands:

php artisan optimize:clear  
php artisan migrate:fresh \--seed  
php artisan test  
php artisan queue:work  
php artisan schedule:run

Useful logs:

* Laravel log  
* Nginx log  
* PHP-FPM log  
* Queue worker log

---

# **15\. Testing Expectations**

Every feature should include:

* Validation testing  
* Authorization testing  
* Customer isolation testing  
* Business rule testing  
* Regression testing

Do not rely solely on manual testing.

---

# **16\. Pull Request Expectations**

Every Pull Request should include:

* Summary  
* Screenshots (if UI changes)  
* Database changes  
* Testing performed  
* Risks  
* Documentation updates

Keep Pull Requests focused and reasonably small.

---

# **17\. Definition of Done**

A task is complete only when:

* Requirements implemented.  
* Security verified.  
* Customer isolation verified.  
* Tests passing.  
* Documentation updated.  
* Code reviewed.  
* Approved for merge.

---

# **18\. Frequently Asked Questions**

### **Where does business logic belong?**

Services.

---

### **Can I use customer\_id from a request?**

No.

Always derive it from the authenticated user.

---

### **Can I edit audit logs?**

Never.

---

### **Can movement history be changed?**

Never.

Use correction records.

---

### **Why are subscriptions and licenses separate?**

Subscription \= commercial entitlement.

License \= technical access.

---

# **19\. Useful Resources**

Internal documentation:

* Developer Blueprint  
* Technical Design Document  
* Database Dictionary  
* Security Matrix  
* API Specification  
* UI/UX Specification  
* Operations Guide

External documentation:

* Laravel  
* Filament  
* PHP  
* Tailwind CSS  
* MariaDB

---

# **20\. Success Criteria**

A developer is considered fully onboarded when they can:

* Explain the system architecture.  
* Understand customer isolation.  
* Navigate the codebase confidently.  
* Implement a feature following project standards.  
* Write tests.  
* Submit production-ready Pull Requests with minimal supervision.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Developer Handover & Onboarding Guide |

