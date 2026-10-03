# Legacy index

Where the material that existed before the re-foundation went, and how to get it back. The baseline is commit `604d9254f6a88958a1f1e36d54beabff54efdb96` on branch `plan/bantukasir-pos-v1` (3 October 2026): every path in the "old path" columns below exists at that commit.

## 1. How to recover anything

```text
git show 604d925:<old path>                 print one file as it was
git archive --format=zip -o <file outside the repository> 604d925 <old path>   export a folder as it was
git log --follow -- <new path>              history of a moved file across the move
```

These commands do not change the working tree. Bringing legacy material back into the working tree needs an authorization recorded in the handoff.

## 2. Documents moved in RF-1 (72 files)

Moved without any change to their content. Their status is in [SOURCE_OF_TRUTH](SOURCE_OF_TRUTH.md). Links inside them still use the old paths; use this table to translate.

| Old path | New path |
| --- | --- |
| `DESIGN.md` | `docs/07-ux-design/legacy/DESIGN.md` |
| `docs/00-CURRENT-STATE.md` | `docs/00-governance/legacy/00-CURRENT-STATE.md` |
| `docs/01-PRD.md` | `docs/01-product/legacy/01-PRD.md` |
| `docs/02-ARCHITECTURE.md` | `docs/04-architecture/legacy/02-ARCHITECTURE.md` |
| `docs/04-AUTH-RBAC-SECURITY.md` | `docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md` |
| `docs/06-INVENTORY-SPEC.md` | `docs/02-domain/legacy/06-INVENTORY-SPEC.md` |
| `docs/08-TESTING-ACCEPTANCE.md` | `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` |
| `docs/09-DEPLOYMENT-OPS.md` | `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` |
| `docs/10-DECISIONS.md` | `docs/00-governance/legacy/10-DECISIONS.md` |
| `docs/11-BUILD-PLAN.md` | `docs/11-tasks/legacy/11-BUILD-PLAN.md` |
| `docs/12-BARCODE-SCANNER.md` | `docs/02-domain/legacy/12-BARCODE-SCANNER.md` |
| `docs/15-FINANCE-PROFITABILITY.md` | `docs/02-domain/legacy/15-FINANCE-PROFITABILITY.md` |
| `docs/17-POS-SALES.md` | `docs/02-domain/legacy/17-POS-SALES.md` |
| `docs/build-units/BU-CASH-001.md` | `docs/11-tasks/legacy/build-units/BU-CASH-001.md` |
| `docs/build-units/BU-CASH-002.md` | `docs/11-tasks/legacy/build-units/BU-CASH-002.md` |
| `docs/build-units/BU-CAT-001.md` | `docs/11-tasks/legacy/build-units/BU-CAT-001.md` |
| `docs/build-units/BU-CFG-001.md` | `docs/11-tasks/legacy/build-units/BU-CFG-001.md` |
| `docs/build-units/BU-CMD-001.md` | `docs/11-tasks/legacy/build-units/BU-CMD-001.md` |
| `docs/build-units/BU-DOC-001.md` | `docs/11-tasks/legacy/build-units/BU-DOC-001.md` |
| `docs/build-units/BU-FOUND-001.md` | `docs/11-tasks/legacy/build-units/BU-FOUND-001.md` |
| `docs/build-units/BU-IAM-001.md` | `docs/11-tasks/legacy/build-units/BU-IAM-001.md` |
| `docs/build-units/BU-INV-001.md` | `docs/11-tasks/legacy/build-units/BU-INV-001.md` |
| `docs/build-units/BU-INV-002.md` | `docs/11-tasks/legacy/build-units/BU-INV-002.md` |
| `docs/build-units/BU-INV-003.md` | `docs/11-tasks/legacy/build-units/BU-INV-003.md` |
| `docs/build-units/BU-OPS-001.md` | `docs/11-tasks/legacy/build-units/BU-OPS-001.md` |
| `docs/build-units/BU-OPS-002.md` | `docs/11-tasks/legacy/build-units/BU-OPS-002.md` |
| `docs/build-units/BU-READ-001.md` | `docs/11-tasks/legacy/build-units/BU-READ-001.md` |
| `docs/build-units/BU-READ-002.md` | `docs/11-tasks/legacy/build-units/BU-READ-002.md` |
| `docs/build-units/BU-RELEASE-001.md` | `docs/11-tasks/legacy/build-units/BU-RELEASE-001.md` |
| `docs/build-units/BU-RELEASE-002.md` | `docs/11-tasks/legacy/build-units/BU-RELEASE-002.md` |
| `docs/build-units/BU-SALE-001.md` | `docs/11-tasks/legacy/build-units/BU-SALE-001.md` |
| `docs/build-units/BU-SALE-002.md` | `docs/11-tasks/legacy/build-units/BU-SALE-002.md` |
| `docs/build-units/BU-UI-001.md` | `docs/11-tasks/legacy/build-units/BU-UI-001.md` |
| `docs/build-units/BU-UI-002.md` | `docs/11-tasks/legacy/build-units/BU-UI-002.md` |
| `docs/build-units/BU-UI-003.md` | `docs/11-tasks/legacy/build-units/BU-UI-003.md` |
| `docs/build-units/BU-UI-004.md` | `docs/11-tasks/legacy/build-units/BU-UI-004.md` |
| `docs/build-units/BU-VERIFY-001.md` | `docs/11-tasks/legacy/build-units/BU-VERIFY-001.md` |
| `docs/build-units/BU-VERIFY-002.md` | `docs/11-tasks/legacy/build-units/BU-VERIFY-002.md` |
| `docs/build-units/BU-VERIFY-003.md` | `docs/11-tasks/legacy/build-units/BU-VERIFY-003.md` |
| `docs/build-units/BU-VERIFY-004.md` | `docs/11-tasks/legacy/build-units/BU-VERIFY-004.md` |
| `docs/build-units/registry.json` | `docs/11-tasks/legacy/build-units/registry.json` |
| `docs/evidence/p7-correction/BROWSER.json` | `docs/07-ux-design/legacy/p7-correction/BROWSER.json` |
| `docs/evidence/p7-correction/REVIEW.md` | `docs/07-ux-design/legacy/p7-correction/REVIEW.md` |
| `docs/evidence/p7-correction/VALIDATION.json` | `docs/07-ux-design/legacy/p7-correction/VALIDATION.json` |
| `docs/evidence/p7-correction/auth-reset-375.jpg` | `docs/07-ux-design/legacy/p7-correction/auth-reset-375.jpg` |
| `docs/evidence/p7-correction/desainpakeai-review.jpg` | `docs/07-ux-design/legacy/p7-correction/desainpakeai-review.jpg` |
| `docs/evidence/p7-correction/mobile-cart-375.jpg` | `docs/07-ux-design/legacy/p7-correction/mobile-cart-375.jpg` |
| `docs/evidence/p7-correction/owner-dashboard-1440.jpg` | `docs/07-ux-design/legacy/p7-correction/owner-dashboard-1440.jpg` |
| `docs/evidence/p7-owner-r1/REVIEW.md` | `docs/07-ux-design/legacy/p7-owner-r1/REVIEW.md` |
| `docs/evidence/p7-owner-r1/VALIDATION.json` | `docs/07-ux-design/legacy/p7-owner-r1/VALIDATION.json` |
| `docs/evidence/p7-owner-r1/VERIFY.json` | `docs/07-ux-design/legacy/p7-owner-r1/VERIFY.json` |
| `docs/evidence/p7-owner-r1/catalog-desktop.png` | `docs/07-ux-design/legacy/p7-owner-r1/catalog-desktop.png` |
| `docs/evidence/p7-owner-r1/owner-chart-desktop.png` | `docs/07-ux-design/legacy/p7-owner-r1/owner-chart-desktop.png` |
| `docs/evidence/p7-owner-r1/owner-issue-mobile.png` | `docs/07-ux-design/legacy/p7-owner-r1/owner-issue-mobile.png` |
| `docs/evidence/p7-owner-r1/pos-mobile.png` | `docs/07-ux-design/legacy/p7-owner-r1/pos-mobile.png` |
| `docs/evidence/p7-owner-r1/product-detail-desktop.png` | `docs/07-ux-design/legacy/p7-owner-r1/product-detail-desktop.png` |
| `docs/planning/GAPS-CHANGES.md` | `docs/00-governance/legacy/GAPS-CHANGES.md` |
| `docs/planning/P0-GOVERNANCE.md` | `docs/00-governance/legacy/P0-GOVERNANCE.md` |
| `docs/planning/P1-PRODUCT.md` | `docs/01-product/legacy/P1-PRODUCT.md` |
| `docs/planning/P10-RELEASE-UAT.md` | `docs/10-release/legacy/P10-RELEASE-UAT.md` |
| `docs/planning/P11-BUILD-UNITS.md` | `docs/11-tasks/legacy/P11-BUILD-UNITS.md` |
| `docs/planning/P2-DOMAIN.md` | `docs/02-domain/legacy/P2-DOMAIN.md` |
| `docs/planning/P3-CRITICAL-WORKFLOWS.md` | `docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md` |
| `docs/planning/P4-ARCHITECTURE.md` | `docs/04-architecture/legacy/P4-ARCHITECTURE.md` |
| `docs/planning/P5-SECURITY.md` | `docs/05-security/legacy/P5-SECURITY.md` |
| `docs/planning/P6-CONCURRENCY-API.md` | `docs/06-api-performance/legacy/P6-CONCURRENCY-API.md` |
| `docs/planning/P7-COVERAGE.md` | `docs/07-ux-design/legacy/P7-COVERAGE.md` |
| `docs/planning/P7-UX-DESIGN.md` | `docs/07-ux-design/legacy/P7-UX-DESIGN.md` |
| `docs/planning/P8-QUALITY.md` | `docs/08-testing/legacy/P8-QUALITY.md` |
| `docs/planning/P9-OPERATIONS.md` | `docs/09-operations/legacy/P9-OPERATIONS.md` |
| `docs/requirements/CORE-MAP.md` | `docs/01-product/legacy/requirements/CORE-MAP.md` |
| `docs/requirements/core.json` | `docs/01-product/legacy/requirements/core.json` |

## 3. Trees retired in RF-1 (128 files)

Removed from the working tree by the RF-1 checkpoint commit, which is the commit that adds this file. They are declared historical by the documents that accompanied them and are not inputs to any planned step. Last commit containing them: `604d925`.

| Path | Tracked files | What it was |
| --- | ---: | --- |
| `docs/archive/plan2/` | 41 | Plan 2.0 archive: 40 task cards T01–T40 and a note |
| `docs/evidence/plan2-validation.md` | 1 | Validation record of the Plan 2.0 baseline |
| `docs/evidence/rebaseline-p0-p11/` | 3 | Review, validation and preservation records of the first P0–P11 baseline |
| `prototypes/` | 26 | HTML prototypes UI01 and UI02 and their registry |
| `.impeccable/` | 57 | Review outputs of the retired Impeccable tool |
| **Total** | **128** | |

## 4. Items preserved outside the commit history

Kept by Owner decision D-022. Neither is tracked. Do not delete or drop them.

| Item | Description | Identification |
| --- | --- | --- |
| `plan-p0-p11-review.patch` (untracked, repository root) | A UTF-16 `git format-patch` export of commit `5e100aa` ("docs: bekukan rebaseline P0-P11 dan Build Units"). It duplicates a commit that is in history. | 1,639,792 bytes; SHA-256 `a13fe0f2c4f494992ed339292c2c2bcdf7f4fc8ea85606bccd5539204ef7f03e` |
| `stash@{0}` | "pre-T01 preserve legacy BK03 and historical evidence": six files of an abandoned UI03 prototype (`prototypes/ui03/index.html`, `script.js`, `shell.js`, `styles.css`; `.impeccable/review/bk03/REVIEW.md`, `TOOLS.md`). **Holds unique content:** `script.js`, `shell.js` and `styles.css` differ from the copies in the ignored folder `.local/ui03`. | Stash commit `f4c98dfa4cc4758dcef8444651c9fce158976da1`, 2 October 2026 00:40 +0700 |

The ignored folders `.local/` and `.agents/` are not tracked, not part of any commit and not part of the workflow. They exist only on the Owner's computer.

## 5. Earlier planning baselines in Git history

| Dates (2026) | Baseline | Commits |
| --- | --- | --- |
| 19–20 Sep | LATANSA Platform architecture | `3deb8e8`, `183cea6`, `1d993c1` |
| 20–21 Sep | Unified retail order Plan 3.1 and 3.1.1 | `44997a0`, `acb4063`, `f7cecfe` (this is the current `main`) |
| 21 Sep | Focused POS v1 | `6e0c13d`, `bd8a807`, `e8011cf` |
| 22–25 Sep | UI01 and UI02 HTML prototypes | `3e30fce`, `1f62e98`, `f6c9c58` |
| 2 Oct | Plan 2.0 with 40 task cards | `c4a145d` |
| 2 Oct | P0–P11 with Build Units | `5e100aa` |
| 3 Oct | P7 moved to actual design; candidate checkpoint | `f7c02df`, `604d925` |

`main` and `origin/main` are at `f7cecfe` and must not be modified (D-022). `origin/plan/bantukasir-pos-v1` is at `d41543d`; the seven commits from `3e30fce` to `604d925` had not been pushed when RF-1 was carried out.

## 6. Legacy identifiers

| Identifier | Meaning | Where it is defined now |
| --- | --- | --- |
| `D-001` … `D-018` | Decisions before the re-foundation | [DECISIONS](DECISIONS.md); original wording in `legacy/10-DECISIONS.md` |
| `BU-…` (27) | Build Units | `docs/11-tasks/legacy/build-units/` — input only, not executable |
| `CORE-001` … `CORE-073` | Core requirement entries | `docs/01-product/legacy/requirements/` — input only |
| `AUTH01`, `POS01`, … (39) | Acceptance suites | `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` |
| `G1` … `G4`, `DEVICES`, `TARGET`, `EXECUTOR`, `RELEASE` | Business and external gates | [OPEN_ITEMS](OPEN_ITEMS.md) section 3 |
| `P7-G01` … `P7-G18` | Capability groups of the former design coverage matrix | `docs/07-ux-design/legacy/P7-COVERAGE.md` — input only |
| `T01` … `T40` | Plan 2.0 task cards | Git history only: `git show 604d925:docs/archive/plan2/tasks/T01.md` |
| `UI01`, `UI02`, `UI03`, `BK01`–`BK03`, `R6`, Gate A/B | Earlier prototype rounds | Git history only (section 3); UI03 in the stash (section 4) |

The gates of the rebuilt P7 are named `P7-G1`, `P7-G2` and `P7-G3` (D-025). They are unrelated to the legacy capability groups `P7-G01` to `P7-G18`.
