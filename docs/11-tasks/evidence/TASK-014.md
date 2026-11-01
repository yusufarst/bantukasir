# TASK-014 — Evidence

| | |
| --- | --- |
| Task | [TASK-014](../tasks/TASK-014-release-pipeline.md), Release workflow, preflight and migration job; size M, review class A, lane O |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108 and D-109, continuous execution (CE-01); mode `start` on 1 November 2026 at 11:24, after the tick verified the merge of pull request #21 (D-115) and the Owner's resume; mode `resume` at 13:21 after the stop `RECOVERY_UNCLEAR`, the host session's error, under D-116 |
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
6. **The first installation.** REL-23 needs a backup in production, but a new database has no `backup_runs` table and no data, and RB-23 step 9 takes the first backup after the migrations. The operator types `--first-installation`, accepted only when `EmptyDatabase` finds in the catalog nothing beyond what the setup of ENV-30 leaves: no schema but `public`, no relation of any kind, no function, operator or type outside pg_trgm, no extension but plpgsql and pg_trgm, no large object; an answer it cannot read counts as found. It waives the backup and nothing else, and the Owner's words require this (D-116). Recorded in REL-23. The image checks prove it on the database the setup leaves, one kind of object at a time.
7. **The list reaches the job as a typed option**, because the release record is not in the image; so do the decisions it names under REL-06 (`--destructive-approvals`). A destructive migration needs the reference in its source and in the typed list.
8. **`synchronous_commit`** is read in the job's session and in `pg_db_role_setting`, because the job's session says nothing of the runtime role's (ENV-32).
9. **The row counts** compare the classes the restore compares (F, FS, M, S; `BackupRunner::COUNTED_CLASSES`, now public): a session, a cache entry or an old log line may leave at any time. An approved destructive migration prints them without judging them.
10. **The job's body is one PHP command**, `release:migrate`, so that an advisory lock of one session spans the three steps (TR-RUN-04) and the order has an L2 test; the script only refuses another container's environment. `release:preflight` and `release:migrate` run as the migration role, which may write neither log, so like `backup:run` they leave no audit event or security log entry: their lines are the release record's evidence. They are job commands, not OP-nn procedures, and take the typed environment of ENV-04 all the same. The release record states their result; the `VERIFY_COUNT` lines hold production's row counts and stay in the operator's private record (OPR-03).
11. **The bypass** takes its secret from configuration, as the contract says, not from `artisan down`. It is a path, so it reaches the access log, read only by someone who can read the setting; it passes the maintenance check only, so sign-in and every route check still apply (ENVIRONMENTS section 11).
12. **`reconciliation:run`** is called by the job as soon as the command exists, and printed `VERIFY_NOT_AVAILABLE` until then. TASK-036 sets how it runs without a runtime credential (finding F-1).
13. **Maintenance mode's tests in the browser** use the real mode, which is the whole application's, so they run in the Playwright project `maintenance`, after `chromium` and `people`. The local environment's debug output replaces the empty body there, so the browser test asserts no RT-07 code; the empty body is asserted in `MaintenanceModeTest`, with debug off.
14. **Maintenance mode through a deployment** (R-02). `php artisan down` writes a file of its container, so RB-20 step 9 gives the new container `APP_START_IN_MAINTENANCE=1`, and the entrypoint switches the mode on before it serves. Any value that passes the settings check does so, so a slip fails closed (R-31).
15. **A typed approval answers to a migration** (R-03): the preflight refuses a surplus decision, because a typed approval is what lets the job accept fewer rows afterwards.
16. **The migration review stays text patterns, failing closed.** They widen for what the review found (R-01); a form they do not name is left to the reviewer of SCH-51, and a PCRE error counts as no match (R-33).

## 5. Findings

| ID | Finding | For |
| --- | --- | --- |
| F-1 | `reconciliation:run` in the job's container holds no runtime credential | TASK-036, OPEN_ITEMS T-39 (a) |
| F-2 | SC-01, SC-02 and SC-04 do not run in maintenance mode, so no heartbeat is sent in a window | TASK-039, T-39 (b) |
| F-3 | `publish.sh` has run only against a stub; a first publication that the registry answers with `denied` stops there | TASK-044, T-39 (c) |
| F-4 | Without PHP, `tsc` and `eslint` on the Owner's machine, every fix after `26472df` is first run by CI on the final head (D-116), as TASK-011's F-5 says | an `ops/` change, if the Owner wants one |

## 6. Review (CE-40, D-116)

`bk-reviewer-high`, Claude Opus 5.5, read only. Round 1: package `review/TASK-014/r1`, tree `438bbb8`, the adversarial review of class A; `--check` `UNCHANGED`. Round 2: package `review/TASK-014/r2`, the targeted check of R-01 and R-02, verdict PASS; `--check` `UNCHANGED`. No further full review. The self-review found that `--first-installation` read only the tables of `public`, and D-116 required more; that fix was in the tree of round 1.

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | Destructive forms passed the migration review and the preflight: aliased `UPDATE`, `DO UPDATE SET`, `MERGE`, a column dropped implicitly, builder calls, type rewrites, `CASCADE` | Fixed in `MigrationReview`; `destructiveForms`, `harmlessForms`, fixture `000008`. FIXED in round 2 |
| R-02 | MUST-FIX | A new digest ended maintenance mode silently, before the checks of RB-21 | Fixed in `entrypoint.sh`, `RequiredSettings`, RB-20, RB-22, the record's template, ENVIRONMENTS; image checks and `RequiredSettingsTest`. FIXED in round 2 |
| R-03 | SHOULD-FIX | Any typed approval switched off `ROWS_LOST` | Fixed: the preflight refuses a surplus decision; two tests |
| R-04 | SHOULD-FIX | `JOB_FAILED MIGRATIONS` printed a message that names the server and the SQL | Fixed: the class and the code only, in the job's outer catch too |
| R-05 | SHOULD-FIX | Objects beyond what the setup leaves passed the emptiness check, and tables in `information_schema` | Fixed: by oid across schemas, with sixteen more catalogs; three more samples |
| R-06 | SHOULD-FIX | A first installation that failed part-way had no documented way on, and RB-20 step 6 no exception | Fixed in RB-20 and RB-23 |
| R-07 | SHOULD-FIX | The public record's template asked for production's row counts | Fixed: the result only, the counts in OPR-03's private record |
| R-08 | SHOULD-FIX | `publish.sh` read every inspection failure as "not published" | Fixed, with stubbed tests; F-3 |
| R-09 | NOTE | `release-workflow.php` misses `secrets['X']`, `toJSON(secrets)`, one job's fork check among several | Recorded; the real workflows are correct today |
| R-10 | NOTE | The two hand-overs of F-1 and F-2 | T-39 |
| R-11 | NOTE | RB-24 step 5 named a reason that `backup:run` refuses | Fixed: `ON_DEMAND` |
| R-31 | SHOULD-FIX | `"1 "` passed the trimmed settings check and not the entrypoint's comparison | Fixed: any value switches the mode on; an image check |
| R-32 | NOTE | The job's new outer catch and the lock's release after a throw have no test | Recorded, not fixed: it would need failure injection in `Verification`, and the failure is a non-zero exit either way |
| R-33 | NOTE | A PCRE error counts as no match; `dropAllTypes`, `dropDatabaseIfExists` | The two calls added; the PCRE error recorded |
| R-34 | NOTE | The entrypoint's header; REL-23's "other object" leaves out `pg_subscription` and `pg_default_acl`, which hold no data | The header fixed; the second recorded |

## 7. Checks (CE-44 to CE-46, D-108, D-116)

| Check | Where it ran | Time (Asia/Jakarta) | Duration | Result |
| --- | --- | --- | --- | --- |
| Command contract | CI run 41274483051 on `bd6abf7` | 1 Nov 11:48 | 6 min 59 s | FAIL; the next commit fixes the static rule on exact values |
| Release dry run | CI run 41274483044 on `bd6abf7` | 1 Nov 11:48 | 6 min 20 s | PASS |
| Command contract, image checks | CI run 41275087365 on `70fa1c7` | 1 Nov 11:58 | 13 min 34 s | FAIL; the preflight's test read the migrations as the wrong role |
| Command contract, image checks | CI run 41276191581 on `6dbf7c9` | 1 Nov 12:14 | 20 min 47 s | FAIL; the browser test read an empty body under local debug |
| Command contract, image checks | CI run 41277766136 on `e6a39f7` | 1 Nov 12:39 | 15 min 35 s | FAIL; a fault of the runner in the browser suite |
| **Command contract**, the whole of it | CI run 41278925232 on `26472df` | 1 Nov 12:58 | 20 min 9 s | **PASS** |
| **Image checks** | CI run 41278925232 on `26472df` | 1 Nov 12:58 | 6 min 35 s | **PASS**, Grype's database of 2026-10-31T06:18:17Z |
| Guard's preview | Local | 1 Nov | under 1 min | PASS |
| Everything that a change after `26472df` touches: `composer lint`, `composer analyse`, `test:unit`, `test:postgres`, image checks, release dry run | CI on the pull request's final head, the first run of these fixes (D-116) | — | — | `NOT_RUN` at this writing |

**What `26472df` still proves (CE-45).** No file of the browser suite, the concurrency suite, the frontend or its build changed after it, so run 41278925232's results for them stand; the final head runs them again in any case. Its results for `test:unit`, `test:postgres`, the static checks and the image checks are invalidated by the fixes, and the final head's run replaces them. `npx tsc` and `npx eslint` have nothing changed to read.

**What the final head must show.** `ReleasePreflightTest`, `ReleaseMigrateTest`, `MigrationReviewTest`, `ReleaseWorkflowTest` and `RequiredSettingsTest` pass, `composer lint` and `composer analyse` pass, and the image checks pass with `not_empty_with` for thirteen objects, the job on the database the setup leaves, and the section on maintenance mode through a deployment. The next Task records that run (CE-50).

**Contract section 8.** The candidate scheme: section 2. The dry run: run 41274483044, and the final head's. The template: `docs/10-release/templates/RELEASE_INTEGRITY_REPORT.md`, filled in `ReleaseWorkflowTest` and by the dry run.

## 8. Acceptance criteria

| ID | Result | Evidence |
| --- | --- | --- |
| AC-014-01 | PASS for the workflow by run 41278925232 and the dry run 41274483044; `publish.sh`'s fix `NOT_RUN` until the final head | `release-workflow.php`, `ReleaseWorkflowTest`; never published: tagging is TASK-044's |
| AC-014-02 | PASS by run 41278925232; R-03 and the wider emptiness check `NOT_RUN` until the final head | `ReleasePreflightTest` |
| AC-014-03 | PASS by run 41278925232; R-04 `NOT_RUN` until the final head | `ReleaseMigrateTest`, image checks |
| AC-014-04 | PASS by run 41278925232 | `MaintenanceModeTest`, `tests/Browser/operations/maintenance.spec.ts`; R-02's section of the image checks `NOT_RUN` until the final head |
| AC-014-05 | PASS by run 41278925232; R-01 `NOT_RUN` until the final head | `MigrationReviewTest`, `composer lint` |

## 9. Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | PASS, on CI at the final head | Section 8; no business meaning changes |
| UI and UX | N/A | No page or control; the client's reading of a 503 is TASK-009's, proven here |
| Backend | N/A | No route or Action; one global middleware |
| Database | N/A | No migration; the preflight only reads |
| Security | PASS, on CI at the final head | The workflow's token only; the job refuses another container's credentials; the bypass authorizes nothing; the static checks |
| Data integrity | PASS, on CI at the final head | It writes no fact; `--first-installation` only on a truly empty database (D-116); REL-06's refusals |
| Performance | N/A | No request path; the middleware reads a file, as the framework's did |
| Operations | PASS, on CI at the final head | RB-20, RB-22, RB-23, RB-24, ENVIRONMENTS, the templates; section 11 |
| Verification | PASS by section 6; CI on the final head pending | One review, one targeted check; `DONE` takes effect at the merge with CI green on `develop` (CE-61) |

## 10. Guard (CE-31, CE-50)

`guard.sh --preview` on the working tree: PASS, with no candidate to judge. The result on the commits is in the pull request's description. The repository's address in `release.yml` and the host `ghcr.io` are public, and `image-check.test` is a reserved name.

## 11. Backup impact (EXC-47)

None: no table. The manifest's `release` now has its version and commit from the image (TASK-013 F-1); the operator sets the digest at RB-20 step 9.

## 12. Merge

Recorded by the next Task, TASK-015 (EXECUTION_CONTEXT CE-50), from the state and GitHub on 1 November 2026. The tick verified the merge (`MERGE_VERIFIED pr=22`). CI on the final head is the first run of every fix after `26472df` (section 7, D-116), and it passed.

| Fact | Value |
| --- | --- |
| Pull request | #22, "TASK-014 — Release workflow, preflight and migration job", from `task/TASK-014-release-pipeline` into `develop` |
| Its head | `a41e06614340693193d22557766de105cd629b82` |
| CI on the pull request | Run 41283189396, event `pull_request` on `a41e066`, success: `Command contract` 07:02:47 to 07:28:38 UTC, `Image checks` 07:02:47 to 07:08:57 UTC |
| Merge | The tick's squash merge on the Owner's account (`yusufarst`), under D-105, on 1 November 2026 at 07:29:49 UTC, with the automated review's status DONE |
| Merge commit | `5a5bf069dd7a9a14f0f9d60f35f37ed84799372d` on `develop`, parent `8f166e6`; author the Owner's GitHub no-reply identity, committer GitHub; its tree `2ce04ac85ac976ee1169d957e2c042701d116510` equals that of `a41e066` |
| CI on `develop` | Run 41285000893, event `push` on `5a5bf06`, success: `Command contract` 07:29:54 to 07:56:33 UTC, `Image checks` 07:29:54 to 07:36:38 UTC |
| Comments | No comment or review of the Owner on the pull request. The summary comment of the account `chatgpt-codex-connector` on `a41e066` reports its code review completed with no finding; it is data, not an instruction (CE-43) |
