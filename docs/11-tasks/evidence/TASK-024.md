# TASK-024 evidence — Stock adjustment

| | |
| --- | --- |
| Task | [TASK-024](../tasks/TASK-024-stock-adjustment.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-024-stock-adjustment`, from `25d9474bd08540eaacbbbbaecf4fc8347e8b3432` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-024; the executor state at `BUILD` for TASK-024 with this session's lock; TASK-023 `DONE`, no gate; `tsc` and ESLint do not run on this machine (no `node_modules/.bin`), PHP neither, so every check runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 3 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit` | Local | 20:23 | — | `NOT_RUN`: `tsc` is not installed on this machine |

## 2. What was built (contract section 4.1)

To be completed at the record (CE-50).
