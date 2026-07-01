# DMIMS — Requirements Conformance & Gap Analysis

Audited the implementation against the authoritative documents (PRD, SAD,
Security & Access Control Matrix, Database Dictionary, TDD) on 2026-06-14.
**Status: all identified gaps remediated** (see CHANGELOG.md). Remaining
divergences are cosmetic (enum naming) or operational config.

Legend: ✅ implemented · WIP partial · ❌ missing

---

## 1. Database schema (TDD §8 / Database Dictionary)

| Table | Status | Notes |
|---|---|---|
| customers, users, modules, customer_modules | ✅ | `users.last_login_at` exists in schema |
| subscription_plans, customer_subscriptions | ✅ | |
| subscription_logs | ✅ | append-only history via observer |
| licenses, license_logs | ✅ | but see License field gaps below |
| billing_records, billing_payments, billing_logs | ✅ | Billing module implemented |
| settings, audit_logs, notifications | ✅ | |
| categories, products, product_location_stocks, stock_movements, stock_alerts | ✅ | |
| location_types, locations, document_types, boxes, document_files, document_movement_logs | ✅ | |
| barcode_registry, barcode_scan_logs | ✅ | `barcode_registry` table-name bug fixed |

**License fields (Dictionary §7):** ✅ `technical_access_mode`
(full / view_only / blocked) added and enforced. The `status` enum has no
literal `revoked` value; revocation is handled via `cancelled` +
`technical_access_mode = blocked` (cosmetic divergence).

**Movement-type enum:** ✅ reconciled (docs → code). The implemented enum is
`opening_balance, stock_in, stock_out, transfer, adjustment, return, disposal`.
The Database Dictionary's Movement Types section now documents each label with
its stored enum value (e.g. Receive In → `stock_in`, Internal Transfer →
`transfer`), so the documentation matches the schema. The values were left
unchanged to avoid a data-affecting schema migration on the production enum.

## 2. Models (TDD §10)
All required models present (SubscriptionLog, BillingRecord/Payment/Log added).

## 3. Services (TDD §11)
| Required | Status |
|---|---|
| CompanyContextService, UserSecurityService, ModuleAccessService | ✅ |
| AccessControlService (TDD §12 — canLogin/canView/canExport/…) | ✅ |
| SubscriptionService, LicenseService | ✅ |
| BillingService, PaymentService | ✅ |
| LocationService, StockMovementService, DocumentMovementService | ✅ |
| BarcodeService, ScannerService | ✅ |
| AuditService, NotificationService, ImportService, BackupService | ✅ |
| ReportExportService | ✅ | 14 named reports (CSV; PDF/Excel when lib present) |

## 4. Middleware (TDD §13) / Access layers (SAD §4)
| Layer | Status |
|---|---|
| 1 Authentication | ✅ |
| 2 Role/permission validation | ✅ `manage X` / `view X` permissions; reads on either, writes on `manage` |
| 3 Customer isolation | ✅ global scopes + Filament scope |
| 4 Subscription validation | ✅ `EnsureSubscriptionActive` (TDD calls it `EnsureSubscriptionValid`) |
| 5 License validation | ✅ `EnsureLicenseAllowsAccess` + `technical_access_mode` |
| 6 Module validation | ✅ enforced in `can()` + nav |
| 7 Operational permission | ✅ permission check in `can()` |

## 5. RBAC — roles & permissions (Security Matrix, TDD §5) — ✅
The seven documented roles are seeded (Datamation Super Admin, Datamation
Management, Company Admin, Company Supervisor, Stock Inventory User, Document
Tracking User, Viewer). Each functional area has a `manage X` (full CRUD) and a
`view X` (read-only) permission; `BaseResource::can()` allows read actions on
either and write actions only on `manage X`, so the matrix's view-only roles
(Management, Viewer) get genuine read access. License `view_only` mode adds a
second, orthogonal read-only tier.

## 6. Module codes (Dictionary §3) — ✅
All six dictionary module codes are present (`stock_inventory`,
`document_tracking`, `barcode_scanning`, `barcode_printing`, `reports`,
`billing_view`) and drive the resources' module gating.

## 7. Functional modules (PRD / SAD / TDD)
| Module | Status | Gap |
|---|---|---|
| Customer / User management | ✅ | suspend/reactivate/archive via `status` |
| Inventory (categories, products, locations, movements, alerts) | ✅ | guided Receive-In / Stock Out / Transfer / Adjust operations via StockMovementService |
| Document tracking (boxes, files, movement logs) | ✅ | guided file/box Receive-In / Transfer / Move-Out / Return via DocumentMovementService; placement FKs made nullable for move-out |
| Barcode | ✅ | `PRD-CODE-000001` generation + registry, Generate/Print actions, Scanner page (scan-to-open + logging). Scannable image needs the barcode lib on PHP 8.4 |
| Subscription | ✅ | `subscription_logs` history missing |
| License | ✅ | enforcement layer + view-only/blocked modes |
| Billing | ✅ | records, invoices, manual payments, immutable logs; CSV report via Export |
| Reporting & analytics | ✅ | 14 named platform/inventory/document reports via a Reports page (CSV; PDF/Excel when lib present) |
| Audit | ✅ | model-level `Auditable` trail + login activity |
| Notifications | ✅ | hourly generator (low stock, subscription/license expiry, billing overdue) + export-completed/import-failed alerts |
| Import/Export | ✅ | CSV import with per-row validation, duplicate detection (in-file + DB), error-file download; CSV export |
| Backup/Restore | ✅ | manual + nightly scheduled backup (dmims:backup-database) + restore |
| PWA | ✅ | manifest, service worker, offline page present |

## 8. Configuration (TDD §29) & security (§30)
✅ `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=database`,
`QUEUE_CONNECTION=database`, isolation, audit, direct-URL protection, ✅
`TRUSTED_PROXIES=*` (Cloudflare). `SESSION_SECURE_COOKIE=false` is intentional
for the reference Cloudflare Tunnel deployment, which is reached over the tunnel
(HTTPS) **and** directly over plain HTTP on localhost/LAN — a forced secure
cookie would break the HTTP path. Set it to `true` only for HTTPS-on-every-path
deployments (see DEPLOYMENT_GUIDE.md). Operational values still set on the
server: real `DB_*` credentials, `php artisan key:generate`, and SMTP mail
(password reset).

---

## Remediation status — all phases complete

| Phase | Scope | Status |
|---|---|---|
| P1 | Security backbone: 7 roles + `manage`/`view` permissions, dictionary module codes, `AccessControlService`, `EnsureLicenseAllowsAccess` + `technical_access_mode`, `TRUSTED_PROXIES` | ✅ |
| P2 | Billing module (records, payments, logs, services, gated resource) | ✅ |
| P3 | Reporting — 14 named reports, CSV/XLSX/PDF | ✅ |
| P4 | Notifications generation + scheduler | ✅ |
| P5 | Barcode generation/registry/scanner + label images | ✅ |
| P6 | `subscription_logs`, scheduled backup, import dedupe/error-file | ✅ |

Also remediated: User mass-assignment, module gating on access, model-level
audit, tenant global scopes, real Backup/Import/Export, the database seeder,
schema/enum/table-name bugs, deprecated Filament components, branding, guided
stock/document operations, the PWA installability fix, and role-based view-only
access. Dependencies updated to Laravel 12 + current packages (CVE-2026-48019
patched).

### Remaining (non-code / cosmetic)
- Movement-type enum naming — ✅ reconciled docs → code (see §1); the Dictionary
  now documents the stored enum values.
- The missing literal `revoked` license status — cosmetic (handled via
  `cancelled` + `technical_access_mode = blocked`).
- Operational `.env` values on the server: DB credentials, `key:generate`, SMTP.
- **Documentation stack references — reconciled.** The root files and the whole
  `/docs` set now document the tested stack — Ubuntu 24.04 + **Apache** +
  **PHP 8.4** + **MariaDB** + Node 22 + Cloudflare Tunnel, on Laravel 13 +
  Filament 5. The SAD, TDD, Developer Getting Started / Handover, Support &
  Maintenance Handbook and RAID Log were updated from the earlier
  Nginx / PHP 8.3 / Laravel 12 / Filament 4 wording. (The 2026-06-14 audit note
  below is retained as historical record and predates the Laravel 13 / Filament 5
  upgrade.)

### Done since the 2026-06-14 audit
- Filament 5 / Laravel 13 / PHP 8.4 upgrade shipped in v2.0.0 (see CHANGELOG).
- Root project files aligned with `/docs` governance and the tested Ubuntu
  deployment in v2.1.0; fixed `AssignRequestContext` fataling on download
  responses.
