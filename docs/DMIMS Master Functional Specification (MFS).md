# **DMIMS Master Functional Specification (MFS)**

## **Datamation Inventory Management System (DMIMS)**

**Version:** 1.0

---

# **Document Purpose**

The Master Functional Specification (MFS) defines every functional aspect of DMIMS.

It is the authoritative reference for:

* Business Owners  
* Project Managers  
* Developers  
* QA Engineers  
* UAT Testers  
* Future Maintenance Teams

Unlike the Developer Blueprint, which provides an overview, this document specifies **exactly how every feature must behave**.

---

# **1\. Functional Scope**

DMIMS Version 1 includes the following modules:

1. Dashboard  
2. Customer Management  
3. User Management  
4. Role & Permission Management  
5. Module Management  
6. Subscription Management  
7. License Management  
8. Billing & Payment Management  
9. Shared Location Management  
10. Stock Inventory  
11. Document Tracking  
12. Barcode Registry  
13. Barcode Scanning  
14. Barcode Printing  
15. Reports & Analytics  
16. Import & Export  
17. Notifications  
18. Audit Logs  
19. System Settings  
20. Progressive Web App (PWA)

Each module is specified in the following chapters.

---

# **2\. Dashboard Module**

## **Purpose**

Provide users with an immediate overview of the information most relevant to their role.

---

## **Super Admin Dashboard**

Displays:

* Total Customers  
* Active Customers  
* Suspended Customers  
* Active Licenses  
* Expiring Licenses  
* Active Subscriptions  
* Expiring Subscriptions  
* Outstanding Billing  
* Total Recorded Revenue  
* Recent Audit Activities  
* System Notifications

### **Quick Actions**

* Create Customer  
* Create User  
* Create Subscription  
* Create License  
* Create Billing Record

---

## **Customer Dashboard**

Displays:

* Total Products  
* Low Stock Items  
* Total Boxes  
* Total Document Files  
* Overdue Returns  
* Recent Inventory Activity  
* Recent Document Activity  
* Subscription Status  
* License Status

---

# **3\. Customer Management Module**

## **Purpose**

Manage customer companies hosted within DMIMS.

---

## **Functions**

* Create Customer  
* Edit Customer  
* Suspend Customer  
* Reactivate Customer  
* Archive Customer  
* View Customer Details  
* View Customer Users  
* View Subscription  
* View License  
* View Billing  
* View Audit History

---

## **Validation Rules**

Company Name

Required

Maximum 255 characters

---

Company Code

Required

Unique

Immutable after creation unless Super Admin overrides

---

Status

Required

Values:

* Trial  
* Active  
* Near Expiry  
* Expired  
* Suspended  
* Cancelled  
* Archived

---

# **4\. User Management Module**

## **Functions**

* Create User  
* Edit User  
* Assign Company  
* Assign Roles  
* Reset Password  
* Lock User  
* Unlock User  
* Deactivate User  
* View Login History

---

## **Validation**

Email

Required

Unique

Valid email format

---

Password

Minimum 12 characters

Strong password policy

---

Company Assignment

Required for customer users

Forbidden for platform users

---

# **5\. Module Management**

## **Available Modules**

* Stock Inventory  
* Document Tracking  
* Barcode Scanning  
* Barcode Printing  
* Reports  
* Import / Export  
* Advanced Audit  
* Backup / Restore  
* Billing View

---

## **Behaviour**

Disabled modules:

* Hidden from menus  
* Blocked by middleware  
* Blocked through direct URLs  
* Blocked in business services

---

# **6\. Subscription Management**

## **Functions**

* Create Plan  
* Edit Plan  
* Assign Plan  
* Renew Subscription  
* Change Modules  
* Update Limits  
* Cancel Subscription

---

## **Limits**

Maximum Users

Maximum Products

Maximum Document Files

Maximum Boxes

Allowed Reports

Enabled Modules

Grace Period

Billing Cycle

---

## **Renewal Workflow**

1. Open subscription.  
2. Select renewal.  
3. Choose plan.  
4. Set validity period.  
5. Confirm limits.  
6. Save.  
7. Generate subscription log.  
8. Generate audit log.

---

# **7\. License Management**

## **Functions**

* Create License  
* Renew License  
* Suspend License  
* Revoke License  
* Reactivate License

---

## **Access Modes**

* Full Access  
* View Only  
* Blocked

---

## **Suspension Workflow**

1. Select customer.  
2. Suspend license.  
3. Enter reason.  
4. System updates status.  
5. Audit log created.  
6. License log created.

---

# **8\. Billing & Payment Module**

## **Functions**

* Create Billing Record  
* Create Invoice  
* Record Manual Payment  
* Upload Payment Proof  
* View Outstanding Balance  
* Export Billing Report

---

## **Payment Methods**

* Bank Transfer  
* Cash  
* Cheque  
* Online Transfer  
* Internal Adjustment  
* Waived  
* Other

---

## **Rules**

Only Datamation Super Admin can modify billing.

Customer users may only view billing if the Billing View module is enabled.

---

# **9\. Shared Location Module**

## **Purpose**

Provide a single hierarchy of physical locations used by both Inventory and Document Tracking.

---

## **Hierarchy Example**

Warehouse

↓

Building

↓

Floor

↓

Room

↓

Rack

↓

Shelf

↓

Cabinet

---

## **Rules**

Products occupy locations.

Boxes occupy locations.

Files occupy boxes.

External destinations are never stored as locations.

---

# **10\. Stock Inventory Module**

## **Pages**

* Categories  
* Products  
* Locations  
* Receive-In  
* Internal Transfer  
* Stock Out  
* Stock Adjustment  
* Stock Movement History  
* Inventory Reports

---

## **Product Fields**

SKU

Barcode

Category

Default Location

Description

Reorder Level

Unit Cost

Unit Price

Status

---

## **Receive-In Workflow**

Input:

Product

Location

Quantity

Source

Reference

↓

Validate

↓

Increase inventory

↓

Write stock movement

↓

Write audit log

↓

Return success

---

## **Stock Out Workflow**

Input:

Product

Quantity

Destination

Reason

↓

Validate

↓

Prevent negative stock

↓

Reduce inventory

↓

Write movement

↓

Audit

---

# **11\. Document Tracking Module**

## **Pages**

* Boxes  
* Files  
* Receive File  
* Transfer File  
* Move Out File  
* Return File  
* Receive Box  
* Transfer Box  
* Move Out Box  
* Return Box  
* Movement History

---

## **Box Fields**

Box Number

Barcode

Current Location

Capacity

Current File Count

Status

Expected Return Date

Remarks

---

## **File Fields**

Barcode

Reference Number

Title

Document Type

Owner

Current Box

Status

Expected Return Date

Remarks

---

# **12\. Barcode Module**

## **Barcode Types**

* Product  
* Location  
* Box  
* Document File

---

## **Barcode Format**

PRD-COMPANYCODE-000001

LOC-COMPANYCODE-000001

BOX-COMPANYCODE-000001

DOC-COMPANYCODE-000001

---

## **Scanner Workflow**

Scan

↓

Lookup Registry

↓

Determine Type

↓

Validate Customer

↓

Validate Permission

↓

Open Related Screen

↓

Log Scan

---

# **13\. Reports & Analytics**

## **Platform Reports**

* Customer Summary  
* Subscription Report  
* License Report  
* Billing Report  
* Payment Report  
* Outstanding Balance  
* Audit Summary  
* Module Usage

---

## **Inventory Reports**

* Inventory Summary  
* Low Stock  
* Stock Movement  
* Stock Value

---

## **Document Reports**

* File Master  
* Box Master  
* Files by Box  
* Boxes by Location  
* Movement History  
* External Movement  
* Overdue Returns

---

# **14\. Import & Export**

## **Import Types**

* Products  
* Opening Stock  
* Locations  
* Boxes  
* Document Files

---

## **Export Formats**

* CSV  
* Excel  
* PDF  
* Print

---

## **Rules**

Preview before import.

Reject invalid rows.

Generate error report.

Audit every import and export.

---

# **15\. Notifications**

Generated for:

* Low Stock  
* Subscription Expiry  
* License Expiry  
* Billing Overdue  
* Payment Recorded  
* File Return Overdue  
* Box Return Overdue  
* Import Failure  
* Export Completion

---

# **16\. Audit Logs**

Audit every critical action.

Audit record includes:

* User  
* Customer  
* Module  
* Action  
* Timestamp  
* IP Address  
* Old Values  
* New Values

Audit history is immutable.

---

# **17\. System Settings**

Platform Settings

Customer Settings

Email Settings (future)

Barcode Settings

Report Settings

Security Settings

PWA Settings

---

# **18\. Progressive Web App**

Requirements:

* Installable  
* Responsive  
* Offline Information Page  
* Mobile Scanner Interface  
* App Icons  
* Manifest  
* Service Worker

Version 1 is online-first.

---

# **19\. Global Validation Rules**

Across all modules:

* Required fields validated.  
* Foreign keys validated.  
* Customer ownership verified.  
* Permissions checked.  
* Subscription checked.  
* License checked.  
* Module enabled.  
* Audit written.  
* Transactions used where required.

---

# **20\. Functional Acceptance Criteria**

A feature is accepted only when:

* Functional behaviour matches specification.  
* Validation rules are enforced.  
* Security rules are enforced.  
* Customer isolation is verified.  
* Audit logging is complete.  
* Error handling is implemented.  
* UI follows the design system.  
* Automated tests pass.  
* UAT is approved.

---

# **21\. Future Functional Enhancements**

Reserved for future releases:

* Native mobile applications  
* Offline synchronization  
* RFID support  
* OCR document indexing  
* AI document classification  
* AI inventory forecasting  
* Public REST API  
* GraphQL API  
* Microsoft 365 integration  
* SAP integration  
* Multi-language  
* Multi-currency

---

# **22\. Functional Summary**

The Master Functional Specification defines the expected behaviour of every module in DMIMS.

No implementation should contradict this specification without an approved change request.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Master Functional Specification |

