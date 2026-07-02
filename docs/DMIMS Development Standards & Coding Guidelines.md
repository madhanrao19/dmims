# **DMIMS Development Standards & Coding Guidelines**

**Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document defines the mandatory development standards for all contributors to the DMIMS project.

It establishes a consistent coding style, architecture, naming convention, and engineering practices to ensure the system remains maintainable, scalable, secure, and production-ready.

These standards apply to:

* Human developers  
* AI coding assistants (Codex, Claude Code, GitHub Copilot, ChatGPT, etc.)  
* Internal and external contractors  
* Future maintainers

---

# **1\. Development Philosophy**

Every line of code should aim to be:

* Simple  
* Readable  
* Maintainable  
* Testable  
* Secure  
* Reusable  
* Scalable

Code is read more often than it is written.

Optimise for readability over cleverness.

---

# **2\. Architecture Principles**

DMIMS follows:

* Layered Architecture  
* Service-Oriented Business Logic  
* Single Responsibility Principle (SRP)  
* Separation of Concerns  
* Dependency Injection  
* Convention over Configuration

Controllers coordinate requests.

Services contain business rules.

Models represent data.

Policies authorize actions.

Middleware performs cross-cutting checks.

---

# **3\. PHP Standards**

Required

* PHP 8.4+  
* PSR-12 coding style  
* Strict typing where practical  
* Constructor property promotion  
* Typed properties  
* Return type declarations

Example

public function findByBarcode(string $barcode): ?Product  
{  
    return Product::where('barcode', $barcode)-\>first();  
}

Avoid mixed types unless necessary.

---

# **4\. Laravel Standards**

Use:

* Form Requests for validation  
* Policies for authorization  
* Service classes for business logic  
* Eloquent relationships  
* Dependency Injection  
* Route model binding  
* Database transactions

Avoid:

* Raw SQL unless justified  
* Business logic in controllers  
* Business logic in Blade templates  
* Duplicated validation rules

---

# **5\. Controller Standards**

Controllers should:

* Receive requests  
* Validate input  
* Call services  
* Return responses

Controllers must not:

* Perform complex calculations  
* Write business rules  
* Access unrelated models directly

Aim for fewer than 200 lines per controller.

---

# **6\. Service Standards**

Every business operation belongs in a Service.

Examples:

ProductService

StockMovementService

DocumentMovementService

BarcodeService

BillingService

Services should:

* Have a single responsibility  
* Be reusable  
* Throw meaningful exceptions  
* Be independently testable

---

# **7\. Model Standards**

Models should contain only:

* Relationships  
* Accessors  
* Mutators  
* Casts  
* Local scopes

Avoid placing business workflows inside models.

---

# **8\. Validation Standards**

Always use Laravel Form Requests.

Validate:

* Required fields  
* Unique values  
* Foreign keys  
* Dates  
* Numeric ranges  
* File uploads  
* Barcodes  
* Customer ownership

Never rely on frontend validation alone.

---

# **9\. Authorization Standards**

Never authorize using UI visibility alone.

Every protected action must be enforced by:

* Middleware  
* Policies  
* AccessControlService

Always derive `customer_id` from the authenticated user.

Never trust client-submitted tenant identifiers.

---

# **10\. Naming Conventions**

## **Classes**

PascalCase

Examples

ProductService

BillingRecord

AuditService

---

## **Methods**

camelCase

Examples

createProduct()

transferStock()

renewSubscription()

---

## **Variables**

camelCase

Examples

$product

$customerId

$movementHistory

---

## **Database Tables**

Plural snake\_case

Examples

products

document\_files

billing\_records

---

## **Database Columns**

snake\_case

Examples

customer\_id

created\_at

last\_login\_at

---

## **Routes**

Use resource-style naming where appropriate.

Examples

/products

/products/{product}

/documents/files

---

# **11\. Database Standards**

Use foreign keys.

Use indexes.

Use soft deletes for master data.

Never soft delete history tables.

History tables include:

* stock\_movements  
* document\_movement\_logs  
* audit\_logs  
* subscription\_logs  
* license\_logs  
* billing\_logs  
* barcode\_scan\_logs

These tables are immutable.

---

# **12\. Transaction Standards**

Wrap multi-table updates in database transactions.

Required for:

* Stock movements  
* Document movements  
* Subscription renewal  
* License renewal  
* Billing updates  
* Barcode registration

Rollback completely on failure.

---

# **13\. Error Handling**

Use typed exceptions.

Provide meaningful error messages.

Log unexpected failures.

Never expose stack traces in production.

Return consistent responses.

---

# **14\. Logging Standards**

Log:

* Authentication failures  
* Authorization failures  
* System exceptions  
* Queue failures  
* Import failures  
* Export failures  
* External integration failures

Do not log sensitive information such as passwords or API secrets.

---

# **15\. Audit Standards**

Every critical business action must create an audit entry.

Audit logs must include:

* User  
* Customer  
* Module  
* Action  
* Old values (where applicable)  
* New values (where applicable)  
* Timestamp  
* IP address  
* User agent

Audit entries must never be edited or deleted.

---

# **16\. Performance Standards**

Use eager loading.

Avoid N+1 queries.

Paginate large datasets.

Index frequently queried columns.

Cache reference data where appropriate.

Profile slow queries before optimizing.

---

# **17\. Security Standards**

Always:

* Enable CSRF protection.  
* Validate all input.  
* Escape output.  
* Use HTTPS in production.  
* Protect file uploads.  
* Enforce least privilege.  
* Store secrets in `.env`.

Never:

* Commit credentials.  
* Disable authorization.  
* Expose debug information in production.  
* Trust client-provided identifiers.

---

# **18\. Filament Standards**

Every Resource should include:

* List page  
* Create page  
* Edit page  
* View page (where useful)  
* Filters  
* Search  
* Sorting  
* Bulk actions (if appropriate)

Forms and tables should reuse shared components where possible.

---

# **19\. Testing Standards**

Each feature should include:

* Unit tests  
* Feature tests  
* Policy tests  
* Validation tests  
* Authorization tests

Target code coverage:

Minimum 80%

Critical services should approach 100% coverage.

---

# **20\. Git Standards**

Branch naming:

feature/customer-management  
bugfix/barcode-print  
hotfix/login-issue

Commit messages:

feat: add stock receive workflow

fix: prevent negative stock

refactor: simplify barcode service

docs: update deployment guide

Follow Conventional Commits where practical.

---

# **21\. Pull Request Checklist**

Before opening a Pull Request:

* Code compiles.  
* Tests pass.  
* Laravel Pint passes.  
* PHPStan passes.  
* No debug statements remain.  
* Documentation updated if required.  
* Database migrations reviewed.  
* Security implications considered.

---

# **22\. Code Review Checklist**

Reviewers should verify:

* Business rules implemented correctly.  
* Customer isolation enforced.  
* Authorization present.  
* Transactions used where required.  
* Tests included.  
* Naming conventions followed.  
* No duplicated logic.  
* Performance acceptable.  
* Security maintained.

---

# **23\. AI-Assisted Development Guidelines**

AI-generated code is permitted but must be reviewed.

When using AI:

* Reuse existing services before creating new ones.  
* Follow the established architecture.  
* Do not introduce unnecessary abstractions.  
* Do not bypass validation or authorization.  
* Ensure generated code matches project naming conventions.  
* Add or update tests.  
* Update documentation when behavior changes.

AI output is a starting point, not a substitute for engineering review.

---

# **24\. Documentation Standards**

Every significant feature should update:

* Developer Blueprint (if scope changes)  
* Technical Design Document  
* Database Dictionary (if schema changes)  
* API Specification (if endpoints change)  
* User Manual (if user workflow changes)

Documentation is part of the Definition of Done.

---

# **25\. Definition of Done**

A feature is complete only when:

* Business requirements implemented.  
* Customer isolation verified.  
* Authorization enforced.  
* Validation completed.  
* Audit logging added.  
* Tests passing.  
* Documentation updated.  
* Code reviewed.  
* No critical defects remain.

---

# **26\. Continuous Improvement**

Engineering standards should be reviewed periodically.

Proposed changes should be:

1. Discussed.  
2. Approved.  
3. Documented.  
4. Communicated to the team.  
5. Applied consistently.

Avoid ad-hoc standards that are not documented.

---

# **27\. Summary**

The purpose of these standards is to ensure that every contribution to DMIMS is:

* Consistent  
* Secure  
* Maintainable  
* Scalable  
* Production-ready

Following these guidelines reduces technical debt, improves onboarding, and keeps the project approachable as it grows.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Development Standards & Coding Guidelines |

