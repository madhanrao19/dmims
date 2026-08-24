# DMIMS Test Strategy, QA Plan & UAT Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# 1. Testing Objectives

Verify:

- Business requirements
- Customer isolation
- Platform/customer boundary
- Authorization
- Module entitlement
- Subscription/license behaviour
- Report filtering
- Security
- Stability
- Production readiness

---

# 2. Testing Principles

Test business rules and HTTP/browser enforcement, not only code structure.

Every authorization fix requires regression coverage.

---

# 3. Test Levels

- Unit
- Feature
- Integration
- System
- Browser / Playwright
- UAT
- Regression
- Security review

---

# 4. Test Environments

Development, QA/Staging, UAT and Production.

Never use production customer data in non-production without explicit approval.

---

# 5. Test Data

Minimum security dataset:

- Customer A
- Customer B
- Platform user(s)
- Company Admin
- Supervisor
- Stock User
- Document User
- Viewer
- Active/expired subscription cases
- Active/suspended license cases
- Platform `customer_id = NULL` audit rows
- Customer A/B audit rows
- Platform and tenant users
- Inventory/document/billing records

---

# 6. Test Categories

Functional, security, performance, usability, compatibility and accessibility.

---

# 7. Unit Test Requirements

Critical access services and report authorization require focused unit tests.

---

# 8. Feature Test Requirements

Include:

- Login
- Customer creation
- User management
- Product/document workflows
- Billing
- Subscription/license
- Audit
- Reports
- Direct authorization paths

---

# 9. Security Test Cases

Verify:

- Invalid login
- Locked/suspended user
- Cross-company access
- Missing permissions
- Disabled module
- Expired subscription
- Suspended/revoked license
- CSRF
- XSS
- IDOR
- Privilege escalation
- Platform/customer boundary

---

# 10. Customer Isolation Tests

Customer A cannot:

- View Customer B
- Edit Customer B
- Export Customer B
- Search Customer B
- Select Customer B in relationship fields
- Reach Customer B by direct URL

Super Admin retains authorized platform visibility.

---

# 11. Customer Presentation and Platform Isolation Tests

Customer roles must not see:

- Platform Customers list
- Platform Users
- Roles & Permissions
- Module catalogue
- Subscription Plans
- License Management
- Backup / Restore
- Platform Settings
- Platform reports
- Platform audit logs

Direct URL attempts must fail.

## TENANT_STRICT Verification

For each TENANT_STRICT resource, verify Customer A retrieves only Customer A records.

Specifically:

- Company Admin cannot see Customer B users.
- Company Admin cannot see `customer_id = NULL` platform users.
- Company Admin cannot see Customer B audit logs.
- Company Admin cannot see `customer_id = NULL` platform audit logs.
- Customer A cannot see Customer B subscription/license/billing.
- Global search cannot reveal these records.

---

# 12. My Company UAT

## Company Admin

Verify:

- Own Profile
- Own Users
- Enabled Modules
- Subscription Summary
- License Status
- Billing when enabled
- Own Audit

## Supervisor

Verify:

- Own Profile
- Limited Users
- Enabled Modules
- Subscription Summary
- License Status
- Billing when enabled
- No Audit Logs by default

## Operational Users

Verify no unrelated My Company administration.

---

# 13. Inventory Workflow Tests

Receive, Transfer, Out, Adjustment.

Verify quantity, ownership, history and audit.

---

# 14. Document Workflow Tests

Receive, Transfer, Move Out, Return.

Verify customer ownership, history and audit.

---

# 15. Barcode Tests

Verify:

- Generation
- Uniqueness
- Unknown barcode
- Cross-company denial
- Permission checks
- Scan history

---

# 16. Billing Tests

Verify:

- Super Admin mutation only
- Customer own-view only
- Billing View gating
- Direct mutation attempts denied
- Correct tenant scope

---

# 17. Subscription Tests

Verify:

- Plan assignment
- Limits
- Renewal
- Grace
- Module sync
- Customer cannot browse Subscription Plans
- Customer sees own summary only

---

# 18. License Tests

Verify:

- Active/view-only/blocked
- Renewal
- Suspension/revocation
- Customer cannot open management resource
- Customer sees simplified own status only

---

# 19. Audit Tests

Verify immutable audit creation.

Customer Admin sees only exact own-customer logs.

Test explicit platform NULL row and other-customer row exclusions.

---

# 20. Report Authorization Tests

Test selector and direct execution independently.

## Stock Inventory User

Allowed: authorized Inventory reports.

Denied:

- Document
- Billing
- Audit
- Platform reports

## Document Tracking User

Allowed: authorized Document reports.

Denied:

- Inventory
- Billing
- Audit
- Platform reports

## Company Admin

Verify:

- Inventory only if enabled/permitted
- Document only if enabled/permitted
- Billing only if Billing View enabled
- Audit only when authorized
- `allowed_reports` enforced
- All report rows own-customer only

## Direct Request

Manually submit unauthorized report code.

Expected:

403 Forbidden.

UI filtering alone is not a passing test.

---

# 21. Import & Export Tests

Verify permission, module, license, tenant ownership, report entitlement and audit.

---

# 22. Performance Targets

Keep existing project performance targets and benchmark after authorization changes.

---

# 23. Browser Compatibility

Chrome, Edge, Firefox, Safari where supported.

---

# 24. Mobile / PWA Testing

Verify mobile navigation does not expose hidden platform functions.

---

# 25. Accessibility Testing

Target WCAG 2.1 AA.

---

# 26. Defect Severity

Critical and High issues block release.

Authorization and tenant leakage are High/Critical depending on exploitability.

---

# 27. Production Readiness Checklist

Before release:

- Migrations pass
- Seeders pass
- Unit/feature/browser tests pass
- Pint/static analysis pass
- No Critical/High gaps
- Tenant isolation verified
- Report authorization verified
- Platform/customer navigation verified
- Audit visibility verified
- Backup/restore verified
- Docs synchronized

---

# 28. Release Approval

Development, QA, Product Owner and Operations as defined in governance.

---

# 29. Definition of Release Ready

No unresolved Critical/High issue.

All access-control acceptance tests must pass.

---

# 30. Summary

Testing must prove that customer users see only own-customer, role/module/entitlement-appropriate functions and that hidden functionality remains inaccessible through direct technical paths.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial Test Strategy, QA Plan & UAT Specification |
| 1.1 | 24 August 2026 | Added platform isolation, My Company and report-family authorization regression coverage |
