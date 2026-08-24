# DMIMS Master Functional Specification (MFS)

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# Document Purpose

The MFS defines the expected functional behaviour of DMIMS.

No implementation may contradict this specification without an approved change request.

---

# 1. Functional Scope

DMIMS Version 1 includes:

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
20. Progressive Web App

Platform administration and customer presentation are separated.

---

# 2. Dashboard Module

## Super Admin Dashboard

Displays platform information such as:

- Total Customers
- Active/Suspended Customers
- Subscription status
- License status
- Billing/outstanding balances
- Revenue summaries
- Recent audit activity
- Platform notifications

## Customer Dashboard

Displays only role-relevant own-customer information.

Examples:

- Total Products
- Low Stock
- Total Boxes
- Total Document Files
- Overdue Returns
- Recent operational activity
- Subscription Status
- License Status
- Billing status where permitted

Widgets for disabled/unpermitted modules must not appear.

---

# 3. Customer Management Module

Platform Customer Management is for Datamation platform roles.

Functions include:

- Create Customer
- Edit Customer
- Suspend
- Reactivate
- Archive
- View Customer Details
- View Customer Users
- View Modules
- View Subscription
- View License
- View Billing
- View Audit History

## Customer-Facing My Company

Customer users do not use the multi-customer Customer Management interface.

Authorized Company Admin and Company Supervisor users receive:

**My Company**

Possible tabs:

1. Overview
2. Users
3. Enabled Modules
4. Subscription
5. License Status
6. Billing
7. Audit Logs

Each tab is independently authorized.

### Company Admin

May see:

- Own company profile
- Own customer users
- Own enabled modules
- Own subscription summary
- Own license status
- Own billing when Billing View is enabled
- Own customer audit logs

### Company Supervisor

May see:

- Own company profile
- Own users according to limited permissions
- Own enabled modules
- Own subscription summary
- Own license status
- Own billing when Billing View is enabled

Does not receive Audit Logs by default.

### Other Customer Roles

Operational roles should not receive administrative tabs unrelated to their role.

---

# 4. User Management

Functions:

- Create
- Edit
- Assign role
- Reset password
- Lock/unlock
- Deactivate
- View login history

Customer Admin users operate only on their own customer's non-platform users.

Platform users must never appear in customer user management.

---

# 5. Module Management

Available modules include:

- Stock Inventory
- Document Tracking
- Barcode Scanning
- Barcode Printing
- Reports
- Import / Export
- Advanced Audit
- Backup / Restore
- Billing View

Disabled modules are hidden and blocked through navigation, routes, actions and services.

Platform Module Management is platform-only.

Customers may see only a read-only summary of their enabled modules through My Company where permitted.

---

# 6. Subscription Management

Platform functions:

- Create Plan
- Edit Plan
- Assign Plan
- Renew Subscription
- Change Modules
- Update Limits
- Cancel Subscription

## Customer Subscription Visibility

Subscription Plans are platform-only master records.

Customer users cannot browse or manage Subscription Plans.

Authorized Company Admin/Supervisor users may see only their effective subscription summary:

- Current plan
- Subscription status
- Validity dates
- Effective limits
- Current usage
- Enabled modules
- Allowed report summary
- Billing cycle

Modification remains a Datamation responsibility.

---

# 7. License Management

Platform functions:

- Create
- Renew
- Suspend
- Revoke
- Reactivate
- Change access mode

## Customer License Visibility

Customers do not receive License Management.

Authorized Company Admin/Supervisor users may see only a simplified License Status panel.

The panel must not expose unnecessary platform licensing configuration.

---

# 8. Billing & Payment Module

Only Datamation Super Admin modifies billing/payment.

Customer users may view own billing only when Billing View is enabled and permission allows.

Customer users cannot:

- Create invoices
- Issue invoices
- Cancel billing
- Record payments
- Change payment status

---

# 9. Shared Location Module

Single location hierarchy shared by Inventory and Document Tracking.

External destinations are never stored as fake locations.

---

# 10. Stock Inventory Module

Pages:

- Categories
- Products
- Locations
- Receive-In
- Internal Transfer
- Stock Out
- Adjustment
- Movement History
- Inventory Reports

All records and actions are tenant-scoped.

---

# 11. Document Tracking Module

Pages:

- Boxes
- Files
- Receive
- Transfer
- Move Out
- Return
- Movement History
- Document Reports

All records and actions are tenant-scoped.

---

# 12. Barcode Module

Types:

- Product
- Location
- Box
- Document File

Scan flow:

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
Validate Module  
↓  
Open Allowed Screen  
↓  
Log Scan

Cross-customer barcodes must not reveal data.

---

# 13. Reports & Analytics

Reports are role-aware, module-aware and entitlement-aware.

## Platform Reports

Platform-only:

- Customer Summary
- Subscription Report
- License Report
- Platform Billing Analytics
- Platform Payment Analytics
- Platform Audit Summary
- Module Usage

## Inventory Reports

Require:

- Reports module where applicable
- Stock Inventory enabled
- Inventory view permission
- Effective report entitlement

Examples:

- Inventory Summary
- Low Stock
- Stock Movement
- Stock Value

## Document Reports

Require:

- Reports module where applicable
- Document Tracking enabled
- Document view permission
- Effective report entitlement

Examples:

- File Master
- Box Master
- Files by Box
- Boxes by Location
- Movement History
- External Movement
- Overdue Returns

## Billing Reports

Require:

- Billing View enabled
- View Billing permission
- Own-customer scope for customer users

## Audit Reports

Company Admin may see only own-customer audit information when audit access is enabled/permitted.

## Report Selector Rule

The report selector must contain only reports the authenticated user is authorized to execute.

Backend generation repeats the same checks.

---

# 14. Import & Export

Imports supported for authorized operational modules.

Exports supported in configured formats.

Every import/export:

- Validates permission
- Validates module
- Validates customer ownership
- Validates license
- Validates entitlement/limits where applicable
- Writes audit record

---

# 15. Notifications

Generated for relevant customer/platform events.

Customer notifications remain tenant-scoped.

---

# 16. Audit Logs

Audit records include user, customer, module, action, timestamp, IP and value changes.

Immutable.

Company Admin may view only exact own-customer audit records.

Platform `customer_id = NULL` audit records are not customer-visible.

---

# 17. System Settings

Platform settings are platform-only.

Customer settings may be exposed only where explicitly supported and authorized.

---

# 18. Progressive Web App

Installable, responsive and online-first.

Navigation remains role-aware in desktop, mobile and PWA modes.

---

# 19. Global Validation Rules

Across all modules:

- Validate required fields
- Validate ownership
- Validate resource scope
- Check permissions
- Check subscription
- Check license
- Check module
- Check report entitlement where applicable
- Audit critical actions
- Use transactions where required

---

# 20. Functional Acceptance Criteria

A feature is accepted only when:

- Behaviour matches specification
- Validation is enforced
- Security is enforced
- Customer isolation is verified
- Platform/customer boundary is verified
- Audit logging is complete
- Error handling is implemented
- UI follows design system
- Automated tests pass
- UAT is approved

---

# 21. Future Functional Enhancements

May include:

- Native mobile apps
- Offline synchronization
- RFID
- OCR
- AI classification
- AI forecasting
- Public REST API expansion
- GraphQL
- Microsoft 365
- SAP
- Multi-language
- Multi-currency

Future features must inherit the same platform/tenant resource-scope rules.

---

# 22. Functional Summary

Customer users only receive features belonging to their customer, enabled modules, role permissions, subscription/report entitlement and license access.

Platform administration remains separate.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Master Functional Specification |
| 1.1 | 24 August 2026 | Added My Company, strict tenant scope and module/permission-aware report model |
