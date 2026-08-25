# DMIMS Database Dictionary

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# 1. Purpose

Documents database ownership and relationships relevant to the DMIMS application.

The actual Laravel migrations remain authoritative for physical schema.

---

# 2. Access Scope Classification

Use:

- PLATFORM_ONLY
- TENANT_STRICT
- TENANT_WITH_GLOBAL_DEFAULTS

Customer 360 does not change table ownership.

It changes how platform administration navigates customer-owned tables.

---

# 3. Customers Table

`customers` remains the customer master.

Customer is the platform administration aggregate/navigation root for Customer 360.

## Logical Relationships

Customer has many:

- Users
- Departments
- Customer Modules
- Customer Subscriptions
- Subscription Logs where related
- Licenses
- License Logs where related
- Billing Records
- Billing Payments
- Billing Logs
- Locations
- Categories
- Products
- Boxes
- Document Files
- Movement Logs
- Barcode Records
- Audit Logs
- Notifications

Implement Eloquent relationships only where actual foreign keys/schema support them.

---

# 4. Users

Customer users contain `customer_id`.

Platform users are distinguished by platform role/flag.

Customer 360 Users query:

```text
users.customer_id = selected Customer ID
```

Customer My Company query:

```text
users.customer_id = authenticated user's customer_id
```

---

# 5. Modules

`modules` is platform master data.

`customer_modules` is customer-owned assignment data.

Customer 360 Modules uses:

```text
customer_modules.customer_id = selected Customer ID
```

---

# 6. Subscription Plans

Platform master templates.

Not merged into customers.

---

# 7. Customer Subscriptions

Customer-owned.

Customer 360 Subscription filters by selected customer.

Plan relationship remains separate.

---

# 8. Licenses

Customer-owned license records.

Customer 360 full License administration uses selected customer.

Customer-facing License Status remains limited presentation.

---

# 9. Billing Records

Customer-owned.

Customer 360 Billing:

```text
billing_records.customer_id = selected Customer ID
```

---

# 10. Billing Payments

Customer-owned.

Where the table contains `customer_id`, selected customer scope must match.

Payments should also belong to billing records owned by the same customer.

---

# 11. Audit Logs

Customer-specific audit rows:

```text
audit_logs.customer_id = Customer ID
```

Platform-level audit rows may use NULL.

Customer 360 customer audit view must include exact selected customer only.

---

# 12. Notifications

Customer-specific notifications remain customer-owned.

Customer 360 may display selected-customer alerts without changing storage.

---

# 13. Inventory / Document / Barcode

No schema change from Customer 360.

These remain tenant-owned operational tables.

Customer 360 Overview may compute counts/usage from them.

---

# 14. Customer 360 Schema Rule

Do not create a `customer_360` table merely to support the UI.

Customer 360 is composed from existing normalized tables.

If performance requires caching/materialized summary data in future, that requires a separate reviewed architecture/database change.

---

# 15. Same-Customer Integrity

Relationships created/updated from Customer 360 must maintain:

```text
child.customer_id = parent_customer.id
```

Cross-customer child assignment is invalid.

---

# 16. Indexing

Existing customer_id indexes are essential for Customer 360 child queries.

Review indexes for:

- users.customer_id
- customer_modules.customer_id
- customer_subscriptions.customer_id
- licenses.customer_id
- billing_records.customer_id
- billing_payments.customer_id
- audit_logs.customer_id
- notifications.customer_id

---

# 17. Summary

Customer 360 relies on existing normalized customer-owned tables and customer_id indexes.

No database-table merge is required.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Database Dictionary |
| 1.1 | 24 August 2026 | Added explicit scope semantics |
| 1.2 | 25 August 2026 | Added Customer aggregate/Customer 360 relationship and integrity guidance |
