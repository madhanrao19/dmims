# DMIMS API & Service Integration Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# 1. API Design Principles

DMIMS APIs must be:

- Authenticated
- Versioned
- Tenant-aware
- Permission-aware
- Module-aware
- Audited
- Secure by default

---

# 2. API Base URL

Use versioned `/api/v1` endpoints as implemented/extended.

Existing endpoint status remains governed by the repository code.

---

# 3. Authentication

Use configured Laravel Sanctum/token architecture for implemented APIs.

Future authentication mechanisms must preserve the same customer context rules.

---

# 4. Headers

Use standard JSON/Authorization headers.

---

# 5. Response Format

Use consistent success/error payloads.

Never reveal another customer's resource existence.

---

# 6. HTTP Status Codes

Use standard HTTP semantics.

Unauthorized = 401.

Authenticated but forbidden = 403.

Use 404 where appropriate to avoid resource enumeration.

---

# 7. Customer Security

Every request resolves:

Authenticated User  
↓  
Platform / Customer Context  
↓  
Resource Scope  
↓  
Permission  
↓  
Subscription  
↓  
License  
↓  
Module  
↓  
Business Rules  
↓  
Database

The client must never determine trusted customer ownership.

## API Resource Scope

### PLATFORM_ONLY

Customer API tokens cannot access platform administration endpoints.

### TENANT_STRICT

Customer API queries use exact authenticated customer:

```text
customer_id = authenticated user's customer_id
```

Never include NULL platform records.

### TENANT_WITH_GLOBAL_DEFAULTS

Global/default records may be returned only when the endpoint specification explicitly permits them.

---

# 8. Product API

Any existing/future product endpoints use TENANT_STRICT.

---

# 9. Inventory Operations API

Receive/transfer/out/adjust operations require:

- Customer ownership
- Inventory module
- Permission
- Subscription/license access
- Transaction
- Movement log
- Audit

---

# 10. Document Tracking API

All file/box APIs are TENANT_STRICT and require Document Tracking permissions.

---

# 11. Barcode API

Lookup/scan must validate customer ownership before returning entity details.

---

# 12. Billing API

Customer-facing billing endpoints, if introduced, are own-customer read-only unless explicitly approved otherwise.

Mutation remains platform administration in Version 1.

---

# 13. Reporting API

Each report is authorized independently.

Required checks may include:

- Authenticated user
- Customer context
- Reports module
- Required operational module
- Required permission
- Effective `allowed_reports`
- License mode
- Tenant ownership

Generic report access does not expose every report family.

Examples:

Inventory report → Inventory access.  
Document report → Document Tracking access.  
Billing report → Billing View + billing permission.  
Audit report → audit permission + exact customer scope.  
Platform reports → Datamation platform roles only.

---

# 14. Notification API

Customer notifications use TENANT_STRICT.

---

# 15. File Upload API

Uploads must validate ownership, permission and file security.

---

# 16. Import API

Imports require exact customer context, module access, permission, limits and audit.

---

# 17. Export API

Exports reuse the same authorization as corresponding report/resource view.

Every export is audited.

---

# 18. Webhooks

Future webhook events must use trusted server-derived customer ownership.

---

# 19. External Integrations

Integrations must use services and must not bypass access-control/business rules.

---

# 20. Mobile Application Support

Mobile clients inherit identical authorization semantics.

---

# 21. API Rate Limiting

Retain configured rate limiting.

Authorization is independent of rate limiting.

---

# 22. API Versioning

Breaking API changes require new API version.

---

# 23. Error Handling

Consistent, machine-readable and non-leaking.

---

# 24. Correlation IDs

Use where implemented/appropriate for audit/troubleshooting.

---

# 25. Future GraphQL

Any GraphQL implementation must preserve per-field/resource tenant authorization.

---

# 26. API Security

Every endpoint must:

- Authenticate
- Resolve context
- Apply resource scope
- Authorize
- Validate
- Execute service rules
- Audit critical actions

---

# 27. Service Integration Principles

External systems never write directly to database tables.

---

# 28. API Lifecycle

Architecture/security review precedes release.

---

# 29. Future Event Architecture

Events must carry trusted server-derived tenant context.

---

# 30. Summary

APIs must enforce the same platform/customer and report/module boundaries as the Filament UI.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial API & Service Integration Specification |
| 1.1 | 24 August 2026 | Added explicit API resource scope and report-family authorization requirements |
