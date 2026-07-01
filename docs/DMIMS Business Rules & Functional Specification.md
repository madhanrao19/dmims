# **DMIMS Business Rules & Functional Specification**

**Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document defines the business rules that govern the behaviour of the DMIMS application.

It explains how the system should behave from a business perspective rather than a programming perspective.

Whenever there is uncertainty during development, the rules in this document take precedence over implementation assumptions.

---

# **1\. Core Business Principles**

DMIMS is built around six fundamental principles.

1. Multi-tenant architecture  
2. Complete customer data isolation  
3. Immutable inventory and document history  
4. Subscription-based commercial control  
5. License-based technical access control  
6. Full auditability of important actions

These principles must never be violated.

---

# **2\. Customer Isolation**

## **Purpose**

Every customer company operates independently inside the same DMIMS platform.

No customer may access another customer's information.

---

## **Rule**

Every customer-owned record contains:

customer\_id

Every database query must be filtered using the authenticated user's customer\_id unless the user is a Datamation Super Admin.

---

## **Super Admin**

Can view all companies.

Can manage all companies.

Can switch between customers.

---

## **Company User**

Can only access records belonging to their assigned customer.

Attempts to access another customer's records must be rejected.

---

# **3\. User Management Rules**

Users belong to one of two groups.

## **Platform Users**

Datamation employees.

customer\_id \= NULL

Examples

Datamation Super Admin

Datamation Management

---

## **Company Users**

Assigned to exactly one customer.

Cannot create platform users.

Cannot change another company's users.

---

## **User Status**

Pending

Active

Inactive

Suspended

Locked

Archived

---

## **Login Rules**

Only Active users may log in.

Locked users must complete an administrator reset before regaining access.

Archived users may never log in.

---

# **4\. Company Status Rules**

A customer company has one status.

Trial

Active

Near Expiry

Expired

Suspended

Cancelled

Archived

---

## **Behaviour**

### **Trial**

Normal access within subscribed limits.

---

### **Active**

Full access according to subscription, license and permissions.

---

### **Near Expiry**

Normal operation.

Show reminders.

---

### **Expired**

Controlled by subscription grace period and license.

---

### **Suspended**

Operational actions blocked.

View-only access if permitted by license.

---

### **Archived**

Hidden from normal operational lists.

No new transactions permitted.

---

# **5\. Role-Based Permissions**

## **Datamation Super Admin**

Full system control.

---

## **Datamation Management**

Read-only analytics.

Cannot modify operational data.

---

## **Company Admin**

Manages own company.

---

## **Company Supervisor**

Operational oversight.

Limited administration.

---

## **Stock Inventory User**

Inventory only.

---

## **Document Tracking User**

Document module only.

---

## **Viewer**

Read-only.

---

# **6\. Access Control Rules**

Before allowing any operational action, DMIMS must evaluate:

User Status

↓

Company Status

↓

Subscription Status

↓

License Status

↓

Module Enabled

↓

Permission Granted

↓

Usage Limits

Only when all checks succeed may the action continue.

---

# **7\. Subscription Rules**

A subscription defines commercial entitlement.

It controls:

* Plan  
* Modules  
* Limits  
* Billing cycle  
* Validity period  
* Grace period

A subscription does **not** directly determine whether the customer can technically use the system.

---

## **Subscription Status**

Trial

Active

Near Expiry

Expired Grace

Expired

Cancelled

---

## **Subscription Limits**

The subscription may define limits for:

Maximum users

Maximum products

Maximum document files

Maximum archive boxes

Maximum reports

Maximum enabled modules

---

## **Limit Rule**

If a subscription limit is exceeded, the system must prevent creation of additional records while allowing existing records to remain accessible, subject to license restrictions.

---

# **8\. License Rules**

The license determines technical system access.

---

## **License Status**

Active

Suspended

Expired

Revoked

---

## **Technical Access Modes**

Full Access

View Only

Blocked

---

## **Behaviour**

Active

Normal operation.

Suspended

Login permitted.

Operational actions blocked.

Viewing and exporting allowed.

Expired

Same as Suspended until renewed.

Revoked

Login denied unless an emergency override is granted by Datamation Super Admin.

---

# **9\. Effective Access Rule**

The effective permission is the combination of:

Company Status

AND

User Status

AND

Subscription

AND

License

AND

Module

AND

Permission

AND

Usage Limits

Failure of any mandatory check denies the requested operation.

---

# **10\. Module Rules**

Each customer has independently enabled modules.

Examples

Stock Inventory

Document Tracking

Barcode

Reports

Audit

Import / Export

Backup

Billing View

---

If a module is disabled:

* Hide the menu.  
* Block direct URL access.  
* Prevent service execution.  
* Display an explanatory message.

---

# **11\. Inventory Rules**

Every product belongs to exactly one customer.

Every SKU must be unique within the customer.

Every barcode must be unique within the customer.

Negative stock is not permitted.

Every stock movement must generate:

* Stock movement record  
* Audit log

---

# **12\. Stock Receive-In**

Receive-In always increases available inventory.

Source may be external.

Destination must be an internal DMIMS location.

---

# **13\. Stock Out**

Stock Out decreases available inventory.

Destination may be external.

Available quantity must never become negative.

---

# **14\. Internal Stock Transfer**

Transfers inventory between two internal locations.

Both locations must belong to the same customer.

The total inventory quantity must remain unchanged.

---

# **15\. Stock Adjustment**

Adjustments require:

Reason

User

Date

Audit record

Negative adjustments may not reduce inventory below zero.

---

# **16\. Shared Location Rules**

Locations are shared by:

Stock Inventory

Document Tracking

Locations are never duplicated.

Products occupy locations.

Boxes occupy locations.

Files occupy boxes.

Boxes are not locations.

---

# **17\. Archive Box Rules**

Boxes may contain multiple document files.

Boxes occupy one location.

Moving a box changes the effective location of all contained files without updating each file record individually.

---

# **18\. Document File Rules**

Files always belong to one customer.

Files may exist in one box at a time.

Moving a file changes its box.

Moving a box does not modify individual file records.

---

# **19\. External Movement Rules**

External locations are not stored as DMIMS locations.

Instead, movement records capture:

Source Type

Source Name

Destination Type

Destination Name

Reference Number

Contact Details

This prevents unnecessary master data pollution.

---

# **20\. Barcode Rules**

Every barcode is registered centrally.

Supported barcode types:

Product

Location

Box

Document File

Unknown barcodes are logged.

Barcodes from another customer must never reveal information.

---

# **21\. Import Rules**

Imports must:

Validate all rows.

Show validation errors.

Allow preview.

Reject duplicate keys.

Respect subscription limits.

Generate audit logs.

Partial imports are not permitted.

---

# **22\. Export Rules**

Exports require:

Permission

Module enabled

License allowing export

Every export generates an audit record.

---

# **23\. Billing Rules**

Billing is entirely manual.

No payment gateway exists in Version 1\.

Only Datamation Super Admin may:

Create invoices.

Record payments.

Update balances.

Mark invoices paid.

Company users may only view billing information if the Billing View module is enabled.

---

# **24\. Audit Rules**

The following actions must always be audited:

Login

Logout

Failed Login

Create

Update

Delete

Role Changes

Permission Changes

Inventory Movements

Document Movements

Barcode Printing

Barcode Scanning

Imports

Exports

Subscription Changes

License Changes

Billing Updates

Payments

Backup

Restore

Audit entries are immutable.

---

# **25\. Notification Rules**

Notifications are generated for:

Low stock

Subscription expiry

License expiry

Overdue invoices

Payment updates

Overdue returns

Import failures

Export completion

Notifications are visible only within the owning customer unless they are platform-wide notifications.

---

# **26\. Progressive Web App Rules**

The application supports installation as a PWA.

Version 1 remains online-first.

Offline mode displays an informational page and prevents operational transactions.

Future versions may support offline synchronization.

---

# **27\. Security Rules**

Developers must never:

Trust customer\_id submitted by the browser.

Disable authorization checks.

Expose hidden routes.

Modify audit history.

Delete movement history.

Bypass the AccessControlService.

---

# **28\. Error Handling Rules**

The system should:

Provide clear error messages.

Log unexpected failures.

Rollback incomplete transactions.

Avoid exposing technical details to end users.

---

# **29\. Business Rule Hierarchy**

When multiple rules apply, precedence is:

1. System Security  
2. Customer Isolation  
3. License  
4. Subscription  
5. Module Availability  
6. User Permission  
7. Business Validation  
8. User Interface

This ensures security always overrides convenience.

---

# **30\. Summary**

DMIMS is designed to:

* Protect customer data through strict tenant isolation.  
* Separate commercial entitlement (Subscription) from technical access (License).  
* Preserve complete inventory and document history.  
* Provide comprehensive auditability.  
* Remain scalable for future growth while maintaining consistent business behaviour.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Business Rules & Functional Specification |

