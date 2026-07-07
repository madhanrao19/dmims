---
description: Produce a compact, copy-pasteable session handoff so context can be cleared and the work continued safely in a fresh session.
argument-hint: [focus or scope, optional]
---
Create a clean session handoff summary. Scope (optional): $ARGUMENTS

Pull the facts from the actual repo and git state (branch, diff, log) — not from memory.
Write each item as a short, labelled section:

1. **Project goal** — what we're ultimately trying to achieve.
2. **Current status** — branch, what's merged, what's open (PRs), test state.
3. **Files changed** — this session, grouped by area.
4. **Important decisions** — and the reasoning behind each.
5. **Known issues / open risks** — anything unresolved.
6. **Commands already run** — tests, migrations, git, deploys.
7. **Next safest steps** — the concrete next actions.
8. **Do NOT touch** — files/areas that must be left alone and why.
9. **Risks / gotchas** — traps for whoever continues.
10. **Continue prompt** — the exact prompt to paste into a fresh Claude Code session to resume safely.

Keep it compact and skimmable — a working handoff, not a narrative.
