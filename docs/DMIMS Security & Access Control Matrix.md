# **DMIMS Security & Access Control Matrix**

**Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document defines the complete authorization model for DMIMS.

It specifies:

* User roles  
* Permissions  
* Module access  
* CRUD permissions  
* Operational permissions  
* Reporting permissions  
* System administration permissions  
* Access restrictions

This document is the authoritative reference for:

* Laravel Policies  
* Spatie Permissions  
* Filament Navigation  
* Middleware  
* UI visibility  
* QA testing  
* User acceptance testing (UAT)

---

# **1\. Security Principles**

DMIMS follows these security principles:

* Least Privilege Access  
* Multi-Tenant Isolation  
* Role-Based Access Control (RBAC)  
* Defense in Depth  
* Secure by Default  
* Audit Everything  
* No Direct Object Access  
* Explicit Permission Checks

---

# **2\. Role Hierarchy**

Highest privilege:

Datamation Super Admin

↓

Datamation Management

↓

Company Admin

↓

Company Supervisor

↓

Stock Inventory User

↓

Document Tracking User

↓

Viewer

Higher roles inherit the permissions of lower roles only where explicitly defined.

---

# **3\. Role Definitions**

## **Datamation Super Admin**

Purpose

Complete system administration.

Responsibilities

* Manage platform  
* Manage customers  
* Manage subscriptions  
* Manage licenses  
* Manage billing  
* Manage users  
* Configure modules  
* View all reports  
* Access audit logs  
* Manage backups  
* System settings

Scope

All customers.

---

## **Datamation Management**

Purpose

Executive reporting.

Responsibilities

View only.

Cannot modify operational data.

Scope

Platform-wide.

---

## **Company Admin**

Purpose

Customer administrator.

Responsibilities

Manage own company.

Manage users.

View reports.

Perform operational work.

Cannot modify platform configuration.

---

## **Company Supervisor**

Purpose

Department supervisor.

Responsibilities

Operational oversight.

Limited user management.

Cannot change company settings.

---

## **Stock Inventory User**

Purpose

Inventory operations.

Responsibilities

Inventory management only.

---

## **Document Tracking User**

Purpose

Document operations.

Responsibilities

Archive and document management only.

---

## **Viewer**

Purpose

Read-only access.

No operational actions.

---

# **4\. Permission Naming Standard**

Permissions use the format:

module.action

Examples

customers.view

customers.create

customers.update

customers.delete

products.receive

products.transfer

documents.move\_out

reports.export

---

# **5\. Customer Management Permissions**

| Permission | Super Admin | Management | Company Admin | Supervisor | Stock User | Document User | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| View Customers | ✓ | ✓ | Own Company | Own Company | No | No | No |
| Create Customer | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Update Customer | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Suspend Customer | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Archive Customer | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

---

# **6\. User Management Permissions**

| Permission | SA | Mgmt | Admin | Sup | Stock | Doc | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| View Users | ✓ | ✓ | Own | Own | ✗ | ✗ | ✗ |
| Create User | ✓ | ✗ | Own | ✗ | ✗ | ✗ | ✗ |
| Update User | ✓ | ✗ | Own | Limited | ✗ | ✗ | ✗ |
| Reset Password | ✓ | ✗ | Own | ✗ | ✗ | ✗ | ✗ |
| Delete User | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

---

# **7\. Subscription Permissions**

Only Datamation Super Admin may:

* Create plans  
* Assign plans  
* Renew subscriptions  
* Change limits  
* Change modules  
* Cancel subscriptions

Management may view summaries only.

Company users cannot edit subscriptions.

---

# **8\. License Permissions**

Only Datamation Super Admin may:

* Create license  
* Renew license  
* Suspend license  
* Revoke license  
* Change technical access

Management may view.

Customers may only view their own license status.

---

# **9\. Billing Permissions**

| Action | SA | Mgmt | Admin | Supervisor | Stock | Document | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| View Billing | ✓ | ✓ | Own\* | Own\* | ✗ | ✗ | ✗ |
| Create Invoice | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Update Payment | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Cancel Billing | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Export Billing | ✓ | ✓ | Own\* | Own\* | ✗ | ✗ | ✗ |

\*Only when the **Billing View** module is enabled.

---

# **10\. Inventory Permissions**

| Action | SA | Mgmt | Admin | Supervisor | Stock | Doc | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| View Products | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ |
| Create Product | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Update Product | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Delete Product | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Receive Stock | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Stock Out | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Transfer Stock | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Adjust Stock | ✓ | ✗ | ✓ | ✓ | ✓ | ✗ | ✗ |

---

# **11\. Document Tracking Permissions**

| Action | SA | Mgmt | Admin | Supervisor | Stock | Doc | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| View Files | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Receive File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Transfer File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Move Out File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Return File | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Receive Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Transfer Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Move Out Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |
| Return Box | ✓ | ✗ | ✓ | ✓ | ✗ | ✓ | ✗ |

---

# **12\. Barcode Permissions**

| Action | SA | Mgmt | Admin | Supervisor | Stock | Doc | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| Scan Barcode | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Print Barcode | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✗ |
| View Registry | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

---

# **13\. Reports & Analytics Permissions**

| Report | SA | Mgmt | Admin | Supervisor | Stock | Doc | Viewer |
| ----- | ----- | ----- | ----- | ----- | ----- | ----- | ----- |
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Customer Reports | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Inventory Reports | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ |
| Document Reports | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Billing Reports | ✓ | ✓ | Own\* | Own\* | ✗ | ✗ | ✗ |
| Audit Reports | ✓ | Limited | Own | ✗ | ✗ | ✗ | ✗ |

---

# **14\. Audit Permissions**

Only AuditService writes audit logs.

No user may edit audit logs.

Only Datamation Super Admin may view all audit logs.

Management may view summarized audit information if permitted.

Company Admin may only view audit logs for their own company.

---

# **15\. Import & Export Permissions**

Imports require:

* Active subscription  
* Active license  
* Module enabled  
* Import permission

Exports require:

* Export permission  
* Module enabled  
* License allowing export

Every import and export creates an audit record.

---

# **16\. Customer Isolation Matrix**

| User | Own Company | Other Company |
| ----- | ----- | ----- |
| Super Admin | ✓ | ✓ |
| Management | ✓ | ✓ (Read-only) |
| Company Admin | ✓ | ✗ |
| Supervisor | ✓ | ✗ |
| Stock User | ✓ | ✗ |
| Document User | ✓ | ✗ |
| Viewer | ✓ | ✗ |

---

# **17\. Access Decision Flow**

Every operational request follows this sequence:

Authenticated?

↓

User Active?

↓

Company Active?

↓

Subscription Valid?

↓

License Allows?

↓

Module Enabled?

↓

Permission Granted?

↓

Usage Limit Available?

↓

Perform Action

↓

Write Audit Log

Any failed check immediately denies the request.

---

# **18\. Security Best Practices**

Developers must:

* Never trust client-submitted `customer_id`.  
* Always authorize through Policies and the AccessControlService.  
* Hide menus **and** enforce backend authorization.  
* Use middleware for cross-cutting checks.  
* Protect against direct URL access.  
* Log every critical mutation.  
* Follow the principle of least privilege.

---

# **19\. QA Verification Checklist**

QA should verify:

* Each role sees only permitted menus.  
* Each role can perform only allowed actions.  
* Cross-company access is blocked.  
* Hidden URLs remain inaccessible.  
* Exports require permission.  
* Imports enforce subscription limits.  
* Billing edits are restricted to Super Admin.  
* Audit logs cannot be modified.  
* License and subscription restrictions are enforced consistently.

---

# **20\. Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Security & Access Control Matrix |

