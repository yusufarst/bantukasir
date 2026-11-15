# UAT-20 rehearsal script — reconciliation incident on staging

| | |
| --- | --- |
| Status | PREPARED by TASK-036 (scope item 6), 15 November 2026 (Asia/Jakarta); run by the operator with the Owner in TASK-045 |
| Script | [UAT_PILOT](UAT_PILOT.md) UAT-20 (UXT-34; suites INV01, OPPROC01) |
| Runbooks | [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md) OPR-01 to OPR-05, RB-03, RB-04; SECURITY_MODEL SEC-OP-07 |
| Where | **Staging only.** Never production: no production operation is authorized (AGENTS rule 10; PRODUCTION_DATA_SAFETY) |

The operator — a person, never an agent — runs this script on staging with the Owner present. It induces one stock mismatch with one documented statement, lets the reconciliation find it, repairs it with the projection rebuild and lets the Owner reopen the product. Every step is recorded in the operator's private operations record (OPR-03); the repository receives a summary without identities (EXC-62).

## Preconditions

1. TASK-045 is the active Task and the Owner authorized this rehearsal on staging; the operator holds staging's migration role credential only for step 3.
2. Staging runs the release candidate's image; `php artisan settings:check` passes in the application container.
3. A synthetic product of staging's own test data — never a product the shop sells — has at least one movement. Write down its internal key `P`, its stock on hand and the time. No real business data is used.
4. The last reconciliation on staging is `CLEAN` (`reconciliation_runs`, the newest row).

## Steps

| Step | Who | Action | Expected |
| --- | --- | --- | --- |
| 1 | Operator | Confirm the environment from its configuration and its database marker (OPR-02): the marker reads `staging` | `staging` |
| 2 | Operator | `php artisan reconciliation:run --operator --environment=staging --product=P` in the application container | `RECONCILIATION_CLEAN`, scope `PRODUCT` |
| 3 | Operator | The documented statement, as staging's migration role, in one transaction: `UPDATE stock_balances SET on_hand = on_hand + 1 WHERE product_id = P;` Nothing else is changed. This is the only statement of this script that touches a projection by hand; OPR-05 forbids it everywhere else | One row updated |
| 4 | Operator | `php artisan reconciliation:run --operator --environment=staging` | Exit status 1, `RECONCILIATION_MISMATCH`, `blocks_written=1`; one `reconciliation_runs` row `MISMATCH`; the audit event `RECONCILIATION_BLOCK` |
| 5 | Owner | Stok → "Insiden rekonsiliasi" (UXI-017, with the count); open the incident (UXP-415) | "Mutasi diblokir"; "Yang tidak cocok" `BALANCE_MISMATCH`; "Produk yang diblokir" names P |
| 6 | Owner | Try a stock posting of P, for example "Barang masuk" of one unit | Refused "Mutasi diblokir" (UXS-28); nothing saved; another product posts |
| 7 | Owner | Request the rebuild, in writing, naming P | The request in the operations record |
| 8 | Operator | `php artisan projection:rebuild --environment=staging --product=P --note="<the Owner's request, by date>"` | `The projections of product P were rebuilt from its facts`; the audit event `PROJECTION_REBUILT` with the procedure as actor; no fact changed |
| 9 | Operator | `php artisan reconciliation:run --operator --environment=staging --product=P` (RB-04 step 6) | `RECONCILIATION_CLEAN` |
| 10 | Owner | On UXP-415, "Buka kembali mutasi", the confirmation, then the password (UXS-11) | "Mutasi dibuka kembali."; the incident shows "Mutasi dibuka kembali"; the count of UXI-017 is one less |
| 11 | Owner | The stock posting of step 6 again | Saved |
| 12 | Operator | `php artisan reconciliation:run --operator --environment=staging` | `RECONCILIATION_CLEAN` |

## Record

For each step: the time in Asia/Jakarta, who ran it by role, the command's last line, and PASS or FAIL. A FAIL stops the script; the operator restores nothing by hand and reports to the Owner (RB-03). The summary for the repository names no person, address or credential.
