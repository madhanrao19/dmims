# DMIMS Technical Design Document (TDD)

**Datamation Inventory Management System**  
**Version:** 1.2  
**Updated:** 25 August 2026

---

# 1. Technical Stack

Use current DMIMS stack:

- Laravel 13
- Filament 5
- PHP 8.4+
- MariaDB/MySQL compatible
- Spatie Permission
- Blade/Tailwind/Alpine
- Vite

---

# 2. Core Design Rules

- Reuse existing architecture.
- Business logic stays in services.
- Resource authorization stays server-side.
- Never trust request customer_id.
- Keep tenant isolation.
- No duplicate Customer 360 business logic.

---

# 3. Resource Scope

Use:

- PLATFORM_ONLY
- TENANT_STRICT
- TENANT_WITH_GLOBAL_DEFAULTS

For Customer 360 child resources, TENANT_STRICT is scoped to the selected authorized parent Customer.

---

# 4. Customer 360 Technical Design

## 4.1 CustomerResource

Add a platform View page:

```php
'view' => Pages\ViewCustomer::route('/{record}')
```

or equivalent approved Customer 360 route.

The customer table row should link to ViewCustomer.

## 4.2 ViewCustomer

ViewCustomer is the main platform customer workspace.

Recommended sections/tabs:

- Overview
- Users
- Modules
- Subscription
- License
- Billing & Payments
- Audit Logs
- Activity/Notifications

## 4.3 Embedded Resource Reuse

Where possible, reuse underlying resources:

- UserResource
- CustomerModuleResource
- CustomerSubscriptionResource
- LicenseResource
- BillingRecordResource
- AuditLogResource

Use reusable relation managers/embedded tables/components.

Do not fork their permission logic.

## 4.4 Parent Context Contract

Customer 360 components receive a trusted `Customer $record`.

Child queries must use:

```php
->where('customer_id', $record->getKey())
```

or a relationship from the parent Customer.

Child create/update mutation must set:

```php
$data['customer_id'] = $record->getKey();
```

server-side.

A browser-supplied alternative must be ignored/rejected.

## 4.5 Customer Selector

Do not display a generic Customer selector inside Customer 360 child forms.

The selected Customer is already known.

This reduces both UX friction and cross-customer assignment risk.

---

# 5. Customer Model Relationships

Current model relationships should be extended where useful for Customer 360.

Recommended:

```php
public function billingRecords(): HasMany
{
    return $this->hasMany(BillingRecord::class);
}

public function billingPayments(): HasMany
{
    return $this->hasMany(BillingPayment::class);
}

public function auditLogs(): HasMany
{
    return $this->hasMany(AuditLog::class);
}

public function notifications(): HasMany
{
    return $this->hasMany(Notification::class);
}
```

Only add relationships that match the actual schema.

No migration is needed solely to add an Eloquent relationship when the foreign key already exists.

---

# 6. Platform Navigation

Target platform navigation:

- Dashboard
- Customers
- Platform Users
- Roles & Permissions
- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit Logs
- Backup / Restore
- System Settings

Hide customer-specific top-level navigation after Customer 360 is complete:

- Customer Users
- Customer Modules
- Customer Subscriptions
- Customer Licenses
- Customer Billing
- Customer Payments

Do not delete underlying routes/resources unless there is a separate approved refactor.

They may remain available as internal/deep-link destinations used by Customer 360.

---

# 7. Customer List Design

Customer list should support:

- Search
- Status filter
- Plan/subscription status where efficient
- License status where efficient
- User usage
- Outstanding billing
- Last activity where efficient

Avoid expensive per-row N+1 queries.

Use aggregates/subqueries/eager loading.

---

# 8. Customer 360 Overview Design

Use summary cards rather than full child datasets.

Suggested cards:

- Customer Status
- Subscription
- License
- Enabled Modules
- Users / Limit
- Products / Limit
- Files / Limit
- Boxes / Limit
- Outstanding Billing

Recent Activity should be limited/paginated or capped.

---

# 9. Authorization

## Super Admin

Full Customer 360 access.

## Datamation Management

Read-only Customer 360.

All mutation actions hidden and server-denied.

## Customer Roles

Customer 360 `canAccess()` returns false.

They use My Company.

---

# 10. Embedded Action Authorization

Any embedded Filament action must explicitly preserve the underlying resource's authorization semantics.

Do not assume embedding automatically maps actions to resource `can()`.

Fail closed for unmapped custom actions.

This is especially important for:

- Edit
- Delete
- Record Payment
- Subscription renewal
- License suspend/revoke
- Module enable/disable

---

# 11. Billing/Payment Integration

Customer 360 Billing uses BillingService/PaymentService.

Do not duplicate invoice/payment calculations.

All child records must match selected Customer.

---

# 12. Subscription Integration

Use existing subscription lifecycle/observer/services.

Customer 360 supplies selected Customer context.

Subscription Plan selection remains a platform template choice, but customer ownership is fixed.

---

# 13. License Integration

Use LicenseService.

License management is available only to authorized platform role.

Selected Customer context is fixed.

---

# 14. Module Integration

Use ModuleAccessService/customer_modules.

Module catalogue remains platform-wide.

Assignment is customer-specific.

---

# 15. Audit Integration

Use AuditLogResource/AuditService.

Customer 360 query must be selected-customer only.

---

# 16. Testing Requirements

Add feature/browser tests for:

- Customer list → ViewCustomer navigation
- Every Customer 360 tab
- Parent scope tampering
- Customer A/B child isolation
- User create parent assignment
- Module assignment parent lock
- Subscription parent lock
- License parent lock
- Billing/payment parent lock
- Audit parent scope
- Management read-only
- Tenant users forbidden
- Standalone platform nav consolidation
- My Company regression
- Custom embedded action authorization
- Global search/deep links

---

# 17. Performance Requirements

Customer 360 must remain responsive with large customers.

Use pagination for users, billing and audit.

Do not load every child relation at once.

---

# 18. Security Review

This implementation is High Risk because it touches:

- Authorization
- Tenant isolation
- Customer assignment
- Billing
- Subscription
- Licensing

Require security review before merge.

---

# 19. Definition of Done

Complete only when:

- Code implemented
- Tests pass
- Browser QA passes
- Tenant isolation verified
- No customer-switch bypass
- Static analysis passes
- UI navigation updated
- Docs synchronized
- Conformance gap closed

---

# 20. Summary

Customer 360 is a composition layer over existing DMIMS resources/services, with the Customer record acting as trusted parent context.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial TDD |
| 1.1 | 24 August 2026 | Added resource scopes/My Company |
| 1.2 | 25 August 2026 | Added technical design for Platform Customer 360 |
