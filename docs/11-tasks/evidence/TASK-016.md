# TASK-016 — Evidence

| | |
| --- | --- |
| Task | [TASK-016](../tasks/TASK-016-barcodes.md), Barcodes and exact lookup (server); size M, review class A, lane C |
| Status | `DONE` when its pull request is squash-merged and CI passes on `develop` at the merge (EXECUTION_CONTEXT CE-50, CE-61); until then `develop` shows `READY`. The status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | D-101 as amended by D-104, D-105, D-107, D-108, D-109 and D-117, continuous execution (CE-01); mode `start` on 1 November 2026 at 18:25, after the tick's verified merge of pull request #24 |
| Branch | `task/TASK-016-barcodes`, created from `origin/develop` (CE-02) |
| Base commit | `a58202c886a94909ec92d7dcf799548891fd6ffa`, the squash commit of pull request #24 |
| Executor | `bk-executor-high` in Claude Code Desktop on the Owner's Windows machine, in the main working tree; Claude Opus 5.5 (`claude-opus-5-5`); effort High, from the table of EXECUTION_CONTEXT section 19 (CE-11, D-107) |
| Reviewer | `bk-reviewer-high` for the adversarial review of class A and for a verification of fixes (CE-11, CE-40) |
| Gates | None of its own; EXECUTOR, checked at the start |
| Authorized beyond local commits | By CE-01 and D-105, for this Task only: its branch, commits and pushes through the scripts, one pull request into `develop` and its CI, the fixes of CE-40 to CE-43, and the tick's squash merge where CE-60 allows it. No tag, no release, no staging or production |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8.

## 1. Start (EXC-20 to EXC-24, CE-20)

- **Model (CE-13).** `claude-opus-5-5`, the model that CE-11 names.
- **Git.** The state held the phase `BUILD`, TASK-016, base `a58202c`; `origin/develop` at that commit with CI run 41299590381 passed; the main working tree on the last merged branch, clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which CURRENT_HANDOFF section 2 accounts for; one stash, the known one; no branch or pull request of the Task.
- **Prerequisites (EXC-21).** TASK-015 `DONE`; no gate.
- **Tools (D-108, D-109, D-117).** Files were read with Read, Grep and Glob and changed only with Edit and Write; every Bash call was one allowed command. No PHP, Composer or Docker runs on this machine for BantuKasir, so Pint, PHPStan and every PHP test ran only in CI.

## 2. What was built (contract section 4.1)

| Scope item | Where |
| --- | --- |
| 1 Tables | `database/migrations/2026_11_01_000002_create_barcode_tables.php`, `database/privileges.php` |
| 2 Token rules | `app/Domain/Catalog/Barcodes/` (`BarcodeToken`, `Symbology`, `InternalCodes`, `BarcodeInput`); `App\Support\Validation\BarcodeTokenRule`; the paths excluded from the global trimming in `bootstrap/app.php`; the keys of COPY_DECK sections 13.2 and 16.1 |
| 3 RC-27 to RC-29, RC-17's barcode | `app/Actions/Catalog/` (`BarcodeAction`, `AddBarcode`, `GenerateBarcode`, `RetireBarcode`, `CreateProduct`); `App\Domain\Catalog\Barcodes\Barcodes`; `app/Http/Requests/Catalog/`, `app/Http/Controllers/Catalog/`, `routes/web.php` |
| 4 RD-01 and RD-06 | `app/Queries/Catalog/ProductSearch.php`, `SearchFirst.php`, `ProductRows.php`, `SearchFilters.php`; `ProductSearchRequest`, `SearchFirstRequest`, `SearchFirstController` |

## 3. Stop at the first checkpoint (1 November 2026, 18:59)

`guard.sh` failed on `1197bb0`, the Task's first local commit, which was never pushed: the secret scan and the default rules of Gitleaks each report one finding, printed redacted. The value is a made-up internal code under the key `token`, in `tests/Feature/Catalog/BarcodeCommandsTest.php`, not a secret, and EXC-63 forbids committing even a fake one. The working tree holds the fix, uncommitted: with it the guard's scan of the tree finds nothing, which proves the location, and the three candidates `number16` of the same file are gone too. The commit stays in the guard's range, because a later commit does not remove it, and the executor may not rewrite it (no reset, no allowlist exception). No check of CI has run yet: every check is `NOT_RUN`.

If the Owner moves the branch back to the base, `git log a58202c..HEAD` is empty and the Task's whole change is in the working tree, uncommitted, which accounts for it on the resume (CE-24).

**Resume (19:17).** The Owner moved the branch back to the base with `git reset --mixed` and resumed, so `1197bb0` is no longer in the branch and nothing of it was published. The resume kept every change of the working tree. Three more literals of an internal code's form, in `BarcodeCommandsTest`, `BarcodeTablesTest` and `BarcodeTokenTest`, are now built at run time, so that no such code stands in the source (EXC-63). `Claude outputs/` and `plan-p0-p11-review.patch` stay untracked and unstaged (EXC-30).
