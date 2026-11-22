# TASK-043 evidence — Performance harness and provisional dataset

| | |
| --- | --- |
| Task | [TASK-043](../tasks/TASK-043-performance-harness.md), review class B |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01): the selection of CE-10 at `origin/develop` `6203e90`; the Owner's directive of 21 November resuming TASK-038 to TASK-050 under D-105, D-130 and D-133; the host's note relaying the Owner's decision on T-56, recorded here as D-137 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-043-performance-harness`, from `6203e9048024f47cf73b241dfc9ca16b6db97066` (origin/develop, the squash merge of TASK-042's pull request #57) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the stash untouched; no branch or pull request of TASK-043 and no open pull request; the executor state at `BUILD` for TASK-043 with this session's lock, the merge of #57 verified; TASK-041, TASK-042, TASK-038 and TASK-014 `DONE`, no gate. PHP, the native `tsc` and ESLint are not on this machine (`npx eslint` answers that `eslint` is not recognized), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npx eslint tests/Performance/scenarios/perf01.js` | Local | 21 November, 23:58 | under 1 min | `NOT_RUN`: ESLint is not installed natively (`'eslint' is not recognized`), nor `tsc` or PHP; the fast run runs them |
| Guard | Local, on `83a0ada` (checkpoint 1) | 21 November 23:59 to 22 November 00:04 | about 5 min | Two candidates, `http://bantukasir-app:8080` in `compose.yaml` and `perf01.js`, judged no finding (section 9); its accepting run superseded by checkpoints 2 to 4, made before any push |
| Guard | Local, on `7dbe362` (checkpoints 1 to 4) | 22 November, 00:21 to 00:28, then the accepting run | about 7 min, two passes | The same two candidates, judged again no finding; accepted with the hash `27190cc7…` |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed PostgreSQL file `DatasetTest` | Fast run 1, 44930804317 on `7dbe362` (checkpoints 1 to 4) | 22 November, 00:34 to 00:40 | about 6 min for the job | `FAIL` (F-1): every step `PASS` — Pint, PHPStan, ESLint, the typecheck, `BudgetsTest` — except `composer test:unit`, one assertion of `PostgresPartsTest`, and the targeted `DatasetTest`, two of its four tests; the growth dataset and the plan and record tests `PASS` |
| Guard | Local, on `1276eb1` (checkpoint 5, F-1's fixes) | 22 November, 00:41 to 00:47 | about 6 min | `GUARD PASS`, no candidate |
| The same checks, the targeted retest of F-1 (D-133) | Fast run 2, 44931936422 on `1276eb1` | 22 November, 00:47 to 00:53 | 5 min 37 s for the run | `PASS`: every step, `PostgresPartsTest` and the four tests of `DatasetTest` among them |
| Guard | Local, on `efaaaa0` (checkpoint 6, the review's fixes) | 22 November, 01:11 to 01:18 | about 7 min | `GUARD PASS`, no candidate (a transient `fork: retry` of Git Bash, then the scans complete) |
| Guard preview of the record | Local, the uncommitted record against `origin/develop` | 22 November, 01:18 | under 1 min | Three candidates, judged no finding (section 9) |
| The same checks, the targeted retest of the review's fixes (D-133) | Fast run 3, 44934771632 on `efaaaa0` (checkpoint 6) | 22 November, 01:18 to 01:23 | 5 min 29 s for the run | `PASS`: every step, `BudgetsTest` with the regression tests of R-01 and R-02, and `DatasetTest`, unchanged, run with the record's new fields |
| Guard | Local, on `352b568` (checkpoint 7, R-12 to R-14) | 22 November, 01:28 to 01:35 | about 7 min | `GUARD PASS`, no candidate |
| The same checks, the targeted retest of R-12 to R-14 (D-133) | Fast run 4, 44936355273 on `352b568` | 22 November, 01:35 to 01:41 | 5 min 38 s for the run | `PASS`: every step, `DatasetTest` with R-12's case and R-14's assertions. Four fast runs of the ten of CE-46 |
| The command contract with `composer test:postgres` (four parts), `test:race`, the build and the browser suite; the image checks; the restore rehearsal; the fast checks; the new job `Migration rehearsal` with the dry run | CI on the pull request's first head, run 44938111782 (fast checks 44938084208) on `0151fe3` | 22 November, 01:55 to 02:37 | Command contract 42 min 10 s; PostgreSQL parts 15 min 34 s, 13 min 18 s, 13 min 44 s, 13 min 46 s; image checks 5 min 38 s; restore rehearsal 4 min 8 s; fast checks 6 min 36 s; `Migration rehearsal` 11 min 59 s | `PASS` for every job but `Migration rehearsal`, `FAIL` (F-3): steps 1 to 3 `PASS` — the base dataset built, its reconciliation `CLEAN`, no new migration, the verification `JOB_PASSED` — and step 4, the dry run, `PERF_DRY_RUN_INCOMPLETE no sample of op_sale_200_lines` |
| The same jobs, the targeted retest of F-3 (D-133) | CI on the fix cycle's head | — | — | `NOT_RUN` until then; every push starts the whole contract and the image checks again (CE-41) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The generators of the base dataset of TECHNICAL_RULES section 13.2 and of the ten-year growth dataset, synthetic, through the Actions, only where the marker and the environment name are test or local, reproducible from a seed, at any scale of the section's counts | `tests/Performance/Dataset/` (`Datasets`, `DatasetSize`, `ShopBuilder`, `Seeded`, `Fingerprint`); `php tests/Performance/perf.php dataset` |
| 2. The load tool, k6 2.3.0 (AGPL-3.0), and the scenarios of section 13.1 — two cashiers checking out (exact lookups, carts of 50 and of 200 lines) while the Owner runs the period and the 366-day reports, Product Search, the stock attention refresh and page visits; the close of a session, refunds, the CSV import of 5,000 rows and the replay of 10,000 movements — with the record of each run: versions, configuration, indexes, host, dataset, run count, each budget's statistic, and a reconciliation before and after | `tests/Performance/scenarios/perf01.js`, the service `k6` of `compose.yaml`; `tests/Performance/Budgets.php`, `Plan.php`, `Recorder.php`; `perf.php plan` and `record`; TOOLCHAIN section 1.1 |
| 3. The dry run, recorded as `NOT_RUN` for PERF01 | The job `Migration rehearsal`, step 4 (section 5) |
| 4. The CI job that rehearses every migration on the populated base dataset (REL-21) | `.github/workflows/ci.yml`, job `Migration rehearsal`; `scripts/checks/migration-rehearsal.sh` |

## 3. Readings and technical choices

| ID | Reading or choice |
| --- | --- |
| C-1 | **Where the harness lives.** In `tests/Performance/`, the test harness, which the release build does not contain (STK-22: the contract's expected folder), on `TokoContoh` and the Actions (EXC-45); it writes only to the ignored folder `storage/app/perf`. Nothing in `app/` changes, so no table gains a writer and the one-writer scans of `app/` are untouched |
| C-2 | **Through the Actions, on the test clock.** Every record is the command of its Action, run by the Owner or by a cashier as themselves; the test clock of STK-31 stands at each record's instant, so the history lies on its business dates: the catalogue on the day before the first day, then 365 days (base) or 3,650 (growth) of documents and sessions, ending on the dataset's last day, yesterday in Asia/Jakarta by default, so that the server, which reads the real clock, sees a past. The builder keeps in memory what a page would have read — prices, product versions, stock — so that no command is refused, and receives a product before a Sale would need more than it holds |
| C-3 | **The base dataset's shape.** 5,000 active and 500 inactive products in 50 categories; one EAN-13 token on each active product and a second on every other one, 7,500 active, and 500 added and retired, in the GS1 prefix 20 of restricted circulation, which names no real product; the first product holds exactly 10,000 movements — its opening receipt, 2,999 Sales of nine lines and 7,000 receivings of ten lines, each with that product — so about 102,000 movement lines; three registers, two cashiers, Rina and Dewi of KEY_SCREENS section 5, with a session each on every day with a Sale. The product of 10,000 movements stays out of the dry run's carts and refunds, so that its replay stays within the cap of BR-COST-28 (`costing.replay.movement_cap`, 10,000) |
| C-4 | **The growth dataset's shape.** The same catalogue; 600,000 Sales of one to four lines over 3,650 days, 1,500,000 lines and movements; each receipt printed once and again three times, so about five audit events a Sale, about 3,000,000 in all, the volume of section 13.2. Its full build through the Actions takes hours, so CI builds only the base dataset; the growth generator is proven at a small scale (section 4) and is for TASK-048 |
| C-5 | **Reproducible from a seed (AC-043-01).** PHP's Mersenne Twister, seeded, makes every choice; the same seed, scale and last day give the same content — categories, products, tokens, each product's movements in order, each Sale's day, lines and payment, the counts of audit events, sessions and lines — compared as one SHA-256 that leaves out what a build makes anew (keys, intents, numbers). The seed of CI is 43 |
| C-6 | **The scale.** In millionths of the counts of section 13.2 (`--scale`, 1,000,000 by default), each count kept at the minimum the shape needs, so that the tests build the same shape small |
| C-7 | **The load tool.** k6 2.3.0, pinned by digest, AGPL-3.0, a separate test tool that nothing distributes (as MinIO, TOOLCHAIN section 1.1); its scripted scenarios keep the sign-in cookies, send the CSRF header from the cookie, the command envelope with a new intent and key, the Inertia visits with the page's version and the deferred prop's partial reload, as a page does. 2.4.0 is less than two weeks old. v2.0.0's breaking changes (its release notes, read on 21 November) touch none of the APIs used (EXC-03) |
| C-8 | **The scenarios and their recording (AC-043-02).** Each of the twelve budgets has its trend `op_*` and failure counter `fail_*`; the record names, for each, the statistic its budget names — the 95th percentile, or the longest for the two 30-second budgets — beside the proposed budget, which a dry run never judges. `BudgetsTest` compares the twelve names and figures with section 13.1's table and the script's metrics. The host is described by its kind, system, processors and memory, never by a name or address. The record step fails when a metric has no successful sample, so that the dry run is of every scenario |
| C-9 | **The rehearsal of REL-21.** The base of the change builds the base dataset with its own code; the head's migration job (`release:migrate`, RB-20 step 8) then applies the head's new migrations — those of its tree that the base lacks, since a merged migration never changes (EXC-41) — with the preflight and the verification queries of REL-20: row counts, constraints valid, privileges equal to SCHEMA section 10, a clean `reconciliation:run`. TASK-043's own base holds no harness, so for this Task alone the dataset is built at the head, which adds no migration, and the job verifies it the same way; from TASK-043's merge on, every change's migrations are rehearsed on data built before them. The earlier migrations each create tables and move no data |
| C-10 | **The dry run (AC-043-03).** In CI, after the rehearsal, on the populated head, one run of 120 seconds by default (`PERF_RUNS`, `PERF_SECONDS`), each with its plan (people with passwords made for the run, never printed), the load tool's run and its record, printed and added to the job's summary. The local environment runs on CI's runner (EXECUTION_CONTEXT section 18, D-108), which is the contract's "locally". Its results are a dry run, labelled so: PERF01 stays `NOT_RUN` until TASK-048 runs it on the intended server |

## 4. Acceptance criteria

| Criterion | Proof | Status |
| --- | --- | --- |
| AC-043-01 | `DatasetTest`: the base dataset at a small scale built through the Actions, its counts, a clean reconciliation, the same fingerprint from the same seed and another from another seed; the growth dataset with a clean reconciliation; the refusal outside test and local; `BudgetsTest`: the full counts of section 13.2 | `PASS`, fast run 2 (`1276eb1`), and again in fast runs 3 and 4 (`efaaaa0`, `352b568`) after the review's fixes changed `Recorder`, `BudgetsTest` and `DatasetTest` |
| AC-043-02 | `BudgetsTest` (twelve budgets, names, figures and statistics against section 13.1, each measured by the script, Product Search on its results and the reports' null read as failures since R-01 and R-02); `DatasetTest` (the record's versions, configuration, indexes, host, dataset, results, reconciliation before and after) | `PASS`, fast run 4 (`352b568`) and the Command contract of run 44938111782 (`0151fe3`); F-3's fix changes the script and `BudgetsTest`, so it runs again on the fix cycle's head |
| AC-043-03 | The dry run of the job `Migration rehearsal`, its record labelled as no PERF01 evidence | `FAIL` on `0151fe3` (F-3, section 11); `NOT_RUN` on the fix cycle's head until its run |
| AC-043-04 | The job `Migration rehearsal` | Steps 1 to 3, the rehearsal, `PASS` on `0151fe3` (run 44938111782); the job as a whole `NOT_RUN` on the fix cycle's head until its run |

## 4.1 Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | Fast run 1 (44930804317 on `7dbe362`): `PostgresPartsTest:262` counted the workflow's `if: always()` steps as the parts plus two, and the new job adds its own stop; `DatasetTest:59` expected at least 61 audit events, one for each movement line, while a document of several lines writes one, so 58; `DatasetTest:98` read through the harness's observer while the test's environment name was still `staging`, which the observer's own marker check refused | Fixed in one push (checkpoint 5, `1276eb1`): the parts plus three; at least 49 audit events, one for each command the builder sends (the comment counts them); the test's name restored before the observer reads. Fast run 2 is their one targeted retest (D-133) |
| F-3 | The automated reviewer's P1 finding on pull request #59 (review comment 4654439960), confirmed by run 44938111782: k6 empties a VU's cookie jar after each iteration by default, while the cashiers and the Owner's reports sign in once, in their first iteration; from the second on their requests ran without a session — 240 failed barcode lookups against 6 samples, 70 failed Sales of 50 lines against 2 — and the Sale of 200 lines, first tried in the eighth iteration, had no sample, so `perf.php record` failed the job | Fixed in the fix cycle's push: `noCookiesReset: true` in the script's options, so each VU keeps its sign-in for the run; regression assertion in `BudgetsTest::test_the_scenarios_measure_each_metric`. The run on the new head is its one targeted retest (D-133) |
| F-2 | Carried for this Task: TASK-025 finding F-5 (the worklist's condition over every cost source) and TASK-036 finding F-9 (whether a reconciliation of every product finishes within its budget on the provisional dataset) | The costs-to-complete worklist is among the page visits of the scenarios; each record times the reconciliation before and after the run. Their measurement against the budgets stays for TASK-048 |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| Dataset generation with a reconciliation after it: the base dataset at a small scale of its shape through the Actions — its counts, no session left open, the Sale on its business date, a clean reconciliation — built twice from one seed to one fingerprint and once from another to another; the growth dataset at a small scale with its prints and a clean reconciliation; the refusal outside test and local and on an installation with products; the plan and the record of a run, a dry run, with no password or host name in it | `tests/Feature/Performance/DatasetTest.php` (L2, joining `Postgres1`) |
| The budgets and the full counts: twelve budgets of section 13.1 by name, figure and statistic, each measured by the script; the counts of section 13.2 | `tests/Unit/Performance/BudgetsTest.php` (L1) |
| The dry run of every scenario | The job `Migration rehearsal`, step 4: the record fails when a metric has no successful sample |
| The CI migration rehearsal | The job `Migration rehearsal`, step 3; the job's place among CI's jobs in `tests/Unit/StaticChecks/PostgresPartsTest.php` |
| The standing obligations of EXECUTION_CONTEXT section 10 | N/A: no route, table, command, read, page, procedure or document is added; the harness runs only where the marker is test or local |

## 6. Database (EXC-41 to EXC-47)

| Rule | This Task |
| --- | --- |
| EXC-41 to EXC-44 | No migration, no table, no key: N/A |
| EXC-45 | Every record of both datasets through the Actions, as the fixtures of Toko Contoh (C-2) |
| EXC-46 | The rehearsal of REL-21 on the provisional dataset exists from this Task on: the job `Migration rehearsal` (C-9) |
| EXC-47 | No new table or kind of stored file; the backup counts and files are unchanged |

## 7. Definition of Done

| Dimension | Status |
| --- | --- |
| Business | `NOT_RUN` — AC-043-01 and AC-043-02 `PASS` at L1 and L2 in fast run 4; AC-043-03 and AC-043-04 on the pull request's final head |
| UI and UX | N/A — no page; the scenarios only read and send what the pages do |
| Backend | N/A — no code in `app/` (C-1) |
| Database | N/A — no migration; the rehearsal of every later one exists (EXC-46) |
| Security | `PASS` by construction, checked in review: the harness refuses every environment but test and local, its passwords are made at run time and kept in an ignored folder, the record holds no host name, address or password; the guard |
| Data integrity | `PASS` at L2 (fast run 4) — every record through the Actions and a clean reconciliation after each build; the full dataset's on the final head |
| Performance | `NOT_RUN` for PERF01, by the contract: the dry run records, PERF01's final point is TASK-048 on the intended server (TS-09) |
| Operations | `NOT_RUN` until the final head — the migration job's verification on the populated dataset; no operator step |
| Verification | `NOT_RUN` — the self-review and the reviewer's check (section 8); the fast run; the command contract, the image checks and the rehearsal on the pull request's final head |

## 8. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review: the generators against section 13.2 and EXC-45 — every record an Action on the test clock, the stock and versions kept as a page reads them, the product of 10,000 movements within the replay's cap; the marker check before any read; the scenarios against the routes, envelopes, FormRequests and rate buckets of each operation; the record's fields against section 13.2; the rehearsal against REL-20, REL-21 and the preflight of `release:migrate`; no secret committed or printed; the record of D-137 against the Owner's words and TASK_PLAN's conventions. Two carried findings taken in (F-2).

Round 1, the reviewer's check of class B, once:

| | |
| --- | --- |
| Agent | `bk-reviewer-high`, model `claude-opus-5-5`, effort High, tools Read, Grep and Glob |
| Package | `.git/bantukasir-executor/review/TASK-043/r1`, base `6203e90`, HEAD `1276eb1` with the uncommitted record, written 22 November 00:54; `--check` after the review: `UNCHANGED` |
| Verdict | `FAIL`: 1 MUST-FIX, 3 SHOULD-FIX, 7 NOTE; no test run asked for |

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | AC-043-02: `op_product_search` timed the Inertia visit of RP-301, whose props carry no result; the page reads its results from RD-01 `context=catalog` | Fixed (checkpoint 6, `efaaaa0`): the metric times the RD-01 read with the page's `X-Search-Sequence` header, after the page's shell; regression test in `BudgetsTest` |
| R-02 | SHOULD-FIX | A report that fails its read answers 200 with `report: null` (UXS-22), counted as a successful sample | Fixed (checkpoint 6): a visit names the prop that must not be null; such a visit is a failure, kept out of the trend; regression test in `BudgetsTest` |
| R-03 | SHOULD-FIX | The evidence's statuses stale after fast run 2; no section 8; the dry-run table to copy from the job's summary | Fixed in this record: sections 4, 7 and 8; the dry run's table and step durations are copied into this file from the final head's run by the record of the merge (section 10) |
| R-04 | SHOULD-FIX | DEPENDENCY_GRAPH's "three edges"; TASK-042's direct dependents; TASK_PLAN's Decisions row | Fixed: two edges; TASK-051 among TASK-042's dependents; D-137 in the row |
| R-05 | NOTE | The job's budget under 55 minutes is unmeasured | Recorded: the durations of the first run go into this file (section 10) |
| R-06 | NOTE | A timeout of a slow runner in the dry run would fail the job | Recorded in section 10: read as the runner's condition in a fix cycle |
| R-07 | NOTE | `/home`'s deferred props are not timed | Kept: the page visit times the shell's server time, which the budget names; the stock attention budget is RD-02's refresh, timed on its own |
| R-08 | NOTE | Nothing measures paging and indexes on the growth dataset | Recorded for TASK-048 (section 10) |
| R-09 | NOTE | On a pull request the checkout is GitHub's merge commit | Fixed (checkpoint 6): the record names the pull request's head too (`PERF_HEAD`) |
| R-10 | NOTE | `opcache.enable` is the CLI's | Fixed (checkpoint 6): the field is named `opcacheCli` |
| R-11 | NOTE | Relabelling the performance target `local` would bend ENV-04 | Fixed in section 10: a change control of TASK-048 |

After the review the working tree, the index, HEAD and the executor state were as the package's manifest records them (`--check` `UNCHANGED`).

Round 2, the targeted check of the MUST-FIX's fix (CE-40), once:

| | |
| --- | --- |
| Agent | `bk-reviewer-high`, model `claude-opus-5-5`, effort High, mode verify |
| Package | `.git/bantukasir-executor/review/TASK-043/r2`, tree `3a08593`, HEAD `efaaaa0`, previous r1, written 22 November 01:24; `--check` after it: `UNCHANGED` |
| Verdict | `PASS`: R-01 and R-02 FIXED, and R-03, R-04, R-09, R-10 and R-11; no MUST-FIX; 1 new SHOULD-FIX, 2 NOTE; no test run asked for |

| ID | Class | Finding | Outcome |
| --- | --- | --- | --- |
| R-12 | SHOULD-FIX | Since R-02, a failed visit counted only as a failure while a failed read or command counted as a sample too, so `unmeasured()` (`samples === 0 or failures >= samples`) would flag a report with one success and one failure and turn the job red | Fixed (checkpoint 7, `352b568`): every trend holds the successful answers only and every failure counter the others, so `unmeasured()` is `samples === 0` for every metric; regression case in `DatasetTest` (one success, one failure: measured) |
| R-13 | NOTE | The Markdown table named only the merge commit, not the head | Fixed (checkpoint 7): the table names the head beside the revision |
| R-14 | NOTE | Fast run 3's row said `DatasetTest` asserted the record's new fields | Fixed: `DatasetTest` now asserts `opcacheCli` and `head` (checkpoint 7), run in fast run 4 |

R-12's fix is not a MUST-FIX's, so it has no targeted check of its own (CE-40); fast run 4 is its one retest.

## 9. Guard's candidates (CE-31; D-076, D-097, D-098)

| Candidate | Where | Judgment |
| --- | --- | --- |
| `http://bantukasir-app:8080` (url) | `compose.yaml` and `tests/Performance/scenarios/perf01.js`, added in `83a0ada` | The network alias of the local environment's application container, already in `compose.yaml` for the browser tests (`E2E_BASE_URL`): no real host, no person, no client datum. No finding; accepted with the candidates' hash `1ec33bac…` on checkpoint 1 and `27190cc7…` on checkpoints 1 to 4 |
| — | Checkpoints 5 (`1276eb1`), 6 (`efaaaa0`) and 7 (`352b568`) | No candidate: `GUARD PASS` (00:47, 01:17, 01:35) |
| `http://bantukasir-app:8080` (url), three times | `guard.sh --preview` of the record against `origin/develop` (22 November, 01:18): `compose.yaml`, `perf01.js` and this file, where it quotes the candidate | The same container alias, judged again: no finding |
| `http://bantukasir-app:8080` (url), three times | `guard.sh --preview` of the fix cycle of F-3 (22 November, 02:41): the same three files | The same container alias: no finding |

## 10. For later Tasks and the record

- TASK-048: PERF01 on the intended server with this harness — `perf.php dataset` at the scale of the final dataset (gate G2), `plan`, the load tool's run and `record` per run, with the operator; the growth dataset's full build through the Actions takes hours there (C-4). The reconciliation of every product is timed in each record (TASK-036 finding F-9), and the costs-to-complete worklist is among the page visits (TASK-025 finding F-5).
- TASK-048: the generators and `perf.php` run only where the marker and the environment name are test or local (scope item 1), and a marker states what an environment is (ENV-04), so the performance target of ENVIRONMENTS section 2 needs a change control in TASK-048 — an environment and marker of its own that the harness accepts — never a relabelled `local`; the load tool's script itself takes the address in `PERF_BASE_URL` and the plan as a file (review R-11).
- TASK-048: the second item of PERF01 — the ten-year growth dataset keeps paging and indexes bounded — needs its own check on the growth dataset, which this harness builds but does not measure (review R-08).
- The dry run fails the job when a metric has no successful sample. A miss of the CSV import or the replay that is only a timeout of a slow shared runner is a condition of the runner, not a failure of the rehearsal of REL-21, and is read so in a fix cycle (review R-06).
- The dry run's table and the job's step durations, which the script prints and adds to the job's summary, are copied into this file from the run on the pull request's final head by the record of this merge (CE-50, TASK-051's first record), or by a fix cycle if that run is red (review R-03, R-05). The red run on the first head is section 11.

## 11. The dry run on the first head (run 44938111782, `0151fe3`, red)

**DRY RUN: not PERF01 evidence.** Run 1 of 1, one run of 120 seconds on CI's shared runner, revision `4796b64` (GitHub's merge commit; head `0151fe3`); the reconciliation of every product `CLEAN` before (35,290 ms) and after (38,592 ms). The counts it ran on: 5,000 active and 500 inactive products in 50 categories, 7,500 active and 500 retired tokens, 101,991 movements, 10,000 of them of the first product, 3 registers, 2 cashiers, 730 sessions, 2,999 Sales, 7,010 receivings, 26,031 audit events, seed 43. The figures from the second iteration on are void (F-3): the requests ran without a session.

| Operation | Proposed budget | Statistic | Observed (ms) | Samples | Failures |
| --- | --- | --- | --- | --- | --- |
| Exact barcode lookup | 500 ms | p(95) | 25 | 6 | 240 |
| Product Search page | 700 ms | p(95) | 33 | 1 | 27 |
| Sale commit, 50 lines | 2000 ms | p(95) | 171 | 2 | 70 |
| Sale commit, 200 lines (the maximum) | 5000 ms | p(95) | - | 0 | 10 |
| Cash session close | 2000 ms | p(95) | 31 | 3 | 0 |
| Refund or return commit | 2000 ms | p(95) | 30 | 10 | 0 |
| Ordinary period report (up to 31 days) | 2000 ms | p(95) | 50 | 1 | 27 |
| Report over the maximum range (366 days) | 10000 ms | p(95) | 39 | 1 | 6 |
| Stock attention refresh | 500 ms | p(95) | 22 | 2 | 54 |
| Server time of an ordinary page visit | 500 ms | p(95) | 28 | 1 | 27 |
| CSV import confirmation, 5,000 rows | 30000 ms | max | 4213 | 1 | 0 |
| Cost-correction replay, 10,000 movements of one product | 30000 ms | max | 226 | 1 | 0 |

The job's steps (review R-05), of 11 min 59 s: 1, the base's environment, 2 min 41 s; 2, the head's, 57 s; 3, the base dataset's build (4 min 20 s), the migration job and its verification, 4 min 56 s; 4, the plan, the load tool's pull and its run of 120 seconds and the record, 3 min 20 s.
- TASK-051, added by D-137, is the next Task; TASK-044 depends on it.

## 12. Merge

Recorded by TASK-051 (EXECUTION_CONTEXT CE-50): pull request #59, head `4684fb6b1f582bd5c185daf51406fccc25ae04dd`, CI run 44943007130 passed (every job, the migration rehearsal with its dry run among them); squash-merged by the tick under D-105 as `ccc02dd019d0ed5755d347baeb07ddf9a204f1d9` on `develop`, the automated review's status DONE; CI on `develop` run 44945896318 passed. TASK-043 is `DONE`.

The dry run on the final head, copied from the job `Migration rehearsal` of run 44943007130 (section 10). **DRY RUN: not PERF01 evidence.** Run 1 of 1, one run of 120 seconds on CI's shared runner, revision `be1a8be` (GitHub's merge commit; head `4684fb6`); the reconciliation of every product `CLEAN` before (33,487 ms) and after (37,768 ms). The counts: 5,000 active and 500 inactive products in 50 categories, 7,500 active and 500 retired tokens, 101,991 movements, 10,000 of them of the first product, 3 registers, 2 cashiers, 730 sessions, 2,999 Sales with 26,991 lines, 7,010 receivings, 26,031 audit events, seed 43. Every sample succeeded.

| Operation | Proposed budget | Statistic | Observed (ms) | Samples | Failures |
| --- | --- | --- | --- | --- | --- |
| Exact barcode lookup | 500 ms | p(95) | 25 | 234 | 0 |
| Product Search page | 700 ms | p(95) | 28 | 27 | 0 |
| Sale commit, 50 lines | 2000 ms | p(95) | 130 | 70 | 0 |
| Sale commit, 200 lines (the maximum) | 5000 ms | p(95) | 426 | 8 | 0 |
| Cash session close | 2000 ms | p(95) | 33 | 3 | 0 |
| Refund or return commit | 2000 ms | p(95) | 35 | 10 | 0 |
| Ordinary period report (up to 31 days) | 2000 ms | p(95) | 40 | 27 | 0 |
| Report over the maximum range (366 days) | 10000 ms | p(95) | 54 | 7 | 0 |
| Stock attention refresh | 500 ms | p(95) | 27 | 54 | 0 |
| Server time of an ordinary page visit | 500 ms | p(95) | 238 | 27 | 0 |
| CSV import confirmation, 5,000 rows | 30000 ms | max | 4247 | 1 | 0 |
| Cost-correction replay, 10,000 movements of one product | 30000 ms | max | 261 | 1 | 0 |

The job's steps, of 11 min 17 s, as the script measured them: 1, the base's environment at `6203e90`, 153 s; 2, the head's, 54 s; 3, the base dataset's build and the rehearsal of the head's migrations (none), 269 s; 4, the plan, the load tool's pull, its run and the record, 196 s.
