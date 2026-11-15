# TASK-037 evidence — Restore tooling and recovery procedures

| | |
| --- | --- |
| Task | [TASK-037](../tasks/TASK-037-restore-tooling.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-037-restore-tooling`, from `5e2eae52ea47801e0a962e897d08bef9ed834dfc` (origin/develop, the squash merge of TASK-036's pull request #50) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; no branch or pull request of TASK-037 and no open pull request; the executor state at `BUILD` for TASK-037 with this session's lock, the merge of #50 verified; TASK-013 and TASK-035 `DONE`, no gate. PHP and the native `tsc` are not on this machine, so every check but the guard runs in CI (D-108, D-117) |
| Owner's directives in force | Forwarded by the host, adding no permission: FULL V1 (D-130), the contract built directly with the cheap relevant checks before each push, evidence reused, one review; the safety boundary unchanged — no restore, recovery or destructive operation against a real, staging or production database or backup store, everything exercised in CI's test environment only; no weakened test, timeout or baseline, a new CVE a stop; PostgreSQL in four parts (D-131); the known flakes classified only on log evidence; commit and pull request texts without tool or vendor names, attribution or agent wording |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 15 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint`, PHP's checks | Local | 21:46, 22:10 | — | `NOT_RUN`: PHP is not on this machine, and `tsc` is not found; the fast runs run them |
| Guard preview | Local, on the working tree against `origin/develop` | 21:47 | under 1 min | First one candidate, the placeholder text of an endpoint in `fetch-backup.sh`'s comment, rephrased; then `PASS` |
| Guard (Gitleaks 8.30.1, the secret scan of the tree and the commits, identity, candidates) | Local, on `8632a00` (checkpoint 1: the procedures, the drill record, the scripts and their tests) | 21:48 to 21:51 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, then `composer analyse` | Fast run 1, 43726663288 on `8632a00` | 21:52 to 21:55 | about 3 min for the job | `FAIL`: `validate` and `lint` `PASS`; PHPStan 18 errors of types in three classes and six test files (F-1); the later steps did not run |
