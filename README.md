# DMIMS — Datamation Inventory Management System

A centralized, multi-company web platform for managing physical inventory,
archive boxes, document files, storage locations, barcode operations, customer
subscriptions, licenses, manual billing, and reporting. Multiple customer
companies operate in a single system with complete data isolation.

**Deployment model:** Datamation-hosted on-premise web platform.

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4+ · Laravel 13 |
| Admin UI | Filament 5 (Livewire + Blade + Tailwind) |
| Database | MySQL 8 / MariaDB (SQLite for local/testing) |
| Auth / RBAC | Laravel Auth · Spatie Laravel Permission |
| Queue / Cache / Session | Database driver |
| Mobile | Progressive Web App (manifest + service worker) |
| Web server (prod) | Nginx + PHP-FPM, behind Cloudflare |

## Modules

- **Customer & User management** — companies, internal/customer users, statuses.
- **Subscriptions, Licenses & Billing** — plans, enabled modules, usage limits;
  license technical-access modes; manual invoices and payments.
- **Inventory** — categories, products, locations, stock, with guided
  Receive-In / Stock Out / Transfer / Adjust operations and low-stock alerts.
- **Document tracking** — archive boxes and files (File → Box → Location), with
  guided Receive-In / Transfer / Move-Out / Return for files and boxes.
- **Barcode** — generation (`PRD/LOC/BOX/DOC-COMPANYCODE-000001`), central
  registry, printing, and a scan-to-open scanner.
- **Reporting** — 14 named platform/inventory/document reports (CSV; PDF/Excel
  on production).
- **Notifications** — scheduled alerts (low stock, expiries, overdue billing).
- **Audit** — immutable, model-level audit trail of all changes.
- **Import / Export, Backup / Restore, PWA.**

## Security model (defence in depth)

Seven enforced access-control layers: authentication → role/permission →
customer isolation → subscription validity → license validity → module
enablement → operational permission. Customer data is isolated by `customer_id`
(global query scopes); `customer_id` is never trusted from the browser; all
changes are audited; direct-URL access is gated, not just navigation.

Roles: Datamation Super Admin, Datamation Management, Company Admin, Company
Supervisor, Stock Inventory User, Document Tracking User, Viewer.

## Local development

Requirements: PHP 8.4+, Composer, Node.js 20+, and a database (SQLite is fine
locally).

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite (quickest local setup)
touch database/database.sqlite   # then set DB_CONNECTION=sqlite in .env

php artisan migrate
php artisan db:seed                       # demo data + roles (dev only)
# or, roles/permissions only:
php artisan db:seed --class=RolesAndPermissionsSeeder

npm install
npm run build                              # or: npm run dev

php artisan serve                          # http://localhost/admin
```

Create a platform administrator:

```bash
php artisan dmims:create-admin admin@example.com --name="Administrator"
```

The seeded demo login (development only) is `admin@example.com` / `password`.

## Testing

```bash
php artisan test
```

The suite (59 tests) covers tenant isolation, the access-control/license layer,
billing, notifications, barcode/scanner, reporting, import/export, stock and
document operations, the seeder, and that every resource page renders.

## Scheduled tasks

Add the Laravel scheduler to cron (`* * * * * php artisan schedule:run`). It runs:
- `dmims:generate-notifications` — hourly operational alerts.
- `dmims:backup-database` — nightly database backup.

## Production deployment

See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for the full Ubuntu + Nginx +
PHP-FPM + MySQL + Cloudflare setup, including queue worker, scheduler, SSL,
backups, and the optional PDF/Excel/barcode-image libraries.

Key production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_KEY`
(`php artisan key:generate`), MySQL credentials, SMTP mail (required for
password resets), `SESSION_SECURE_COOKIE=true`, and `TRUSTED_PROXIES=*`.

## Documentation

- [CHANGELOG.md](CHANGELOG.md) — full change history.
- [docs/CONFORMANCE_GAP_ANALYSIS.md](docs/CONFORMANCE_GAP_ANALYSIS.md) —
  requirements conformance audit.
- [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) — production operations guide.

## License

Proprietary — © Datamation Group. All rights reserved.
