# DMIMS Test Strategy, QA Plan & UAT Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# 1. Testing Objectives

Verify:

- Functional correctness
- Authorization
- Customer isolation
- Customer 360 parent context
- Existing My Company isolation
- Role behaviour
- Module/subscription/license rules
- Billing integrity
- Regression safety
- Production readiness

---

# 2. Risk Classification

Customer 360 implementation is **High Risk change work** because it touches authorization, tenant context, subscription, license and billing presentation/actions.

The feature itself is primarily an administration/UX improvement, but implementation must receive security review.

---

# 3. Test Data

Create:

- Customer A
- Customer B
- Super Admin
- Datamation Management
- Company Admin A/B
- Supervisor
- Stock User
- Document User
- Viewer
- Subscriptions A/B
- Licenses A/B
- Billing A/B
- Audit A/B
- Platform NULL audit rows

---

# 4. Customer List Tests

Super Admin:

- Sees Customer A and B
- Can search/filter
- Can open each Customer 360

Management:

- Can view permitted customers
- Cannot mutate

Customer roles:

- Cannot access Platform Customers list

---

# 5. Customer 360 Overview Tests

Verify selected-customer summary only.

Customer A overview must not contain Customer B:

- Counts
- Billing
- Audit
- Subscription
- License
- Usage

---

# 6. Users Tab Tests

Inside Customer A:

- List contains A users only
- Platform users absent
- B users absent
- Create User automatically assigns A
- Crafted customer_id=B is rejected/overwritten
- Edit cannot transfer user to B

---

# 7. Modules Tab Tests

Inside Customer A:

- Shows A assignments only
- Enable/disable affects A only
- Crafted B customer assignment fails
- Module Catalogue remains unchanged

---

# 8. Subscription Tab Tests

Inside Customer A:

- Shows A current/history only
- Renewal remains A
- Plan selection allowed from platform plans
- No arbitrary customer selector
- B subscription cannot be accessed through A context

---

# 9. License Tab Tests

Inside Customer A:

- Shows A license/history only
- Renew/suspend/revoke affects A only
- No cross-customer assignment
- Management cannot mutate

---

# 10. Billing & Payments Tests

Inside Customer A:

- Only A invoices/payments
- Record payment affects A invoice
- Direct B invoice ID denied
- Outstanding totals use A only
- Management read-only

---

# 11. Audit Tests

Inside Customer A:

```text
audit_logs.customer_id = A
```

Exclude:

- Customer B logs
- Platform NULL logs

Filters must not leak other-customer module/action data.

---

# 12. Navigation Tests

After implementation, Super Admin primary sidebar should not require separate entries for:

- Customer Users
- Customer Modules
- Customer Subscriptions
- Customer Licenses
- Customer Billing
- Customer Payments

Remain separate:

- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit
- Backup / Restore
- System Settings

---

# 13. Direct URL / Deep Link Tests

Attempt:

- Customer A profile with B child ID
- Unauthorized child resource routes
- Customer role access to Customer 360
- Management mutation routes

Expected denial.

---

# 14. Embedded Filament Action Tests

Verify every embedded action maps to correct underlying resource authorization.

Custom actions must fail closed.

---

# 15. Global Search Tests

Customer 360 changes must not make customer-owned child records globally searchable outside authorization.

Customer roles must not discover Platform Customer 360 records.

---

# 16. My Company Regression

All existing My Company tests must continue to pass.

Customer Admin remains own-company only.

Customer 360 is not visible to customer roles.

---

# 17. Operational Regression

Inventory, Documents, Barcode, Reports, Imports/Exports remain functional.

---

# 18. Browser / Playwright QA

Required browser scenarios:

1. Super Admin opens Customer A.
2. Navigate every Customer 360 tab.
3. Create/edit allowed child records.
4. Confirm customer context never changes.
5. Management opens same profile read-only.
6. Customer user receives 403/404 for Customer 360.
7. Sidebar consolidation correct.
8. My Company still correct.
9. Mobile/tablet tab navigation acceptable where supported.

---

# 19. Performance Tests

Check:

- Customers list with many customers
- Overview query count
- Paginated users/billing/audit
- No N+1 on status/plan/license columns

---

# 20. Security Review Checklist

- Parent route authorization
- Child scope
- Mass assignment
- IDOR
- Hidden customer_id manipulation
- Embedded action authorization
- Cross-customer relation selectors
- Billing mutation
- Subscription/license mutation
- Audit leakage

---

# 21. UAT

Business UAT confirms:

- Customer list is easier to operate
- Customer profile contains required customer information
- No need to jump between separate customer-specific menus
- Customer context is clear
- Customer management tasks are efficient
- Platform master areas remain easy to find

---

# 22. Release Ready

Customer 360 is release-ready only when:

- All tests pass
- Browser QA passes
- Security review passes
- No Critical/High issue remains
- Documentation synchronized
- Conformance gap closed

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial QA/UAT Specification |
| 1.1 | 24 August 2026 | Added strict tenant/My Company/report authorization tests |
| 1.2 | 25 August 2026 | Added Customer 360 parent-context, navigation and embedded-action test requirements |
