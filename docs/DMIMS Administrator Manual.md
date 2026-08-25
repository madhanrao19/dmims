# DMIMS Administrator Manual

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# 1. Administrator Roles

## Datamation Super Admin

Full platform administrator.

Uses Customer 360 for customer-specific administration.

## Datamation Management

Read-only platform analytics and customer review.

## Customer Company Administrator

Uses My Company for own-company administration.

---

# 2. Platform Customer Management

From the Platform sidebar select:

**Customers**

The Customers page lists all customers available to the platform role.

Search/filter as needed.

Click the customer name or View action.

This opens the customer's:

**Customer 360 / Customer Profile**

---

# 3. Customer 360 Overview

Overview summarizes:

- Company status
- Contact information
- Subscription
- License
- Enabled modules
- Usage/limits
- Outstanding billing
- Recent activity
- Alerts

Use this page as the starting point before customer-specific administration.

---

# 4. Customer Users

Open:

```text
Customers → Customer → Users
```

Functions for Super Admin may include:

- Create user
- Edit user
- Deactivate user
- Reset password
- Assign customer role

The customer is already selected.

Do not choose another customer.

---

# 5. Customer Modules

Open:

```text
Customers → Customer → Modules
```

Enable/disable customer module assignments according to subscription/business rules.

The platform Module Catalogue remains separate.

---

# 6. Customer Subscription

Open:

```text
Customers → Customer → Subscription
```

View:

- Current plan
- Status
- Validity
- Limits
- Usage
- Modules
- Billing cycle
- History

Perform permitted renewal/change actions.

Subscription Plans remain a separate platform template catalogue.

---

# 7. Customer License

Open:

```text
Customers → Customer → License
```

Super Admin may perform authorized:

- Create
- Renew
- Suspend
- Revoke
- Reactivate
- Access-mode changes

The selected customer is fixed.

---

# 8. Customer Billing & Payments

Open:

```text
Customers → Customer → Billing & Payments
```

View/manage permitted:

- Invoices
- Billing status
- Outstanding balance
- Payment history
- Manual payment records

No online payment gateway is used in Version 1.

---

# 9. Customer Audit Logs

Open:

```text
Customers → Customer → Audit Logs
```

Shows only audit events belonging to that customer.

Use filters for module/action/date/user.

Platform-level audit remains available separately to authorized platform roles.

---

# 10. Platform Users

Datamation platform users remain separate from Customer Users.

Use the dedicated Platform Users area for internal Datamation accounts.

---

# 11. Roles & Permissions

Remain platform-level.

Assign least privilege.

Customer roles must never receive platform-tier privileges.

---

# 12. Module Catalogue

Platform-wide module master.

Customer assignment is performed from Customer 360 → Modules.

---

# 13. Subscription Plans

Platform-wide plan templates.

Customer assignment/renewal is performed from Customer 360 → Subscription.

---

# 14. Reports & Analytics

Remain separate because reports may span customers.

Use customer filters where permitted.

Customer-specific follow-up can return to Customer 360.

---

# 15. Platform Audit Logs

Platform-wide audit view remains separate.

Customer 360 Audit shows only selected customer.

---

# 16. Backup / Restore

Platform administration only.

---

# 17. System Settings

Platform administration only.

---

# 18. Customer My Company

Customer roles use My Company, not Platform Customer 360.

Company Admin may see own:

- Profile
- Users
- Enabled Modules
- Subscription summary
- License status
- Billing
- Audit where permitted

---

# 19. Troubleshooting

## Wrong customer data appears inside Customer 360

Treat as a security incident.

Stop the affected workflow and report immediately.

## User creation asks for arbitrary customer inside Customer 360

This is not the intended design.

Customer should be inherited from the selected parent.

## Duplicate customer management menus remain

Verify Customer 360 navigation implementation and resource navigation flags.

## Reports missing

Check role/module/report entitlement/license.

---

# 20. Security Best Practices

- Confirm selected customer before high-impact actions.
- Never use one customer's Customer 360 to modify another customer.
- Do not share platform accounts.
- Review audit logs after subscription/license/billing changes.
- Apply least privilege.

---

# 21. Daily Platform Administration

Recommended:

- Review platform alerts
- Review expiring subscriptions/licenses
- Review overdue billing
- Review customer issues
- Use Customer 360 for customer-specific follow-up
- Verify backups/health

---

# 22. Summary

Customer 360 is the standard Super Admin workflow for customer-specific management.

Platform master/configuration areas remain separate.

Customer users continue using My Company.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Administrator Manual |
| 1.1 | 24 August 2026 | Added My Company/access boundary |
| 1.2 | 25 August 2026 | Added Super Admin Customer 360 operating workflow |
