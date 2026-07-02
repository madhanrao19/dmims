# Security Policy

DMIMS (Datamation Inventory Management System) is a proprietary, multi-tenant
platform. Security is a first-class requirement — see the
[DMIMS Security & Access Control Matrix](docs/DMIMS%20Security%20&%20Access%20Control%20Matrix.md)
and the [Engineering Constitution](docs/DMIMS%20Engineering%20Constitution.md)
in `/docs` for the authoritative security governance.

## Supported Versions

Only the current `2.x` line receives security updates.

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |
| < 1.0   | :x:                |

See [CHANGELOG.md](CHANGELOG.md) for the release history.

## Reporting a Vulnerability

Please report suspected vulnerabilities **privately** — do not open a public
issue or pull request.

- Email the maintainer at **madhanrao.v@gmail.com** with a description of the
  issue, affected version, and reproduction steps.
- You can expect an acknowledgement within **5 business days** and a status
  update within **10 business days**.
- If accepted, we will work on a fix, coordinate a disclosure timeline with you,
  and credit you if you wish. If declined, we will explain why.

Please do not disclose the issue publicly until a fix has been released.

## Security model (defence in depth)

DMIMS enforces seven access-control layers on every request:
authentication → role/permission → customer isolation → subscription validity →
license validity → module enablement → operational permission.

Key guarantees:

- Customer data is isolated by `customer_id` via global query scopes;
  `customer_id` is **never** trusted from the browser.
- All model changes are recorded in an immutable audit trail.
- Direct-URL access is gated, not just navigation visibility.
- Optional per-user TOTP two-factor authentication is available.
- Backups are encrypted at rest and integrity-verified before being trusted as
  restorable.

## Production security notes

- Run production with `APP_ENV=production` and `APP_DEBUG=false`.
- Never commit `.env` or real secrets to version control.
- TLS is terminated by Cloudflare at the edge (Cloudflare Tunnel). See
  [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for the `SESSION_SECURE_COOKIE`
  and `TRUSTED_PROXIES` guidance specific to this topology.
- Keep dependencies patched (`composer audit`, `npm audit`).
