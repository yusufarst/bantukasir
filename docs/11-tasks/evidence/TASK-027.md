# TASK-027 evidence — Cash registers and cash session open

| | |
| --- | --- |
| Task | [TASK-027](../tasks/TASK-027-registers-session-open.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-027-registers-session-open`, from `8118cbcb0a3ec06f3e75c13cfc74bfa9f8837e7b` (origin/develop, the squash merge of TASK-026) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-027; the executor state at `BUILD` for TASK-027 with this session's lock; TASK-011 and TASK-022 `DONE`, no gate; `tsc`, ESLint and PHP do not run on this machine (`node_modules/.bin` absent), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 7 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 13:45 | — | `NOT_RUN`: `tsc` is not installed on this machine |
