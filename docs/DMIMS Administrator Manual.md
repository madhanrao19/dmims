# DMIMS Administrator Manual

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# 1. Administrator Roles

## Datamation Super Admin

Full platform administrator.

## Datamation Management

Read-only platform analytics/summary role.

## Customer Company Administrator

Responsible only for their own company.

Uses the customer-facing **My Company** administration area plus authorized operational modules.

---

# 2. Logging In

Use the DMIMS login URL.

After login, menus and dashboard content depend on:

- Role
- Customer
- Enabled modules
- Subscription
- License
- Permissions

---

# 3. Dashboard

Shows only relevant authorized information.

Customer users do not see platform customer counts, platform billing totals or platform audit activity.

---

# 4. Platform Customer Management

Available to authorized Datamation platform roles only.

Functions:

- Create customer
- Edit customer
- Suspend/reactivate
- Archive
- View customer details
- Manage subscription/license/billing

Customer users do not access this multi-customer screen.

---

# 5. Customer My Company

Authorized customer administrators use:

**My Company**

## Company Profile

Shows only the logged-in customer's own information.

No customer selector is available.

## Users

Company Admin may manage permitted users belonging to the same company.

Platform users and other-company users must not appear.

Company Supervisor may have only limited view/update functionality.

## Enabled Modules

Shows a read-only summary of modules enabled for the company.

This is not Platform Module Management.

## Subscription

Shows current subscription summary.

Customers do not access Subscription Plans.

## License Status

Shows current license/access status.

Customers do not access administrative License Management.

## Billing

Available only if Billing View is enabled and role permits.

Customers cannot create, issue, cancel or record payment against billing records.

## Audit Logs

Company Admin may see only own-company audit records.

Datamation platform audit activity and other customers' audit records are not visible.

---

# 6. User Management

Platform administrators may manage platform/customer users according to role.

Company Admin manages own customer users only.

Minimum privilege should always be assigned.

---

# 7. Role Assignment

Roles:

- Datamation Super Admin
- Datamation Management
- Company Admin
- Company Supervisor
- Stock Inventory User
- Document Tracking User
- Viewer

Customer administrators must never assign platform-tier roles.

---

# 8. Module Management

Only Datamation Super Admin configures platform/customer module assignments.

Customers may view their enabled modules but do not manage the platform Module catalogue.

---

# 9. Subscription Management

Datamation Super Admin handles:

- Plan assignment
- Renewal
- Limits
- Modules
- Expiry

Customers see only their current subscription summary.

---

# 10. License Management

Datamation Super Admin handles:

- Activation
- Suspension
- Revocation
- Renewal
- Access mode

Customers see only simplified own License Status.

---

# 11. Billing Management

Datamation Super Admin manages billing and manual payments.

Customers have view-only own billing when enabled.

---

# 12. Inventory Administration

Authorized users manage:

- Categories
- Products
- Locations
- Receive In
- Transfer
- Stock Out
- Adjustment
- Reports

Inventory data is customer-scoped.

---

# 13. Document Administration

Authorized users manage:

- Boxes
- Files
- Receive
- Transfer
- Move Out
- Return
- Document reports

Document data is customer-scoped.

---

# 14. Shared Locations

Single hierarchy shared by inventory and documents.

Do not create fake external locations.

---

# 15. Barcode Administration

Barcode lookup, print and scan are role/module controlled.

Cross-customer barcode information is never exposed.

---

# 16. Reports

Reports shown depend on:

- Customer
- Role
- Enabled modules
- Subscription/report entitlement
- License
- Report permission

Stock Inventory User sees Inventory reports only.

Document Tracking User sees Document reports only.

Billing reports require Billing View.

Customer audit reports contain own-customer information only.

Unauthorized reports do not appear in the selector.

---

# 17. Notifications

Customer notifications remain within the customer's boundary.

---

# 18. Audit Logs

Audit records cannot be edited.

Company Admin sees only own customer logs.

Platform administrators follow platform audit permissions.

---

# 19. System Settings

Platform settings are Datamation platform-only.

---

# 20. Backup Verification

Backup/restore is platform administration.

Customer roles do not receive Backup / Restore access.

---

# 21. Routine Tasks

Datamation administrators monitor:

- Platform health
- Backups
- Expiring subscriptions/licenses
- Billing
- Audit

Customer Admin monitors:

- Own users
- Own operational alerts
- Own reports
- Own billing/subscription/license status where permitted

---

# 22. Troubleshooting

## User Cannot Login

Check:

- User status
- Company status
- Subscription
- License
- Role

## Module Missing

Check:

- Customer module assignment
- Role permission
- Subscription entitlement
- License mode

## Report Missing

Check:

- Reports availability
- Required underlying module
- Required permission
- `allowed_reports`
- License mode

## Audit Record Missing for Customer Admin

Confirm the audit record belongs to the same customer.

Platform `customer_id = NULL` logs are intentionally not visible.

---

# 23. Security Best Practices

- Use individual accounts
- Apply least privilege
- Review roles regularly
- Never share platform accounts
- Review customer ownership before role changes
- Report suspicious access
- Do not attempt to bypass hidden navigation via URL

---

# 24. FAQ

### Can a customer see another customer's data?

No.

### Can Company Admin see platform users?

No.

### Can a customer browse Subscription Plans?

No. They may see only their current subscription summary.

### Can a customer manage licenses?

No. They may see only own License Status where permitted.

### Can Company Admin see Datamation audit logs?

No.

### Why is a report not shown?

DMIMS only shows reports allowed by the user's module, role, entitlement and license.

---

# 25. Summary

DMIMS separates platform administration from customer administration and presents customer users only with own-company functions appropriate to their role and entitlement.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Administrator Manual |
| 1.1 | 24 August 2026 | Added My Company workflow and strict customer/platform visibility guidance |
