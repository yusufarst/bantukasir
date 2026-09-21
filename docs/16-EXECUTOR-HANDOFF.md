# 16 — Antigravity / Codex executor handoff

A fresh session continues from repository, not conversation summary. Plan 1.0 has 36 Core tasks, all NOT STARTED. Current task none; next **BK01 — UI01 Foundation + Shell**. This replanning does not authorize starting it.

## Startup and continuity

1. Inspect status/branch/log; preserve local work. Read AGENTS → 00 → active/next task in 11 → 01 → 02 → 10 → task-specific docs.
2. Select one task, verify dependencies, actual tools, scope and gates. Mark IN PROGRESS only when work begins; at most one active executor by default.
3. Follow task metadata and common DoD. Backend/UI work is a vertical slice, not a disconnected full frontend.
4. Record changed files, tests actually run, outcomes, missing evidence, exact owner review revision and next step. No unrun check receives credit.
5. Update 11 status/evidence, 10 only for actual architecture decisions, and 00 LAST. Commit completed substantial work with intended files only. Do not push/merge/deploy unless explicitly authorized.

Statuses: NOT STARTED, IN PROGRESS, WAITING TECHNICAL CHECK, WAITING OWNER REVIEW, BLOCKED, COMPLETE. Waiting is not complete. UI approval evidence names bundle/integrated screen revision; inherited CP/RV approvals do not count.

## Deterministic short prompt

Usually <=120–180 words; repository carries context. Fill from one task card rather than repeating specs:

```text
TASK: <ID + title>
READ: AGENTS, docs/00, docs/11 task, docs/01, docs/02, docs/10, <task docs>
GOAL: <task goal, 1–3 sentences>
DO:
- <main deliverable and affected domain/files>
VERIFY:
- <task acceptance IDs + applicable common DoD>
STOP IF:
- <task-specific gate/blocker; docs conflict or safety invariant cannot hold>
UPDATE:
- 11 status/evidence; 10 only for real decisions; 00 LAST.
No unrelated changes. No push/merge/deploy unless authorized.
```

Do not pre-generate dozens of large prompts. Task cards list goal/dependencies/read/files/verification/stop, making construction mechanical. Task IDs are permanent; no renumbering after work begins.

## Stops

Docs conflict, new scope/architecture, missing required tool or paid-only access, failed integrity/privacy check, unresolved actual policy/device/recovery gate, or destructive operation without exact authorization. Report concrete blocker and preserved work; never assume approval.

Before meaningful UI work verify 21st.dev free MCP/catalog and actual Impeccable skill/CLI/integration, then follow 05. Missing setup stops that UI task. BK01–BK04 produce compact contracts; BK05 records complete owner Gate A/frozen DESIGN before production frontend. Gate B reviews real integration. Screenshots alone are not approval.

Production safety in AGENTS/09 applies to all maintenance/bugfixes; generic fix/migrate/deploy is never permission to destroy data. Required stop report names why/data/alternatives/migration/backup/rollback.

Final executor report: TASK, STATUS, CHANGED, CHECKS (actual evidence), GATES/BLOCKERS, COMMIT, NEXT. Leave 00 sufficient for another agent to resume without chat.
