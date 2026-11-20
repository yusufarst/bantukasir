# TASK-040 evidence — Finance report

| | |
| --- | --- |
| Task | [TASK-040](../tasks/TASK-040-finance-report.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); the Owner's directive of 21 November resuming the automatic execution of TASK-038 to TASK-050 under D-105, D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-040-finance-report`, from `f46d215b9d02f268991f7983afa5ca585e3a6b27` (origin/develop, the squash merge of TASK-039's pull request #54) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; no branch or pull request of TASK-040 and no open pull request; the executor state at `BUILD` for TASK-040 with this session's lock, the merge of #54 verified; TASK-034 and TASK-025 `DONE`, no gate. PHP and the native `tsc` are not on this machine (`npm run typecheck` answers that `tsc` is not found), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 21 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, PHP's checks | Local | 06:12 | under 1 min | `NOT_RUN`: `tsc` is not installed natively and PHP is not on this machine; the fast run runs them |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL files | Fast run 1 | — | — | `NOT_RUN` until then |
| The whole PostgreSQL suite in four parts, `composer test:race`, the browser suite with `tests/Browser/reporting/finance.spec.ts`, `npm run build`, the image checks | CI on the pull request's final head | — | — | `NOT_RUN` until then (CE-44, D-117) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The report query in one read-only REPEATABLE READ transaction whose snapshot is the as-of time; the five periods as half-open Asia/Jakarta ranges with the custom refusals; figures from the allocation projections; the counts of lines with UNKNOWN cost and of products with an active mark; margin only when cost is complete; never net profit | `App\Queries\Reporting\FinanceReport`; the periods of TASK-003, `App\Support\Time\Period`; the refusals in `App\Http\Requests\Reporting\FinanceReportRequest` |
| 2. RP-502, UXP-502 with KS-10's states: the period choice (UXI-308, UXI-309), completeness, the figures, the trend (UXI-310) and its table (UXI-311), the link "Biaya perlu dilengkapi" (UXI-312), the sub-navigation item UXI-020; the percent format of CC-03 (TASK-003 F-8, D-100) in both formatters with shared cases | `App\Http\Controllers\Reporting\FinanceController`, `routes/web.php`; `resources/js/pages/reporting/Finance.tsx`, `resources/js/lib/reporting/finance.ts`, `resources/js/lib/navigation.ts`; `App\Support\Formatting\Format::percent`, `formatPercent` of `resources/js/lib/format.ts`, `tests/Unit/Support/cases/formatting.json`; `lang/id/reporting.php`, `lang/id/ux.php` |
