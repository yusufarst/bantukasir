# 16 â€” Executor handoff

Business rules remain in canonical specs. This file owns session execution procedure.

## Session start

1. Check branch/commit and `git status --short`; preserve local work.
2. Read AGENTS, 00 and the active/next row in 11.
3. Read 01/02/10 and task-specific docs.
4. Select exactly one task and verify dependencies/gates.
5. Mark [~] only when work actually begins.

## Next execution

After owner accepts planning 3.1, next eligible work is **R00.1 â€” RV01**.

RV01 is a standalone deterministic visual/interaction prototype:
- brand-neutral shell/auth;
- role-specific landing hierarchy;
- persistent desktop sidebar/mobile drawer;
- fast POS skeleton;
- progressive-disclosure entry to **Pesanan / DP**;
- relevant loading/empty/error/uncertain states.

It must not depend on production API/database and must not reuse LATANSA-specific palette as the universal product identity.

Browser/keyboard/mobile evidence â†’ mark [V] â†’ STOP for owner Gate A.

Production R01.1 additionally requires explicit owner implementation authorization.

## Lifecycle

`[ ] â†’ [~] â†’ [T] â†’ [V] when visual â†’ [x]`.

BE can move [T] â†’ [x] after required technical verification. FE/FS requiring owner review remains [V] until explicit approval.

## Vertical slice rule

For each production feature use only applicable steps:
database/constraints â†’ validation â†’ service/transaction â†’ RBAC â†’ audit â†’ UI â†’ tests â†’ browser/device verification.

Do not build the entire frontend first.

## Stop conditions

Stop/report rather than guess when:
- canonical docs conflict;
- a safety invariant must change;
- tax/legal invoice meaning is required but Q01 is unresolved;
- required credential/device/recovery evidence is unavailable;
- a destructive migration threatens history;
- an unapproved paid dependency is required;
- a visual Gate A/B is due;
- requested scope materially expands beyond the active task.

## Completion

Run relevant lint/typecheck/tests/build only when tooling exists and the task needs them. Never claim an unrun check passed.

Visual work needs actual browser/mobile evidence. Scanner/printer work needs actual device checks at the specified gate.

Update task status/evidence in 11, real decisions in 10, and 00 last. Never push/merge/deploy without authorization.

## Report

```text
TASK:
STATUS:
CHANGED:
CHECKS:
OWNER_REVIEW:
BLOCKERS:
NEXT:
```
