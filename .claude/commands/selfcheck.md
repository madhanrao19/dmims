---
description: Self-review the latest work against a completion checklist before calling it done — runs the test suite and linter where available.
argument-hint: [what to check, optional]
allowed-tools: Bash(php artisan test:*), Bash(vendor/bin/pint:*), Bash(git status:*), Bash(git diff:*), Read, Grep, Glob
---
Do NOT declare the task complete until you have actually verified it. Focus (optional): $ARGUMENTS

Work through each item and report the real result of each — no assumptions:

1. **Runs / boots** — no syntax errors, no missing files or imports.
2. **Setup steps** — are migrations, `composer install`/`npm ci`, or a build needed, and are they accounted for?
3. **Security & tenant isolation** — preserved? (`customer_id` never trusted from the request; CSRF, authorization, and validation intact.)
4. **Validation & error handling** — preserved for the changed paths.
5. **Tests** — run `php artisan test`, then `vendor/bin/pint --test`. If nothing covers the change, do a manual smoke check and say so explicitly.
6. **Docs** — updated where behaviour changed? (CHANGELOG, `/docs`, `docs/CONFORMANCE_GAP_ANALYSIS.md`.)
7. **UI checks** — does anything need a browser/screenshot check (Filament resource pages)?

Then return:
- **Checked** — what you verified
- **Passed** — what's confirmed working
- **Failed** — what's broken
- **Fix needed** — the exact change required for each failure
- A final line: `DONE` or `NOT DONE` — with the reason.

Measure "done" against `docs/DEFINITION_OF_DONE.md`.
