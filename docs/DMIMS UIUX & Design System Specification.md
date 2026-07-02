# **DMIMS UI/UX & Design System Specification**

**Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document defines the complete user interface (UI), user experience (UX), and design system standards for DMIMS.

It ensures that every page, form, table, dashboard, and interaction follows a consistent design language across the application.

This document applies to:

* Laravel  
* Filament  
* Progressive Web App (PWA)  
* Desktop  
* Tablet  
* Mobile

---

# **1\. Design Principles**

The DMIMS interface should be:

* Clean  
* Professional  
* Fast  
* Consistent  
* Accessible  
* Mobile-friendly  
* Easy to learn  
* Optimized for daily operational use

The system is used for long periods by office staff, so readability and efficiency take priority over decorative design.

---

# **2\. Design Philosophy**

Every screen should answer three questions immediately:

1. Where am I?  
2. What can I do?  
3. What should I do next?

Users should never need to search for common actions.

---

# **3\. Theme**

Primary Style

Modern Enterprise Dashboard

Inspired by

* Microsoft 365 Admin  
* GitHub  
* Linear  
* Notion  
* Laravel Filament

Avoid excessive gradients, animations, or visual clutter.

---

# **4\. Colour Palette**

| Element | Colour |
| ----- | ----- |
| Primary | \#2563EB |
| Secondary | \#0B1F3A |
| Success | \#16A34A |
| Warning | \#F59E0B |
| Danger | \#DC2626 |
| Information | \#0284C7 |
| Background | \#F5F7FA |
| Card | \#FFFFFF |
| Border | \#E5E7EB |
| Text Primary | \#111827 |
| Text Secondary | \#6B7280 |

---

# **5\. Typography**

Primary Font

Inter

Fallback

System UI

Arial

Sans-serif

Font Sizes

| Purpose | Size |
| ----- | ----- |
| Page Title | 28 px |
| Section Heading | 22 px |
| Card Heading | 18 px |
| Table Header | 14 px |
| Body Text | 14 px |
| Helper Text | 12 px |

---

# **6\. Spacing**

Use an 8-point spacing system.

Examples

8 px

16 px

24 px

32 px

48 px

Avoid arbitrary spacing values.

---

# **7\. Icons**

Use Heroicons (default Filament set).

Examples

Dashboard

Users

Products

Boxes

Reports

Settings

Notifications

Keep icon usage consistent throughout the application.

---

# **8\. Navigation Structure**

## **Platform Navigation**

Dashboard

Customers

Users

Roles & Permissions

Modules

Subscription Plans

Customer Subscriptions

Licenses

Billing

Payments

Reports & Analytics

Audit Logs

System Settings

---

## **Customer Navigation**

Dashboard

Stock Inventory

Document Tracking

Barcode

Reports

Audit Logs

Profile

---

# **9\. Dashboard Design**

Every dashboard should include:

Summary cards

Recent activity

Alerts

Quick actions

Charts

Upcoming renewals

Notifications

The most important information should appear above the fold.

---

# **10\. Summary Cards**

Every summary card contains:

* Icon  
* Label  
* Value  
* Trend (optional)  
* Status colour

Example

Total Products

1,245

\+18 this week

---

# **11\. Tables**

Every table should support:

Search

Sorting

Pagination

Column visibility

Export

Bulk actions (where appropriate)

Responsive layout

Sticky header (recommended)

---

# **12\. Filters**

Filters should appear above tables.

Examples

Status

Category

Location

Date

Customer

Movement Type

Assigned User

Filters should remain visible after refresh where practical.

---

# **13\. Forms**

Forms should be divided into logical sections.

Example

General Information

↓

Location

↓

Additional Details

↓

Audit Information

Do not present more than 10–12 unrelated fields in a single section.

---

# **14\. Required Field Indicators**

Required fields

Red asterisk

Optional fields

No indicator

Validation messages should appear directly beneath the affected field.

---

# **15\. Buttons**

Primary

Blue

Save

Create

Submit

Secondary

Grey

Cancel

Back

Close

Danger

Red

Delete

Revoke

Suspend

---

# **16\. Status Badges**

| Status | Colour |
| ----- | ----- |
| Active | Green |
| Pending | Blue |
| Trial | Blue |
| Near Expiry | Amber |
| Expired | Orange |
| Suspended | Red |
| Revoked | Dark Red |
| Cancelled | Grey |
| Archived | Grey |

Use badges consistently across all modules.

---

# **17\. Confirmation Dialogues**

Require confirmation for:

Delete

Archive

Suspend

Revoke

Move Out

Stock Adjustment

Import

Restore

Display the impact of the action before confirmation.

---

# **18\. Notifications**

Notification types

Success

Information

Warning

Error

Notifications should:

Be concise.

Explain the outcome.

Suggest the next step where appropriate.

---

# **19\. Empty States**

Every empty table should display:

Friendly illustration (optional)

Clear explanation

Primary action

Example

"No products have been created yet."

Button

Create Product

---

# **20\. Loading States**

Display loading indicators during:

Search

Filtering

Saving

Import

Export

Barcode lookup

Avoid blocking the entire interface when only one component is loading.

---

# **21\. Inventory Screens**

Products

Categories

Locations

Receive In

Transfer

Stock Out

Adjustment

Movement History

Each page should have:

Header

Filters

Table/Form

Quick Actions

Recent Activity (where relevant)

---

# **22\. Document Tracking Screens**

Boxes

Files

Receive

Transfer

Move Out

Return

Movement History

Location Lookup

Keep inventory and document layouts visually consistent.

---

# **23\. Barcode Scanner Screen**

Optimised for mobile.

Features

Large scan input

Large action buttons

Current scanned item

Recent scan history

Error feedback

Auto-focus after each successful scan

Support both USB scanners and camera scanners.

---

# **24\. Dashboard Widgets**

Examples

Products

Low Stock

Boxes

Documents

Pending Returns

Recent Stock Movements

Recent File Movements

Subscription Status

License Status

Outstanding Billing

---

# **25\. Responsive Behaviour**

Desktop

Full navigation

Tablet

Collapsible navigation

Mobile

Drawer menu

Stacked cards

Responsive tables

Large touch targets

---

# **26\. Progressive Web App**

PWA requirements

Install prompt

Offline page

App icon

Splash screen

Standalone mode

Responsive layouts

Version 1 remains online-first.

---

# **27\. Accessibility**

Target WCAG 2.1 AA compliance.

Requirements

Keyboard navigation

Visible focus indicators

Colour contrast

Screen reader labels

Descriptive buttons

Accessible forms

Avoid colour-only communication.

---

# **28\. Error Pages**

Provide branded pages for:

401

403

404

419

429

500

503

Each page should explain the issue in plain language and provide navigation back to a safe location.

---

# **29\. Reusable Components**

Create reusable Filament components for:

Status badges

Customer selector

Location selector

Barcode display

Barcode print button

Movement history panel

Audit timeline

Notification panel

Confirmation dialog

Summary cards

Avoid duplicating UI logic.

---

# **30\. Page Layout Standard**

Every page follows this structure:

Page Title

↓

Breadcrumb

↓

Quick Actions

↓

Summary Cards (optional)

↓

Filters

↓

Main Table or Form

↓

Recent Activity (optional)

↓

Audit Information (optional)

---

# **31\. Future Enhancements**

Reserved for future versions:

Dark Mode

Custom themes

Multi-language interface

Custom dashboards

Drag-and-drop widgets

AI assistant sidebar

Voice search

Offline data entry

Advanced barcode camera overlay

---

# **32\. UI Quality Checklist**

Every page should satisfy the following before release:

✓ Consistent layout

✓ Correct colours

✓ Responsive on desktop, tablet and mobile

✓ Accessible labels

✓ Validation messages

✓ Loading indicators

✓ Empty state

✓ Error handling

✓ Keyboard navigation

✓ Matches design system

---

# **33\. Summary**

The DMIMS Design System establishes a consistent, professional user experience across the entire platform.

By following these standards, developers can:

* Build faster through reusable patterns.  
* Deliver a consistent interface across all modules.  
* Reduce user training requirements.  
* Improve accessibility and maintainability.  
* Ensure the application remains scalable as new modules are added.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial UI/UX & Design System Specification |

