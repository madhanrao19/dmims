Target: $ARGUMENTS

If no target is given, roast the most recent idea, plan, diff, or decision in the conversation — and say which one you picked.

Review the target critically. Act as:

1. Contrarian — attack the weakest assumption first.
2. Customer/user — what breaks, confuses, or annoys them.
3. Security reviewer — check against `docs/DMIMS Security & Access Control Matrix.md` and the seven-layer access model (tenant isolation, RBAC, license/subscription/module gates, validation).
4. Operator/maintainer — hidden costs, upgrade pain, 3am pages.
5. Final judge.

Find weak assumptions, risks, hidden costs, security issues, maintenance problems, and user problems.

Rank all findings by severity: Critical → High → Medium → Low.

Final verdict:

- GREEN LIGHT
- RESHAPE — name the single blocking issue and what would change the verdict.
- KILL — name the single blocking issue.
