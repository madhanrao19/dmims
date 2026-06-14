# DMIMS — Requirements Conformance & Gap Analysis

Audited the implementation against the authoritative documents (PRD, SAD,
Security & Access Control Matrix, Database Dictionary, TDD) on 2026-06-14.

Legend: ✅ implemented · WIP partial · ❌ missing

---

## 1. Database schema (TDD §8 / Database Dictionary)

| Table | Status | Notes |
|---|---|---|
| customers, users, modules, customer_modules | ✅ | `users.last_login_at` exists in schema |
| subscription_plans, customer_subscriptions | ✅ | |
| **subscription_logs** | ❌ | Required by TDD §8/§10 (immutable history) |
| licenses, license_logs | ✅ | but see License field gaps below |
| billing_records, billing_payments, billing_logs | ✅ | Billing module implemented |
| settings, audit_logs, notifications | ✅ | |
| categories, products, product_location_stocks, stock_movements, stock_alerts | ✅ | |
| location_types, locations, document_types, boxes, document_files, document_movement_logs | ✅ | |
| barcode_registry, barcode_scan_logs | ✅ | `barcode_registry` table-name bug fixed |

**License fields (Dictionary §7):** dictionary specifies `license_status` and
`technical_access_mode` (Full / View Only / Blocked). Actual table uses a single
`status` enum without `revoked` and has no `technical_access_mode`. → schema
change needed for faithful license enforcement.

**Movement-type enum:** dictionary lists `receive_in, stock_out,
internal_transfer, adjustment`; TDD §18 adds `return, disposal, opening_balance`.
Actual enum: `opening_balance, stock_in, stock_out, transfer, adjustment, return,
disposal`. → values diverge (`stock_in` vs `receive_in`, `transfer` vs
`internal_transfer`). Cosmetic but should be reconciled.

## 2. Models (TDD §10)
Missing: **SubscriptionLog**. BillingRecord/BillingPayment/BillingLog now present.

## 3. Services (TDD §11)
| Required | Status |
|---|---|
| CompanyContextService, UserSecurityService, ModuleAccessService | ✅ |
| AccessControlService (TDD §12 — canLogin/canView/canExport/…) | ✅ |
| SubscriptionService, LicenseService | ✅ |
| BillingService, PaymentService | ✅ |
| LocationService, StockMovementService, DocumentMovementService | ✅ |
| BarcodeService | ✅ · **ScannerService** ❌ |
| AuditService, NotificationService, ImportService, BackupService | ✅ |
| **ReportExportService** | WIP `ExportService` exists (CSV); no PDF/Excel, no named reports |

## 4. Middleware (TDD §13) / Access layers (SAD §4)
| Layer | Status |
|---|---|
| 1 Authentication | ✅ |
| 2 Role/permission validation | WIP coarse permissions, not the matrix granularity |
| 3 Customer isolation | ✅ global scopes + Filament scope |
| 4 Subscription validation | ✅ `EnsureSubscriptionActive` (TDD calls it `EnsureSubscriptionValid`) |
| 5 License validation | ✅ `EnsureLicenseAllowsAccess` + `technical_access_mode` |
| 6 Module validation | ✅ enforced in `can()` + nav |
| 7 Operational permission | ✅ permission check in `can()` |

## 5. RBAC — roles & permissions (Security Matrix, TDD §5)
Required roles: **Datamation Super Admin, Datamation Management (read-only),
Company Admin, Company Supervisor, Stock Inventory User, Document Tracking User,
Viewer**.
Current seeder roles: `admin, manager, user`. → **mismatch**.
- Management & Viewer "view-only" not enforced (current permission model grants
  full CRUD once a `manage *` permission is held).
- Permissions are coarse (`manage inventory`) vs the matrix's per-module CRUD.

## 6. Module codes (Dictionary §3)
Required: `stock_inventory, document_tracking, barcode_scanning,
barcode_printing, reports, billing_view`.
Current: `inventory, documents` only. → rename + add the missing four.

## 7. Functional modules (PRD / SAD / TDD)
| Module | Status | Gap |
|---|---|---|
| Customer / User management | ✅ | suspend/reactivate/archive via `status` |
| Inventory (categories, products, locations, movements, alerts) | ✅ data · WIP ops | dedicated Receive-In/Transfer/Out/Adjustment screens are generic CRUD |
| Document tracking (boxes, files, movement logs) | ✅ data · WIP ops | dedicated file/box receive/transfer/move-out/return actions partial |
| Barcode | WIP | registry/scan log exist; no `PRD-CODE-000001` generation, print, or scan-to-open flow (ScannerService) |
| Subscription | ✅ | `subscription_logs` history missing |
| License | ✅ | enforcement layer + view-only/blocked modes |
| Billing | ✅ | records, invoices, manual payments, immutable logs; CSV report via Export |
| Reporting & analytics | WIP | generic CSV export only; no named platform/inventory/document reports, no PDF/Excel |
| Audit | ✅ | model-level `Auditable` trail + login activity |
| Notifications | WIP | table + service exist; **no generation** (low stock, expiry, overdue) or scheduler |
| Import/Export | WIP | CSV import w/ per-row validation ✅; no preview-before-import, duplicate detection, or error-file download |
| Backup/Restore | ✅ manual · WIP | scheduled backup cron not configured |
| PWA | ✅ | manifest, service worker, offline page present |

## 8. Configuration (TDD §29) & security (§30)
✅ `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
`SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, HTTPS, isolation, audit,
direct-URL protection, secure cookies.
❌ `TRUSTED_PROXIES=*` not set (required behind Cloudflare).

---

## Prioritised remediation roadmap

**P1 — Security backbone (TDD §5, §12–16; Security Matrix)**
1. RBAC: create the 7 documented roles with matrix-aligned permissions.
2. Align module codes to the dictionary; add the four missing modules.
3. `AccessControlService` + `EnsureLicenseAllowsAccess`; license field/enum fixes.
4. `TRUSTED_PROXIES=*`.

**P2 — Billing module (PRD §13, SAD §11, Dictionary §8, TDD §8/§10/§11)**
`billing_records`, `billing_payments`, `billing_logs` tables + models +
`BillingService`/`PaymentService` + Filament resources + `billing_view` gating.

**P3 — Reporting (TDD §22)**
Named platform/inventory/document reports with CSV/Excel/PDF (`ReportExportService`).

**P4 — Notifications generation (TDD §24)** + scheduler (low stock, expiry, overdue).

**P5 — Barcode generation/printing/scanner (TDD §21)** + `ScannerService`.

**P6 — History/logs**: `subscription_logs`, `billing_logs`; import preview/dedupe/error-file; scheduled backups.

Items already remediated in prior work: mass-assignment, module gating on
access, model-level audit, tenant global scopes, real Backup/Import/Export,
seeder, schema/enum form bugs, deprecated components, branding.
