# Project Overview

DMIMS is a multi-tenant Laravel 13 + Filament 5 modular monolith covering inventory, document tracking, billing, licensing, barcode workflows, reporting, and PWA access. It is currently admin-panel-centric (no public API, `routes/web.php` only redirects `/` and `/login` into `/admin`).

Per the Production Readiness Review: the domain model and tenant-isolation approach are solid, but the platform is **not yet production-ready** — the real blockers are operability, correctness under concurrency, delivery discipline, and security hardening, not missing features. Strategy: harden the existing modular monolith incrementally; do not rewrite into microservices.

# Architecture Principles

- Stay a **modular monolith** — code grouped by services and Filament resources, not independently deployable services. Do not split into microservices without a proven need.
- Business logic belongs in service classes (e.g. `StockMovementService`, `DocumentMovementService`, `BackupService`, `ExportService`, `ReportExportService`) — not in controllers/resources.
- Tenant isolation is enforced via the `BelongsToCustomer` trait + per-resource scoping + the global middleware stack (company/subscription/license/user-status gatekeeping). Extend this pattern for any new tenant-owned data; never bypass it.
- Target architecture: the same modular monolith, plus deterministic background job processing, modern telemetry (traces/metrics/logs), and a versioned internal API seam before any external integrations are published.
- If the system ever needs decomposition, the first extraction candidates are report generation, barcode/rendering services, and notification delivery — they're operationally distinct. Keep the transactional inventory/document core together until event contracts and correctness guarantees are mature.

# Coding Standards

- **Never generate sequence/movement/barcode numbers via `count() + 1`** (or any non-atomic read-then-write) — it collides under concurrent requests. Use DB sequences, a row-locked counter table, ULIDs, or DB-generated unique IDs with a stable human-readable display ID.
- **Wrap every multi-step write in `DB::transaction()`**: barcode registration, stock movement, document movement, payment posting, restore operations. Use Laravel's deadlock retry support where contention is expected.
- **Never silently clamp or mask an invalid state.** E.g. `max(0, $stock - $qty)` hides overshipment, double submission, bad transfers, or stale reads — let it fail/surface instead of producing a quietly wrong number.
- **Keep heavy/synchronous work out of the request cycle.** Backups, exports, report generation, barcode image generation, and notification generation must be queued, idempotent jobs with retries, backoff, and dead-letter handling — not inline service calls during a web request.
- Avoid loading full result sets into memory for reports/exports (e.g. repeated `get()->map(...)`); chunk or stream instead.

# UI/UX Standards

Not specified by the production-readiness review — no UI/UX rules were defined. Follow Filament's own conventions until/unless project-specific guidance is added here.

# Database Standards

- Every tenant-owned model must use the `BelongsToCustomer` trait/scoping pattern (`app/Models/Concerns/BelongsToCustomer.php`) — this is the multi-tenancy boundary.
- Never derive IDs/sequence numbers by counting existing rows; use a concurrency-safe strategy (see Coding Standards).
- Multi-row/multi-table writes that must stay consistent (stock, document movement, billing, restores) must be transactional.
- Backups must not remain local-disk-only: move to encrypted object storage with a retention policy, restore verification, and periodic disaster-recovery drills.

# Security Requirements

- **Resolve deployment/security config contradictions before any production deploy.** README and `DEPLOYMENT_GUIDE.md` currently disagree on web server (Nginx vs Apache), TLS termination point, whether direct local HTTP access is allowed, and `SESSION_SECURE_COOKIE` (`true` vs `false`). Pick one authoritative story; if mixed access is a real requirement, isolate it behind a separate admin-access path rather than weakening the default session posture.
- CI must include dependency review (Dependabot for Composer + npm) and CodeQL/SAST — not just a front-end build check.
- Use **OWASP ASVS** as the baseline for verifying auth, session, logging, access-control, and data-protection controls — not just "good intent."
- Backups must be encrypted at rest, with verified restores and periodic DR drills.
- Longer-term, add enterprise controls as compliance/market pressure requires: SSO/SAML/OIDC, SCIM/provisioning hooks, immutable audit export, retention management, legal hold for document records, field-level encryption for sensitive attributes, and a jurisdiction-aligned privacy/compliance matrix.

# Authentication & Authorization

- **2FA must be real or removed.** Today the user model only stores a `two_factor_enabled` boolean with no enrollment, secret provisioning, challenge, or recovery-code flow. Don't expose a security toggle in the UI that isn't backed by a complete implementation.
- Add tenant-aware login throttling and require reauthentication for privileged/sensitive actions.
- Enforce a strong password/reset policy, baselined on the OWASP Authentication and Session Management Cheat Sheets.
- Keep "active"/usable-state logic consistent across the stack: `AccessControlService` treats `trial` as usable, but `EnsureCompanyActive`, `EnsureSubscriptionActive`, `SubscriptionService`, and `LicenseService` only treat `active`/`near_expiry` as usable. Reconcile any new status logic with all of these, not just one — this drift is what causes "works in staging, blocked in production" incidents.
- Extend the existing 7-layer access-control stack (company/subscription/license/user-status middleware + `AccessControlService`) rather than adding parallel ad-hoc checks.

# Input Validation

Not explicitly addressed by the production-readiness review beyond the concurrency/transaction concerns already captured under Coding Standards and Database Standards. No additional input-validation rules were specified — use standard Laravel request validation as the baseline.

# Error Handling & Logging

- Never mask a real failure as a "safe" default (e.g. clamping stock to zero) — invalid states must surface, not get silently absorbed, because hidden invalid states poison trust in reports.
- Audit logging and a health-check route alone are **not** sufficient observability for production operations.
- Instrument HTTP requests, DB calls, background jobs, and critical business workflows (stock movement, barcode scans, report generation, imports, billing, license/subscription transitions) with structured logs, correlation IDs, traces, and metrics, following OpenTelemetry's signal model and semantic conventions.

# Performance

- Move all heavy/synchronous operations (backups, exports, report generation, barcode image generation, notification generation) to background queues with idempotent jobs, retries, backoff, and dead-letter handling.
- Run queue workers via `queue:work` under Supervisor (or equivalent process manager) — **never `queue:listen`** in any serious environment.
- Avoid unbounded in-memory collection of large datasets for reports/exports; chunk or stream instead.

# Accessibility

Not addressed in the production-readiness review — no accessibility requirements were defined for this project. Treat this as an open gap, not as "no requirements exist."

# Testing Requirements

- Maintain and grow the existing automated test suite (59 tests is the documented baseline) — don't regress coverage.
- CI must actually execute `php artisan test` (plus Composer install/validation and PHP lint) on every PR/push, in addition to the existing front-end build check.
- Any fix to the concurrency/transaction issues above (sequence numbering, locking, transactional boundaries) must ship with a test that would fail without the fix.

# Deployment Requirements

- Maintain a **single authoritative deployment guide**; eliminate the current README vs `DEPLOYMENT_GUIDE.md` contradictions (web server, TLS termination, session-cookie security).
- CI/CD pipeline, in order, before any deploy: Composer install → PHP lint → `php artisan test` → front-end build → dependency-review action → CodeQL/SAST.
- CI Node runtime must be Vite-8-compatible (20.19+ or 22.12+) — not Node 18.
- Use a staging environment with a manual-approval gate before production (blue/green or rolling release).
- Never package `vendor/`, `node_modules/`, or bundled runtime binaries into a release artifact.
- Backups: encrypted object storage, retention policy, verified restores, periodic DR drills — not local-disk-only.

# Documentation Requirements

- Keep README.md, CHANGELOG.md and `DEPLOYMENT_GUIDE.md` in sync at all times — their current disagreement on web server/TLS/session-cookie security is a security and operational risk, not a wording nitpick.
- Document the access-control layers, service boundaries, and tenant-scoping pattern so new contributors don't have to reverse-engineer them from code.
- Keep operational runbooks short, testable, and versioned (see list below).

Minimum runbook set:

| Runbook | Must answer |
|---|---|
| Failed deployment | How to stop traffic, roll back artifact, verify DB compatibility |
| Database restore | How to restore safely, validate integrity, communicate blast radius |
| Queue backlog | How to scale workers, inspect poison jobs, retry safely |
| Stock discrepancy | How to identify source movement, reconcile, preserve audit evidence |
| License/subscription lockout | How support validates status without unsafely bypassing controls |
| Barcode incident | How to reissue, trace duplicates, repair registry integrity |
| Security incident | Credential rotation, audit export, log preservation, access isolation, notification |

# Code Review Checklist

- [ ] No `count() + 1` (or equivalent non-atomic read) used to generate any ID/sequence number.
- [ ] Multi-step writes (stock movement, document movement, barcode registration, payment posting, restore) are wrapped in `DB::transaction()`.
- [ ] No invalid state is silently clamped/swallowed — errors surface instead of being masked.
- [ ] New heavy/slow work is queued, not run synchronously inside a request.
- [ ] Tenant scoping (`BelongsToCustomer` + resource scoping) is applied to any new tenant-owned model/resource.
- [ ] New subscription/license/company status logic stays consistent with `AccessControlService`'s definition of usable states across all four consumers (`AccessControlService`, `EnsureCompanyActive`, `EnsureSubscriptionActive`, `SubscriptionService`/`LicenseService`).
- [ ] Deployment-relevant changes are reflected in the single deployment guide, not left to drift.
- [ ] Any new auth/2FA-adjacent UI control is backed by a complete implementation, not just a flag.
- [ ] New/changed tests cover the change; full suite stays green.

# Things Never To Do

- Never generate sequence/movement/barcode numbers via `count() + 1` or similar non-atomic reads.
- Never silently clamp or hide an invalid business state (e.g. stock going negative) instead of surfacing/erroring it.
- Never run backups, exports, or report generation as long synchronous work inside a web request.
- Never use `queue:listen` for production background processing — use `queue:work` under Supervisor.
- Never let deployment docs (README, `DEPLOYMENT_GUIDE.md`) disagree with each other or with actual config on security-relevant settings (web server, TLS termination, `SESSION_SECURE_COOKIE`).
- Never expose a security control (e.g. 2FA toggle) in the UI without a complete backing implementation.
- Never ship `vendor/`, `node_modules/`, or bundled runtime binaries as part of a release/deploy artifact.
- Never deploy without CI having run tests, dependency review, and CodeQL/SAST.
- Never weaken default session security (e.g. `SESSION_SECURE_COOKIE=false`) to work around a deployment constraint — isolate the constrained path instead.

# Definition of Done

A change is production-ready per this review only when:

- Any multi-step/concurrent-sensitive write uses transactions and collision-safe ID generation.
- No business-critical failure mode is hidden by clamping/defaulting instead of surfacing.
- Heavy operations run via queued jobs with retries/backoff/dead-letter handling, not inline in requests.
- CI has run tests, dependency review, CodeQL/SAST, and produced a successful build before merge/deploy.
- Deployment docs and actual configuration agree (web server, TLS, session-cookie security).
- Access-control/tenant-scoping/subscription-state logic stays consistent with `AccessControlService`.
- The test suite covers the change and stays green.
