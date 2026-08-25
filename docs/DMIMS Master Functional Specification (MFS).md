# DMIMS Master Functional Specification (MFS)

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# Document Purpose

The MFS defines the expected functional behaviour of DMIMS.

No implementation may contradict this specification without an approved change request.

---

# 1. Functional Scope

DMIMS Version 1 includes:

1. Dashboard
2. Platform Customer Management / Customer 360
3. Platform User Management
4. Role & Permission Management
5. Module Catalogue
6. Subscription Plans
7. Customer Subscription Management
8. Customer License Management
9. Billing & Payment Management
10. Shared Location Management
11. Stock Inventory
12. Document Tracking
13. Barcode Registry/Scanning/Printing
14. Reports & Analytics
15. Import & Export
16. Notifications
17. Audit Logs
18. System Settings
19. Progressive Web App
20. Customer-facing My Company

---

# 2. Platform Dashboard

Displays platform information such as:

- Customer totals/status
- Subscription/license status
- Billing/outstanding balances
- Platform alerts
- Recent platform activity

Quick links may open relevant customer profiles.

---

# 3. Platform Customer Management / Customer 360

## 3.1 Customers List

Datamation Super Admin can view all customers.

Datamation Management may view permitted read-only customer information.

Recommended list columns:

- Company
- Company Code
- Status
- Current Plan
- Subscription Status/Expiry
- License Status/Expiry
- Users Used / Limit
- Outstanding Billing
- Last Activity

Search/filter/sort are supported.

Selecting a customer opens Customer 360.

## 3.2 Customer 360 Route/Workspace

Conceptual workflow:

```text
Customers
→ Customer
→ Customer 360
```

Required tabs:

### Overview

Shows customer health and summary.

### Users

Lists only users belonging to the selected customer.

Super Admin can create/edit/deactivate permitted customer users.

Customer ownership is automatically the selected Customer.

### Modules

Lists customer module assignments.

Super Admin can enable/disable allowed modules for the selected customer.

### Subscription

Shows current/history subscription details and permits authorized renewal/change actions.

Customer is fixed to the parent context.

### License

Shows current/history license information and permits authorized Super Admin actions.

Customer is fixed to the parent context.

### Billing & Payments

Shows selected-customer billing, invoices, payment history and permitted actions.

### Audit Logs

Shows selected-customer audit events only.

### Activity / Notifications

Optional consolidated timeline of customer-related events/alerts.

## 3.3 Customer 360 Context Rule

No child tab should require an arbitrary Customer selector.

All child queries/actions derive the selected Customer from the parent route/record.

## 3.4 Existing Resources

Customer 360 must reuse existing resources, services, tables and authorization.

It is not a database merge.

---

# 4. Platform Navigation

Recommended platform navigation after Customer 360 implementation:

Dashboard

Customers

Platform Users

Roles & Permissions

Module Catalogue

Subscription Plans

Reports & Analytics

Platform Audit Logs

Backup / Restore

System Settings

Customer-specific Users, Modules, Subscriptions, Licenses, Billing and Payments are managed primarily through Customer 360 rather than separate top-level navigation.

Cross-customer summaries remain available under Reports & Analytics.

---

# 5. Customer-Facing My Company

Customer users do not use Platform Customer 360.

Authorized customer roles use My Company:

- Overview/Profile
- Users
- Enabled Modules
- Subscription Summary
- License Status
- Billing
- Audit Logs

Each tab remains role/module/entitlement controlled.

---

# 6. Platform User Management

Platform users remain separate from Customer Users.

Platform Users include Datamation Super Admin and Datamation Management.

Customer 360 Users tab must not include platform users.

---

# 7. Module Management

## Module Catalogue

Platform master data.

## Customer Modules

Managed through selected Customer 360 → Modules.

Customer users may see enabled module summary only where permitted.

---

# 8. Subscription Management

## Subscription Plans

Platform template catalogue remains separate.

## Customer Subscription

Managed through selected Customer 360 → Subscription.

Customer users see own summary only.

---

# 9. License Management

Customer-specific full administration is available to Super Admin through selected Customer 360 → License.

Customer users receive only simplified own status.

---

# 10. Billing & Payments

Customer-specific billing administration occurs inside selected Customer 360 → Billing & Payments.

Only authorized platform role may mutate billing/payment.

Customer users have permitted own read-only billing.

---

# 11. Inventory Module

Existing inventory pages/workflows remain unchanged.

Customer 360 may show usage/status summaries but does not replace Inventory operations.

---

# 12. Document Tracking Module

Existing document workflows remain unchanged.

Customer 360 may show usage/status summaries but does not replace operational pages.

---

# 13. Barcode Module

Existing barcode workflows remain tenant-scoped and permission controlled.

---

# 14. Reports & Analytics

Platform Reports & Analytics remain separate because they support cross-customer analysis.

Customer-specific reports may be linked/filter-scoped from Customer 360.

Customer users receive only role/module/entitlement-authorized reports.

---

# 15. Audit Logs

Platform Audit Logs remain separate for platform-level review.

Customer 360 Audit Logs show only the selected customer.

Customer My Company Audit shows only authenticated user's customer.

---

# 16. Import & Export

Existing module/permission/customer rules remain authoritative.

---

# 17. Notifications

Platform and customer notifications remain correctly scoped.

Customer 360 may display customer-related alerts.

---

# 18. Progressive Web App

Customer 360 is primarily a platform administration workflow.

Customer My Company and operational navigation remain responsive/PWA compatible.

---

# 19. Global Validation Rules

Across Customer 360:

- Parent customer authorization required
- Child customer ownership derived server-side
- No arbitrary child customer switching
- Existing resource authorization reused
- Direct child routes protected
- Mutations audited
- Transactions preserved
- Existing tenant isolation unchanged

---

# 20. Functional Acceptance Criteria

Customer 360 is accepted only when:

- Customers list works
- View Customer / Customer 360 works
- All required tabs work
- Parent context cannot be tampered with
- Child lists show selected customer only
- Child create/update automatically uses selected customer
- Datamation Management is read-only
- Customer roles cannot access Customer 360
- Existing My Company remains correct
- Reports/operational modules do not regress
- Automated and browser tests pass

---

# 21. Future Enhancements

Potential Customer 360 enhancements:

- Customer health score
- Renewal alerts
- Usage trend charts
- Billing timeline
- Support tickets
- Account notes
- Customer contact history

Future additions must reuse authoritative services/data.

---

# 22. Functional Summary

Platform customer administration is centered on one Customers list and one Customer 360 workspace per customer.

Customer users remain centered on My Company and authorized operational modules.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial MFS |
| 1.1 | 24 August 2026 | Added My Company and strict tenant access |
| 1.2 | 25 August 2026 | Added Platform Customer 360 and consolidated customer-specific management workflow |
