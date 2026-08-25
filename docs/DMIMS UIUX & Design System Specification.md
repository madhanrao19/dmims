# DMIMS UI/UX & Design System Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.3  
**Updated:** 25 August 2026

---

# 1. Design Principles

DMIMS UI is:

- Clean
- Professional
- Fast
- Consistent
- Accessible
- Customer-centric
- Least-privilege by presentation

---

# 2. Platform Navigation

Recommended Platform navigation:

Dashboard

Customers

Module Catalogue

Subscription Plans

Reports & Analytics

Platform Audit Logs

Backup / Restore

System Settings

Customer-specific administration is accessed through Customers → selected Customer.

**"Platform Users" is not a separate top-level nav item.** `UserResource` (the
only user-management resource — it serves both platform staff and tenant
users, distinguished by `customer_id`) sets `$consolidatedViaCustomer360 =
true`, which per the 25 August 2026 Customer 360 design review
(`docs/CONFORMANCE_GAP_ANALYSIS.md` §1, item 10) hides its standalone
top-level nav for platform users too, not only tenant users — a deliberate,
test-locked decision (`tests/playwright/role-qa.spec.js`: "old standalone
nav items are gone, routes still work"). The route (`/admin/users`) still
works and is reachable from Customer 360's Users tab for a given customer's
tenant users. **Known gap:** there is currently no navigation path to list
or manage platform staff accounts (`customer_id IS NULL`) — a Super Admin
must know the direct `/admin/users` URL. Not fixed here to avoid regressing
the tested consolidation; flagged for a future dedicated "Platform Users"
view scoped to `customer_id IS NULL` if this becomes a real workflow need.

**"Roles & Permissions" is not implemented** as a Filament resource (no
`RoleResource`/`PermissionResource` exists). Roles and permissions
(Spatie Laravel Permission) are currently managed only via
`RolesAndPermissionsSeeder`, not an admin UI. Removed from the list above;
tracked as an unimplemented feature, not a navigation defect.

---

# 3. Customers List

Customers is the primary platform customer-management landing page.

Recommended columns:

| Column | Purpose |
|---|---|
| Company | Name |
| Code | Customer code |
| Status | Active/Suspended/etc. |
| Plan | Current plan |
| Subscription | Status/expiry |
| License | Status/expiry |
| Users | Used / limit |
| Outstanding | Billing balance |
| Last Activity | Recent activity |

Rows should be clickable or include a clear **View Customer** action.

---

# 4. Customer 360 / Customer Profile

Opening a customer displays a single customer-focused workspace.

Header should show:

- Company name
- Company code
- Status badge
- Key contact
- Quick actions allowed to role

Recommended tabs:

**Overview | Users | Modules | Subscription | License | Billing & Payments | Audit Logs**

Optional:

**Activity / Notifications**

---

# 5. Customer 360 Overview

Use summary cards.

Recommended:

- Customer Status
- Subscription
- License
- Enabled Modules
- Users
- Products
- Document Files
- Boxes
- Outstanding Billing
- Recent Activity

Use clear warning badges for:

- Near expiry
- Expired
- Suspended
- Overdue billing
- Usage limit reached

---

# 6. Users Tab

Shows selected-customer users only.

Do not display customer selector.

Create User action automatically applies selected customer.

---

# 7. Modules Tab

Shows selected-customer module assignments.

Module catalogue details may be referenced but assignment is fixed to selected customer.

---

# 8. Subscription Tab

Shows:

- Current plan
- Status
- Valid dates
- Limits
- Usage
- Enabled modules
- Billing cycle
- History

Super Admin may perform permitted renewal/change actions.

Do not ask user to select customer again.

---

# 9. License Tab

Shows:

- License number where appropriate
- Status
- Access mode
- Validity
- Technical details for Super Admin where authorized
- History

Customer is fixed to selected parent.

---

# 10. Billing & Payments Tab

Shows:

- Invoices
- Billing status
- Payment status
- Total
- Paid
- Outstanding
- Due date
- Payment history

Super Admin actions may include record payment, issue/cancel invoice as currently permitted.

Customer is fixed to parent.

---

# 11. Audit Logs Tab

Shows selected-customer audit logs only.

Use filters for:

- Date
- Module
- Action
- User

Do not include unrelated platform NULL logs.

---

# 12. Platform Navigation Consolidation

Once Customer 360 is complete, do not show duplicate primary navigation for:

- Customer Users
- Customer Modules
- Customer Subscriptions
- Customer Licenses
- Customer Billing
- Customer Payments

These functions remain reachable contextually inside Customer 360.

---

# 13. Top-Level Items That Remain

Keep:

- Module Catalogue
- Subscription Plans
- Reports & Analytics
- Platform Audit
- Backup / Restore
- System Settings

These have platform-wide meaning. **Platform Users and Roles & Permissions
are deliberately not top-level items — see §2** (Platform Users is
consolidated/reachable only via direct URL or Customer 360; Roles &
Permissions has no admin UI at all).

---

# 14. Customer My Company

Customer-facing My Company remains:

- Overview/Profile
- Users
- Enabled Modules
- Subscription
- License Status
- Billing
- Audit Logs where permitted

Do not expose Platform Customer 360 navigation to customer roles.

---

# 15. Breadcrumbs

Example:

```text
Customers > ABC Manufacturing > Billing & Payments
```

The selected customer should remain obvious throughout the workspace.

---

# 16. Quick Actions

Customer 360 may offer:

- Edit Customer
- Add User
- Renew Subscription
- Renew/Suspend License
- Create Invoice

Only display allowed actions.

---

# 17. Forms

Within Customer 360:

- Customer field is not user-selectable.
- Customer context is displayed as read-only label if needed.
- Server-side context remains authoritative.

---

# 18. Responsive Behaviour

Customer 360 tabs may collapse into mobile/tablet tab menu.

Security/navigation semantics must remain identical.

---

# 19. Accessibility

Target WCAG 2.1 AA.

Tabs, badges and actions must be keyboard/screen-reader accessible.

---

# 20. Empty States

Each tab provides a useful customer-context action where permitted.

Example:

"No users have been created for ABC Manufacturing."

Button:

"Add User"

---

# 21. Error States

403/404 must not disclose unauthorized customer data.

Parent Customer not found/unauthorized returns a safe error.

---

# 22. Performance UX

Load Overview quickly.

Lazy-load/paginate heavy child lists.

Do not render thousands of audit/billing rows at once.

---

# 23. UI Quality Checklist

- One Customers entry for customer administration
- Customer row opens Customer 360
- Customer name always visible
- Correct tabs
- No duplicate customer selectors
- No duplicate customer-specific sidebar items
- Platform master items remain separate
- Management role read-only
- Customer roles cannot access Customer 360
- My Company still works
- Embedded actions authorized
- Responsive
- Accessible

---

# 24. Summary

Customer 360 reduces navigation clutter and makes customer administration faster while preserving the same underlying authorization and business architecture.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial UI/UX Specification |
| 1.1 | 24 August 2026 | Added My Company/access-aware navigation |
| 1.2 | 25 August 2026 | Added Platform Customer 360 and consolidated platform customer navigation |
| 1.3 | 25 August 2026 | Production UI/UX audit: reconciled §2/§13 with the actual tested nav (Platform Users has no standalone entry; Roles & Permissions was never implemented); added custom-branded 401/403/404/419/429/500/503 error pages |
