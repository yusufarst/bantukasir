# 16 — Gemini Antigravity executor handoff

This file owns the execution checklist only. Business rules remain in their canonical specifications. Planning is complete. **NEXT EXECUTION TASK: P00.1.** No prototype or production implementation exists yet.

## Session start

1. Open/clone this repository and select the documentation branch containing this checkpoint: `codex/refine-product-ux-architecture`. Check the commit and `git status --short`; preserve existing work. This checkpoint is local unless separately pushed; a remote clone must first receive it through an authorized push or local repository transfer. Do not assume `main` contains it.
2. Read [AGENTS](../AGENTS.md), [current state](00-CURRENT-STATE.md), then the active/next row in [11](11-BUILD-PLAN.md). Current state determines the next task on later sessions; do not restart P00.1 after it is complete.
3. Select exactly one task. Verify dependencies and actual approval evidence. Search before reading more files; read only relevant sections.
4. Mark that task [~] only when execution begins. Never silently expand scope.

## First task: P00.1

Architecture approval and the [official colored mark](../assets/brand/README.md) are available. The owner designated P00.1 as the first executor task; no further architecture approval is needed. Read [05](05-DESIGN-SYSTEM.md), the visual/completion gates in [08](08-TESTING-ACCEPTANCE.md), and the [prototype registry](../prototypes/README.md).

Prepare `prototypes/cp01-shell-auth/`: standalone HTML/CSS, optional deterministic demo JavaScript, and revision-specific REVIEW.md. Cover shell expanded/rail/mobile drawer, brand toggle/account, login/reset/2FA and required interaction/error states. Preserve the official source image and use the specified pastel tokens, Source Sans 3 and Lucide. Check logo whitespace and small-size legibility on white; do not synthesize missing variants. Use safe labeled demo data, no real credentials or production APIs/DB.

Verify browser behavior, keyboard/focus, contrast, reduced motion, 375/768/1024/1440/1920 px and 360 px/200% zoom regression. Record evidence, asset hash, anti-slop/redundancy audit and the reviewable revision. Mark **[V]** and STOP for **owner Gate A**. No Next.js/scaffold/migrations or P00.2 work in this task.

Only explicit approval of that revision plus technical evidence permits P00.1 [x]. P00.2 then becomes eligible. P01 remains blocked by unapproved CP01–CP04 or missing explicit production authorization; architecture approval alone never opens production.

## Task lifecycle

`[ ] → [~] → [T] → [V] when visual → [x]`.

Only one [~]. [T] means verification pending, not completed. [V] means owner visual review pending. BE may move [T] → [x] without [V]. Record a real blocker as [!] with its unblock condition; do not label future-phase validation a planning blocker.

## Backend tasks

Where applicable: database/constraints → input validation → backend service → backend RBAC → audit → risk tests → technical verification. Use only steps relevant to the task; do not add a schema change merely to follow the sequence. No visual output means no visual approval requirement. Preserve ledger, concurrency, security and secret rules from AGENTS and the relevant specifications.

## Frontend and full-stack tasks

New major pattern: requirements → standalone HTML/CSS → browser verification → **[V] Gate A** → owner approval → production implementation in its later authorized task → technical/browser checks → **[V] Gate B** → owner approval → [x].

A prototype-only task completes after Gate A; that does not complete production integration. Reuse approved patterns directly in Next.js without recreating every screen in HTML. Significant new patterns/deviations revise the relevant CP. Minor reuse still requires applicable browser and owner implementation review.

## Stop conditions

STOP and report, rather than guess, when canonical documents conflict, an accepted invariant must change, required credentials are unavailable, a migration threatens historical data, a visual gate is due, an unapproved paid dependency is needed, or scope would materially expand. Preserve work and record the exact conflict/blocker. Never invent credentials/approval, relax invariants, or silently replace a decision.

## Per-task completion

- Deliver the selected prototype/implementation only; no unrelated refactor.
- Verify relevant validation, backend RBAC, audit and tests. Apply [08](08-TESTING-ACCEPTANCE.md) gates. Run lint/typecheck/build only when available and applicable; absent P00 application tooling is not a failed application build or a reason to scaffold Next.js.
- Visual work needs browser/mobile evidence and the relevant owner gate. Never mark [x] from test success alone.
- Update actual status and concise evidence in 11; update the prototype registry/REVIEW when relevant. Update decisions only for authorized real changes. Update 00 LAST.
- Inspect diff/secrets and run `git diff --check` and `git status --short`. Commit reviewable work when authorized; never push/merge/deploy automatically. Stop after the selected task or its review gate, without automatically starting the next task.

## Executor report

Use only a concise report, not large logs:

```text
TASK:
STATUS:
CHANGED:
CHECKS:
OWNER_REVIEW:
BLOCKERS:
NEXT:
```
