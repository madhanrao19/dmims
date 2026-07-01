# CLAUDE.md

# DMIMS AI Engineering Instructions

Before starting any task, read and follow these documents in order:

1. docs/DMIMS Engineering Constitution.md
2. docs/DMIMS Project Governance.md
3. docs/DEFINITION_OF_DONE.md
4. docs/CONFORMANCE_GAP_ANALYSIS.md

These documents define the engineering standards for DMIMS and override your default behaviour.

---

# Engineering Mode

Operate in **Continuous Engineering Loop** mode.

For every task, repeat the following cycle until the current scope of work is complete:

1. Understand
   - Read the relevant documentation.
   - Inspect the existing implementation.
   - Understand the business requirements.

2. Discover
   - Identify Critical, High, Medium and Low issues related to the current scope.
   - Identify technical debt and architectural inconsistencies.

3. Prioritize
   - Resolve Critical issues first.
   - Then High.
   - Then Medium.
   - Then Low.

4. Implement
   - Fix the root cause.
   - Reuse existing architecture.
   - Avoid duplication.
   - Keep changes atomic.

5. Verify
   - Run relevant tests.
   - Build frontend if required.
   - Verify migrations.
   - Verify security.
   - Verify tenant isolation.
   - Verify documentation.

6. Review
   - Look for regressions.
   - Look for architectural improvements.
   - Look for security improvements.
   - Update documentation if required.

7. Update
   - Update CONFORMANCE_GAP_ANALYSIS.md.
   - Update Release Notes if applicable.
   - Update documentation if implementation changed.

Repeat this loop until:

- No Critical issues remain for the current scope.
- No High issues remain for the current scope.
- All relevant tests pass.
- Documentation is synchronized.
- The Definition of Done has been satisfied.

---

# Working Rules

- Treat everything in `/docs` as the source of truth.
- Never violate documented business rules.
- Never bypass security.
- Never bypass tenant isolation.
- Never duplicate existing architecture.
- Always determine the root cause.
- Keep code, documentation and deployment synchronized.
- Never guess—inspect the code first.
- If documentation and implementation conflict, report the conflict and recommend the correct resolution before making significant changes.

---

# Goal

Deliver a production-ready, secure, maintainable, scalable and future-ready DMIMS platform.
