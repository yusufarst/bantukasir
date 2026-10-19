# TASK-003 evidence — Shared kernel, formatting and localization

| | |
| --- | --- |
| Task | [TASK-003](../tasks/TASK-003-kernel-localization.md), size M, review class A, lane P |
| Status | `IN_PROGRESS`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | The Owner's instruction of 19 October 2026, "TASK-003: Shared kernel, formatting and localization (executor instruction)", prepared by the planning agent on 19 October 2026 and sent by the Owner the same day, approving its whole content |
| Branch | `task/TASK-003-kernel-localization`, created from `develop` (EXC-22) |
| Base commit | `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, the D-098 bookkeeping commit on `develop` (parent `b212819a1761ffc468283d6813bf6ec7581d097e`) |
| Executor | Implementation executor in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort XHigh, as the Owner's instruction names it (D-031, D-095), and the desktop app's record of the session shows effort `xhigh`; a new session dedicated to TASK-003, started on 19 October 2026 at 22:02 |
| Gates | None besides EXECUTOR, checked at the start (TASK_PLAN section 3) |
| Authorized beyond local commits | The local environment (`docker compose up -d --build --wait` and `docker compose down`, never `-v`); project-local dependency changes only if a need is proven; after local verification, the publication guard, non-force pushes of the Task branch, one pull request into `develop` and watching its CI. Not authorized: any merge, any push of `develop`, `main`, `plan/bantukasir-pos-v1` or a tag, and any change to an approved P7 artifact, COPY_DECK, GLOSSARY, OPERATIONS_RUNBOOK, OPEN_ITEMS, SOURCE_OF_TRUTH or DECISIONS |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24)

- **Read path (EXC-01).** AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT, TASK_PLAN sections 1 to 4 and the TASK-003 contract, then the sources of its section 3.
- **Git state (EXC-20)**, checked on 19 October 2026 at 22:03 with `GIT_OPTIONAL_LOCKS=0`: branch `develop` at `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, equal to `origin/develop` after a fetch; nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`; `.claude/settings.local.json` ignored by the user-global rule (D-097); `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" present; the ref under `refs/codex/turn-diffs/` present; remote `origin` only; the only lock is `.git/objects/maintenance.lock`. `git ls-remote origin`: `develop` `fc0055b`, `main` `f7cecfe`, `plan/bantukasir-pos-v1` `86822d5`, `task/TASK-001-toolchain-ci` `8f0b96a`.
- **CURRENT_HANDOFF section 1.** `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md` prints `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, then `b212819a1761ffc468283d6813bf6ec7581d097e`: the D-098 bookkeeping is committed.
- **Prerequisites (EXC-21).** TASK_PLAN section 4 on `fc0055b`: TASK-001 `DONE`, TASK-003 `READY`, no gate in its row besides EXECUTOR; totals `TODO` 47, `READY` 2, `DONE` 1.
- **Docker.** Docker engine 29.8.0 answers; Docker Compose v5.5.1; the BantuKasir environment is stopped (no container); the volume `bantukasir_db-data` exists. Another Compose project of the machine, unrelated to BantuKasir, was running; it was not touched.
- **Tools on the host.** Git 2.55.0.windows.3. PHP, Composer and the command contract run in the project's containers (TASK-001).
- **Branch (EXC-22).** `git switch -c task/TASK-003-kernel-localization` at `fc0055b`, on 19 October 2026 at 22:08.
- **Start recorded (EXC-24).** The Task's first commit, `TASK-003: start`, sets TASK-003 `IN_PROGRESS` in TASK_PLAN section 4 (totals `TODO` 47, `READY` 1, `IN_PROGRESS` 1, `DONE` 1, recounted from the rows) and creates this file.
