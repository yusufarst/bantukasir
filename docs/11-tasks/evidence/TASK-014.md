# TASK-014 — Evidence

| | |
| --- | --- |
| Task | [TASK-014](../tasks/TASK-014-release-pipeline.md), Release workflow, preflight and migration job; size M, review class A, lane O |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (CE-01); mode `start` on 1 November 2026 at 11:24, after the tick verified the merge of pull request #21 (D-115) and the Owner's resume |
| Branch | `task/TASK-014-release-pipeline`, created from `origin/develop` (CE-02) |
| Base commit | `8f166e6055768c691199870f59fccfd26870fa59`, the squash commit of pull request #21 (`ops/`, D-115) |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no publication, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-014, base `8f166e6`; `origin/develop` at that commit; the working tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for.
- **Prerequisites (EXC-21).** TASK-013 `DONE`, operationally verified under D-115's one-time exception (TASK-013 evidence section 17); no gate.
- **Tools (D-109, CE-47).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, so Pint, PHPStan and every PHP test ran only in CI (section 7). `npx tsc` and `npx eslint` could not start natively on this machine (`'tsc' is not recognized`), so they too ran only in CI.
- **D-115 and T-38.** Read before the image work. D-115 accepts no future CVE; a new finding would be a stop. The runs below found none: the gate read Grype's database of 2026-10-31T06:18:17Z (section 7).

## 2. The candidate tag scheme (contract section 8)

`vMAJOR.MINOR.PATCH-rc.N`, N from 1, for example `v1.0.0-rc.1`, recorded in RELEASE_PROCESS section 3. A candidate's commit is on `develop` or `main`; a release's commit is on `main` (`scripts/release/check-tag.sh`). A hotfix's candidate is therefore tagged once its branch is merged into `main` (ADR-011 item 3).

## 3. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 Release workflow, dry run | `.github/workflows/release.yml`, `.github/workflows/release-dry-run.yml`; `scripts/release/check-tag.sh`, `scripts/release/publish.sh`; `scripts/checks/image-checks.sh` takes the version, commit and source of a release; the rules in `scripts/checks/release-workflow.php`, in `composer lint` |
| 2 Templates | `docs/10-release/templates/RELEASE_INTEGRITY_REPORT.md`, filled by `scripts/release/integrity-report.sh`; `docs/10-release/templates/RELEASE_RECORD.md` |
| 3 `release:preflight` | `app/Console/Commands/ReleasePreflight.php`, `app/Domain/Operations/Release/Preflight.php`, `PreflightInput.php`, `config/release.php` |
| 4 Migration job | `docker/app/migration-job.sh`, in the release image as `bantukasir-migration-job`; `app/Console/Commands/ReleaseMigrate.php` (`release:migrate`); `app/Domain/Operations/Release/Verification.php` |
| 5 Maintenance mode | `app/Http/Middleware/MaintenanceWindow.php`, `APP_MAINTENANCE_BYPASS_SECRET`; `tests/Browser/operations/maintenance.spec.ts`, as its own Playwright project after every other |
| 6 Migration review | `app/Domain/Operations/Release/MigrationReview.php`, read by `scripts/checks/migration-review.php` and the preflight |

## 4. Readings and technical choices

1. **The dry run is its own workflow**, because AC-014-01 lets the release workflow run for nothing but a tag. It shares the scripts, so it exercises the same path except the publishing step.
2. **The image these checks passed is the image published.** The workflow builds once, `image-checks.sh` runs on that image, and `publish.sh` tags it. A version already on the registry is refused, because every released image is kept (ADR-011 item 5). No `latest` tag exists.
3. **The integrity report goes to the run's summary**, with no artifact action, which would add a dependency for what the release record keeps (REL-09).
4. **`APP_RELEASE_IMAGE_DIGEST` cannot be in the image** that it names (TASK-013 F-1 expected the workflow to set all three). The workflow sets the version and the commit in the image; the operator sets the digest on the resources at deployment (RB-20 step 9, ENVIRONMENTS section 11).
5. **REL-20's metadata stays prose in each migration's comment**, which the six merged migrations already hold and may not change (EXC-41); no separate register.
6. **The first installation.** REL-23 needs a backup in production, but a new database has no `backup_runs` table and no data, and RB-23 step 9 takes the first backup after the migrations. The operator types `--first-installation`, accepted only when the schema holds no table; on any other database it is a refusal. Recorded in REL-23. No business meaning changes: there is nothing to lose.
7. **The list reaches the job as a typed option**, because the release record is not in the image; so do the decisions it names under REL-06 (`--destructive-approvals`). A destructive migration needs the reference in its source and in the typed list.
8. **`synchronous_commit`** is read in the job's session and in `pg_db_role_setting`, because the job's session says nothing of the runtime role's (ENV-32).
9. **The row counts** compare the classes the restore compares (F, FS, M, S; `BackupRunner::COUNTED_CLASSES`, now public): a session, a cache entry or an old log line may leave at any time. An approved destructive migration prints them without judging them.
10. **The job's body is one PHP command**, `release:migrate`, so that an advisory lock of one session spans the three steps (TR-RUN-04) and the order has an L2 test; the script only refuses another container's environment. `release:preflight` and `release:migrate` run as the migration role, which may write neither log, so like `backup:run` they leave no audit event or security log entry: their lines are the release record's evidence. They are job commands, not OP-nn procedures, and take the typed environment of ENV-04 all the same.
11. **The bypass** takes its secret from configuration, as the contract says, not from `artisan down`. It is a path, so it reaches the access log, read only by someone who can read the setting; it passes the maintenance check only, so sign-in and every route check still apply (ENVIRONMENTS section 11).
12. **`reconciliation:run`** is called by the job as soon as the command exists, and printed `VERIFY_NOT_AVAILABLE` until then. TASK-036 sets how it runs without a runtime credential (finding F-1).
13. **Maintenance mode's tests in the browser** use the real mode, which is the whole application's, so they run in the Playwright project `maintenance`, after `chromium` and `people`. The local environment's debug output replaces the empty body there, so the browser test asserts no RT-07 code; the empty body is asserted in `MaintenanceModeTest`, with debug off.
