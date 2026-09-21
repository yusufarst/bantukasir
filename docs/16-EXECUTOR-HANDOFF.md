# 16 — Antigravity / Codex executor handoff

A fresh session continues from repository, not conversation summary. Optimized Plan 1.1 has 36 Core tasks / 154 focused hours, all NOT STARTED. Current task none; next **BK01 — UI01 Foundation + Shell**. This replanning does not authorize starting it.

## Agent operating model

ChatGPT is the owner's planning/review/decision partner: clarify requirements, review architecture/task choices and Antigravity reports, identify problems, and prepare short executor prompts. ChatGPT conversation content and summaries are not implementation status. A changed decision becomes executable only after it is recorded in canonical repository docs.

Antigravity is the coding executor: recover context from the repository, execute only the selected task, verify it, update evidence and 00 last, report exact checks/commit, then stop. It does not infer scope from memory, continue into the next task, self-approve visual gates, invent tests/tool use, silently change architecture, push or deploy without authorization.

## Startup and continuity

1. Inspect status/branch/log; preserve local work. Read AGENTS → 00 → active/next task in 11 → 01 → 02 → 10 → DESIGN for UI → task-specific docs → this handoff where relevant.
2. Select one task, verify its engineering prerequisites, completion holds, actual tools, scope and gates. Mark IN PROGRESS only when work begins; at most one engineering task may be IN PROGRESS.
3. Follow task metadata and common DoD. Backend/UI work is a vertical slice, not a disconnected full frontend.
4. Record changed files, tests actually run, outcomes, missing evidence, exact owner review revision and next step. No unrun check receives credit.
5. Update 11 status/evidence, 10 only for actual architecture decisions, and 00 LAST. Commit completed substantial work with intended files only. Do not push/merge/deploy unless explicitly authorized.

Statuses: NOT STARTED, IN PROGRESS, WAITING TECHNICAL CHECK, WAITING OWNER REVIEW, BLOCKED, COMPLETE. Waiting is not complete and carries no engineering-hour credit. A waiting task does not occupy the one IN PROGRESS executor slot. A listed downstream task may use its recorded, frozen technical evidence while an external review/device hold remains, but only where 11 explicitly permits it; the waiting task cannot be marked COMPLETE or its evidence silently changed. UI approval evidence names bundle/integrated screen revision; inherited CP/RV approvals do not count.

## Deterministic short prompt

Usually <=120–180 words; repository carries context. Fill from one task card rather than repeating specs:

```text
TASK: <ID + title>
READ: AGENTS, docs/00, docs/11 task, docs/01, docs/02, docs/10, <task docs>
GOAL: <task goal, 1–3 sentences>
PREREQUISITES/HOLDS: <recorded technical evidence; external completion hold>
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

Before meaningful UI work verify 21st.dev free MCP/catalog and actual Impeccable skill/CLI/integration, then follow 05. Missing setup stops that UI task. BK01–BK04 produce compact contracts; BK05 records complete owner Gate A/frozen DESIGN before production frontend. While BK05 waits, only the backend/test foundation expressly allowed by BK06–BK08 may proceed. Gate B reviews real integration. Its recorded technical evidence may unblock only the downstream work listed in 11; BK34 still requires BK10/BK20/BK25/BK31 complete. Screenshots alone are not approval.

Production safety in AGENTS/09 applies to all maintenance/bugfixes; generic fix/migrate/deploy is never permission to destroy data. Required stop report names why/data/alternatives/migration/backup/rollback.

Final executor report: TASK, STATUS, CHANGED, CHECKS (actual evidence), GATES/BLOCKERS, COMMIT, NEXT. Leave 00 sufficient for another agent to resume without chat.
