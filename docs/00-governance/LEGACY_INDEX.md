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
| `CORE-001` … `CORE-073` | Core requirement entries | Superseded in RF-2 by the requirement register. [REQUIREMENTS](../01-product/REQUIREMENTS.md) section 21 maps every entry to the requirements that carry it, or records why it was retired. The original entries stay in `docs/01-product/legacy/requirements/` |
| `AUTH01`, `POS01`, … (39) | Acceptance suites | `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md`; cited as test references by REQUIREMENTS until RF-6 |
| `G1` … `G4`, `DEVICES`, `TARGET`, `EXECUTOR`, `RELEASE` | Business and external gates | [OPEN_ITEMS](OPEN_ITEMS.md) section 3 |
| `P7-G01` … `P7-G18` | Capability groups of the former design coverage matrix | `docs/07-ux-design/legacy/P7-COVERAGE.md` — LEGACY — SUPERSEDED in RF-4A; [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) section 12 maps every group to its pages (section 10 below) |
| `T01` … `T40` | Plan 2.0 task cards | Git history only: `git show 604d925:docs/archive/plan2/tasks/T01.md` |
| `UI01`, `UI02`, `UI03`, `BK01`–`BK03`, `R6`, Gate A/B | Earlier prototype rounds | Git history only (section 3); UI03 in the stash (section 4) |

The gates of the rebuilt P7 are named `P7-G1`, `P7-G2` and `P7-G3` (D-025). They are unrelated to the legacy capability groups `P7-G01` to `P7-G18`.

## 7. Documents superseded in RF-2

RF-2 (D-041) wrote the CURRENT product and domain documents. The legacy documents below stay in the working tree, byte-identical, with the status LEGACY — SUPERSEDED in [SOURCE_OF_TRUTH](SOURCE_OF_TRUTH.md). They are removed only by a later authorized step; Git history keeps them in any case.

| Legacy document | Superseded by |
| --- | --- |
| `docs/01-product/legacy/01-PRD.md` | `docs/01-product/PRODUCT_BLUEPRINT.md` and `docs/01-product/REQUIREMENTS.md` |
| `docs/01-product/legacy/P1-PRODUCT.md` | `docs/01-product/PRODUCT_BLUEPRINT.md` |
| `docs/01-product/legacy/requirements/core.json` | `docs/01-product/REQUIREMENTS.md` (section 21) |
| `docs/01-product/legacy/requirements/CORE-MAP.md` | `docs/01-product/REQUIREMENTS.md` (section 21) |
| `docs/02-domain/legacy/P2-DOMAIN.md` | `docs/02-domain/DOMAIN_MODEL.md` |
| `docs/02-domain/legacy/06-INVENTORY-SPEC.md` | `docs/02-domain/INVENTORY.md` |
| `docs/02-domain/legacy/12-BARCODE-SCANNER.md` | `docs/02-domain/CATALOG_BARCODE.md` for domain rules; the scanner screen behaviour is input only for RF-4 and RF-5 |
| `docs/02-domain/legacy/15-FINANCE-PROFITABILITY.md` | `docs/02-domain/COSTING.md` |
| `docs/02-domain/legacy/17-POS-SALES.md` | `docs/02-domain/SALES_CASH.md` for domain rules; receipt and screen layouts are input only for RF-4 and RF-5 |
| `docs/04-architecture/legacy/02-ARCHITECTURE.md` — domain part only (entity table, numbers and time) | `docs/02-domain/DOMAIN_MODEL.md`; its other parts are dealt with in RF-3A and RF-3B (section 8) |

New identifiers written in RF-2: requirements `REQ-<area>-<nn>` in REQUIREMENTS, and domain rules `BR-<area>-<nn>` in the domain documents.

## 8. Documents superseded in RF-3A

RF-3A (D-055) wrote the CURRENT architecture, security and technical-rule documents and ADR-001 to ADR-007. Supersession is set per section and per checkpoint (plan decision P-5): no legacy document is shown as fully superseded while any of its parts is still the authority. All the legacy files stay in the working tree, byte-identical; they are removed only by a later authorized step.

| Legacy document and section | Status after RF-3A | Carried by |
| --- | --- | --- |
| `02-ARCHITECTURE` introduction (stack, authentication, layers, no Redis, queue or SSR) | LEGACY — SUPERSEDED | ADR-001, ADR-002, ADR-004; STACK_AND_MODULES section 1; ARCHITECTURE section 2 |
| `02-ARCHITECTURE` modular monolith paragraph and target structure | LEGACY — SUPERSEDED | STACK_AND_MODULES sections 2 and 3 |
| `02-ARCHITECTURE` §Model domain and §Angka dan waktu | LEGACY — SUPERSEDED in RF-2 | DOMAIN_MODEL (section 7 of this index); number representation also ADR-006 |
| `02-ARCHITECTURE` §Constraint database | **LEGACY — IN FORCE until RF-3B** | SCHEMA (RF-3B) |
| `02-ARCHITECTURE` §Kontrak command dan idempotency | LEGACY — SUPERSEDED | ADR-007; TECHNICAL_RULES section 3 |
| `02-ARCHITECTURE` §Urutan lock dan batas transaksi | LEGACY — SUPERSEDED | TECHNICAL_RULES sections 6 and 7 |
| `02-ARCHITECTURE` §Batas read dan projection | LEGACY — SUPERSEDED | ARCHITECTURE section 4; TECHNICAL_RULES sections 11 and 12; SECURITY_MODEL section 5 |
| `02-ARCHITECTURE` §Runtime, asset dan operasi | LEGACY — SUPERSEDED | ARCHITECTURE sections 6, 8 and 9; ADR-002; ADR-003; SECURITY_MODEL sections 8 and 13 |
| `02-ARCHITECTURE` §Payment extension boundary dan schema delivery — the payment paragraph and the number representation | LEGACY — SUPERSEDED | ARCHITECTURE section 5; ADR-006 |
| `02-ARCHITECTURE` same section — schema delivery (raw SQL constraints, partial uniqueness, immutable-history privileges, real PostgreSQL evidence) | **LEGACY — IN FORCE until RF-3B** | SCHEMA (RF-3B); the privilege principle is also ARCHITECTURE section 9 |
| `02-ARCHITECTURE` last paragraph (read API providers per Build Unit; cash register configuration) | LEGACY — SUPERSEDED | Module ownership in STACK_AND_MODULES section 2; register configuration in SALES_CASH BR-CASH-02 and TECHNICAL_RULES section 7. The assignment of read APIs to Build Units is not carried: Build Units are input only for RF-7 |
| `P4-ARCHITECTURE` introduction, §Konteks dan kriteria pemilihan, §Perbandingan minimum A dan B | LEGACY — SUPERSEDED | ADR-001 |
| `P4-ARCHITECTURE` §Dampak kecepatan dan biaya tradeoff | LEGACY — SUPERSEDED | ADR-001 (consequences). The hour estimates are not carried: effort is estimated in RF-7 |
| `P4-ARCHITECTURE` §Pembekuan dan pin versi | LEGACY — SUPERSEDED | ADR-001; STACK_AND_MODULES STK-01 and STK-02 |
| `P4-ARCHITECTURE` §Struktur modular minimal | LEGACY — SUPERSEDED | STACK_AND_MODULES; ARCHITECTURE sections 2 and 3 |
| `P4-ARCHITECTURE` §Schema, immutability dan constraints | **LEGACY — IN FORCE until RF-3B**, except its statement that barcode tokens are never reused (D-051, D-052; SOURCE_OF_TRUTH section 2) | SCHEMA (RF-3B) |
| `P4-ARCHITECTURE` §Angka dan waktu exact | LEGACY — SUPERSEDED | ADR-006; TECHNICAL_RULES section 11; DOMAIN_MODEL section 7. Its statement about which cost a saleable return uses has no authority while T-16 is open (COSTING section 10) |
| `P4-ARCHITECTURE` §Transaksi, locking dan original-result recovery | LEGACY — SUPERSEDED | TECHNICAL_RULES sections 6 to 9; ADR-007 |
| `P4-ARCHITECTURE` §Payment boundary dan kesiapan provider kelak | LEGACY — SUPERSEDED | ARCHITECTURE section 5, with the methods of D-036 as amended by D-077 |
| `P4-ARCHITECTURE` §Keamanan dan privacy implications | LEGACY — SUPERSEDED | SECURITY_MODEL; ADR-004. Its write-only cost rule was already superseded by D-033 |
| `P4-ARCHITECTURE` §Testing implications | LEGACY — INPUT ONLY for RF-6 | The test strategy (RF-6) |
| `P4-ARCHITECTURE` §Deployment, operasi dan maintenance | LEGACY — INPUT ONLY for RF-6; ADR-002 and ADR-003 prevail where it differs | RF-6 |
| `P4-ARCHITECTURE` §Sumber primer yang diverifikasi saat planning | RECORD — no rule | Current sources are recorded in each ADR |
| `P4-ARCHITECTURE` §History Inertia, pergantian actor dan privasi browser, and its cache paragraph | LEGACY — SUPERSEDED | SECURITY_MODEL section 7; ADR-002 |
| `04-AUTH-RBAC-SECURITY`, whole document | LEGACY — SUPERSEDED | SECURITY_MODEL; ADR-004 |
| `P5-SECURITY`, whole document | LEGACY — SUPERSEDED | SECURITY_MODEL |
| `P6-CONCURRENCY-API`, whole document | LEGACY — SUPERSEDED | TECHNICAL_RULES; ADR-007 |

The final status of `02-ARCHITECTURE` and `P4-ARCHITECTURE` is settled after RF-3B is accepted (P-5).

Legacy technical rules changed or retired in RF-3A, with the reason:

| Legacy rule | Change | Reason |
| --- | --- | --- |
| L-P6: at most 100 distinct product lines per request | 200 lines per Sale, 500 per receiving document, 200 per manual issue or adjustment (TECHNICAL_RULES section 11.2); 100 rows per opening command stay (BR-INV-25) | A limit must never constrain a plausible shop operation (BR-NUM-04) |
| L-P5: limits for public password-reset requests (3 per hour per identity, 10 per IP, 100 global) | Retired | There is no public reset request (D-049); one-time link submissions have their own limits (SECURITY_MODEL section 11) |
| L-P4, L-09: Docker Compose packaging; Nginx-managed certificate renewal | Replaced | The hosting topology is Cloudflare, VPS and Coolify (D-053, ADR-003) |
| L-04, L-P4, L-P5: purchase cost entered by Operations is write-only | Not carried | Superseded by D-033 before RF-3A |
| L-P4, L-P6: CASH or BANK_TRANSFER only | Not carried | Superseded by D-036 before RF-3A. Current again since D-077 (ARCHITECTURE ARC-30, TECHNICAL_RULES TR-VAL-04) |
| L-P6 race "Replay melawan posting": "source and allocation stable and versioned" | Not carried as a rule | It would settle T-16 (a) (COSTING section 10); TECHNICAL_RULES RACE-11 keeps the rest of the rule |
| L-P4: Fortify chosen from the version notes of the time; 12-character password in the former design candidate | Replaced | Fortify confirmed from current documentation (ADR-004); password profile AICWDF-COMPAT-8 (D-049) |
| L-P6, L-04: a record missing after an uncertain outcome stays UNCERTAIN or becomes an incident; "not found" is never permission for a replacement | Kept and refined: within one recovery epoch, a resolution that writes a fence makes NOT_COMMITTED a server fact, after which a new draft is allowed (ADR-007 section 4); a missing result alone still permits nothing (a lost command reference also needs the hard time bound of TECHNICAL_RULES TR-TIME-03); after a restore such intents are refused `EPOCH_STALE` and handled by the restore runbook | The fence guarantees that the original can never commit afterwards, so a new draft cannot duplicate it; BR-CMD-03 is kept (ADR-007 section 4) |
| L-02, L-P6: after a restore, a new recovery epoch stops old browser intents | Kept and made precise: the restore sets an epoch value never issued before, and a command whose epoch differs from the current one is refused (ADR-007 section 6) | A counter would reissue an epoch already given to browsers when the same backup is restored twice |

New identifiers written in RF-3A: `ADR-NNN` in `docs/adr/`; `ARC-nn` in ARCHITECTURE; `STK-nn` in STACK_AND_MODULES; `SEC-<area>-nn`, `CAP-nn` and `THR-nn` in SECURITY_MODEL; `TR-<area>-nn` and `RACE-nn` in TECHNICAL_RULES; `BR-CAT-50` to `BR-CAT-52` in CATALOG_BARCODE; `REQ-ACC-12`, `REQ-CAT-13` and `REQ-OPS-06` in REQUIREMENTS.

## 9. Documents superseded in RF-3B

RF-3B (D-059) wrote the logical database schema, [SCHEMA](../04-architecture/SCHEMA.md), and [ADR-008](../adr/ADR-008-cost-allocation-representation.md). Under plan decision P-6 the final status of the two legacy P4 documents is written in the RF-3B checkpoint and takes effect when the Owner accepts it. All the legacy files stay in the working tree, byte-identical; they are removed only by a later authorized step.

| Legacy document and section | Status after RF-3B | Carried by |
| --- | --- | --- |
| `02-ARCHITECTURE` §Constraint database | LEGACY — SUPERSEDED | SCHEMA sections 5, 8 and 9 (SCHEMA section 14 traces each statement) |
| `02-ARCHITECTURE` §Payment extension boundary dan schema delivery — schema delivery (raw SQL constraints, partial uniqueness, immutable-history privileges, real PostgreSQL evidence) | LEGACY — SUPERSEDED | SCHEMA sections 10 and 13 (SCH-51), section 9.4 |
| `02-ARCHITECTURE`, whole document | **LEGACY — SUPERSEDED** | DOMAIN_MODEL (RF-2); ARCHITECTURE, STACK_AND_MODULES, SECURITY_MODEL, TECHNICAL_RULES and ADR-001 to ADR-007 (RF-3A, section 8); SCHEMA and ADR-008 (RF-3B) |
| `P4-ARCHITECTURE` §Schema, immutability dan constraints | LEGACY — SUPERSEDED | SCHEMA (SCHEMA section 14 traces each statement) |
| `P4-ARCHITECTURE`, whole document | **LEGACY — SUPERSEDED**, except §Testing implications and §Deployment, operasi dan maintenance (LEGACY — INPUT ONLY for RF-6; ADR-002 and ADR-003 prevail where the deployment section differs) and §Sumber primer yang diverifikasi saat planning (RECORD) | ADR-001 to ADR-008, ARCHITECTURE, STACK_AND_MODULES, SECURITY_MODEL, TECHNICAL_RULES, SCHEMA |
| The legacy statements listed in COSTING section 10 (L-15, L-02, L-P2, L-P3, L-P6, L-06, L-17, L-08; Build Units BU-INV-003, BU-SALE-002) | Each received its disposition — adopted or rejected, with its reason — in ADR-008; the status of its parent document is unchanged by that disposition | ADR-008 section "Legacy statements listed in COSTING section 10" |

This settles plan decision P-5 for the two documents: no legacy document is now in force for the subject of P4.

Legacy schema rules changed or made concrete in RF-3B, with the reason:

| Legacy rule | Change | Reason |
| --- | --- | --- |
| L-P4: "Unik normalized SKU" | Made concrete: outer whitespace removed before storage; letter case kept as typed; uniqueness without regard to letter case; no control characters; 1 to 64 characters (SCHEMA section 5.6; CATALOG_BARCODE BR-CAT-05) | T-17 g, a technical reading the Owner accepted for RF-3B planning (D-059) |
| L-02, L-P4: a barcode token unique globally and never reused | Changed before RF-3B: a token is unique and active on at most one product; a manufacturer token retired as a misassignment may be re-attached by the Owner's correction; `BK-P-…` codes are never reused (SCHEMA section 5.6) | D-051, D-052 |
| L-P4: "satu full Payment ... per completed Sale" | Kept, with the two methods of D-077 (SCHEMA section 5.9) | D-036, D-077 |
| L-P4, L-02: "Sale number" unique and immutable, with no generation rule | Kept, with the generation rule of D-062: unique per installation, taken inside the committing transaction, increasing in allocation order, gaps allowed, with the Asia/Jakarta business date as a readable part (SCHEMA section 6) | D-062 |
| L-02 entity table: CashRegister "code/label unik" | Made concrete: the register code is unique without regard to letter case; the label is not unique (SCHEMA section 5.10) | Carried from the legacy entity table as the code's identity rule; reported to the Owner with the RF-3B report |
| L-02: cumulative refund and return bounds held by aggregate checks under lock | Kept and strengthened: running totals with check constraints under the original Sale lock (SCHEMA section 5.9) | Data integrity first (PROJECT_RULES section 2) |

No legacy schema rule is dropped without a written reason.

New identifiers written in RF-3B: `ADR-008` in `docs/adr/`; `SCH-nn` rules and `G-nn` guards in SCHEMA; questions 32 to 35 in OPEN_ITEMS.

## 10. Documents superseded in RF-4A

RF-4A (D-070) wrote the six CURRENT documents of `docs/07-ux-design/` for gate P7-G1: [USER_TASKS](../07-ux-design/USER_TASKS.md), [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](../07-ux-design/INTERACTION_INVENTORY.md), [UI_STATES](../07-ux-design/UI_STATES.md) and [ADAPTIVE_AND_ACCESSIBILITY](../07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md). The gate itself is not approved by the checkpoint (plan decision P-10). All the legacy files stay in the working tree, byte-identical; they are removed only by a later authorized step.

| Legacy document | Status after RF-4A | Carried by |
| --- | --- | --- |
| `docs/07-ux-design/legacy/P7-UX-DESIGN.md` | LEGACY — SUPERSEDED | The six RF-4A documents; the order of P7 work and the use of the design tool are plan decisions P-10 and P-14 (D-070) |
| `docs/07-ux-design/legacy/P7-COVERAGE.md` | LEGACY — SUPERSEDED | INFORMATION_ARCHITECTURE section 12 (every group traced) and INTERACTION_INVENTORY |
| `docs/07-ux-design/legacy/DESIGN.md` | LEGACY — INPUT ONLY, unchanged | Input to RF-4C (gate P7-G3) |
| `docs/07-ux-design/legacy/p7-correction/`, `docs/07-ux-design/legacy/p7-owner-r1/` | RECORD, unchanged | Evidence for the former candidate; the re-foundation analysis (section 20) and INFORMATION_ARCHITECTURE section 11 trace its destinations and dialogs |

Legacy capability groups and the pages that now carry them:

| Group | Pages |
| --- | --- |
| `P7-G01` Auth dan account | UXP-001, UXP-002, UXP-003, UXP-004, UXP-005; Owner user administration UXP-602 to UXP-606 |
| `P7-G02` Shell, dashboard dan utilities | UXP-000, UXP-007 |
| `P7-G03` Catalog dan search-first | UXP-301, UXP-302, UXP-303, UXP-304, UXP-307 |
| `P7-G04` Barcode dan internal label | UXP-302 (barcode section), UXP-305, UXP-306 |
| `P7-G05` Receiving | UXP-404, UXP-405, UXP-406 |
| `P7-G06` Manual issue non-sale | UXP-407, UXP-408, UXP-409 |
| `P7-G07` Opening awal | UXP-412, UXP-413 |
| `P7-G08` Current stock, restock dan history | UXP-401, UXP-402, UXP-403; the Stok badge in UXP-000 |
| `P7-G09` Register dan own session entry | UXP-104; cash registers UXP-609, UXP-610 |
| `P7-G10` POS draft, scanner dan cart | UXP-101 |
| `P7-G11` Full payment, result dan recovery | UXP-102, UXP-103, UXP-006 |
| `P7-G12` Close, own history dan immutable report | UXP-105, UXP-201, UXP-202, UXP-205, UXP-206, UXP-207 |
| `P7-G13` Owner stock/cost correction | UXP-410, UXP-411, UXP-503, UXP-504, UXP-505 |
| `P7-G14` Refund dan physical return | UXP-203, UXP-204 |
| `P7-G15` Finance, periods, audit dan scoped reports | UXP-501, UXP-502, UXP-612; staff scope through UXP-201, UXP-205, UXP-402 |
| `P7-G16` Runtime identity, config dan system status | UXP-607, UXP-608, UXP-609, UXP-610, UXP-611 |
| `P7-G17` Original documents dan exports | UXP-103, UXP-202 (receipt print and reprint), UXP-206 (session report print and CSV), UXP-306 (labels) |
| `P7-G18` Incident dan recovery state | UXP-006, UXP-414, UXP-415, UXP-611; UI_STATES UXS-14, UXS-15, UXS-16 |

New identifiers written in RF-4A: `UXT-nn` (user tasks), `UXP-nnn` (pages), `UXI-nnn` (interactions), `UXS-nn` (states), `UXA-nn` (adaptive and accessibility rules) and `NC-nn` (global navigation rules) in `docs/07-ux-design/`; decisions D-066 to D-076.

## 11. Q38 amendment

The canonical amendment for the Owner's payment-method decision Q38 (D-077, authorized by D-079) supersedes no legacy document. It retires in place, without reuse, the rule BR-PAY-04 in SALES_CASH, the task UXT-05 in USER_TASKS and the term STATIC_QRIS in GLOSSARY. New identifiers: decisions D-077 to D-079; open item N-11.

## 12. RF-4B

RF-4B (D-083) supersedes no legacy document. It finalizes the shop terms of [GLOSSARY](../02-domain/GLOSSARY.md) and writes [COPY_DECK](../07-ux-design/COPY_DECK.md) for gate P7-G2. The receipt and screen layouts of `docs/02-domain/legacy/17-POS-SALES.md` and the scanner screen behaviour of `docs/02-domain/legacy/12-BARCODE-SCANNER.md` stay input only for RF-5, and `docs/07-ux-design/legacy/DESIGN.md` stays input only for RF-4C; all three are unchanged. New identifiers: the copy keys, the writing rules `CW-nn` and the findings `F-nn` of COPY_DECK; decisions D-081 to D-083.
