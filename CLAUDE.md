# CLAUDE.md

# DMIMS AI Engineering Rules

Before doing any task:

1. Read this file first.
2. Read `/docs` before editing.
3. Inspect the existing project before changing code.
4. Make the smallest safe production-ready change.
5. Reuse existing components and dependencies.
6. Do not create unnecessary abstractions.
7. Preserve security, validation, accessibility, tenant isolation, and error handling.
8. Do not expose secrets.
9. Do not break existing working features.
10. Verify everything before saying done.
11. Use Playwright for website QA.
12. Fix bugs found during testing.
13. Update documentation if behavior changes.
14. Never say “production ready” unless verified.


`/docs` is the source of truth for DMIMS. Before starting any task, read and
follow these governance documents, in order — they define the engineering
standards for DMIMS and override your default behaviour:

1. `docs/DMIMS Engineering Constitution.md`
2. `docs/DMIMS Project Governance.md`
3. `docs/DEFINITION_OF_DONE.md`
4. `docs/CONFORMANCE_GAP_ANALYSIS.md`

For deeper context, the full document set (SAD, TDD, Business Rules, Security &
Access Control Matrix, Database Dictionary, Deployment/Operations Guide, etc.)
also lives in `/docs`. Production deployment specifics are in
`DEPLOYMENT_GUIDE.md`.

---

# How to work

Operate in a **Continuous Engineering Loop**: Understand → Discover →
Prioritize (Critical → High → Medium → Low) → Implement the root cause →
Verify (tests, build, migrations, security, tenant isolation) → Review →
Update documentation. Repeat until no Critical/High issues remain for the
current scope, tests pass, docs are synchronized, and the Definition of Done is
met. The Engineering Constitution and Project Governance define the full loop
and the risk classification that drives it.

# Non-negotiable rules

- Treat everything in `/docs` as the source of truth.
- Never violate documented business rules; never bypass security, CSRF,
  authorization, validation or tenant isolation.
- Never trust `customer_id` from the request — derive it from the authenticated
  user. Customer data is isolated by `customer_id` global scopes.
- Inspect the code before changing it; never guess and never fabricate.
- Fix the root cause, reuse existing architecture, avoid duplication, keep
  changes atomic.
- Keep code, documentation and deployment synchronized (CHANGELOG, deployment
  docs and `docs/CONFORMANCE_GAP_ANALYSIS.md` in particular).
- If documentation and implementation conflict, report the conflict and
  recommend the correct resolution before making significant changes.

# Operating rules (every task)

A quick checklist that operationalises the governance above — apply it to every change:

1. Read the relevant `/docs` and this file before editing.
2. Understand the existing structure; reuse what is already there.
3. Make the smallest safe change that fixes the root cause.
4. Do not add unnecessary abstractions or duplication.
5. Preserve security, tenant isolation, validation, accessibility and error handling.
6. Do not break existing working features.
7. Verify before claiming done — run tests/build; measure against `docs/DEFINITION_OF_DONE.md`.
8. Update documentation (CHANGELOG, `/docs`, `docs/CONFORMANCE_GAP_ANALYSIS.md`) when behaviour changes.
9. Report risks and any doc/code conflicts clearly.
10. Never call anything production-ready unless it is tested and verified.

Helper workflows live in `.claude/`: commands `/roast` (challenge an idea), `/selfcheck`
(verify before done), `/handoff` (clean context handoff); and sub-agents `security-reviewer`,
`qa-tester`, `docs-writer`.

# Goal

Deliver a production-ready, secure, maintainable, scalable and future-ready
DMIMS platform.
