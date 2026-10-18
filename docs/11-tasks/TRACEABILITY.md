# Traceability

| | |
| --- | --- |
| Status | CURRENT — written in RF-7 under D-090, 18 October 2026 (Asia/Jakarta); generated from the Task contracts and [TASK_PLAN](TASK_PLAN.md), which prevail |
| Phase | P11 |
| Related | [REQUIREMENTS](../01-product/REQUIREMENTS.md), [CRITICAL_FLOWS](../03-workflows/CRITICAL_FLOWS.md), [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md), [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md), [SCHEMA](../04-architecture/SCHEMA.md), [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md), [USER_TASKS](../07-ux-design/USER_TASKS.md), [UAT_PILOT](../10-release/UAT_PILOT.md) |

Where each item of the V1 specification is built and where it is finally proven. Every row names the one Task that carries or owns the item and, where others add to it, those Tasks. A Task contract lists the same items in its sections 4.2 and 5.

## 1. How to read

- **Carried by / owned by** — the one Task accountable for the item: it implements the requirement's core, the workflow end to end, the suite's harness and main cases, the race, guard or threat test, the route, the table, the page or the control.
- **Contributed by** — Tasks that add a part: a case of a suite, a context of a read, a section of a page, a key of a table created earlier.
- **Final point** — the verification Task where the suite reaches `FINAL_PASS` (TEST_STRATEGY section 12; FD-07).

## 2. Requirements

| Requirement | Carried by | Contributed by | Suites (ACCEPTANCE_SUITES section 5) | Final point |
| --- | --- | --- | --- | --- |
| REQ-SCOPE-01 | [TASK-044](tasks/TASK-044-integrated-verification.md) | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-028](tasks/TASK-028-checkout.md) | SEARCH02, INV01, POS01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SCOPE-02 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-028](tasks/TASK-028-checkout.md), [TASK-044](tasks/TASK-044-integrated-verification.md) | PAYEXT01, UX01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SCOPE-03 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — | IDENTITY01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-01 | [TASK-006](tasks/TASK-006-authentication.md) | — | AUTH01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-02 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-011](tasks/TASK-011-user-management.md) | AUTH01, ACC01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-03 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — | AUTH01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-04 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-006](tasks/TASK-006-authentication.md) | AUTH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-05 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-006](tasks/TASK-006-authentication.md), [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | LINK01, OPPROC01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-ACC-06 | [TASK-011](tasks/TASK-011-user-management.md) | — | AUTH01, AUTH03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-07 | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-007](tasks/TASK-007-authorization-shell.md) | SIGNIN01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-08 | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-011](tasks/TASK-011-user-management.md), [TASK-020](tasks/TASK-020-barcode-correction.md), [TASK-024](tasks/TASK-024-stock-adjustment.md), [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-026](tasks/TASK-026-reversal.md), [TASK-033](tasks/TASK-033-refunds-core.md), [TASK-035](tasks/TASK-035-onboarding.md) | AUTH03, INV03, RET01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-09 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-035](tasks/TASK-035-onboarding.md) | AUTH03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-10 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-042](tasks/TASK-042-audit-views.md) | ACC01, AUDIT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-11 | [TASK-011](tasks/TASK-011-user-management.md) | — | ACC01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-ACC-12 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md) | ACC01, OPPROC01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-CFG-01 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — | IDENTITY01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CFG-02 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | IDENTITY01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CFG-03 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-018](tasks/TASK-018-photos-labels.md) | SEC01, IDENTITY01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CFG-04 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | AUTH03, CONFIG01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-01 | [TASK-015](tasks/TASK-015-catalog-core.md) | [TASK-017](tasks/TASK-017-catalog-pages.md) | SEARCH01, SEARCH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-02 | [TASK-015](tasks/TASK-015-catalog-core.md) | — | SEARCH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-03 | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-016](tasks/TASK-016-barcodes.md) | SEARCH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-04 | [TASK-016](tasks/TASK-016-barcodes.md) | — | SEARCH01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-05 | [TASK-016](tasks/TASK-016-barcodes.md) | — | SEARCH01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-06 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | — | SEARCH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-07 | [TASK-015](tasks/TASK-015-catalog-core.md) | [TASK-017](tasks/TASK-017-catalog-pages.md) | SEARCH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-08 | [TASK-015](tasks/TASK-015-catalog-core.md) | [TASK-028](tasks/TASK-028-checkout.md) | SEARCH02, POS02, POS03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-09 | [TASK-019](tasks/TASK-019-csv-import.md) | [TASK-038](tasks/TASK-038-housekeeping.md) | IMPORT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-10 | [TASK-018](tasks/TASK-018-photos-labels.md) | [TASK-038](tasks/TASK-038-housekeeping.md) | MEDIA01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-11 | [TASK-018](tasks/TASK-018-photos-labels.md) | [TASK-046](tasks/TASK-046-device-verification.md) | SESSION08, DEVICE01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-046](tasks/TASK-046-device-verification.md) |
| REQ-CAT-12 | [TASK-015](tasks/TASK-015-catalog-core.md) | [TASK-017](tasks/TASK-017-catalog-pages.md) | SEARCH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CAT-13 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — | BARCODE01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SCAN-01 | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-046](tasks/TASK-046-device-verification.md) | SEARCH03, DEVICE01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-046](tasks/TASK-046-device-verification.md) |
| REQ-SCAN-02 | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-016](tasks/TASK-016-barcodes.md) | SEARCH03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SCAN-03 | [TASK-029](tasks/TASK-029-till-pages.md) | — | SEARCH03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SCAN-04 | [TASK-029](tasks/TASK-029-till-pages.md) | — | SEARCH03, SESSION03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-01 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | — | INV01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-02 | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-028](tasks/TASK-028-checkout.md) | INV02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-03 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-024](tasks/TASK-024-stock-adjustment.md), [TASK-028](tasks/TASK-028-checkout.md) | INV01, INV02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-04 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | INV01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-05 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-025](tasks/TASK-025-cost-correction.md) | AUTH02, INV01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-06 | [TASK-023](tasks/TASK-023-manual-issue.md) | — | INV02, FIN01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-07 | [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-037](tasks/TASK-037-restore-tooling.md) | OPEN01, REC02 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-INV-08 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — | INV03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-09 | [TASK-026](tasks/TASK-026-reversal.md) | [TASK-035](tasks/TASK-035-onboarding.md) | INV03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-10 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — | STOCK01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-11 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — | AUTH02, INV01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-INV-12 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | [TASK-039](tasks/TASK-039-system-status.md) | INV01, STATUS01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-INV-13 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | — | INV01, FIN03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-01 | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md) | FIN01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-02 | [TASK-003](tasks/TASK-003-kernel-localization.md) | — | FIN01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-03 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-025](tasks/TASK-025-cost-correction.md) | FIN02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-04 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-040](tasks/TASK-040-finance-report.md) | AUTH02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-05 | [TASK-025](tasks/TASK-025-cost-correction.md) | — | INV03, FIN02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-06 | [TASK-025](tasks/TASK-025-cost-correction.md) | [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-043](tasks/TASK-043-performance-harness.md), [TASK-048](tasks/TASK-048-performance-verification.md) | FIN03, PERF01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-048](tasks/TASK-048-performance-verification.md) |
| REQ-COST-07 | [TASK-033](tasks/TASK-033-refunds-core.md) | — | FIN03, RET01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-08 | [TASK-026](tasks/TASK-026-reversal.md) | — | INV03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-09 | [TASK-040](tasks/TASK-040-finance-report.md) | [TASK-025](tasks/TASK-025-cost-correction.md) | FIN03, SESSION07 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-COST-10 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-023](tasks/TASK-023-manual-issue.md) | FIN01, POS01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-01 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS01, SESSION03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-02 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS02, POS03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-03 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-003](tasks/TASK-003-kernel-localization.md) | FIN01, POS02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-04 | [TASK-029](tasks/TASK-029-till-pages.md) | — | POS04, UX01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-05 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-06 | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-009](tasks/TASK-009-command-client.md) | POS01, POS03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-07 | [TASK-029](tasks/TASK-029-till-pages.md) | — | POS03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-08 | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-009](tasks/TASK-009-command-client.md) | POS03, CMD01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-09 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-10 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-041](tasks/TASK-041-owner-home-daily.md) | SESSION09 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-11 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — | POS03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-SALE-12 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-030](tasks/TASK-030-session-close.md) | AUTH01, SESSION10 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-PAY-01 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS02, PAYEXT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-PAY-02 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-029](tasks/TASK-029-till-pages.md) | POS02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-PAY-03 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS02, PAYEXT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-PAY-04 | [TASK-030](tasks/TASK-030-session-close.md) | — | SESSION04 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-PAY-05 | [TASK-028](tasks/TASK-028-checkout.md) | — | PAYEXT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-DOC-01 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | SESSION08, IDENTITY01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-DOC-02 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-046](tasks/TASK-046-device-verification.md) | SESSION08, DEVICE01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-046](tasks/TASK-046-device-verification.md) |
| REQ-DOC-03 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — | POS03, SESSION08 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-DOC-04 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — | AUTH02, SESSION10 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-DOC-05 | [TASK-003](tasks/TASK-003-kernel-localization.md) | — | UX01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-01 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | SESSION02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-02 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | SESSION02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-03 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | SESSION03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-04 | [TASK-028](tasks/TASK-028-checkout.md) | — | SESSION03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-05 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-034](tasks/TASK-034-drawer-refunds.md) | SESSION04 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-06 | [TASK-030](tasks/TASK-030-session-close.md) | — | SESSION05, SESSION06 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-07 | [TASK-030](tasks/TASK-030-session-close.md) | — | SESSION05 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-08 | [TASK-030](tasks/TASK-030-session-close.md) | — | SESSION06 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-09 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | SESSION07 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-10 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-027](tasks/TASK-027-registers-session-open.md) | SESSION01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-11 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — | SESSION10 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-12 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-030](tasks/TASK-030-session-close.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-046](tasks/TASK-046-device-verification.md) | SESSION07, SESSION08, SESSION10 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CASH-13 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-034](tasks/TASK-034-drawer-refunds.md) | RET02, SESSION05 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-01 | [TASK-033](tasks/TASK-033-refunds-core.md) | — | AUTH01, RET01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-02 | [TASK-033](tasks/TASK-033-refunds-core.md) | — | RET01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-03 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | [TASK-033](tasks/TASK-033-refunds-core.md) | RET02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-04 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — | RET02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-05 | [TASK-033](tasks/TASK-033-refunds-core.md) | — | RET01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-06 | [TASK-033](tasks/TASK-033-refunds-core.md) | — | RET01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-07 | [TASK-033](tasks/TASK-033-refunds-core.md) | [TASK-034](tasks/TASK-034-drawer-refunds.md), [TASK-040](tasks/TASK-040-finance-report.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) | FIN03, RET01, SESSION09 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-08 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — | RET02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RFD-09 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — | RET02 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RPT-01 | [TASK-040](tasks/TASK-040-finance-report.md) | — | FIN03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RPT-02 | [TASK-040](tasks/TASK-040-finance-report.md) | [TASK-003](tasks/TASK-003-kernel-localization.md) | FIN03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RPT-03 | [TASK-040](tasks/TASK-040-finance-report.md) | [TASK-025](tasks/TASK-025-cost-correction.md) | FIN02, FIN03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RPT-04 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — | SESSION09 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RPT-05 | [TASK-042](tasks/TASK-042-audit-views.md) | [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) | AUTH01, AUTH02, AUDIT01, SESSION10 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-RPT-06 | [TASK-040](tasks/TASK-040-finance-report.md) | — | FIN03 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-AUD-01 | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-042](tasks/TASK-042-audit-views.md) | AUDIT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-AUD-02 | [TASK-042](tasks/TASK-042-audit-views.md) | [TASK-006](tasks/TASK-006-authentication.md), [TASK-038](tasks/TASK-038-housekeeping.md) | SEC01, AUDIT01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CMD-01 | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-009](tasks/TASK-009-command-client.md) | INV01, POS01, POS03, CMD01, SESSION06 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CMD-02 | [TASK-044](tasks/TASK-044-integrated-verification.md) | [TASK-033](tasks/TASK-033-refunds-core.md) | INV01, RET01, SESSION06, UX01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-CMD-03 | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md) | REC02, OPPROC01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-CMD-04 | [TASK-008](tasks/TASK-008-command-platform.md) | — | POS01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-OPS-01 | [TASK-013](tasks/TASK-013-independent-backup.md) | [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-045](tasks/TASK-045-operations-verification.md), [TASK-050](tasks/TASK-050-production-pilot.md) | REC01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-OPS-02 | [TASK-004](tasks/TASK-004-database-foundation.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-043](tasks/TASK-043-performance-harness.md), [TASK-045](tasks/TASK-045-operations-verification.md) | DB01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-OPS-03 | [TASK-050](tasks/TASK-050-production-pilot.md) | [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-049](tasks/TASK-049-release-readiness.md) | READY01, RELEASE01 | [TASK-045](tasks/TASK-045-operations-verification.md), [TASK-050](tasks/TASK-050-production-pilot.md) |
| REQ-OPS-04 | [TASK-039](tasks/TASK-039-system-status.md) | [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-041](tasks/TASK-041-owner-home-daily.md), [TASK-045](tasks/TASK-045-operations-verification.md) | STATUS01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-OPS-05 | [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-045](tasks/TASK-045-operations-verification.md) | DB01, KEEP01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-OPS-06 | [TASK-045](tasks/TASK-045-operations-verification.md) | [TASK-012](tasks/TASK-012-application-image.md), [TASK-049](tasks/TASK-049-release-readiness.md) | SEC02, READY01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-NFR-01 | [TASK-003](tasks/TASK-003-kernel-localization.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-002](tasks/TASK-002-p7-change-control-1.md) | L10N01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-NFR-02 | [TASK-046](tasks/TASK-046-device-verification.md) | [TASK-005](tasks/TASK-005-design-system.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-047](tasks/TASK-047-p7-change-control-2.md) | UX01, DEVICE01 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-046](tasks/TASK-046-device-verification.md) |
| REQ-NFR-03 | [TASK-046](tasks/TASK-046-device-verification.md) | — | DEVICE01 | [TASK-046](tasks/TASK-046-device-verification.md) |
| REQ-NFR-04 | [TASK-048](tasks/TASK-048-performance-verification.md) | [TASK-043](tasks/TASK-043-performance-harness.md) | PERF01 | [TASK-048](tasks/TASK-048-performance-verification.md) |
| REQ-NFR-05 | [TASK-045](tasks/TASK-045-operations-verification.md) | [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-039](tasks/TASK-039-system-status.md), [TASK-050](tasks/TASK-050-production-pilot.md) | REC01, REC02 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-NFR-06 | [TASK-049](tasks/TASK-049-release-readiness.md) | [TASK-012](tasks/TASK-012-application-image.md), [TASK-013](tasks/TASK-013-independent-backup.md) | READY01 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-NFR-07 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-006](tasks/TASK-006-authentication.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-045](tasks/TASK-045-operations-verification.md) | SEC01, SEC02 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-045](tasks/TASK-045-operations-verification.md) |
| REQ-NFR-08 | [TASK-044](tasks/TASK-044-integrated-verification.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-003](tasks/TASK-003-kernel-localization.md), [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-028](tasks/TASK-028-checkout.md) | INV01, POS01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-NFR-09 | [TASK-003](tasks/TASK-003-kernel-localization.md) | [TASK-040](tasks/TASK-040-finance-report.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) | FIN03, SESSION09 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-NFR-10 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-049](tasks/TASK-049-release-readiness.md) | SESSION08 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-UX-01 | [TASK-044](tasks/TASK-044-integrated-verification.md) | [TASK-002](tasks/TASK-002-p7-change-control-1.md), [TASK-005](tasks/TASK-005-design-system.md), [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-029](tasks/TASK-029-till-pages.md) | IDENTITY01, UX01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-UX-02 | [TASK-044](tasks/TASK-044-integrated-verification.md) | [TASK-002](tasks/TASK-002-p7-change-control-1.md), [TASK-005](tasks/TASK-005-design-system.md), [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-009](tasks/TASK-009-command-client.md) | UX01 | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| REQ-UX-03 | [TASK-046](tasks/TASK-046-device-verification.md) | [TASK-005](tasks/TASK-005-design-system.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-047](tasks/TASK-047-p7-change-control-2.md) | UX02 | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-046](tasks/TASK-046-device-verification.md) |

## 3. Workflows

| Workflow | Carried by | Contributed by |
| --- | --- | --- |
| WF-01 Sign in, sign out and the sign-in session | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-007](tasks/TASK-007-authorization-shell.md) |
| WF-02 First password and reset links | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-006](tasks/TASK-006-authentication.md) |
| WF-03 Own account | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-011](tasks/TASK-011-user-management.md) |
| WF-04 Open a cash session | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| WF-05 Sale checkout | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-028](tasks/TASK-028-checkout.md) |
| WF-06 Receipt print and reprint | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| WF-07 Recover a request whose outcome is uncertain | [TASK-009](tasks/TASK-009-command-client.md) | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-029](tasks/TASK-029-till-pages.md) |
| WF-08 Close one's own cash session | [TASK-030](tasks/TASK-030-session-close.md) | — |
| WF-09 Owner-assisted close | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| WF-10 Session review and Owner review notes | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| WF-11 Refunds and returns | [TASK-034](tasks/TASK-034-drawer-refunds.md) | [TASK-033](tasks/TASK-033-refunds-core.md) |
| WF-12 Sales and sessions: lookup, print and export | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| WF-13 Product search and stock lookup | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-017](tasks/TASK-017-catalog-pages.md) |
| WF-14 Add a product (search first) | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-016](tasks/TASK-016-barcodes.md) |
| WF-15 Edit products and categories | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-015](tasks/TASK-015-catalog-core.md) |
| WF-16 Barcodes, photos and labels | [TASK-018](tasks/TASK-018-photos-labels.md) | [TASK-016](tasks/TASK-016-barcodes.md) |
| WF-17 Barcode misassignment correction | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| WF-18 CSV product import | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| WF-19 Receiving | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| WF-20 Manual issue | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| WF-21 Stock attention and history | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| WF-22 Stock adjustment | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| WF-23 Reversal | [TASK-026](tasks/TASK-026-reversal.md) | — |
| WF-24 Start and release onboarding | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| WF-25 Opening stock entry and corrections before release | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| WF-26 Cost completion and correction | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| WF-27 Reconciliation incidents | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| WF-28 Owner home and reports | [TASK-041](tasks/TASK-041-owner-home-daily.md) | [TASK-040](tasks/TASK-040-finance-report.md) |
| WF-29 User management | [TASK-011](tasks/TASK-011-user-management.md) | — |
| WF-30 BusinessProfile and cash registers | [TASK-027](tasks/TASK-027-registers-session-open.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) |
| WF-31 System status, activity and security log | [TASK-039](tasks/TASK-039-system-status.md) | [TASK-042](tasks/TASK-042-audit-views.md) |
| WF-32 Operator procedures and restore | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-011](tasks/TASK-011-user-management.md), [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-045](tasks/TASK-045-operations-verification.md) |

## 4. User tasks and UAT

UXT-05 is retired (D-077). The UAT scripts of UAT_PILOT section 2 run in TASK-049, except UAT-20, UAT-23 and UAT-24, which run with the operator in TASK-045.

| User task | Workflows | Built by | UAT scripts |
| --- | --- | --- | --- |
| UXT-01 "Masuk" | WF-01 | [TASK-006](tasks/TASK-006-authentication.md) | UAT-01, UAT-07 |
| UXT-02 "Buka sesi kas" | WF-04 | [TASK-027](tasks/TASK-027-registers-session-open.md) | UAT-01 |
| UXT-03 "Jual, bayar tunai" | WF-05 | [TASK-029](tasks/TASK-029-till-pages.md) | UAT-01 |
| UXT-04 "Jual, bayar transfer bank" | WF-05 | [TASK-029](tasks/TASK-029-till-pages.md) | UAT-02 |
| UXT-06 "Cetak struk; cetak ulang" | WF-06 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | UAT-01 |
| UXT-07 "Tangani harga berubah, stok kurang atau produk nonaktif saat bayar" | WF-05 | [TASK-029](tasks/TASK-029-till-pages.md) | UAT-03 |
| UXT-08 "Pulihkan permintaan yang statusnya belum pasti" | WF-07 | [TASK-009](tasks/TASK-009-command-client.md) | UAT-04 |
| UXT-09 "Tutup sesi kas" | WF-08 | [TASK-030](tasks/TASK-030-session-close.md) | UAT-05 |
| UXT-10 "Lihat penjualan dan sesi kas sendiri; cetak atau unduh laporan sesi" | WF-12 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | UAT-06 |
| UXT-11 "Ganti kata sandi; keluar" | WF-01, WF-03 | [TASK-006](tasks/TASK-006-authentication.md) | UAT-07 |
| UXT-12 "Aktifkan akun dari tautan" | WF-02 | [TASK-011](tasks/TASK-011-user-management.md) | UAT-07 |
| UXT-13 "Cari produk dan cek stok" | WF-13 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | UAT-08 |
| UXT-14 "Catat barang masuk" | WF-19 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | UAT-09 |
| UXT-15 "Catat barang keluar (bukan penjualan)" | WF-20 | [TASK-023](tasks/TASK-023-manual-issue.md) | UAT-10 |
| UXT-16 "Pantau stok perlu restok" | WF-21 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | UAT-08 |
| UXT-17 "Tambah produk (cari dulu)" | WF-14 | [TASK-017](tasks/TASK-017-catalog-pages.md) | UAT-11 |
| UXT-18 "Ubah data produk, harga jual atau stok minimum" | WF-15 | [TASK-017](tasks/TASK-017-catalog-pages.md) | UAT-11 |
| UXT-19 "Kelola barcode dan foto produk" | WF-16 | [TASK-018](tasks/TASK-018-photos-labels.md) | UAT-11 |
| UXT-20 "Cetak label barcode" | WF-16 | [TASK-018](tasks/TASK-018-photos-labels.md) | UAT-11 |
| UXT-21 "Impor produk baru dari CSV" | WF-18 | [TASK-019](tasks/TASK-019-csv-import.md) | UAT-12 |
| UXT-22 "Kelola kategori; nonaktifkan atau aktifkan kembali produk" | WF-15 | [TASK-017](tasks/TASK-017-catalog-pages.md) | UAT-11 |
| UXT-23 "Lihat keadaan hari ini dan hal yang perlu perhatian" | WF-28 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | UAT-14 |
| UXT-24 "Rekap harian" | WF-28 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | UAT-14 |
| UXT-25 "Tinjau sesi kas; tambah catatan tinjauan" | WF-10 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | UAT-14 |
| UXT-26 "Tutup sesi oleh Pemilik" | WF-09 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | UAT-15 |
| UXT-27 "Pengembalian uang dan retur" | WF-11 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | UAT-16 |
| UXT-28 "Laporan keuangan dan tren" | WF-28 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | UAT-17 |
| UXT-29 "Lengkapi atau koreksi biaya" | WF-26 | [TASK-025](tasks/TASK-025-cost-correction.md) | UAT-17 |
| UXT-30 "Penyesuaian stok" | WF-22 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | UAT-18 |
| UXT-31 "Pembatalan mutasi" | WF-23 | [TASK-026](tasks/TASK-026-reversal.md) | UAT-18 |
| UXT-32 "Koreksi salah pasang barcode" | WF-17 | [TASK-020](tasks/TASK-020-barcode-correction.md) | UAT-18 |
| UXT-33 "Masa input stok awal" | WF-24, WF-25 | [TASK-035](tasks/TASK-035-onboarding.md) | UAT-19 |
| UXT-34 "Tangani insiden rekonsiliasi" | WF-27 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | UAT-20 |
| UXT-35 "Kelola pengguna" | WF-02, WF-29 | [TASK-011](tasks/TASK-011-user-management.md) | UAT-21 |
| UXT-36 "Ubah profil usaha" | WF-30 | [TASK-027](tasks/TASK-027-registers-session-open.md) | UAT-22 |
| UXT-37 "Atur laci kas" | WF-30 | [TASK-027](tasks/TASK-027-registers-session-open.md) | UAT-22 |
| UXT-38 "Status sistem, riwayat aktivitas, log keamanan" | WF-31 | [TASK-039](tasks/TASK-039-system-status.md) | UAT-23 |
| UXT-39 "Ubah nama atau email Pemilik" | WF-03 | [TASK-006](tasks/TASK-006-authentication.md) | UAT-22 |

## 5. Acceptance suites

| Suite | Title | Owned by | Contributed by | Final point |
| --- | --- | --- | --- | --- |
| AUTH01 | Server authorization and object scope | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-030](tasks/TASK-030-session-close.md), [TASK-033](tasks/TASK-033-refunds-core.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| AUTH02 | Sensitive channels per role | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-006](tasks/TASK-006-authentication.md), [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-042](tasks/TASK-042-audit-views.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| AUTH03 | Ordered guards and recent authentication | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-011](tasks/TASK-011-user-management.md), [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-020](tasks/TASK-020-barcode-correction.md), [TASK-024](tasks/TASK-024-stock-adjustment.md), [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-026](tasks/TASK-026-reversal.md), [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| ACC01 | Account lifecycle and the single Owner | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| LINK01 | One-time links and no email | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-011](tasks/TASK-011-user-management.md), [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SIGNIN01 | Sign-in sessions and shared devices | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SEC01 | Application threat paths | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-006](tasks/TASK-006-authentication.md), [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-018](tasks/TASK-018-photos-labels.md), [TASK-019](tasks/TASK-019-csv-import.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SEC02 | Infrastructure exposure on the target | [TASK-045](tasks/TASK-045-operations-verification.md) | — | [TASK-045](tasks/TASK-045-operations-verification.md) |
| AUDIT01 | Audit log and security log | [TASK-042](tasks/TASK-042-audit-views.md) | [TASK-006](tasks/TASK-006-authentication.md), [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SEARCH01 | Barcode tokens and SKUs | [TASK-016](tasks/TASK-016-barcodes.md) | [TASK-015](tasks/TASK-015-catalog-core.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SEARCH02 | Product Search, master data and lifecycle | [TASK-015](tasks/TASK-015-catalog-core.md) | [TASK-016](tasks/TASK-016-barcodes.md), [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SEARCH03 | Scanning logic | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| BARCODE01 | Barcode misassignment correction | [TASK-020](tasks/TASK-020-barcode-correction.md) | [TASK-016](tasks/TASK-016-barcodes.md), [TASK-028](tasks/TASK-028-checkout.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| IMPORT01 | CSV product import | [TASK-019](tasks/TASK-019-csv-import.md) | [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| MEDIA01 | Product photos and media | [TASK-018](tasks/TASK-018-photos-labels.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| INV01 | Ledger, receiving and reconciliation | [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| INV02 | Manual issue, aggregation and the last unit | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| INV03 | Adjustment and reversal | [TASK-026](tasks/TASK-026-reversal.md) | [TASK-024](tasks/TASK-024-stock-adjustment.md), [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| OPEN01 | Opening stock and onboarding | [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| STOCK01 | Stock attention | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| FIN01 | Costing arithmetic | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-003](tasks/TASK-003-kernel-localization.md), [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-028](tasks/TASK-028-checkout.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| FIN02 | Unknown cost and corrections | [TASK-025](tasks/TASK-025-cost-correction.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-040](tasks/TASK-040-finance-report.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| FIN03 | Replay, returns and reports | [TASK-025](tasks/TASK-025-cost-correction.md) | [TASK-003](tasks/TASK-003-kernel-localization.md), [TASK-026](tasks/TASK-026-reversal.md), [TASK-033](tasks/TASK-033-refunds-core.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-040](tasks/TASK-040-finance-report.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| POS01 | Atomic checkout and command identity | [TASK-028](tasks/TASK-028-checkout.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| POS02 | Payment arithmetic and methods | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-003](tasks/TASK-003-kernel-localization.md), [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| POS03 | Rejections, uncertainty and printing after commit | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-009](tasks/TASK-009-command-client.md), [TASK-028](tasks/TASK-028-checkout.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| POS04 | One payment screen | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-047](tasks/TASK-047-p7-change-control-2.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| PAYEXT01 | Payment boundary | [TASK-028](tasks/TASK-028-checkout.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| CMD01 | Request receipts across devices | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-009](tasks/TASK-009-command-client.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| RET01 | Refund and return bounds | [TASK-033](tasks/TASK-033-refunds-core.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| RET02 | Drawer-funded refunds | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION01 | Same-day sessions and handover | [TASK-030](tasks/TASK-030-session-close.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION02 | One OPEN session per register and per user | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION03 | Opening cash and attribution | [TASK-027](tasks/TASK-027-registers-session-open.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-028](tasks/TASK-028-checkout.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION04 | Expected cash and variance | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-034](tasks/TASK-034-drawer-refunds.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION05 | Sale against close and unresolved checkouts | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-034](tasks/TASK-034-drawer-refunds.md), [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION06 | Repeated and lost closes | [TASK-030](tasks/TASK-030-session-close.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION07 | Closed sessions are final | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION08 | Documents, print and export | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-018](tasks/TASK-018-photos-labels.md), [TASK-030](tasks/TASK-030-session-close.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION09 | Daily summary | [TASK-041](tasks/TASK-041-owner-home-daily.md) | [TASK-003](tasks/TASK-003-kernel-localization.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| SESSION10 | Session access and Owner-assisted close | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | [TASK-030](tasks/TASK-030-session-close.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| CONFIG01 | Cash register configuration | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| IDENTITY01 | Shop identity | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-028](tasks/TASK-028-checkout.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| UX01 | Approved design and states | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-002](tasks/TASK-002-p7-change-control-1.md), [TASK-005](tasks/TASK-005-design-system.md), [TASK-009](tasks/TASK-009-command-client.md), [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-034](tasks/TASK-034-drawer-refunds.md), [TASK-039](tasks/TASK-039-system-status.md), [TASK-040](tasks/TASK-040-finance-report.md), [TASK-041](tasks/TASK-041-owner-home-daily.md), [TASK-047](tasks/TASK-047-p7-change-control-2.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| UX02 | Accessibility targets | [TASK-005](tasks/TASK-005-design-system.md) | [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-040](tasks/TASK-040-finance-report.md), [TASK-046](tasks/TASK-046-device-verification.md), [TASK-047](tasks/TASK-047-p7-change-control-2.md) | [TASK-044](tasks/TASK-044-integrated-verification.md), [TASK-046](tasks/TASK-046-device-verification.md) |
| L10N01 | Localization | [TASK-003](tasks/TASK-003-kernel-localization.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-002](tasks/TASK-002-p7-change-control-1.md), [TASK-005](tasks/TASK-005-design-system.md), [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-044](tasks/TASK-044-integrated-verification.md) |
| DB01 | Migrations and privileges | [TASK-004](tasks/TASK-004-database-foundation.md) | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-043](tasks/TASK-043-performance-harness.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| KEEP01 | Retention | [TASK-038](tasks/TASK-038-housekeeping.md) | [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-006](tasks/TASK-006-authentication.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REC01 | Backup and isolated restore | [TASK-013](tasks/TASK-013-independent-backup.md) | [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-050](tasks/TASK-050-production-pilot.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| REC02 | After a restore: fence and reconciliation | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| STATUS01 | System status and monitoring | [TASK-039](tasks/TASK-039-system-status.md) | [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| OPPROC01 | Operator procedures | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-011](tasks/TASK-011-user-management.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| PERF01 | Performance on the intended server | [TASK-043](tasks/TASK-043-performance-harness.md) | — | [TASK-048](tasks/TASK-048-performance-verification.md) |
| DEVICE01 | Real devices | [TASK-046](tasks/TASK-046-device-verification.md) | [TASK-047](tasks/TASK-047-p7-change-control-2.md) | [TASK-046](tasks/TASK-046-device-verification.md) |
| READY01 | Release readiness | [TASK-045](tasks/TASK-045-operations-verification.md) | [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-049](tasks/TASK-049-release-readiness.md) | [TASK-045](tasks/TASK-045-operations-verification.md) |
| RELEASE01 | Pilot and production acceptance | [TASK-050](tasks/TASK-050-production-pilot.md) | — | [TASK-050](tasks/TASK-050-production-pilot.md) |

## 6. Races, guards and threats

### 6.1 Races

| Race | Owned by | Contributed by | Suites |
| --- | --- | --- | --- |
| RACE-01 | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-028](tasks/TASK-028-checkout.md) | INV01, POS01, POS03, SESSION06 |
| RACE-02 | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-028](tasks/TASK-028-checkout.md) | POS01 |
| RACE-03 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-023](tasks/TASK-023-manual-issue.md) | INV02 |
| RACE-04 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-035](tasks/TASK-035-onboarding.md) | AUTH03, OPEN01 |
| RACE-05 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-015](tasks/TASK-015-catalog-core.md) | SEARCH02 |
| RACE-06 | [TASK-035](tasks/TASK-035-onboarding.md) | — | OPEN01 |
| RACE-07 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | SESSION02 |
| RACE-08 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-028](tasks/TASK-028-checkout.md) | SESSION05 |
| RACE-09 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | [TASK-030](tasks/TASK-030-session-close.md) | SESSION06, SESSION10 |
| RACE-10 | [TASK-033](tasks/TASK-033-refunds-core.md) | [TASK-026](tasks/TASK-026-reversal.md) | INV03, RET01 |
| RACE-11 | [TASK-025](tasks/TASK-025-cost-correction.md) | [TASK-033](tasks/TASK-033-refunds-core.md) | FIN03 |
| RACE-12 | [TASK-037](tasks/TASK-037-restore-tooling.md) | — | REC02 |
| RACE-13 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | [TASK-030](tasks/TASK-030-session-close.md) | RET02, SESSION05 |
| RACE-14 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — | RET02 |
| RACE-15 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — | RET02 |
| RACE-16 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | ACC01 |
| RACE-17 | [TASK-020](tasks/TASK-020-barcode-correction.md) | [TASK-016](tasks/TASK-016-barcodes.md) | BARCODE01 |
| RACE-18 | [TASK-020](tasks/TASK-020-barcode-correction.md) | [TASK-028](tasks/TASK-028-checkout.md) | BARCODE01 |
| RACE-19 | [TASK-020](tasks/TASK-020-barcode-correction.md) | [TASK-016](tasks/TASK-016-barcodes.md) | BARCODE01 |
| RACE-20 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-006](tasks/TASK-006-authentication.md) | LINK01 |
| RACE-21 | [TASK-011](tasks/TASK-011-user-management.md) | — | ACC01, OPPROC01 |
| RACE-22 | [TASK-019](tasks/TASK-019-csv-import.md) | — | IMPORT01 |
| RACE-23 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-015](tasks/TASK-015-catalog-core.md) | SEARCH02, POS03 |
| RACE-24 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md) | INV01 |
| RACE-25 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | IDENTITY01 |
| RACE-26 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — | CONFIG01 |
| RACE-27 | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-009](tasks/TASK-009-command-client.md), [TASK-029](tasks/TASK-029-till-pages.md) | POS03, CMD01 |
| RACE-28 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | [TASK-011](tasks/TASK-011-user-management.md) | ACC01, SESSION10 |
| RACE-29 | [TASK-015](tasks/TASK-015-catalog-core.md) | — | SEARCH02 |
| RACE-30 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | ACC01, OPPROC01 |
| RACE-31 | [TASK-035](tasks/TASK-035-onboarding.md) | — | OPEN01 |
| RACE-32 | [TASK-035](tasks/TASK-035-onboarding.md) | — | OPEN01 |
| RACE-33 | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-009](tasks/TASK-009-command-client.md) | CMD01 |
| RACE-34 | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-008](tasks/TASK-008-command-platform.md) | REC02 |
| RACE-35 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | SESSION05 |

### 6.2 Guards

| Guard | Owned by | Contributed by | Suites |
| --- | --- | --- | --- |
| G-01 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS01 |
| G-02 | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-024](tasks/TASK-024-stock-adjustment.md), [TASK-028](tasks/TASK-028-checkout.md) | INV01, INV02 |
| G-03 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md) | INV01 |
| G-04 | [TASK-028](tasks/TASK-028-checkout.md) | — | POS01 |
| G-05 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | [TASK-030](tasks/TASK-030-session-close.md) | RET02, SESSION04 |
| G-06 | [TASK-033](tasks/TASK-033-refunds-core.md) | — | RET01 |
| G-07 | [TASK-025](tasks/TASK-025-cost-correction.md) | [TASK-026](tasks/TASK-026-reversal.md) | INV03, FIN03 |
| G-08 | [TASK-011](tasks/TASK-011-user-management.md) | — | ACC01 |
| G-09 | [TASK-016](tasks/TASK-016-barcodes.md) | [TASK-020](tasks/TASK-020-barcode-correction.md) | SEARCH01, BARCODE01 |
| G-10 | [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-028](tasks/TASK-028-checkout.md), [TASK-033](tasks/TASK-033-refunds-core.md) | OPEN01 |
| G-11 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-026](tasks/TASK-026-reversal.md), [TASK-028](tasks/TASK-028-checkout.md), [TASK-033](tasks/TASK-033-refunds-core.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md) | INV01, INV03 |
| G-12 | [TASK-008](tasks/TASK-008-command-platform.md) | [TASK-037](tasks/TASK-037-restore-tooling.md) | REC02 |
| G-13 | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-028](tasks/TASK-028-checkout.md), [TASK-033](tasks/TASK-033-refunds-core.md), [TASK-040](tasks/TASK-040-finance-report.md) | FIN01, FIN03 |
| G-14 | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-025](tasks/TASK-025-cost-correction.md) | FIN03 |
| G-15 | [TASK-025](tasks/TASK-025-cost-correction.md) | — | FIN02 |
| G-16 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-006](tasks/TASK-006-authentication.md), [TASK-042](tasks/TASK-042-audit-views.md) | ACC01, AUDIT01 |
| G-17 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-030](tasks/TASK-030-session-close.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | POS01, SESSION08 |
| G-18 | [TASK-028](tasks/TASK-028-checkout.md) | [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) | POS01, SESSION09 |
| G-19 | [TASK-027](tasks/TASK-027-registers-session-open.md) | [TASK-030](tasks/TASK-030-session-close.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md) | SESSION10, CONFIG01 |
| G-20 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-018](tasks/TASK-018-photos-labels.md) | MEDIA01, IDENTITY01 |
| G-21 | [TASK-025](tasks/TASK-025-cost-correction.md) | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | FIN03 |
| G-22 | [TASK-035](tasks/TASK-035-onboarding.md) | — | OPEN01 |
| G-23 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-027](tasks/TASK-027-registers-session-open.md) | SESSION03, IDENTITY01 |
| G-24 | [TASK-035](tasks/TASK-035-onboarding.md) | — | OPEN01 |
| G-25 | [TASK-008](tasks/TASK-008-command-platform.md) | — | CMD01 |
| G-26 | [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-037](tasks/TASK-037-restore-tooling.md) | OPEN01, REC02 |
| G-27 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | SESSION05 |

### 6.3 Threats

| Threat | Owned by | Contributed by | Suites |
| --- | --- | --- | --- |
| THR-01 | [TASK-006](tasks/TASK-006-authentication.md) | — | LINK01, SEC01 |
| THR-02 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-040](tasks/TASK-040-finance-report.md) | AUTH01 |
| THR-03 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-011](tasks/TASK-011-user-management.md) | AUTH01, ACC01 |
| THR-04 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-015](tasks/TASK-015-catalog-core.md) | SEC01 |
| THR-05 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-005](tasks/TASK-005-design-system.md) | SEC01 |
| THR-06 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — | SEC01 |
| THR-07 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — | SEC01 |
| THR-08 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-042](tasks/TASK-042-audit-views.md) | AUTH02 |
| THR-09 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-018](tasks/TASK-018-photos-labels.md), [TASK-019](tasks/TASK-019-csv-import.md) | SEC01, IMPORT01, MEDIA01 |
| THR-10 | [TASK-001](tasks/TASK-001-toolchain-ci.md) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-019](tasks/TASK-019-csv-import.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-038](tasks/TASK-038-housekeeping.md) | SEC01, OPPROC01 |
| THR-11 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — | SEC01 |
| THR-12 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-018](tasks/TASK-018-photos-labels.md) | SEC01, MEDIA01 |
| THR-13 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — | SEC01 |
| THR-14 | [TASK-001](tasks/TASK-001-toolchain-ci.md) | [TASK-012](tasks/TASK-012-application-image.md), [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-049](tasks/TASK-049-release-readiness.md) | SEC01, READY01 |
| THR-15 | [TASK-012](tasks/TASK-012-application-image.md) | [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-045](tasks/TASK-045-operations-verification.md) | SEC01, SEC02 |
| THR-16 | [TASK-045](tasks/TASK-045-operations-verification.md) | [TASK-007](tasks/TASK-007-authorization-shell.md) | SEC02 |
| THR-17 | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-008](tasks/TASK-008-command-platform.md), [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-045](tasks/TASK-045-operations-verification.md) | SEC02, DB01, REC01, REC02 |
| THR-18 | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-006](tasks/TASK-006-authentication.md), [TASK-009](tasks/TASK-009-command-client.md) | AUTH02, SIGNIN01 |
| THR-19 | [TASK-033](tasks/TASK-033-refunds-core.md) | [TASK-025](tasks/TASK-025-cost-correction.md) | AUTH01, RET01 |
| THR-20 | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-011](tasks/TASK-011-user-management.md) | AUTH03, OPPROC01 |
| THR-21 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-006](tasks/TASK-006-authentication.md) | LINK01 |

## 7. Routes

Every route of ROUTE_CONTRACTS, including the print views, the health route, the scheduled commands SC-01 to SC-04 and the operator procedures OP-01 to OP-08.

| Route | Owned by | Elements added by other Tasks |
| --- | --- | --- |
| RN-01 | [TASK-007](tasks/TASK-007-authorization-shell.md) | home of OPERATIONS_ADMIN and of the combined account without an OPEN session (TASK-022); home of a cashier without an OPEN session (RP-104) (TASK-027); home of a cashier and of the combined account with an OPEN session (RP-101) (TASK-029); home of the Owner (RP-007) (TASK-041) |
| RP-001 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RP-002 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RP-003 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RP-004 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RP-005 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RP-006 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| RP-007 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| RP-101 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| RP-102 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| RP-103 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| RP-103P | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RP-104 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| RP-105 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RP-202 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RP-203 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| RP-204 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| RP-205 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| RP-206 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| RP-206P | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RP-207 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| RP-301 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| RP-302 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| RP-303 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| RP-304 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| RP-305 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| RP-306 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| RP-306P | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| RP-307 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| RP-308 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| RP-401 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RP-403 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RP-404 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RP-405 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RP-406 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RP-407 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| RP-408 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| RP-409 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| RP-410 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| RP-411 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| RP-412 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RP-413 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RP-414 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| RP-415 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| RP-501 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| RP-502 | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| RP-503 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| RP-504 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| RP-505 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| RP-601 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| RP-602 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RP-603 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RP-604 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RP-605 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RP-606 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RP-607 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| RP-608 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| RP-609 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| RP-610 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| RP-611 | [TASK-039](tasks/TASK-039-system-status.md) | — |
| RP-612 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| RP-613 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| RD-01 | [TASK-015](tasks/TASK-015-catalog-core.md) | `exact` mode and barcode rank (TASK-016); `correction` context (TASK-020); stock fields per role (TASK-021); `receiving` and `stock` contexts (TASK-022); `issue` context (TASK-023); `adjustment` context (TASK-024); `till` context (TASK-028); `opening` context (TASK-035) |
| RD-02 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| RD-03 | [TASK-009](tasks/TASK-009-command-client.md) | stock-attention count (TASK-022); onboarding state (TASK-035); open incidents (TASK-036) |
| RD-04 | [TASK-008](tasks/TASK-008-command-platform.md) | — |
| RD-05 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| RD-06 | [TASK-015](tasks/TASK-015-catalog-core.md) | barcode check (TASK-016) |
| RD-07 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | product photos and thumbnails (TASK-018) |
| RA-01 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RA-02 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RA-03 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RA-04 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RA-05 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RA-06 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RA-07 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| RR-01 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| RR-02 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| RH-01 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| RC-01 | [TASK-028](tasks/TASK-028-checkout.md) | — |
| RC-02 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| RC-03 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| RC-04 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| RC-05 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| RC-06 | [TASK-033](tasks/TASK-033-refunds-core.md) | — |
| RC-07 | [TASK-021](tasks/TASK-021-ledger-receiving.md) | — |
| RC-08 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| RC-09 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| RC-10 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| RC-11 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RC-12 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RC-13 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RC-14 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RC-15 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| RC-16 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| RC-17 | [TASK-015](tasks/TASK-015-catalog-core.md) | barcodes at product creation (TASK-016) |
| RC-18 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-19 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-20 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-21 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-22 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-23 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-24 | [TASK-015](tasks/TASK-015-catalog-core.md) | — |
| RC-25 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| RC-26 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| RC-27 | [TASK-016](tasks/TASK-016-barcodes.md) | — |
| RC-28 | [TASK-016](tasks/TASK-016-barcodes.md) | — |
| RC-29 | [TASK-016](tasks/TASK-016-barcodes.md) | — |
| RC-30 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| RC-31 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| RC-32 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| RC-33 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| RC-34 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| RC-35 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-36 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-37 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-38 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-39 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-40 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-41 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-42 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| RC-43 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| RC-44 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RC-45 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RC-46 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| RC-47 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| RC-48 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| RC-50 | [TASK-008](tasks/TASK-008-command-platform.md) | — |
| SC-01 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| SC-02 | [TASK-038](tasks/TASK-038-housekeeping.md) | — |
| SC-03 | [TASK-013](tasks/TASK-013-independent-backup.md) | — |
| SC-04 | [TASK-039](tasks/TASK-039-system-status.md) | — |
| OP-01 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| OP-02 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| OP-03 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| OP-04 | [TASK-037](tasks/TASK-037-restore-tooling.md) | — |
| OP-05 | [TASK-037](tasks/TASK-037-restore-tooling.md) | — |
| OP-06 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| OP-07 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| OP-08 | [TASK-037](tasks/TASK-037-restore-tooling.md) | — |

## 8. Tables

Every table of SCHEMA section 10. Keys of a table that target a table created later are completed by the later Task (EXECUTION_CONTEXT EXC-44); the contracts list them under "Keys completed".

| Table | Created by |
| --- | --- |
| `users` | [TASK-006](tasks/TASK-006-authentication.md) |
| `sessions` | [TASK-006](tasks/TASK-006-authentication.md) |
| `password_reset_tokens` | [TASK-006](tasks/TASK-006-authentication.md) |
| `cache` | [TASK-004](tasks/TASK-004-database-foundation.md) |
| `cache_locks` | [TASK-004](tasks/TASK-004-database-foundation.md) |
| `business_profile_revisions` | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) |
| `business_profile_current` | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) |
| `recovery_epoch` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `command_identities` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `command_results` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `command_request_receipts` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `stale_intent_reports` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `intent_reconciliations` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `audit_events` | [TASK-006](tasks/TASK-006-authentication.md) |
| `security_log_entries` | [TASK-006](tasks/TASK-006-authentication.md) |
| `stored_files` | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) |
| `categories` | [TASK-015](tasks/TASK-015-catalog-core.md) |
| `products` | [TASK-015](tasks/TASK-015-catalog-core.md) |
| `selling_price_revisions` | [TASK-015](tasks/TASK-015-catalog-core.md) |
| `barcode_tokens` | [TASK-016](tasks/TASK-016-barcodes.md) |
| `barcode_mappings` | [TASK-016](tasks/TASK-016-barcodes.md) |
| `barcode_mapping_retirements` | [TASK-016](tasks/TASK-016-barcodes.md) |
| `barcode_active_mappings` | [TASK-016](tasks/TASK-016-barcodes.md) |
| `csv_product_imports` | [TASK-019](tasks/TASK-019-csv-import.md) |
| `onboarding_state` | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) |
| `onboarding_releases` | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) |
| `onboarding_restore_holds` | [TASK-035](tasks/TASK-035-onboarding.md) |
| `onboarding_restore_reconciliations` | [TASK-035](tasks/TASK-035-onboarding.md) |
| `onboarding_zero_count_withdrawals` | [TASK-035](tasks/TASK-035-onboarding.md) |
| `stock_movements` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `stock_balances` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `receiving_documents` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `receiving_lines` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `manual_issues` | [TASK-023](tasks/TASK-023-manual-issue.md) |
| `manual_issue_lines` | [TASK-023](tasks/TASK-023-manual-issue.md) |
| `opening_stock_entries` | [TASK-035](tasks/TASK-035-onboarding.md) |
| `opening_stock_lines` | [TASK-035](tasks/TASK-035-onboarding.md) |
| `onboarding_zero_counts` | [TASK-035](tasks/TASK-035-onboarding.md) |
| `adjustments` | [TASK-024](tasks/TASK-024-stock-adjustment.md) |
| `adjustment_lines` | [TASK-024](tasks/TASK-024-stock-adjustment.md) |
| `reversals` | [TASK-026](tasks/TASK-026-reversal.md) |
| `inventory_posting_blocks` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `inventory_posting_block_reopenings` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `acquisition_cost_evidence` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `cost_corrections` | [TASK-025](tasks/TASK-025-cost-correction.md) |
| `inventory_valuations` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `issue_cost_allocations` | [TASK-023](tasks/TASK-023-manual-issue.md) |
| `return_cost_allocations` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `issue_cost_allocation_projections` | [TASK-023](tasks/TASK-023-manual-issue.md) |
| `return_cost_allocation_projections` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `cost_incomplete_marks` | [TASK-025](tasks/TASK-025-cost-correction.md) |
| `cost_incomplete_mark_clearances` | [TASK-025](tasks/TASK-025-cost-correction.md) |
| `sales` | [TASK-028](tasks/TASK-028-checkout.md) |
| `sale_lines` | [TASK-028](tasks/TASK-028-checkout.md) |
| `payments` | [TASK-028](tasks/TASK-028-checkout.md) |
| `receipt_snapshots` | [TASK-028](tasks/TASK-028-checkout.md) |
| `sale_corrections` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `refunds` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `refund_lines` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `return_lines` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `sale_correction_totals` | [TASK-028](tasks/TASK-028-checkout.md) |
| `sale_line_correction_totals` | [TASK-028](tasks/TASK-028-checkout.md) |
| `cash_registers` | [TASK-027](tasks/TASK-027-registers-session-open.md) |
| `cash_sessions` | [TASK-027](tasks/TASK-027-registers-session-open.md) |
| `cash_session_closes` | [TASK-030](tasks/TASK-030-session-close.md) |
| `cash_session_reports` | [TASK-030](tasks/TASK-030-session-close.md) |
| `cash_session_review_notes` | [TASK-032](tasks/TASK-032-assisted-close-notes.md) |
| `backup_runs` | [TASK-013](tasks/TASK-013-independent-backup.md) |
| `restore_drills` | [TASK-037](tasks/TASK-037-restore-tooling.md) |
| `reconciliation_runs` | [TASK-036](tasks/TASK-036-reconciliation-repair.md) |
| `migrations` | [TASK-004](tasks/TASK-004-database-foundation.md) |

| Keys completed | By |
| --- | --- |
| `audit_events.command_id` → `command_identities` | [TASK-008](tasks/TASK-008-command-platform.md) |
| `stock_movements (product_id, base_unit, quantity_precision)` → `products` with `ON UPDATE RESTRICT` | [TASK-021](tasks/TASK-021-ledger-receiving.md) |
| `stock_movements.manual_issue_line_id` → `manual_issue_lines` | [TASK-023](tasks/TASK-023-manual-issue.md) |
| `stock_movements.issue_allocation_presence` → `issue_cost_allocations` (deferred) | [TASK-023](tasks/TASK-023-manual-issue.md) |
| `stock_movements.adjustment_line_id` → `adjustment_lines` | [TASK-024](tasks/TASK-024-stock-adjustment.md) |
| `stock_movements.reversal_id` → `reversals` | [TASK-026](tasks/TASK-026-reversal.md) |
| `cost_incomplete_mark_clearances` → `reversals (id, original_movement_id)` | [TASK-026](tasks/TASK-026-reversal.md) |
| `stock_movements.sale_line_id` → `sale_lines` | [TASK-028](tasks/TASK-028-checkout.md) |
| `issue_cost_allocations (sale_line_id, issued_quantity)` → `sale_lines (id, quantity)` | [TASK-028](tasks/TASK-028-checkout.md) |
| `sale_lines.id` → `stock_movements (sale_line_id)` (deferred) | [TASK-028](tasks/TASK-028-checkout.md) |
| `sales.id` → `payments`, `receipt_snapshots` and `sale_correction_totals` (deferred) | [TASK-028](tasks/TASK-028-checkout.md) |
| `cash_sessions.close_presence` → `cash_session_closes` (deferred) | [TASK-030](tasks/TASK-030-session-close.md) |
| `stock_movements.return_line_id` → `return_lines` | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `stock_movements.return_allocation_presence` → `return_cost_allocations` (deferred) | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `return_lines.movement_presence` → `stock_movements (return_line_id)` (deferred) | [TASK-033](tasks/TASK-033-refunds-core.md) |
| `stock_movements.opening_line_id` → `opening_stock_lines` | [TASK-035](tasks/TASK-035-onboarding.md) |
| the replacement keys of `opening_stock_lines` to `stock_movements` and `reversals` | [TASK-035](tasks/TASK-035-onboarding.md) |

## 9. Pages and controls

### 9.1 Pages

| Page | Owned by | Other Tasks with controls on it |
| --- | --- | --- |
| UXP-000 "Kerangka aplikasi" | [TASK-007](tasks/TASK-007-authorization-shell.md) | [TASK-009](tasks/TASK-009-command-client.md), [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-019](tasks/TASK-019-csv-import.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-030](tasks/TASK-030-session-close.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-035](tasks/TASK-035-onboarding.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-040](tasks/TASK-040-finance-report.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) |
| UXP-001 "Masuk" | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXP-002 "Atur kata sandi" | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXP-003 "Akun saya" | [TASK-006](tasks/TASK-006-authentication.md) | [TASK-011](tasks/TASK-011-user-management.md) |
| UXP-004 "Ubah kata sandi" | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXP-005 "Ubah nama dan email" | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXP-006 "Permintaan belum pasti" | [TASK-009](tasks/TASK-009-command-client.md) | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) |
| UXP-007 "Beranda" | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXP-101 "Kasir" | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-030](tasks/TASK-030-session-close.md) |
| UXP-102 "Pembayaran" | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXP-103 "Struk" | [TASK-029](tasks/TASK-029-till-pages.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) |
| UXP-104 "Buka sesi kas" | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXP-105 "Tutup sesi kas" | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXP-201 "Penjualan" | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXP-202 "Detail penjualan" | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | [TASK-034](tasks/TASK-034-drawer-refunds.md) |
| UXP-203 "Pengembalian uang dan retur" | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXP-204 "Pengembalian tercatat" | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXP-205 "Sesi kas" | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXP-206 "Detail sesi kas" | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md) |
| UXP-207 "Tutup sesi oleh Pemilik" | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXP-301 "Produk" | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-018](tasks/TASK-018-photos-labels.md) |
| UXP-302 "Detail produk" | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-018](tasks/TASK-018-photos-labels.md), [TASK-020](tasks/TASK-020-barcode-correction.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-025](tasks/TASK-025-cost-correction.md) |
| UXP-303 "Tambah produk" | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXP-304 "Ubah produk" | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXP-305 "Koreksi salah pasang barcode" | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXP-306 "Cetak label barcode" | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXP-307 "Kategori" | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXP-308 "Impor produk (CSV)" | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXP-401 "Stok" | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-024](tasks/TASK-024-stock-adjustment.md) |
| UXP-402 "Riwayat stok" | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-024](tasks/TASK-024-stock-adjustment.md) |
| UXP-403 "Detail mutasi stok" | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-026](tasks/TASK-026-reversal.md), [TASK-035](tasks/TASK-035-onboarding.md) |
| UXP-404 "Barang masuk" | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXP-405 "Catat barang masuk" | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXP-406 "Detail barang masuk" | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-026](tasks/TASK-026-reversal.md) |
| UXP-407 "Barang keluar" | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXP-408 "Catat barang keluar" | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXP-409 "Detail barang keluar" | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-026](tasks/TASK-026-reversal.md) |
| UXP-410 "Penyesuaian stok" | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXP-411 "Pembatalan mutasi" | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXP-412 "Stok awal" | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXP-413 "Input stok awal" | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXP-414 "Insiden rekonsiliasi" | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXP-415 "Detail insiden" | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXP-501 "Rekap harian" | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXP-502 "Laporan keuangan" | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXP-503 "Biaya perlu dilengkapi" | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXP-504 "Koreksi biaya" | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXP-505 "Riwayat biaya produk" | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXP-601 "Pengaturan" | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | [TASK-011](tasks/TASK-011-user-management.md), [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-039](tasks/TASK-039-system-status.md), [TASK-042](tasks/TASK-042-audit-views.md) |
| UXP-602 "Pengguna" | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXP-603 "Tambah pengguna" | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXP-604 "Detail pengguna" | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-030](tasks/TASK-030-session-close.md) |
| UXP-605 "Ubah pengguna" | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXP-606 "Tautan kata sandi" | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXP-607 "Profil usaha" | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXP-608 "Ubah profil usaha" | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXP-609 "Laci kas" | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXP-610 "Form laci kas" | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXP-611 "Status sistem" | [TASK-039](tasks/TASK-039-system-status.md) | — |
| UXP-612 "Riwayat aktivitas" | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXP-613 "Log keamanan" | [TASK-042](tasks/TASK-042-audit-views.md) | — |

### 9.2 Controls

Every row of INTERACTION_CONTRACTS section 9. A control is built by the Task that delivers the last of its page and its route; a control that leads to one of several destinations is built with its first destination and extended by the Tasks that deliver the others. UXI-028, UXI-031 to UXI-033, UXI-036 and UXI-037 belong to the command client (TASK-009).

| Control | Page | Kind | Route | Built by | Extended by |
| --- | --- | --- | --- | --- | --- |
| UXI-001 | UXP-000 | Navigate | RP-007 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-002 | UXP-000 | Navigate | RP-101, or RP-104 | [TASK-027](tasks/TASK-027-registers-session-open.md) | [TASK-029](tasks/TASK-029-till-pages.md) |
| UXI-003 | UXP-000 | Navigate | RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-004 | UXP-000 | Navigate | RP-205 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-005 | UXP-000 | Navigate | RP-301 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-006 | UXP-000 | Navigate | RP-401 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-007 | UXP-000 | Navigate | RP-501 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-008 | UXP-000 | Navigate | RP-601 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-009 | UXP-000 | Navigate | RP-301 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-010 | UXP-000 | Navigate | RP-307 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-011 | UXP-000 | Navigate | RP-308 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-012 | UXP-000 | Navigate | RP-401 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-013 | UXP-000 | Navigate | RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-014 | UXP-000 | Navigate | RP-404 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-015 | UXP-000 | Navigate | RP-407 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-016 | UXP-000 | Navigate | RP-412 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-017 | UXP-000 | Navigate | RP-414 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXI-018 | UXP-000 | Navigate | RP-501 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-019 | UXP-000 | Navigate | RP-205 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-020 | UXP-000 | Navigate | RP-502 | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXI-021 | UXP-000 | Navigate | RP-503 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-022 | UXP-000 | Local | — | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-023 | UXP-000 | Local | — | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-024 | UXP-000 | Navigate | RP-003 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-025 | UXP-000 | Navigate | RP-006 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-026 | UXP-000 | Authentication | RA-02 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-027 | UXP-000 | Navigate | RP-006 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-028 | UXP-000 | Authentication | RA-02 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-029 | UXP-000 | Navigate | RP-006 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-030 | UXP-000 | Navigate | RP-412 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-031 | UXP-000 | Local | — | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-032 | UXP-000 | Authentication | RA-03, with RA-04 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-033 | UXP-000 | Local | — | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-034 | UXP-000 | Read | RA-07 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-035 | UXP-000 | Authentication | RA-02 | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-036 | UXP-000 | Command | The frozen request's RC route | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-037 | UXP-000 | Resolution | RC-50 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-038 | UXP-000 | Local | — | [TASK-007](tasks/TASK-007-authorization-shell.md) | — |
| UXI-039 | UXP-001 | Local | — | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-040 | UXP-001 | Local | — | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-041 | UXP-001 | Authentication | RA-01 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-042 | UXP-002 | Local | — | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-043 | UXP-002 | Authentication | RA-06 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-044 | UXP-002 | Navigate | RP-001 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-045 | UXP-003 | Navigate | RP-004 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-046 | UXP-003 | Navigate | RP-005 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-047 | UXP-004 | Local | — | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-048 | UXP-004 | Authentication | RA-05 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-049 | UXP-004 | Navigate | RP-003 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-050 | UXP-004 | Navigate | RP-003 | [TASK-006](tasks/TASK-006-authentication.md) | — |
| UXI-051 | UXP-005 | Local | — | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-052 | UXP-005 | Command | RC-37 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-053 | UXP-005 | Navigate | RP-003 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-054 | UXP-005 | Navigate | RP-003 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-055 | UXP-006 | Resolution | RC-50 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-056 | UXP-006 | Navigate | RP-103, RP-204, RP-206, RP-406, RP-409, RP-403 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-030](tasks/TASK-030-session-close.md), [TASK-034](tasks/TASK-034-drawer-refunds.md) |
| UXI-057 | UXP-006 | Navigate | Opener (NC-01) | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-058 | UXP-006 | Resolution | RD-04 (`scope=all`); RC-50 | [TASK-009](tasks/TASK-009-command-client.md) | — |
| UXI-059 | UXP-007 | Navigate | RP-501 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-060 | UXP-007 | Navigate | RP-206 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-061 | UXP-007 | Navigate | RP-401 (`state`) | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-062 | UXP-007 | Navigate | RP-503 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-063 | UXP-007 | Navigate | RP-414 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-064 | UXP-007 | Navigate | RP-611 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-065 | UXP-007 | Navigate | RP-412 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-066 | UXP-101 | Read | RD-01 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-067 | UXP-101 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-068 | UXP-101 | Read | RD-01 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-069 | UXP-101 | Read | RD-01 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-070 | UXP-101 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-071 | UXP-101 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-072 | UXP-101 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-073 | UXP-101 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-074 | UXP-101 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-075 | UXP-101 | Navigate | RP-102 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-076 | UXP-101 | Navigate | RP-006 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-077 | UXP-101 | Navigate | RP-105 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-078 | UXP-102 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-079 | UXP-102 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-080 | UXP-102 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-081 | UXP-102 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-082 | UXP-102 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-083 | UXP-102 | Command | RC-01 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-084 | UXP-102 | Navigate | RP-101 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-085 | UXP-102 | Command | RC-01 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-086 | UXP-102 | Resolution | RC-50 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-087 | UXP-102 | Navigate | RP-101 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-088 | UXP-103 | Navigate | RP-101 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-089 | UXP-103 | Navigate | RP-101 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-090 | UXP-103 | Command | RC-44, then RP-103P | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-091 | UXP-103 | Local | — | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-092 | UXP-103 | Navigate | RP-202 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-093 | UXP-103 | Navigate | RP-101 | [TASK-029](tasks/TASK-029-till-pages.md) | — |
| UXI-094 | UXP-104 | Local | — | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-095 | UXP-104 | Local | — | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-096 | UXP-104 | Local | — | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-097 | UXP-104 | Command | RC-02 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-098 | UXP-104 | Local | — | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-099 | UXP-104 | Command | RC-02; RC-50 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-100 | UXP-104 | Navigate | RP-007, RP-401, RP-201 | [TASK-027](tasks/TASK-027-registers-session-open.md) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-041](tasks/TASK-041-owner-home-daily.md) |
| UXI-101 | UXP-105 | Read | RP-105 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-102 | UXP-105 | Navigate | RP-006 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-103 | UXP-105 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-104 | UXP-105 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-105 | UXP-105 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-106 | UXP-105 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-107 | UXP-105 | Command | RC-03 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-108 | UXP-105 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-109 | UXP-105 | Command | RC-03; RC-50 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-110 | UXP-105 | Navigate | RP-101 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-111 | UXP-105 | Navigate | RP-101 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-112 | UXP-201 | Read | RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-113 | UXP-201 | Read | RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-114 | UXP-201 | Read | RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-115 | UXP-201 | Navigate | RP-202 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-116 | UXP-201 | Read | RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-117 | UXP-202 | Command | RC-44, then RP-103P | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-118 | UXP-202 | Navigate | RP-203 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-119 | UXP-202 | Navigate | RP-206 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-120 | UXP-202 | Read | RP-202 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-121 | UXP-202 | Navigate | RP-201 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-122 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-123 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-124 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-125 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-126 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-127 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-128 | UXP-203 | Command | RC-05 or RC-06 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-129 | UXP-203 | Local | — | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-130 | UXP-203 | Command | RC-05 or RC-06; RC-50 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-131 | UXP-203 | Navigate | RP-202 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-132 | UXP-203 | Navigate | RP-202 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-133 | UXP-204 | Read | RP-204 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-134 | UXP-204 | Navigate | RP-202 | [TASK-034](tasks/TASK-034-drawer-refunds.md) | — |
| UXI-135 | UXP-205 | Read | RP-205 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-136 | UXP-205 | Read | RP-205 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-137 | UXP-205 | Navigate | RP-206 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-138 | UXP-205 | Read | RP-205 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-139 | UXP-206 | Navigate | RP-105 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-140 | UXP-206 | Navigate | RP-207 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-141 | UXP-206 | Command | RC-45, then RP-206P | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-142 | UXP-206 | Command | RC-46 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| UXI-143 | UXP-206 | Read | RP-206 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-144 | UXP-206 | Read | RP-206 | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-145 | UXP-206 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-146 | UXP-206 | Command | RC-43 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-147 | UXP-206 | Local | — | [TASK-030](tasks/TASK-030-session-close.md) | — |
| UXI-148 | UXP-206 | Navigate | RP-205, RP-105, RP-207 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md) |
| UXI-149 | UXP-207 | Read | RP-207 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-150 | UXP-207 | Local | — | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-151 | UXP-207 | Local | — | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-152 | UXP-207 | Local | — | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-153 | UXP-207 | Local | — | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-154 | UXP-207 | Command | RC-04 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-155 | UXP-207 | Local | — | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-156 | UXP-207 | Command | RC-04; RC-50 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-157 | UXP-207 | Navigate | RP-206 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-158 | UXP-207 | Navigate | RP-206 | [TASK-032](tasks/TASK-032-assisted-close-notes.md) | — |
| UXI-159 | UXP-301 | Read | RD-01 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-160 | UXP-301 | Read | RD-01 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-161 | UXP-301 | Navigate | RP-303 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-162 | UXP-301 | Navigate | RP-302 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-163 | UXP-301 | Navigate | RP-306 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-164 | UXP-301 | Read | RD-01 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-165 | UXP-302 | Navigate | RP-304 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-166 | UXP-302 | Command | RC-19 or RC-20 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-167 | UXP-302 | Navigate | RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-168 | UXP-302 | Read | RP-302 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-169 | UXP-302 | Command | RC-25 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-170 | UXP-302 | Command | RC-26 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-171 | UXP-302 | Command | RC-27 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-172 | UXP-302 | Command | RC-28 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-173 | UXP-302 | Command | RC-29 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-174 | UXP-302 | Navigate | RP-305 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-175 | UXP-302 | Navigate | RP-306 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-176 | UXP-302 | Navigate | RP-505 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-177 | UXP-302 | Navigate | RP-303, RP-612, RP-006, RP-301 | [TASK-017](tasks/TASK-017-catalog-pages.md) | [TASK-042](tasks/TASK-042-audit-views.md) |
| UXI-178 | UXP-303 | Read | RD-06 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-179 | UXP-303 | Navigate | RP-302 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-180 | UXP-303 | Local | — | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-181 | UXP-303 | Local | — | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-182 | UXP-303 | Local | — | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-183 | UXP-303 | Command | RC-17 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-184 | UXP-303 | Navigate | Opener (NC-01) | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-185 | UXP-303 | Navigate | Opener (NC-01) | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-186 | UXP-304 | Local | — | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-187 | UXP-304 | Command | RC-18 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-188 | UXP-304 | Navigate | RP-302 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-189 | UXP-304 | Navigate | RP-302 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-190 | UXP-305 | Read | RD-05 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-191 | UXP-305 | Read | RD-01 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-192 | UXP-305 | Local | — | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-193 | UXP-305 | Local | — | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-194 | UXP-305 | Command | RC-30 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-195 | UXP-305 | Local | — | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-196 | UXP-305 | Command | RC-30; RC-50 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-197 | UXP-305 | Navigate | RP-302 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-198 | UXP-305 | Navigate | RP-302 | [TASK-020](tasks/TASK-020-barcode-correction.md) | — |
| UXI-199 | UXP-306 | Local | — | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-200 | UXP-306 | Command | RC-47, then RP-306P | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-201 | UXP-306 | Navigate | RP-302 | [TASK-018](tasks/TASK-018-photos-labels.md) | — |
| UXI-202 | UXP-307 | Read | RP-307 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-203 | UXP-307 | Command | RC-21 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-204 | UXP-307 | Command | RC-22 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-205 | UXP-307 | Command | RC-23 or RC-24 | [TASK-017](tasks/TASK-017-catalog-pages.md) | — |
| UXI-206 | UXP-308 | Read | RP-308 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-207 | UXP-308 | Request | RR-01 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-208 | UXP-308 | Read | RR-01 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-209 | UXP-308 | Navigate | RP-307 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-210 | UXP-308 | Command | RC-31 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-211 | UXP-308 | Command | RC-31; RC-50 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-212 | UXP-308 | Request | RR-02 | [TASK-019](tasks/TASK-019-csv-import.md) | — |
| UXI-213 | UXP-401 | Read | RD-02 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-214 | UXP-401 | Read | RD-02 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-215 | UXP-401 | Read | RD-02 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-216 | UXP-401 | Navigate | RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-217 | UXP-401 | Navigate | RP-410 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-218 | UXP-401 | Read | RD-02 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-219 | UXP-402 | Read | RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-220 | UXP-402 | Navigate | RP-403 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-221 | UXP-402 | Navigate | RP-410 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-222 | UXP-402 | Read | RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-223 | UXP-402 | Navigate | RP-302, RP-401, RP-415, RP-006 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-036](tasks/TASK-036-reconciliation-repair.md) |
| UXI-224 | UXP-403 | Navigate | RP-406, RP-409, RP-202 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) |
| UXI-225 | UXP-403 | Read | RP-403 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-226 | UXP-403 | Navigate | RP-504 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-227 | UXP-403 | Navigate | RP-411 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-228 | UXP-403 | Command | RP-411 → RC-10; then RP-413 → RC-48 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-229 | UXP-403 | Navigate | RP-402 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-230 | UXP-404 | Read | RP-404 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-231 | UXP-404 | Navigate | RP-405 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-232 | UXP-404 | Navigate | RP-406 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-233 | UXP-404 | Read | RP-404 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-234 | UXP-405 | Read | RD-01 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-235 | UXP-405 | Navigate | RP-303 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-236 | UXP-405 | Local | — | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-237 | UXP-405 | Local | — | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-238 | UXP-405 | Local | — | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-239 | UXP-405 | Local | — | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-240 | UXP-405 | Command | RC-07 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-241 | UXP-405 | Local | — | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-242 | UXP-405 | Command | RC-07; RC-50 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-243 | UXP-405 | Navigate | RP-404 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-244 | UXP-405 | Not provided | — | Not provided | — |
| UXI-245 | UXP-405 | Navigate | RP-404 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-246 | UXP-406 | Read | RP-406 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-247 | UXP-406 | Read | RP-406 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-248 | UXP-406 | Navigate | RP-403 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| UXI-249 | UXP-406 | Navigate | RP-504 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-250 | UXP-406 | Navigate | RP-411 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-251 | UXP-406 | Navigate | RP-403, RP-612, RP-006, RP-404, RP-405 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | [TASK-042](tasks/TASK-042-audit-views.md) |
| UXI-252 | UXP-407 | Read | RP-407 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-253 | UXP-407 | Navigate | RP-408 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-254 | UXP-407 | Navigate | RP-409 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-255 | UXP-407 | Read | RP-407 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-256 | UXP-408 | Read | RD-01 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-257 | UXP-408 | Local | — | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-258 | UXP-408 | Local | — | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-259 | UXP-408 | Local | — | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-260 | UXP-408 | Command | RC-08 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-261 | UXP-408 | Local | — | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-262 | UXP-408 | Command | RC-08; RC-50 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-263 | UXP-408 | Navigate | RP-407 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-264 | UXP-408 | Navigate | RP-407 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-265 | UXP-409 | Navigate | RP-403 | [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| UXI-266 | UXP-409 | Navigate | RP-411 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-267 | UXP-409 | Navigate | RP-403, RP-612, RP-006, RP-407, RP-408 | [TASK-023](tasks/TASK-023-manual-issue.md) | [TASK-042](tasks/TASK-042-audit-views.md) |
| UXI-268 | UXP-410 | Read | RD-01 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-269 | UXP-410 | Local | — | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-270 | UXP-410 | Local | — | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-271 | UXP-410 | Local | — | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-272 | UXP-410 | Command | RC-09 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-273 | UXP-410 | Local | — | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-274 | UXP-410 | Command | RC-09; RC-50 | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-275 | UXP-410 | Navigate | Opener (NC-01) | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-276 | UXP-410 | Navigate | Opener (NC-01) | [TASK-024](tasks/TASK-024-stock-adjustment.md) | — |
| UXI-277 | UXP-411 | Read | RP-411 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-278 | UXP-411 | Local | — | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-279 | UXP-411 | Local | — | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-280 | UXP-411 | Command | RC-10 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-281 | UXP-411 | Local | — | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-282 | UXP-411 | Command | RC-10; RC-50 | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-283 | UXP-411 | Navigate | Opener (NC-01) | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-284 | UXP-411 | Navigate | Opener (NC-01) | [TASK-026](tasks/TASK-026-reversal.md) | — |
| UXI-285 | UXP-412 | Command | RC-12 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-286 | UXP-412 | Navigate | RP-413 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-287 | UXP-412 | Read | RP-412 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-288 | UXP-412 | Navigate | RP-403 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-289 | UXP-412 | Command | RC-13 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-290 | UXP-412 | Command | RC-12 or RC-13; RC-50 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-291 | UXP-412 | Command | RC-14; or RP-411 → RC-10, then RC-48 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-292 | UXP-413 | Read | RD-01 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-293 | UXP-413 | Navigate | RP-303 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-294 | UXP-413 | Local | — | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-295 | UXP-413 | Local | — | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-296 | UXP-413 | Command | RC-11 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-297 | UXP-413 | Local | — | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-298 | UXP-413 | Command | RC-11; RC-50 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-299 | UXP-413 | Navigate | RP-412 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-300 | UXP-413 | Navigate | RP-412 | [TASK-035](tasks/TASK-035-onboarding.md) | — |
| UXI-301 | UXP-414 | Read | RP-414 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXI-302 | UXP-414 | Navigate | RP-415 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXI-303 | UXP-415 | Navigate | RP-402 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXI-304 | UXP-415 | Command | RC-16 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXI-305 | UXP-415 | Navigate | RP-414 | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | — |
| UXI-306 | UXP-501 | Read | RP-501 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-307 | UXP-501 | Navigate | RP-206 | [TASK-041](tasks/TASK-041-owner-home-daily.md) | — |
| UXI-308 | UXP-502 | Read | RP-502 | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXI-309 | UXP-502 | Read | RP-502 | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXI-310 | UXP-502 | Read | RP-502 | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXI-311 | UXP-502 | Local | — | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXI-312 | UXP-502 | Navigate | RP-503 | [TASK-040](tasks/TASK-040-finance-report.md) | — |
| UXI-313 | UXP-503 | Read | RP-503 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-314 | UXP-503 | Navigate | RP-504 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-315 | UXP-503 | Navigate | RP-505 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-316 | UXP-503 | Read | RP-503 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-317 | UXP-504 | Local | — | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-318 | UXP-504 | Local | — | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-319 | UXP-504 | Command | RC-15 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-320 | UXP-504 | Local | — | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-321 | UXP-504 | Command | RC-15; RC-50 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-322 | UXP-504 | Navigate | Opener (NC-01) | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-323 | UXP-504 | Navigate | Opener (NC-01) | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-324 | UXP-505 | Navigate | RP-504 | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-325 | UXP-505 | Navigate | Opener (NC-01) | [TASK-025](tasks/TASK-025-cost-correction.md) | — |
| UXI-326 | UXP-601 | Navigate | RP-602 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-327 | UXP-601 | Navigate | RP-607 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-328 | UXP-601 | Navigate | RP-609 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-329 | UXP-601 | Navigate | RP-611 | [TASK-039](tasks/TASK-039-system-status.md) | — |
| UXI-330 | UXP-601 | Navigate | RP-612 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-331 | UXP-601 | Navigate | RP-613 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-332 | UXP-602 | Read | RP-602 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-333 | UXP-602 | Navigate | RP-603 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-334 | UXP-602 | Navigate | RP-604 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-335 | UXP-602 | Navigate | RP-601 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-336 | UXP-603 | Local | — | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-337 | UXP-603 | Command | RC-35 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-338 | UXP-603 | Navigate | RP-602 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-339 | UXP-603 | Navigate | RP-602 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-340 | UXP-604 | Navigate | RP-206, RP-207 | [TASK-030](tasks/TASK-030-session-close.md) | [TASK-032](tasks/TASK-032-assisted-close-notes.md) |
| UXI-341 | UXP-604 | Navigate | RP-605 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-342 | UXP-604 | Command | RC-41 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-343 | UXP-604 | Command | RC-41 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-344 | UXP-604 | Command | RC-42 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-345 | UXP-604 | Command | RC-38 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-346 | UXP-604 | Command | RC-39 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-347 | UXP-604 | Command | RC-40 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-348 | UXP-604 | Not provided | — | Not provided | — |
| UXI-349 | UXP-604 | Navigate | RP-612, RP-602 | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-042](tasks/TASK-042-audit-views.md) |
| UXI-350 | UXP-605 | Local | — | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-351 | UXP-605 | Command | RC-36 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-352 | UXP-605 | Navigate | RP-604 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-353 | UXP-605 | Navigate | RP-604 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-354 | UXP-606 | Read | RC-35, RC-41 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-355 | UXP-606 | Local | — | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-356 | UXP-606 | Read | RP-606 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-357 | UXP-606 | Navigate | RP-604 | [TASK-011](tasks/TASK-011-user-management.md) | — |
| UXI-358 | UXP-607 | Navigate | RP-608 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-359 | UXP-607 | Local | — | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-360 | UXP-607 | Navigate | RP-601 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-361 | UXP-608 | Local | — | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-362 | UXP-608 | Local | — | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-363 | UXP-608 | Field | RC-32 (`accentToken`) | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-364 | UXP-608 | Command | RC-32 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-365 | UXP-608 | Navigate | RP-607 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-366 | UXP-608 | Navigate | RP-607 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| UXI-367 | UXP-609 | Navigate | RP-610 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-368 | UXP-609 | Navigate | RP-610 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-369 | UXP-609 | Navigate | RP-601 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-370 | UXP-610 | Local | — | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-371 | UXP-610 | Command | RC-33 or RC-34 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-372 | UXP-610 | Navigate | RP-609 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-373 | UXP-610 | Navigate | RP-609 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| UXI-374 | UXP-611 | Read | RP-611 | [TASK-039](tasks/TASK-039-system-status.md) | — |
| UXI-375 | UXP-611 | Navigate | RP-414 | [TASK-039](tasks/TASK-039-system-status.md) | — |
| UXI-376 | UXP-611 | Navigate | RP-601 | [TASK-039](tasks/TASK-039-system-status.md) | — |
| UXI-377 | UXP-612 | Read | RP-612 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-378 | UXP-612 | Local | — | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-379 | UXP-612 | Navigate | RP-202, RP-206, RP-302, RP-403, RP-406, RP-409, RP-604 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-380 | UXP-612 | Read | RP-612 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-381 | UXP-612 | Navigate | RP-601 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-382 | UXP-613 | Read | RP-613 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-383 | UXP-613 | Local | — | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-384 | UXP-613 | Read | RP-613 | [TASK-042](tasks/TASK-042-audit-views.md) | — |
| UXI-385 | UXP-613 | Navigate | RP-601 | [TASK-042](tasks/TASK-042-audit-views.md) | — |

## 10. Documents, runbooks and acceptance by people

### 10.1 Document contracts

| Document | Owned by |
| --- | --- |
| DC-01 Receipt and "Nota Penjualan" | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) |
| DC-02 Session report | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) |
| DC-03 Session report export (CSV) | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) |
| DC-04 Product import file (CSV) | [TASK-019](tasks/TASK-019-csv-import.md) |
| DC-05 Barcode label | [TASK-018](tasks/TASK-018-photos-labels.md) |

### 10.2 Runbooks

The operator runs the runbooks (D-088); the Tasks prepare their tooling and the verification Tasks have them rehearsed.

| Runbook | Subject | Tooling prepared by | Exercised in | Note |
| --- | --- | --- | --- | --- |
| RB-01 | Restore after loss or corruption | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | BACKUP_RECOVERY section 10 |
| RB-02 | Restore drill | [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-045](tasks/TASK-045-operations-verification.md), [TASK-050](tasks/TASK-050-production-pilot.md) | BACKUP_RECOVERY section 13 |
| RB-03 | Incident response | [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-039](tasks/TASK-039-system-status.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 6; approved with the runbooks |
| RB-04 | Reconciliation incident | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7; UAT-20 |
| RB-05 | Security incident or leaked secret | [TASK-011](tasks/TASK-011-user-management.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-013](tasks/TASK-013-independent-backup.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7; approved with the runbooks |
| RB-06 | Server or application unavailable | [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-039](tasks/TASK-039-system-status.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7; approved with the runbooks |
| RB-07 | Certificate | [TASK-012](tasks/TASK-012-application-image.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7; gate TARGET |
| RB-08 | Disk space | [TASK-039](tasks/TASK-039-system-status.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7 |
| RB-09 | Backup failure | [TASK-013](tasks/TASK-013-independent-backup.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7 |
| RB-10 | Owner recovery | [TASK-011](tasks/TASK-011-user-management.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 2; OPPROC01 |
| RB-11 | Owner replacement | [TASK-011](tasks/TASK-011-user-management.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 2; OPPROC01 |
| RB-12 | Cost recovery | [TASK-036](tasks/TASK-036-reconciliation-repair.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7; OPPROC01 |
| RB-13 | Maintenance and updates | [TASK-012](tasks/TASK-012-application-image.md) | [TASK-045](tasks/TASK-045-operations-verification.md) | OPERATIONS_RUNBOOK section 7 |
| RB-20 | Deploying a release | [TASK-014](tasks/TASK-014-release-pipeline.md) | [TASK-045](tasks/TASK-045-operations-verification.md), [TASK-050](tasks/TASK-050-production-pilot.md) | RELEASE_PROCESS section 6 |
| RB-21 | Checking a release | [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-014](tasks/TASK-014-release-pipeline.md) | [TASK-045](tasks/TASK-045-operations-verification.md), [TASK-050](tasks/TASK-050-production-pilot.md) | RELEASE_PROCESS section 7 |
| RB-22 | Rolling back | [TASK-014](tasks/TASK-014-release-pipeline.md) | — | RELEASE_PROCESS section 8; exercised only when needed |
| RB-23 | First installation | [TASK-014](tasks/TASK-014-release-pipeline.md), [TASK-037](tasks/TASK-037-restore-tooling.md) | [TASK-045](tasks/TASK-045-operations-verification.md), [TASK-049](tasks/TASK-049-release-readiness.md), [TASK-050](tasks/TASK-050-production-pilot.md) | RELEASE_PROCESS section 10: steps 1 to 6 and 10 before the release, 7 to 11 in TASK-050 |
| RB-24 | Cutover | [TASK-035](tasks/TASK-035-onboarding.md) | [TASK-049](tasks/TASK-049-release-readiness.md), [TASK-050](tasks/TASK-050-production-pilot.md) | RELEASE_PROCESS section 11: rehearsed on staging, then real |

### 10.3 UAT scripts, pilot criteria and guidance

| Items | Task |
| --- | --- |
| UAT-20, UAT-23, UAT-24 | [TASK-045](tasks/TASK-045-operations-verification.md) |
| UAT-01 to UAT-19, UAT-21, UAT-22 | [TASK-049](tasks/TASK-049-release-readiness.md) |
| PIL-01 to PIL-11 | [TASK-050](tasks/TASK-050-production-pilot.md) |
| UG-01 to UG-13 (finalized at training) | [TASK-049](tasks/TASK-049-release-readiness.md) |
| DVT-01 to DVT-40 | [TASK-046](tasks/TASK-046-device-verification.md) |

## 11. Checks

| Check | Result |
| --- | --- |
| Tasks; identifiers `TASK-001` to `TASK-050` in order | 50 |
| Dependencies: every Task depends only on smaller numbers; the graph has no cycle | Yes |
| Requirements of REQUIREMENTS carried by exactly one Task | 134 of 134 |
| Workflows of CRITICAL_FLOWS carried by exactly one Task | 32 of 32 |
| User tasks of USER_TASKS in force (UXT-05 is retired by D-077) with a carrying workflow, Task and UAT script | 38 of 38 |
| Acceptance suites with exactly one owning Task and a final-point Task | 56 of 56 |
| Races of TECHNICAL_RULES section 8 owned by exactly one Task | 35 of 35 |
| Guards of SCHEMA section 8 owned by exactly one Task | 27 of 27 |
| Threats of SECURITY_MODEL section 10 owned by exactly one Task | 21 of 21 |
| Routes of ROUTE_CONTRACTS (pages, print views, reads, authentication, other requests, health, commands, scheduled commands, operator procedures) owned by exactly one Task | 142 of 142 |
| Tables of SCHEMA section 10 created by exactly one Task | 71 of 71 |
| Keys of SCHEMA section 5 from a table to one that a later Task creates, completed by that later Task (EXC-44) | 11 of 11 |
| Pages of INFORMATION_ARCHITECTURE section 5 with one owning Task | 61 of 61 |
| Controls of INTERACTION_CONTRACTS section 9 built by exactly one Task | 383 of 385; the other 2 are "Not provided" |
| Controls whose owning Task depends on every Task that delivers its page and routes | Yes |
| Document contracts DC-01 to DC-05 owned by exactly one Task | 5 of 5 |
| Former Build Units mapped | 27 of 27 |
| Errors found by the generator | 0 |
| Runbooks of the P9 and P10 documents mapped | 18 of 18 |
| UAT scripts mapped | 24 of 24 |
