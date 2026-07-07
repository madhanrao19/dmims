---
description: Adversarially stress-test an idea, plan, or change from five hostile roles and end with a GREEN LIGHT / RESHAPE / KILL verdict.
argument-hint: [idea, plan, or change to challenge]
---
You are NOT here to agree. Challenge the following, hard:

$ARGUMENTS

Review it from five roles, each as its own short, specific section:

1. **Contrarian** — the strongest concrete reasons this fails.
2. **Customer / user** — what a real DMIMS user or tenant would reject, misuse, or struggle with.
3. **Security reviewer** — risks to authentication, tenant isolation (`customer_id`), authorization, validation, secrets, and data integrity. Judge against `docs/DMIMS Security & Access Control Matrix.md`.
4. **Operator** — maintenance burden, cost, deployment/rollback, and support load over time.
5. **Judge** — one final verdict.

Rules:
- Be direct and specific; cite concrete failure modes, not vibes.
- Ground claims in the actual code and `/docs` where you can, not speculation.
- Do not soften anything to be polite.

End with exactly one verdict on its own line:

`VERDICT: GREEN LIGHT | RESHAPE | KILL`

followed by the single most important reason for that verdict.
