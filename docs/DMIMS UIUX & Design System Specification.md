# DMIMS UI/UX & Design System Specification

**Datamation Inventory Management System (DMIMS)**  
**Version:** 1.1  
**Updated:** 24 August 2026

---

# 1. Design Principles

DMIMS should be:

- Clean
- Professional
- Fast
- Consistent
- Accessible
- Mobile-friendly
- Easy to learn
- Optimized for operational work
- Least-privilege by presentation

Users should not be shown irrelevant platform functions.

---

# 2. Design Philosophy

Every screen should answer:

1. Where am I?
2. What can I do?
3. What should I do next?

Navigation must reflect actual authorization.

---

# 3. Theme

Modern enterprise dashboard.

Use the documented DMIMS theme and avoid unnecessary visual clutter.

---

# 4. Colour Palette

Use existing DMIMS palette and status conventions.

---

# 5. Typography

Use Inter/system UI with readable enterprise sizing.

---

# 6. Spacing

Use consistent 8-point spacing.

---

# 7. Icons

Use Heroicons consistently.

---

# 8. Navigation Structure

## Platform Navigation

Authorized Datamation users may see platform items such as:

- Dashboard
- Customers
- Users
- Roles & Permissions
- Modules
- Subscription Plans
- Customer Subscriptions
- Licenses
- Billing
- Reports & Analytics
- Audit Logs
- Backup / Restore
- System Settings

Actual visibility remains role-aware.

## Customer Navigation

Recommended:

Dashboard

My Company  
├── Overview  
├── Users  
├── Enabled Modules  
├── Subscription  
├── License Status  
├── Billing  
└── Audit Logs  

Stock Inventory  
├── Categories  
├── Products  
├── Locations  
├── Receive In  
├── Transfer  
├── Stock Out  
├── Adjustment  
└── Stock Reports  

Document Tracking  
├── Locations  
├── Boxes  
├── Document Files  
├── File / Box Movements  
└── Document Reports  

Barcode  
├── Scanner  
├── Registry  
└── Printing  

Reports

Profile / Account

Not every role sees every item.

### Customer Navigation Rules

- Hide My Company tabs the role cannot access.
- Hide Stock Inventory when disabled/unpermitted.
- Hide Document Tracking when disabled/unpermitted.
- Hide Barcode functions according to module/permission.
- Hide Billing unless Billing View is enabled.
- Hide Reports when unavailable.
- Never show platform administration to customer roles.

## Platform-Only Navigation

Customer users must never see:

- Multi-customer Customers
- Platform Users
- Roles & Permissions management
- Module catalogue management
- Subscription Plans
- Customer Subscription administration
- License Management
- Platform Billing administration
- Backup / Restore
- Platform Settings
- Platform reports
- Platform audit logs

---

# 9. Dashboard Design

Widgets are role/module aware.

Do not show empty or unauthorized module cards merely because the widget exists.

---

# 10. Summary Cards

Use icon, label, value and status.

Customer cards use own-customer data only.

---

# 11. Tables

Support:

- Search
- Sort
- Pagination
- Filters
- Responsive layout

Export is shown only when authorized.

---

# 12. Filters

Customer users must not receive multi-customer selectors.

Platform-only customer filters are restricted to Datamation platform roles.

---

# 13. Forms

Forms are logically grouped.

Customer ownership fields are hidden/locked where derived server-side.

Do not expose platform fields to customer users.

---

# 14. Required Fields

Use clear validation.

Server-side authorization and validation remain authoritative.

---

# 15. Buttons

Only render actions the user can execute.

Backend must still authorize each action.

---

# 16. Status Badges

Use consistent documented status colours.

---

# 17. Confirmation Dialogues

Require confirmation for destructive/high-impact actions.

---

# 18. Notifications

Messages should be concise and should not disclose unauthorized entity existence.

---

# 19. Empty States

Empty states should offer only actions the user may perform.

---

# 20. Loading States

Use component-level loading where practical.

---

# 21. Inventory Screens

Shown only when Inventory is enabled and role permits.

---

# 22. Document Tracking Screens

Shown only when Document Tracking is enabled and role permits.

---

# 23. Barcode Scanner

Mobile-friendly.

Do not reveal barcode details belonging to another customer.

---

# 24. Dashboard Widgets

Examples:

- Products
- Low Stock
- Boxes
- Documents
- Returns
- Subscription Status
- License Status
- Own Billing

Show only relevant authorized widgets.

---

# 25. Responsive Behaviour

Authorization does not change between desktop, tablet and mobile.

Hidden desktop navigation must remain hidden in mobile drawer/PWA.

---

# 26. PWA

Installable and online-first.

PWA navigation follows exactly the same role/module rules.

---

# 27. Accessibility

Target WCAG 2.1 AA.

---

# 28. Error Pages

401/403/404/419/429/500/503 must not disclose sensitive resource details.

---

# 29. Reusable Components

Recommended reusable components include:

- Status badge
- Customer-scoped selector
- Location selector
- Barcode display
- Movement history
- Audit timeline
- Notification panel
- Summary cards
- My Company tabs

---

# 30. Page Layout Standard

Use:

Page Title  
↓  
Breadcrumb  
↓  
Allowed Quick Actions  
↓  
Summary Cards  
↓  
Filters  
↓  
Main Content

---

# 31. Report Selector UX

The selector must contain only reports the user may execute.

### Stock Inventory User

Inventory reports only.

### Document Tracking User

Document reports only.

### Company Admin

Only enabled/authorized operational report families.

Billing reports only with Billing View.

Audit reports only when authorized and always own-customer.

Prefer complete omission of unauthorized report types.

---

# 32. UI Quality Checklist

Before release:

- Correct role navigation
- Correct module navigation
- No platform items visible to customer roles
- My Company tabs filtered
- No customer selector for tenant users
- Report selector filtered
- Direct access protection separately tested
- Responsive
- Accessible
- Consistent

---

# 33. Summary

DMIMS UI presents only functions the authenticated user is entitled to use while backend authorization independently enforces the same boundary.

---

# Document History

| Version | Date | Description |
|---|---|---|
| 1.0 | June 2026 | Initial UI/UX & Design System Specification |
| 1.1 | 24 August 2026 | Added role-aware My Company, platform-only customer boundary and filtered reports/navigation |
