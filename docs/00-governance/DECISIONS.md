# Decision log

The single log of decisions for BantuKasir. A decision made in conversation does not exist for the project until it is written here.

## 1. How to use this log

- Identifiers are permanent and continue the original numbering. The next free identifier is **D-064**.
- "Source" says who decided: **Owner**, or **Planner** (a planning agent acting on its own authority under the rules of the time).
- "Status" says whether the decision is in force today. `REVALIDATION PENDING` means the decision still applies but the Owner has been asked to re-confirm it; the item number refers to [OPEN_ITEMS](OPEN_ITEMS.md).
- D-001 to D-018 were recorded on 2 and 3 October 2026 in the former register, now [legacy/10-DECISIONS.md](legacy/10-DECISIONS.md). The table below summarizes them in English. For the exact meaning of D-001 to D-018 the original Indonesian wording governs; for whether a decision is in force, this log governs.
- A new decision that changes an older one names it in the "Status" of the older row. Rows are not deleted or rewritten.

## 2. Decisions carried over (D-001 to D-018)

| ID | Source | Decision (summary) | Status after re-foundation |
| --- | --- | --- | --- |
| D-001 | Owner | Adopt the governance, planning, review, quality, continuity and Build Unit framework from an earlier discussion; never copy the MULTIPLECORP domain, assets, tokens or design project. P0 to P11 may be planned without routine questions between phases. | **SUPERSEDED by D-019** as to the framework and as to planning without per-phase authorization. The prohibition on any use of MULTIPLECORP material is **IN FORCE**. |
| D-002 | Owner | V1 minimum scope: goods only; one stock pool; exactly three roles; catalogue, search and barcode; receiving, opening stock and manual issue; low and out-of-stock attention; stock ledger and balance; moving weighted average cost and owner-only gross profit; cash accountability; fully paid sale; documents; corrections; audit; recovery; runtime client branding. Everything on the "Later" list is inactive. | **IN FORCE, AMENDED** by D-032 (one account may hold CASHIER and OPERATIONS_ADMIN), D-036 (payment methods), D-037 (CSV product import and product photos in V1) and D-038 (one business and one stock pool per installation). R-01, R-05, R-06 and R-08 resolved. |
| D-003 | Owner | One completed sale has exactly one full payment, cash or bank transfer. No mixed, split, partial or credit payment and no second instrument. A transfer is confirmed by a person. A future payment-provider boundary is architectural only: no callback, QRIS, webhook or asynchronous payment state in V1. | **IN FORCE, AMENDED by D-036**: the methods are CASH, BANK_TRANSFER and STATIC_QRIS (static, manually confirmed); one full payment per sale and no gateway, callback, webhook or asynchronous state stay. R-06 resolved. |
| D-004 | Planner | Modular monolith on Laravel, Inertia, React with TypeScript, PostgreSQL and Nginx, chosen by comparison with a full-TypeScript alternative. Versions are pinned at the foundation unit. | **IN FORCE.** Identical to the AICWDF-4.3 default stack. Recorded as ADR-001 in RF-3A (D-055). |
| D-005 | Planner | Minimum infrastructure: database-backed sessions and rate limiter; Vite at build time; PHP-FPM, Nginx and private PostgreSQL; scheduled commands. No SSR Node, Redis, queue, Octane or microservices by default. An independent backup is mandatory. | **IN FORCE** within the hosting topology of D-053 (R-12 resolved). Recorded as ADR-002 in RF-3A (D-055); the topology is ADR-003. |
| D-006 | Planner | Integrity: immutable stock ledger with a synchronous non-negative balance; the whole sale is one atomic transaction including snapshot, audit and result; exact decimal cost with explicit UNKNOWN; command identity by source intent, key, fingerprint and recovery epoch; ordered guards and bounded retries. | **IN FORCE.** Recorded as ADR-006 (exact numbers) and ADR-007 (command identity) in RF-3A (D-055). |
| D-007 | Planner | Authority and privacy: policies, object scope and actor and configuration checks; per-role data allowlists on every output channel. Purchase cost entered by the Operations role is write-only. The Cashier has no master-data management. | Allowlists and server authorization **IN FORCE**. Write-only cost **SUPERSEDED by D-033**. R-03 resolved. |
| D-008 | Planner | Corrections: the original is preserved; the money refund bound and the physical return quantity bound are separate; historical cost is restored once; Owner reason and recent authentication; cost revisions with bounded all-or-nothing replay; unsafe chronology is rejected. | **IN FORCE, AMENDED**: earlier derived valuations by D-034; the source of refund money by D-035. Preserved originals, separate money and quantity bounds, one-time restoration of historical cost, Owner reason and recent authentication, bounded all-or-nothing replay and rejection of unsafe chronology stay. R-04 and R-09 resolved. |
| D-009 | Planner | Cash accountability: one open session per register and per cashier; everyone, including the Owner and for transfer-only sales, sells in their own session; opening cash is explicit, zero allowed; race-safe close with an immutable report. No reopening, no change of responsibility, no shared drawer, no paid-out. | **IN FORCE, AMENDED by D-035**: a drawer-funded CASH refund linked to the original Sale is the one permitted drawer outflow; no general paid-in or paid-out. R-04 resolved. R-14 resolved by D-039. |
| D-010 | Owner (direction), Planner (contract) | Design pipeline: an exact Owner-approved revision in the BantuKasir design project, then repository semantic tokens, then native shadcn/ui with React and Inertia. "BantuKasir" and its logo are a codename; client identity comes from a versioned runtime business profile. The MULTIPLECORP design project must never be touched. Observing a design context is not approval. | Pipeline **ADAPTED by D-024 and D-025**. Runtime client identity **IN FORCE, confirmed and scoped by D-038** (one business per installation). R-10 resolved. MULTIPLECORP prohibition **IN FORCE**. |
| D-011 | Owner | Executor: Claude Opus 5.5, effort xhigh. Fable 5.1, effort xhigh, selectively for bounded high-risk problems. The labels are a request, not a claim of availability; work units must be portable without chat memory. | **SUPERSEDED by D-031** as to models and effort. Portability of work without chat memory stays in force, and a model name remains a request, not a claim of availability (AGENTS.md section 6). |
| D-012 | Planner | Delivery plan of 27 Build Units with hour estimates, 73 Core requirement entries and 39 acceptance suites. | **NOT EXECUTABLE.** To be superseded by the Task baseline in RF-7. The Build Units are input only. |
| D-013 | Planner | Security support: threat-path model; Argon2id; session regeneration and revocation; CSRF; headers and CSP; upload rules; advisory checks. Technical defaults: recent authentication 15 minutes, inactivity 30 minutes, absolute session 12 hours, stated rate limits. | **IN FORCE.** Session timeouts confirmed by D-039 (R-14 resolved). The upload rules also cover CSV product import and product photos (D-037). Password rule set by D-049 (AICWDF-COMPAT-8; R-02 resolved); the security support is carried by SECURITY_MODEL and ADR-004 (RF-3A). |
| D-014 | Planner | Review, fix and validation come before any freeze. P7 must be complete before P8 to P11 are frozen. Client, hardware, backup and release holds stay open. Affected work is reopened when evidence changes. | **IN FORCE** as to review before freeze and the order P7 before P8 to P11. The form of P7 approval is now **D-025**. |
| D-015 | Owner | One local planning commit with an exact, safe path scope; no Build Unit executed; push, deploy, purchase and destruction need separate authorization. | **CONSUMED.** The authorization was used; it grants nothing today. The safety limits are restated in AGENTS.md. |
| D-016 | Planner | Preservation: Plan 2.0 inputs remain in Git and its 40 task cards are archived in the tree; an unrelated untracked patch is preserved; protected sources, assets and evidence are kept byte-identical; no clean-up of unrelated work. | **SUPERSEDED by D-019** as to keeping legacy trees in the working tree. Preservation of the patch and the stash continues under **D-022**. |
| D-017 | Owner | P7 is actual design work in the BantuKasir design project, completed by the Owner's visual approval of one exact revision and a normalized design contract. P8 to P11 are finalized only after P7 is complete. All coding stays unauthorized. The separate design Build Unit is removed. | P7 as real design work and the order P7 before P8 to P11: **IN FORCE.** The single exact-revision approval: **SUPERSEDED by D-025.** The former candidate path: **SUPERSEDED by D-024.** |
| D-018 | Owner | Four notes on the former design candidate: dates with the full Indonesian month name and dotted time (for example `01 Oktober 2026, 09.15.00`); example product photos with fallbacks, without any uploader or storage; an explicit "Barang keluar" menu for the Owner leading to non-sale manual issue; interactive trend-chart points with date and value. Not a visual approval. | Date format and the Owner's "Barang keluar" entry: **IN FORCE** as requirements for the P7 rebuild. Chart behaviour: carried as input to RF-4. Example product photos without an uploader: **SUPERSEDED by D-037** (one optional primary photo per product is a V1 feature). R-08 resolved. |

## 3. Decisions under the re-foundation (D-019 onward)

All by the Owner (Asia/Jakarta). D-019 to D-025 answer the questions of the [re-foundation analysis report](records/REFOUNDATION_ANALYSIS_2026-10-03.md) (3 October 2026). D-026 to D-030 authorize and accept RF-1 (3 October 2026). D-031 to D-040 come from the Owner decision session held after RF-1 (3 and 4 October 2026). D-041 authorizes RF-2 (4 October 2026). D-042 to D-045 answer questions 21 to 24, which the planning agent asked while drafting RF-2; D-046 and D-047 answer questions 27 and 28, which came from the independent adversarial review of RF-2 (all 4 October 2026). D-048 to D-054 answer questions 25, 6, 5, 26, 31, 7 and 30, which the planning agent asked after the Owner reviewed its RF-3 pre-authorization package and before RF-3A was authorized; D-055 authorizes RF-3A (all 4 October 2026). D-056 and D-057 record the Owner's second review of the RF-3A report (4 October 2026). D-058 records the Owner's acceptance of the RF-3A checkpoint; D-059 authorizes RF-3B; D-060 to D-062 answer questions 33, 34 and 32, which the planning agent asked, in that order set by the Owner, after the Owner reviewed its RF-3B pre-authorization package and before RF-3B was authorized (all 5 October 2026). D-063 records the Owner's second review of the RF-3B report (6 October 2026). The full terms of D-031 to D-063 are in section 6.

| ID | Decision | Effect |
| --- | --- | --- |
| D-019 | Full restructure of the repository to the AICWDF-4.3 layout. | Framework deviation FD-01 approved. Supersedes D-001 (framework) and D-016 (legacy trees). AICWDF-4.3 as the operating framework is stated in the Owner's Project instructions. Adopting the framework does not by itself approve a framework rule that changes business, authorization or release meaning; each such change is a revalidation item in [OPEN_ITEMS](OPEN_ITEMS.md) until the Owner decides it. |
| D-020 | Agent-facing repository documents are in English. Indonesian remains the primary interface language. Localization for V1 will be finalized separately. | Resolves R-15. Question 5 and deviation FD-02 stayed open by the Owner's choice until D-050, which answers question 5 and approves FD-02. |
| D-021 | The planning architect may write planning and re-foundation documents directly into the working tree, with no commits and no other Git mutations. Claude Opus 5.5 xhigh on Windows independently validates and creates the checkpoint commit. | Defines how re-foundation changes are applied. The executor's model and effort follow D-031. |
| D-022 | Housekeeping. (a) `plan-p0-p11-review.patch` is not deleted during RF-1; it is kept until RF-1 and the legacy mapping are independently validated. (b) `stash@{0}` is not dropped during RF-1; it is kept until its unique content is verified as safely recoverable elsewhere. (c) The `upstream` remote pointing at `latansa-inventory` is unrelated legacy state and is removed by the Windows executor during checkpoint work. (d) No push during RF-1; `main` is not modified; no merge. After the re-founded baseline is reviewed and explicitly approved, a later pull request may replace the obsolete `main`. | Applied in RF-1. Items (a) and (b) are extended by D-030. |
| D-023 | Planning architect: Fable 5.1, effort xhigh. Implementation executor: Claude Opus 5.5, effort xhigh. | Confirms D-011. **SUPERSEDED by D-031.** |
| D-024 | P7 is rebuilt from information architecture, user tasks and plain-language copy, retaining useful visual tokens. | The former candidate `sha256-bef01d649ea91b2b` will not be approved. There is no approved design revision. |
| D-025 | The single all-or-nothing P7 approval is replaced by three Owner gates: (1) navigation and page structure; (2) terminology and copy; (3) visual direction and key screens. | Supersedes the approval mechanism of D-017. Resolves R-11. |
| D-026 | RF-1 is authorized exactly as scoped in section 40 of the analysis report, revision 2. The planner performs Part A only (file writes and moves, no Git mutation, no deletion). Part B is sent to the executor only after the Owner has reviewed the planner's Part A report and the executor instruction. RF-2 and later steps are not authorized. Implementation is not authorized. | Authorizes RF-1. |
| D-027 | A safety copy is made in RF-1 Part B: the executor creates and verifies a Git bundle at `C:\Backups\BantuKasir` before any retired tree is removed. **Accepted risk:** that location is on the same physical drive as the repository; the copy protects against repository or Git mistakes, not against loss of the disk. | Resolves N-02. |
| D-028 | RF-1 must not knowingly commit a broken link in a tracked document. The two relative links in `assets/brand/README.md` whose targets RF-1 moved are updated to point at the new `legacy/` locations. Nothing else in that file, and no brand asset, is changed. | Amends the RF-1 scope of D-026 by this one file. Applied by the planner in Part A. Does not decide question 14 (branding), which stays open. |
| D-029 | The RF-1 Part B checkpoint instruction, revision 4, is authorized for execution as written. No further revision is made unless the executor reaches a defined stop condition or finds an actual mismatch. The untracked folder `Claude outputs/` is kept as it is and is not deleted or modified before execution. No push, merge, change to `main`, RF-2 work or any work beyond revision 4 is authorized. | Authorizes RF-1 Part B. Executed on 3 October 2026 as commit `3c94f4f5d45223468ae85844da6d37561faeab3e`, with no stop condition and no deviation reported. |
| D-030 | RF-1 Part B and commit `3c94f4f5d45223468ae85844da6d37561faeab3e` are accepted as successfully completed. A small RF-1 close-out is authorized: bring CURRENT_HANDOFF to the state after the checkpoint; record D-029 and D-030; correct the wording contradiction in SOURCE_OF_TRUTH; close open item T-12. `plan-p0-p11-review.patch` is not deleted and `stash@{0}` is not dropped; both stay preserved until a later explicit Owner decision. RF-2 is not started. No push, merge, change to `main`, deployment or application implementation. | RF-1 is complete ([REFOUNDATION_PLAN](REFOUNDATION_PLAN.md) section 5). The acceptance follows the planning architect's check of the executor's report against the repository on 3 October 2026; that review is held in the Owner's Claude Project, not in this repository. Extends D-022 (a) and (b); open item N-05 stays open. The close-out is one commit on top of the checkpoint; it also brings REFOUNDATION_PLAN in line with these changes (the same wording correction and the status of RF-1). |
| D-031 | Agent model, effort and handoff convention: Opus 5.5 is the default model for planning and execution; effort High, XHigh or Max according to the actual complexity and risk of the task; Fable 5.1 only for escalation. Every handoff states Agent, Model, Effort, Session, Reason and Escalation. Instructions to agents are in English; communication with the Owner is in Bahasa Indonesia. | Supersedes D-011 (models and effort) and D-023. Amends D-021. Applied in AGENTS.md section 6. |
| D-032 | Roles per person (question 8): one account may hold CASHIER and OPERATIONS_ADMIN together, with the union of their permissions. SUPER_ADMIN stays separate. Exactly three fixed roles. No Owner-only right is reachable through the combination. | Resolves R-01. Amends D-002. |
| D-033 | Purchase cost and the Operations role (question 9): OPERATIONS_ADMIN sees and may correct purchase cost on receiving documents until confirmation; after posting only the Owner corrects cost. OPERATIONS_ADMIN never sees cost of goods sold, margin, profit, inventory value or cost reports. CASHIER never sees purchase cost. UNKNOWN cost stays available and is never zero. | Resolves R-03. Supersedes the write-only cost rule of D-007. |
| D-034 | Correcting a cost later (question 10): an Owner-only, append-only and audited correction, followed by a bounded deterministic moving-weighted-average replay and an atomic publication of the corrected current valuation, cost of goods sold and profitability projection. Original facts and correction history are retained. V1 need not keep earlier derived projections only to re-display an old report exactly. | Resolves R-09. Amends D-008 as to earlier derived valuations; its other rules stay. |
| D-035 | Refunds and returns (question 11): executed by the Owner only. A cash refund is paid from the drawer of one OPEN CashSession or from the Owner's own funds, chosen at execution. A drawer-funded refund reduces that session's expected cash and is shown separately in its report. No general paid-in or paid-out. | Resolves R-04. Amends D-008 and D-009 as to the source of refund money. |
| D-036 | Payment and price at the till (question 12): methods CASH, BANK_TRANSFER and STATIC_QRIS; one full payment per Sale; STATIC_QRIS is confirmed manually, with no gateway. No discount and no price change at the till; prices change only in the product master data. | Resolves R-06. Amends D-003. Confirms the no-discount rule of D-002. |
| D-037 | Catalogue (question 13): CSV import of new product master data only, never stock or cost; one optional primary photo per product, with thumbnails at the till. | Resolves R-05 and R-08. Amends D-002 and D-018. The catalogue size and an existing product list stay open facts (gate G2). |
| D-038 | One shop or many (question 14): BantuKasir is installed separately for each shop; one business and one stock pool per installation; not multi-tenant; client-neutral code; shop identity from BusinessProfile with revisions. The LATANSA mark leaves the current tree in the next authorized repository change; Git history is not rewritten. | Resolves R-10 and T-13. Confirms and scopes D-010. Confirms D-002 per installation. |
| D-039 | The counter (question 15): PC and tablet are both primary production cashier devices with equivalent cashier capabilities; the phone is not a primary cashier device. Receipt templates: thermal 58 mm, thermal 80 mm and A4 through browser print. V1 calculates no tax. Sign-in sessions: 30 minutes of inactivity, 12 hours absolute, never changing a CashSession. | Resolves R-14 and confirms the D-013 timeouts. Approves FD-05 as a product and design decision; it is not device acceptance. First-store facts stay open (OF-02; gates G1, G3, DEVICES). |
| D-040 | Confirming a sale (question 19): an ordinary Sale is confirmed on one payment screen with one final action; for BANK_TRANSFER and STATIC_QRIS that action states that the funds were received. Server-side integrity is unchanged. Other mutations keep their review steps. | Resolves R-07. Amends the legacy P3 rule for ordinary Sale checkout only. |
| D-041 | RF-2 is authorized as one documentation checkpoint on baseline `4af59a5423298d25d672923279439951fc67ea4d`, the governance checkpoint that records D-031 to D-040, which the Owner accepted on 4 October 2026 after the planning agent's review. Scope: eight CURRENT product and domain documents and changes to seven existing files; superseded legacy documents stay byte-identical and are marked superseded through SOURCE_OF_TRUTH and LEGACY_INDEX; T-16 and every unknown first-store fact and open question stay open. The authorization also clarifies the D-031 workflow. RF-3 is not authorized. | Governance; no product or domain content of its own. Authorizes RF-2 ([REFOUNDATION_PLAN](REFOUNDATION_PLAN.md) section 6). Where the planning agent's RF-2 authorization packet differs from these terms, these terms prevail. |
| D-042 | Lifecycle of master data and configuration (question 21): no deletion in V1. Categories, products, cash registers and users are only deactivated and reactivated — categories and products by the Owner or OPERATIONS_ADMIN, cash registers and users by the Owner. The file of a replaced or removed product photo is kept: no longer shown, included in backups. BusinessProfile revisions are never deleted. | Applied in DOMAIN_MODEL section 11.1, CATALOG_BARCODE and REQUIREMENTS (REQ-ACC-10, REQ-CAT-10, REQ-CAT-12, REQ-OPS-05). Retired barcodes stay under their existing rule and question 26 (R-17). |
| D-043 | Personal data of former staff (question 22): the Owner may anonymize a deactivated account. It is not a deletion; the internal identity and audit relationships remain; historical records are never rewritten. | Terms in section 6. Applied in DOMAIN_MODEL section 8 (BR-USR-11, BR-USR-12) and REQUIREMENTS REQ-ACC-11. An anonymized account cannot be reactivated (D-042). |
| D-044 | Retention of business records (question 23): records that form the basis of bookkeeping or of business and tax records are kept at least 10 years; this is a minimum, not a deletion deadline; V1 has no deletion feature for them. | Terms in section 6. Applied in DOMAIN_MODEL section 12 (BR-KEEP-03 to BR-KEEP-08) and REQUIREMENTS REQ-OPS-05. Data classes outside this rule are not decided (OPEN_ITEMS T-17 c). |
| D-045 | Drawer-funded refund and expected cash (question 24): a drawer-funded CASH refund is allowed only when its amount is at most the selected OPEN session's current expected cash; expected cash never becomes negative because of a refund. | Terms in section 6. Applied in SALES_CASH (BR-RFD-15, BR-RFD-16) and REQUIREMENTS REQ-RFD-08. D-035 stays in force. |
| D-046 | Timing of the drawer payout for a drawer-funded refund (question 27): cash is taken from the drawer only after the refund has committed, with the D-045 check first; if the outcome is uncertain, the status of the original refund is checked first and no cash is taken until the refund is recorded. | Terms in section 6. Applied in SALES_CASH (BR-RFD-05, BR-RFD-07, BR-RFD-14, BR-RFD-17, BR-CASH-07, BR-CASH-16) and REQUIREMENTS REQ-RFD-03, REQ-RFD-09. Owner-funded and BANK_TRANSFER refunds still record the payout actually made. D-035 and D-045 stay in force. |
| D-047 | What OPERATIONS_ADMIN sees after an Owner cost correction (question 28): on a confirmed receiving document, OPERATIONS_ADMIN sees the current cost after the Owner's correction, the mark "Dikoreksi Pemilik" and the cost at confirmation; the reason for the correction, cost of goods sold, margin, profit and inventory value stay hidden. | Terms in section 6. Applied in INVENTORY BR-INV-15, COSTING BR-COST-07 and REQUIREMENTS REQ-INV-05, REQ-COST-04. Correcting cost after confirmation stays Owner-only (D-033, D-034). |
| D-048 | Number of Owner accounts (question 25): exactly one active Owner (SUPER_ADMIN) account; Owner recovery and replacement by documented operator procedure. | Terms in section 6. Applied in SECURITY_MODEL (SEC-ACC-01, section 14), ADR-004, DOMAIN_MODEL BR-USR-04 and REQUIREMENTS REQ-ACC-12. How the single active Owner is stored is designed in RF-3B. |
| D-049 | Sign-in profile (question 6): email and password, the email not verified and never sent; no Google sign-in; Owner-issued one-time links for a staff member's first password and reset; Owner recovery by operator command; password profile AICWDF-COMPAT-8; no two-factor authentication; sign-in sessions per D-013 and D-039. FD-03 and FD-04 approved. | Terms in section 6. Resolves R-02. Applied in SECURITY_MODEL section 2, ADR-004, FRAMEWORK_DEVIATIONS, COST_POLICY, DOMAIN_MODEL BR-USR-03 and BR-USR-11, and REQUIREMENTS REQ-ACC-05 and REQ-ACC-11. |
| D-050 | English in the interface (question 5): translation keys from the first Task; V1 Indonesian only, with no language switcher; English later as a separate scope decision. FD-02 approved. | Terms in section 6. Resolves R-13 and settles the open localization point of D-020. Applied in ADR-005, STACK_AND_MODULES, FRAMEWORK_DEVIATIONS and REQUIREMENTS REQ-NFR-01. |
| D-051 | Misassigned manufacturer barcode (question 26): the Owner may move an active manufacturer barcode from the wrong product to the correct one, audited, with a reason and recent authentication; internal `BK-P-…` codes are never reused. | Terms in section 6. Resolves R-17 together with D-052. Applied in CATALOG_BARCODE BR-CAT-19, BR-CAT-22 and BR-CAT-50, DOMAIN_MODEL BR-USR-07 and the Barcode lifecycle, SECURITY_MODEL CAP-07 and REQUIREMENTS REQ-CAT-05 and REQ-CAT-13. Storage in RF-3B. |
| D-052 | Re-attaching a retired manufacturer barcode (question 31, follow-up of 26): retired manufacturer barcodes are not generally reusable; re-attachment happens only as the Owner's verified misassignment correction (traceable prior mapping, mandatory reason, immutable history and audit, recent authentication, at most one active mapping); manufacturer barcodes retired for another reason stay unusable; `BK-P-…` codes are never reused; recording a retirement reason is the enforcement mechanism; reason labels are P7 copy. | Terms in section 6. Applied in CATALOG_BARCODE BR-CAT-19, BR-CAT-51 and BR-CAT-52, DOMAIN_MODEL (Barcode states and lifecycle), SECURITY_MODEL CAP-06 and CAP-07, GLOSSARY and REQUIREMENTS REQ-CAT-13. Storage of mappings, history and retirement reason in RF-3B. |
| D-053 | Hosting topology (question 7): Cloudflare, VPS, Coolify; staging on the same VPS with separate database, storage and secrets; FD-06 withdrawn; server facts stay UNKNOWN in gate TARGET. | Terms in section 6. Resolves R-12. D-005 stays in force inside this topology. Applied in ADR-002, ADR-003, ARCHITECTURE, FRAMEWORK_DEVIATIONS, COST_POLICY and REQUIREMENTS REQ-OPS-06. |
| D-054 | Uploaded CSV import files (question 30, from T-17 c): the original CSV import file is not kept after the command reaches its final lifecycle outcome; the import result is kept in the audit; temporary handling and bounded cleanup are technical detail. | Terms in section 6. Resolves T-17 c. Applied in CATALOG_BARCODE BR-CAT-40, DOMAIN_MODEL BR-KEEP-07, SECURITY_MODEL section 13, ARCHITECTURE ARC-43 and REQUIREMENTS REQ-CAT-09. |
| D-055 | RF-3A is authorized on baseline `0c4945d001daf76723568f015e69b6f6c43342fb`, the RF-2 checkpoint, which the Owner accepted on 4 October 2026 after the planning agent's review. The authorization records the plan decisions P-1 to P-5 (P-5 with the Owner's clarification) and D-048 to D-054. RF-3B is not authorized. | Governance; no product or domain content of its own. Completes RF-2 (D-041). Authorizes RF-3A ([REFOUNDATION_PLAN](REFOUNDATION_PLAN.md) section 7). Terms in section 6. Scope amended by D-057 (T-18). |
| D-056 | Residual risk of Owner-issued one-time links (OPEN_ITEMS N-06; SECURITY_MODEL THR-21): accepted for V1 under the existing controls. It authorizes no email delivery, one-time code, two-factor authentication, passkey or other authentication channel. | Terms in section 6. Resolves N-06. Recorded in section 5 and in SECURITY_MODEL THR-21. |
| D-057 | Second review of the RF-3A report: RF-3A is substantively accepted, subject to one narrow governance remediation before any executor checkpoint instruction; the report's reading of the onboarding freeze for saleable returns is accepted, with no new rule and no question 32; the RF-3A scope is amended so that the stale localization wording of AGENTS.md and PROJECT_RULES is corrected (T-18). RF-3B is not authorized. | Governance. Amends the scope of D-055. Resolves T-18. Terms in section 6. |
| D-058 | Acceptance of the RF-3A checkpoint, commit `ee67b3aac58f24198fd8f617410e824bc7ca1119`, on 5 October 2026 after the planning agent's review of the executor's report; the Owner's final second review of the amended RF-3A report was PASS; a process note on D-031. | Governance. RF-3A (D-055, D-057) is complete: P5 and P6 are complete, and P4 in its architecture part. Terms in section 6. |
| D-059 | RF-3B is authorized on baseline `ee67b3aac58f24198fd8f617410e824bc7ca1119`, the accepted RF-3A checkpoint, with the RF-3B pre-authorization package (revision 2) and the Owner's recorded answers as the controlling scope. Plan decisions P-6 to P-9 are approved. RF-4 is not authorized. | Governance; no product or domain content of its own. Authorizes RF-3B ([REFOUNDATION_PLAN](REFOUNDATION_PLAN.md) section 8). Terms in section 6. |
| D-060 | Issue-cost allocation at commit (question 33): the allocation recorded when a Sale commits — and likewise for MANUAL_ISSUE and negative ADJUSTMENT — is an immutable original fact, which may be UNKNOWN; the result of a correction replay is stored separately as a derived projection that each replay replaces; Owner reports use the projection. | Terms in section 6. Resolves T-16 (a). Applied in ADR-008, SCHEMA section 5.8, COSTING (BR-COST-14, BR-COST-16, section 10), DOMAIN_MODEL section 11.2, ARCHITECTURE ARC-21 and ARC-24, and TECHNICAL_RULES RACE-11. |
| D-061 | Cost restored by a saleable return after a later correction (question 34): the return references a link to the original issue allocation; a correction replay recomputes the linked issue and return projections together; the cost is restored exactly once; the value at return commit stays as a fact. Within D-034 and D-035. | Terms in section 6. Resolves T-16 (b); with ADR-008, T-16 (c). Applied in ADR-008, SCHEMA sections 5.8 and 5.9, COSTING (BR-COST-17, section 10), SALES_CASH BR-RFD-11 and TECHNICAL_RULES RACE-10 and RACE-11. |
| D-062 | Sale and cash session numbering (question 32): unique per installation, generated by the server inside the committing transaction, monotonically allocated, gaps permitted, with a readable Asia/Jakarta business-date part; "monotonically increasing" means allocation order, not commit order. | Terms in section 6. Applied in SCHEMA section 6, TECHNICAL_RULES TR-TX-10, SALES_CASH BR-DOC-01 and BR-CASH-04, and PRODUCT_BLUEPRINT section 14. |
| D-063 | Second review of the RF-3B report: PASS at working-tree level, with clarifications. Accepted interpretation of D-034 for the "reporting incomplete" mark: a cost correction that cannot obtain the product guard before its replay starts is `SERVICE_BUSY`, not a rejection, writes no mark and is repeated with the same command identity; a refused reversal writes no mark; a correction refused because the product is reconciliation-blocked writes no further mark. Cost corrections and reversals stay refused while a product is reconciliation-blocked, until the Owner reopens it. The installation-wide sequence is the monotonic authority for numbers; the date part is not a chronological sort key. Question 35 is deferred to before RF-5. The reviewer's SQL in a disposable sandbox is accepted for this run only. | Terms in section 6. Applied in COSTING BR-COST-27, TECHNICAL_RULES TR-TIME-01 and TR-TX-10, SCHEMA sections 5.8, 5.9, 6 and guard G-11, and ADR-008 section 4.3. RF-4 is not authorized. |

## 4. Facts supplied by the Owner

| ID | Date | Fact | Consequence |
| --- | --- | --- | --- |
| OF-01 | 3 October 2026 | The GitHub repository `yusufarst/bantukasir` is public. | Anything pushed is published. See open item N-01. |
| OF-02 | 4 October 2026 | The first client has confirmed that its cashiers will use both a PC and a tablet in production. | Recorded in D-039. The devices themselves are not yet verified (gate DEVICES). |

## 5. Accepted risks

| Risk | Accepted by | Reference |
| --- | --- | --- |
| The RF-1 safety bundle is on the same physical drive as the repository. | Owner | D-027 |
| Staging runs on the same server as production and shares its resources and machine risk. | Owner | D-053 |
| If the single Owner is locked out, only a person with technical access to the server can restore access, through the documented operator procedure. | Owner | D-048, D-049 |
| The Owner account, like every account, has no second authentication factor in V1. | Owner | D-049 |
| Whoever holds a staff member's one-time link, including the Owner who issued it, can set that person's password and act under their identity until the staff member notices. Controls: single use, 60-minute expiry, shown once and only to its issuer, revocable, issuance and use audited, staff handover guidance and reporting of a password they did not set. | Owner | D-056 |

## 6. Terms of D-031 to D-063

The Owner gave these decisions with detailed limits. The limits below are part of each decision.

### D-031 — Agent model, effort and handoff convention

- Opus 5.5 is the default model for both the planning agent (Claude Project Chat) and the executor (Claude Code or Claude Desktop).
- Effort is chosen for each task: High for bounded work of moderate complexity; XHigh for complex cross-document planning, security-sensitive or architectural work, substantial repository changes, difficult reviews, migration-sensitive or repository-state-sensitive work, or execution that needs careful multi-step validation; Max for unusually difficult, high-risk, highly coupled or failure-sensitive work. XHigh and Max are not chosen mechanically.
- Fable 5.1 is not a default. It is used only when Opus 5.5 cannot resolve a task reliably, or when an exceptionally difficult independent review is genuinely justified. Importance or repository-wide scope alone is not a reason.
- The planning agent plans, reviews and writes one bounded executor instruction at a time. The executor carries out only the instruction it was given, validates, commits locally when instructed, reports and stops. It never invents the next task or authorizes itself.
- Every planning-to-executor and executor-to-planning handoff states Agent, Model, Effort, Session (the same session or a new chat), Reason and Escalation. Each executor instruction requires the executor to end its report with that block, including when it stops on a stop condition. The block never authorizes the next task.
- Prompts and instructions to agents are in English. Communication with the Owner is in Bahasa Indonesia, except exact text such as code, commands, commit messages and file contents.
- This convention changes no repository safety rule and authorizes no step.

### D-032 — Roles per person

- One account may hold CASHIER and OPERATIONS_ADMIN together. SUPER_ADMIN (the Owner) stays a separate role and is not combined with another.
- The system keeps exactly the three fixed roles. There are no custom or dynamic roles.
- A combined account has the union of the CASHIER and OPERATIONS_ADMIN permissions.
- No Owner-only right is ever reachable through the combination, including cost of goods sold and profit, sensitive corrections, refunds, Owner-only stock adjustments, user management and every other right restricted to the Owner.
- Sales always use the CashSession owned by, and the responsibility of, that user.
- Every activity is audited against the one identity of that user.
- Server-side authorization and tests must prove that a combined account never gains an Owner right by accident.

### D-033 — Purchase cost and the Operations role

- OPERATIONS_ADMIN may see the purchase cost on every receiving document that the OPERATIONS_ADMIN role can access, not only the documents that person created.
- OPERATIONS_ADMIN may enter and correct the cost while the receiving document is still being entered or reviewed, before confirmation.
- After a receiving is confirmed and posted, OPERATIONS_ADMIN may not change its cost. A cost correction after posting is for the Owner only, under D-034.
- OPERATIONS_ADMIN never sees cost of goods sold or moving weighted average cost, margin, profit, aggregate inventory value, cost reports or profitability reports.
- CASHIER has no access to purchase cost. On a combined account (D-032), cost is available only through the operational functions and screens entitled to it; point-of-sale screens never expose cost data.
- When the cost is not available at receiving, the status UNKNOWN ("Biaya perlu dilengkapi pemilik") may be used. UNKNOWN is never treated as zero.
- Every relevant entry, confirmation and correction is audited against the user's identity.

### D-034 — Correcting a cost later

- Only the Owner may correct a cost after posting. The correction is append-only and audited.
- Original receiving and other source facts, original cost evidence, correction events, Sale facts, StockMovement facts, refunds and returns, actors, timestamps, reasons and audit history are immutable and retained.
- Every correction is a new fact that records at least the cost source corrected, the old value, the new value, the actor, the time and the reason.
- The system runs a bounded deterministic moving-weighted-average replay from the affected cost source to the current state of that product, and publishes the corrected current derived valuation, cost of goods sold and profitability projection atomically: all new results appear at once, or nothing changes.
- V1 does not need to preserve every earlier derived valuation or report projection merely so that an old profit report can be reproduced exactly as it appeared before the correction. This is never permission to erase an original business fact or any correction history.
- If the replay cannot be done safely, or an unresolved conflict or concurrency issue, a timeout or the set capacity limit occurs, the correction is rejected with no partial change, and the affected reporting stays marked incomplete until a reviewed recovery procedure exists.
- The latest purchase price or any other shortcut never replaces the replay. When an UNKNOWN cost is completed later, the same rules apply; UNKNOWN is never treated as zero.
- A refund without a return does not reverse cost of goods sold and does not add stock. A saleable return restores the cost tied to the original sale issue once only. If the source cost is corrected later, the replay updates the related valuation consistently, without creating a new return, a new movement or a double cost recovery.
- CashSession reports and receipt snapshots never change, because they hold no private cost or profit.
- Owner reports show the corrected figures based on the latest cost evidence. The audit shows who changed which cost, from which value to which value, when and why.

### D-035 — Refunds and returns

- Only the Owner (SUPER_ADMIN) executes refunds and returns. CASHIER, OPERATIONS_ADMIN and combined accounts never have that right.
- For a cash refund the Owner chooses the source at execution: the drawer of a CashSession that is OPEN, or the Owner's own funds. A BANK_TRANSFER refund does not affect drawer cash.
- A drawer-funded refund is linked to exactly one CashSession that is OPEN when the payout is made. That session may differ from the session of the original Sale. The refund is a correction fact at refund time and never rewrites an earlier CashSession or session report.
- When several drawers or sessions are OPEN, the Owner selects explicitly the drawer or session the money actually came from.
- A drawer-funded refund reduces that session's expected cash: expectedCash = openingCash + applied CASH Sale payments − drawer-funded CASH refunds. It appears separately in the session report with the Sale and refund reference, amount, time and the Owner as actor, so that the cashier is not held responsible for the difference.
- A drawer-funded refund is a session activity. It follows the CashSession guards and concurrency rules and increments activityVersion. If it happens after the close preview or count began, a close with the old activityVersion is rejected and the cash must be counted again.
- A CLOSED CashSession accepts no new refund payout, and a closed session report is never rewritten. An Owner-funded refund changes no session's expected cash.
- There is no general paid-in, paid-out, cash pickup or expense-from-drawer function in V1. The only drawer outflow is a valid CASH refund linked to the Refund and the original Sale.
- Refund payout, any saleable return movement, any historical-cost recovery, the CashSession cash effect when applicable, audit and the durable command result are committed atomically; no partial state is published.
- The refund-money bound and the return-quantity bound stay separate. A refund without returned goods adds no stock. A saleable return restores the historical issue cost once (D-034). A damaged or non-saleable return adds no saleable stock.
- Sale, Payment, receipt snapshot and closed CashSession report stay immutable. A mandatory reason, recent authentication, audit actor and time, and an idempotent durable result stay required.
- A drawer-funded refund is not counted twice: the same refund fact feeds net sales and refund reporting and the cash-session reconciliation in their own domains, never as two commercial refunds.

### D-036 — Payment and price at the till

- V1 payment methods are CASH, BANK_TRANSFER and STATIC_QRIS. Each Sale has exactly one full payment method. There is no split, partial, deposit or credit payment.
- STATIC_QRIS is static, with manual confirmation only: no payment gateway, provider API, webhook, callback, status polling or pending-payment state. Adding it authorizes no dynamic QRIS or gateway integration.
- The amount recorded equals the full Sale total. The cashier explicitly confirms that the funds were received before the Sale is posted, based on reasonable merchant evidence (merchant app, soundbox or available account statement), not only on a screenshot or the customer's phone. A reference is optional when the merchant practice provides one.
- STATIC_QRIS does not add to a CashSession's expected physical cash. Session and Owner reports show CASH, BANK_TRANSFER and STATIC_QRIS separately. The receipt shows the QRIS method explicitly.
- Provider fees, MDR, settlement delay and net bank settlement are not modelled in V1. The Sale is recorded at the gross amount paid by the customer.
- There is no discount engine, no cashier price override and no per-line or per-Sale negotiated price at the point of sale. The Sale price is the authoritative current selling price in the product master data.
- Prices change only through the master data or catalogue functions, by an authorized role, durably and audited. On a combined account, that right comes from the OPERATIONS_ADMIN functions, not from the point of sale. A master price change is never a hidden per-transaction override.
- If the authoritative price changes while a cart is open, checkout is stale and needs an explicit review or refresh before confirmation; the total never changes silently. SaleLine and ReceiptSnapshot store the price that applied to that Sale.

### D-037 — Catalogue

Product onboarding — CSV import:

- CSV import creates new Product master data only. It never creates opening stock, StockMovement, acquisition cost, cost of goods sold or any financial fact. Opening stock and cost go through the separate, reviewed and audited opening-stock workflow.
- There is no silent upsert or overwrite of an existing Product, no partial success and no silently skipped row. The import has a preview and validation before confirmation, and a confirmed file is processed all-or-nothing.
- The header and schema are explicit and strict. Barcode and SKU are text, so leading zeros are kept. Duplicate SKU or barcode and conflicts with existing data are detected before commit. Unknown or unexpected columns are never silently accepted. File size and row count are explicitly limited.
- The import is idempotent and durable, so a retry or lost response never creates duplicate products. Authorization is enforced on the server; only roles entitled to manage the catalogue may import.
- It is not a general spreadsheet, XLSX or enterprise import engine. XLSX stays outside V1 unless decided separately. The import is never extended to stock or cost.

Product photos:

- V1 supports one optional primary photo per product. A product without a photo stays valid and shows a simple, consistent placeholder.
- The cashier sees a thumbnail in search results and product lists at the till, and may see it on detail or in the cart where relevant. The photo is never the only product identity; name, barcode or SKU and text information stay clear.
- Only roles entitled to manage the catalogue upload or change photos; the cashier only views them.
- File formats are limited to safe types such as JPG, PNG or WebP, with validation of type, file size and dimensions. The system may store and serve thumbnails for the till.
- There is no multi-photo gallery, complex crop editor, camera-capture workflow, background removal or bulk image import in V1. Photo support is not a digital asset management system and changes no stock, costing, pricing or catalogue audit rule.

Open facts (not decided): the number of products at go-live, and whether the initial list already exists in Excel, Google Sheets or CSV (gate G2).

### D-038 — One shop or many

- BantuKasir is a product and codebase that can be installed separately for several shops or clients. V1 stays single-business and single-stock-pool per installation.
- It is not multi-tenant: no tenant table, tenant switching, shared multi-client database, cross-business administration or per-query tenant isolation engine in V1. One installation serves one business; another client uses a separate installation with its own database, storage and configuration.
- The codebase is client-neutral and contains no hard-coded client identity as a product default. BantuKasir stays the product and internal codebase name; the shop's identity comes from BusinessProfile and configuration.
- Shop name, logo, address, contact, receipt and document header and footer, and a constrained accent come from the runtime BusinessProfile. Historical receipts and documents keep the identity revision in force when they were created; later branding changes never alter them. A logo file referenced by a historical document is never overwritten in place. Only the configuration and revision mechanism needed for one business per installation is built.
- LATANSA is never a default identity or hard-coded brand. If LATANSA is one of the installations, its identity is configured through BusinessProfile like any other client. Whether LATANSA is the first installation does not change this architecture. The past presence of the LATANSA mark in the repository is not authorization to use that brand for any other client.
- The LATANSA mark `assets/brand/source/latansa-official-colored-mark.png` leaves the current tree in the next authorized repository change, and `assets/brand/README.md` is updated at the same time. Git history is not rewritten; the historical copy, including any copy already published, is not erased. Whether the repository stays public is open item N-01.

### D-039 — The counter

- PC and tablet are both primary, supported production cashier devices in V1 (OF-02). The phone is not a primary cashier device.
- The PC stays optimized for a physical keyboard and USB HID barcode scanners.
- The tablet supports the full primary cashier workflow: product search and list, product photo thumbnails, compatible scanner input, cart, payment, receipt, CashSession open and close, refund-related visibility where authorized, and the recovery, loading, error, stale and uncertain-result states. It is not a fallback or emergency mode. Layouts may differ, but the primary cashier capabilities stay functionally equivalent, and touch targets must be usable in production.
- Bluetooth HID scanners are supported on the tablet under the existing scanner contract. Tablets are not assumed to support USB scanners the same way. Camera barcode scanning stays outside V1 unless separately authorized.
- Receipt printing uses browser-print templates for thermal 58 mm, thermal 80 mm and A4.
- V1 does not calculate tax and does not claim equivalence with a tax invoice.
- Sign-in sessions end after 30 minutes of inactivity and after 12 hours at most. Signing out never closes, transfers or otherwise changes a CashSession.
- The cash-drawer and opening-float rules in force stay (D-009 as amended by D-035).
- This decision approves FD-05 as a product and design decision. It is not device acceptance: real PC, tablet, scanner, printer and browser compatibility must still pass the DEVICES gate with the production-intended devices.
- Facts of the first store still unknown: whether one device is shared by several staff, the receipt printer and paper width, the number of drawers, the usual opening float, and the PKP/PPN status and tax-document needs (gates G1, G3, DEVICES).

### D-040 — Confirming a sale

- An ordinary Sale uses one payment screen, not a separate mandatory review dialog and checkbox. The screen shows the Sale total, the selected method, for CASH the tendered amount and the calculated change, and for BANK_TRANSFER and STATIC_QRIS the explicit confirmation that the funds were received. Exactly one final action posts the Sale.
- CASH: the final action is enabled only when the tendered amount is valid and at least the Sale total. An exact-cash action ("Uang Pas") may be provided. The change is shown before confirmation. No checkbox is required.
- BANK_TRANSFER and STATIC_QRIS: no routine checkbox. The final button itself states the confirmation in clear Indonesian, for example "Dana sudah diterima — Selesaikan"; activating it is the cashier's explicit confirmation that the funds were received. No gateway verification is claimed.
- Server-side integrity is not weakened. The final action freezes the authoritative source intent, payment payload, product and price state and command identity. The Sale commits atomically with its Payment, stock issue, cost and valuation facts, ReceiptSnapshot, audit and durable result.
- A double click, double tap or repeated submission never creates a duplicate Sale or Payment. While the request is submitting, edits and repeated posting are prevented. If the outcome is uncertain, the original intent is frozen and the same transaction identity is recovered, queried or retried; a replacement Sale is never created because a response was lost.
- A definite rejection (stale price, insufficient stock, invalid or closed CashSession) returns the user to an explicit review or correction state without silently changing the payable total.
- Scanner Enter never triggers the final action, and the control is never focused or wired so that a scanner terminator can submit the Sale.
- On the tablet the final action has an adequate touch target and is placed away from ordinary cart interactions. On the PC keyboard and scanner efficiency stays high. The final action shows distinct enabled, submitting, committed, rejected and uncertain states. After a successful commit the committed receipt or result is shown, with no further confirmation step.
- This applies to ordinary Sale checkout only. Corrections, Owner refunds and returns, receiving, opening stock and CashSession close keep their review and confirmation steps. It is not a rule that every business mutation becomes one action.

### D-041 — RF-2 authorization

Scope:

- RF-2 is one documentation checkpoint on baseline `4af59a5423298d25d672923279439951fc67ea4d`.
- It creates exactly eight CURRENT documents: `docs/01-product/PRODUCT_BLUEPRINT.md`, `docs/01-product/REQUIREMENTS.md`, `docs/02-domain/GLOSSARY.md`, `docs/02-domain/DOMAIN_MODEL.md`, `docs/02-domain/CATALOG_BARCODE.md`, `docs/02-domain/INVENTORY.md`, `docs/02-domain/COSTING.md` and `docs/02-domain/SALES_CASH.md`.
- It changes only `docs/00-governance/SOURCE_OF_TRUTH.md`, `docs/00-governance/LEGACY_INDEX.md`, `docs/00-governance/OPEN_ITEMS.md`, `docs/00-governance/DECISIONS.md`, `docs/00-governance/REFOUNDATION_PLAN.md`, `docs/handoff/CURRENT_HANDOFF.md` and the root `README.md`.
- Superseded legacy documents are not deleted in RF-2. They stay byte-identical and their status is set through SOURCE_OF_TRUTH and LEGACY_INDEX.
- The deferred root README wording (T-13), the note that D-023 is superseded (question 4) and the handoff "Next step" wording are cleaned up only because those files are already changed by RF-2. This is not a separate remediation.

Boundary. RF-2 is documentation consolidation only. It does not write application code, design database schemas or migrations, start RF-3, redesign authentication, design page flows or interface layouts, create the P7 replacement, rewrite the test suite, repartition Build Units or Tasks, deploy or operate production, push, merge, rebase or rewrite Git history, update the safety bundle, or decide any unresolved Owner or client fact by assumption.

D-031 workflow clarification:

- Opus 5.5 remains the default for planning and execution.
- Effort may be High, XHigh or Max according to the actual complexity and risk.
- Fable 5.1 is escalation only: when Opus 5.5 cannot resolve the task reliably, or when an exceptionally difficult independent review is genuinely justified. Escalation is not restricted to cases where the Owner explicitly asks for it, and it is never chosen silently for convenience.
- RF-2 planning: Planning agent, Opus 5.5, effort XHigh, new chat. The fresh-session adversarial review of COSTING, INVENTORY and SALES_CASH may use Opus 5.5 at effort Max if the actual complexity justifies it.

T-16. T-16 stays an unresolved design issue. RF-2 may document the decided invariants: immutable original Sale and StockMovement facts; append-only cost correction evidence; bounded deterministic moving-weighted-average replay; atomic publication; UNKNOWN is never zero; a saleable return restores the original issue-linked cost exactly once; corrected current reporting reflects the latest valid cost evidence. RF-2 must not decide whether the issue-cost allocation captured at Sale commit is an immutable original fact or a replaceable, recomputable derived value, how an at-commit value and a corrected derived value are represented, or the schema representation. If resolving either of the first two becomes necessary, the planning agent stops and asks the Owner one question at a time.

Unknown facts. The go-live product count, whether an initial spreadsheet exists, shared-device practice, drawer count, opening float, actual printer and paper width, PKP/PPN status, whether LATANSA is the first installation, questions 5, 6, 7, 18 and 20, items N-01, N-03, N-04 and N-05, and T-14, T-15 and T-16 stay UNKNOWN or OPEN. If lifecycle or retention work needs a business decision — hard deletion of unused master data, anonymization of former staff, retention of business records, or any other unresolved business meaning — the planning agent stops and asks the Owner one question at a time instead of choosing a policy.

Validation before any executor handoff:

- every applicable decision D-001 to D-041 is traceable to at least one CURRENT rule or requirement, or classified as non-product/domain;
- every CORE-001 to CORE-073 maps to a CURRENT requirement or is retired with a written reason; no capability disappears silently;
- lifecycle matrices hold an explicit value in every cell, with OPEN or UNRESOLVED and the item where a decision is pending; no policy is invented to fill a cell;
- the glossary keeps one concept to one canonical English term plus the provisional Indonesian shop term;
- money and quantity invariants stay exact: whole rupiah, half-up rounding once per line, six-decimal cost, no floating point, UNKNOWN is not zero;
- superseded legacy files stay byte-identical; no secret or private client data is introduced; link integrity passes, with the document count re-derived;
- COSTING, INVENTORY and SALES_CASH receive an independent adversarial review before the executor instruction is prepared.

Safety note. The accepted baseline and the two RF-1 commits before it (`3c94f4f`, `77f6676`, `4af59a5`) exist only in the local repository and are not in the pre-RF-1 safety bundle. The risk stays visible in the RF-2 handoff and report. This is not an authorization to push or to refresh the bundle.

Workflow: planning in a fresh chat; reading the canonical governance and the applicable legacy product and domain sources; writing the RF-2 working tree without staging or committing; one Owner question at a time at a stop condition; self-review and the separate adversarial review; a concise Bahasa Indonesia summary for the Owner; then exactly one deterministic English executor instruction for one local RF-2 checkpoint commit, which the planning agent does not execute itself. RF-3 remains not authorized.

### D-042 — Lifecycle of master data and configuration

- There is no deletion of master data or configuration in V1, neither soft nor permanent.
- Categories and products are deactivated and reactivated by the Owner or OPERATIONS_ADMIN; cash registers and users by the Owner.
- The file of a replaced or removed product photo is kept: it is no longer shown and stays in backups.
- BusinessProfile revisions are never deleted.
- Consequences accepted with the choice: data-entry mistakes are handled by deactivation; inactive records accumulate behind filters; photo backups are slightly larger.

### D-043 — Personal data of former staff

- Anonymization is for the Owner only, and only after the user account has been deactivated.
- It is not a hard delete. The stable internal user identity and every audit relationship remain.
- Personal and profile fields may be replaced with a neutral, non-personal label where applicable. Which sign-in identifier fields are affected is not decided here; it follows question 6.
- Historical business records stay immutable. Sales, ReceiptSnapshots, cash session reports, refunds, audit events and other records keep the actor identity and display information that applied when they were created, where the existing decisions require historical reproduction. Anonymizing the current user profile never rewrites them.
- The anonymization needs a reason and is audited permanently with the Owner as actor and its time.
- An anonymized account stays deactivated and is never reused for another person. A former staff member who is employed again gets a new user account; the anonymized identity is never reconnected silently.
- This is product lifecycle policy only. It is not a claim of full legal compliance and not a substitute for legal advice about personal-data deletion requests.

### D-044 — Retention of business records

- Business books, records, documents and electronic data that form the basis of bookkeeping or of business and tax records are retained for at least 10 years.
- The 10-year period is a minimum retention rule, not an automatic deletion deadline. There is no universal "delete after 10 years" rule.
- V1 has no automatic expiry, purge, archival deletion or scheduled deletion of business records.
- Immutable transactional and audit facts never become deletable merely because 10 years have passed.
- After the minimum period, any future archival or deletion capability needs a separate Owner decision, legal and compliance revalidation, an explicit data classification, and safeguards for historical references, audit, reconciliation and document reproduction.
- Data classes not covered by the bookkeeping rule may have their own lifecycle and retention rules; none is decided in RF-2.
- This product rule is not a claim that BantuKasir by itself guarantees legal or tax compliance.

### D-045 — Drawer-funded refund and expected cash

- A drawer-funded CASH refund is allowed only when the refund amount is at most the current expected cash of the selected OPEN cash session. Equality is valid: expected cash may become exactly Rp0. Expected cash never becomes negative as the result of a refund.
- There is no Owner override that lets a selected drawer go below zero.
- If the selected drawer's expected cash is not sufficient, that funding source is rejected before commit. The Owner may then explicitly choose another eligible OPEN cash session whose expected cash is sufficient, or the Owner's own funds.
- Drawers and funding sources are never switched automatically. One refund is never split silently across several drawers, or between a drawer and the Owner's funds: a cash refund has one explicitly selected funding source unless a separate future decision introduces split payout.
- No general cash top-up, paid-in, cash adjustment or negative-drawer mechanism is created as a workaround.
- The guard rests on the system's expected-cash record; it does not claim that the physical cash has been counted.
- D-035 stays in force: a drawer-funded refund is linked to exactly one OPEN cash session, reduces its expected cash, increments its activity version, is shown separately in its session report and never rewrites a CLOSED session.

### D-046 — Timing of the drawer payout for a drawer-funded refund

- For a drawer-funded refund, cash is taken from the drawer only after the system has committed the refund.
- Order: the Owner selects the drawer; the system checks the D-045 bound and commits the refund; only then is the cash taken from the drawer and handed over. A D-045 refusal therefore happens before any cash leaves the drawer.
- If the outcome is uncertain, the Owner first checks the status of the original refund; no cash is taken until the refund is recorded.
- Consequences accepted with the choice: the drawer always matches the records; a D-045 refusal comes before any cash leaves; the cashier never carries the difference; a session close cannot precede a refund whose cash has already left the drawer.
- Owner-funded refunds and BANK_TRANSFER refunds are not affected; both still record the payout actually made.
- Raised by the independent adversarial review of RF-2 (finding F-06).

### D-047 — What OPERATIONS_ADMIN sees after an Owner cost correction

- After the Owner corrects the cost of a confirmed receiving document, OPERATIONS_ADMIN sees on that document the current cost after the correction, the mark "Dikoreksi Pemilik" and the cost recorded at confirmation. Example: 10 pcs confirmed at Rp5.000 per piece and corrected by the Owner to Rp5.500: OPERATIONS_ADMIN sees Rp5.500, the mark and Rp5.000.
- The reason for the correction stays Owner-only. Cost of goods sold, margin, profit and inventory value stay hidden from OPERATIONS_ADMIN (D-033).
- The question covered both correcting and completing a cost after confirmation; completing an UNKNOWN cost follows the same rules (D-034).
- Who may correct does not change: after confirmation only the Owner corrects cost (D-033, D-034).
- Consequences accepted with the choice: it matches the wording of D-033 (purchase cost on receiving documents); the document always shows the cost in force, so the next supplier invoice can be matched correctly; no cost-of-goods-sold or profit data is exposed.
- Raised by the independent adversarial review of RF-2 (finding F-05). Interface labels are provisional until gate P7-G2.

### D-048 — Number of Owner accounts

- There is exactly one Owner (SUPER_ADMIN) account per installation. Because accounts are never deleted (D-042), this means exactly one **active** SUPER_ADMIN account at any time.
- One person is accountable for every Owner-only action, and the audit trail stays simple.
- If the Owner forgets the password or is locked out, access is restored through a documented operator procedure on the server, which needs a person with technical access to the server.
- Replacing the person who is the Owner, for example when the shop changes hands, is also a documented operator procedure. The former Owner account is deactivated, never deleted, and may then be anonymized by the new Owner (D-043).
- No function of the product creates a second Owner account or assigns SUPER_ADMIN, and the Owner cannot deactivate or anonymize the only active Owner account through the product.
- A second Owner, if one is ever needed, is a small additive change that needs its own Owner decision; going from several Owners back to one would be much harder.
- The operator procedures are designed in RF-3A (SECURITY_MODEL section 14), written as runbooks and rehearsed before the pilot (RF-6). Who may carry them out on production follows question 20.
- Option declined: more than one Owner account.

### D-049 — Sign-in profile

The Owner approved this profile:

1. The sign-in identifier is an email address. It is not verified, and the application never sends email.
2. No Google sign-in (FD-03 approved).
3. A staff member's first password: the Owner creates the account and a short-lived one-time link, handed to the staff member directly, not by email.
4. A staff password reset: the Owner creates a one-time reset link (FD-04 approved).
5. The Owner's forgotten password or lockout: an operator command on the server, documented and rehearsed (FD-04).
6. Password profile AICWDF-COMPAT-8: at least 8 characters with uppercase, lowercase, a number and a symbol; paste and password managers allowed; no forced periodic rotation. It is a usability choice, not a claim of NIST compliance, and it replaces the "minimum 12 characters" of the former design candidate.
7. No two-factor authentication, passkey, magic link or one-time code in V1.
8. Sign-in sessions as D-013 and D-039.

- The implementation is Laravel-native authentication (AICWDF §4A.1), a technical choice recorded in ADR-004.
- The expiry of the one-time links, the rate limits, the uniqueness rule of the email identifier and the neutral value used on anonymization are security design in SECURITY_MODEL. How a link is shown for handover (for example copy or a QR code) is interface design for RF-4 and RF-5.
- Consequences accepted with the choice: no email service and no cost; the single Owner's lockout risk is handled by an operator procedure that must be rehearsed before the pilot; the staff email is stored as the sign-in identifier and is replaced on anonymization (D-043).
- Options declined: a username created by the Owner instead of an email; Google sign-in for existing accounts.
- Resolves R-02.

### D-050 — English in the interface

- Every interface text uses translation keys from the first Task; hard-coded user-facing copy is forbidden (AICWDF §4B.4).
- V1 ships Indonesian only, with no language switcher and no second-language translation or testing.
- Adding English later is a scope change that needs its own Owner decision.
- FD-02 is approved as an Owner-approved deviation from AICWDF §4B.2.
- The localization mechanism is chosen in RF-3A from current official documentation (ADR-005). The date and time presentation of D-018 is unchanged. The copy is approved at gate P7-G2.
- Options declined: Indonesian and English both shipped and tested in V1; no localization architecture.
- Resolves R-13.

### D-051 — Moving a misassigned manufacturer barcode

- The Owner, and only the Owner, may move a manufacturer barcode that is active on the wrong product to the correct product.
- The barcode on the wrong product is retired with the reason "misassigned" ("salah pasang"), and the same token is attached to the correct product, as one audited Owner action.
- The mapping history — from which product to which, when, by whom and why — is kept and audited. Recent authentication and a reason are required.
- Earlier Sales stay recorded on the product of that time, because Sale lines reference the product, not the token.
- Internal `BK-P-…` codes are never reused.
- A token is active on at most one product at a time; uniqueness applies to active mappings, and the mapping history is kept. The storage is designed in RF-3B.
- Example given with the question: manufacturer barcode `8991234567890` of "Pulpen Biru 0,5" attached by mistake to "Pulpen Hitam 0,5" is moved to "Pulpen Biru 0,5"; the cashier can scan the original packaging again and history stays intact.
- Options declined: a retired token is never reused (A); OPERATIONS_ADMIN may also move a barcode (C).

### D-052 — Re-attaching a retired manufacturer barcode

The Owner answered question 31 in these words:

> Boleh hanya sebagai koreksi salah-pasang yang terverifikasi.
>
> Barcode pabrik yang sudah dipensiunkan tidak boleh digunakan kembali secara umum. Pengecualian hanya bila Pemilik sedang memperbaiki pemetaan barcode yang terbukti salah sesuai Q26:
>
> - hanya Pemilik;
> - token harus barcode pabrik, bukan kode internal BK-P;
> - harus ada produk asal/pemetaan lama yang dapat ditelusuri;
> - alasan koreksi wajib dicatat;
> - riwayat pemetaan lama tetap dipertahankan dan diaudit;
> - token hanya boleh aktif pada satu produk pada satu waktu;
> - koreksi memindahkan token ke produk yang benar sebagai satu koreksi terkontrol, bukan membuka mekanisme umum "reuse retired barcode";
> - barcode yang dipensiunkan karena alasan lain tetap tidak dapat dipakai kembali;
> - kode internal BK-P tidak pernah didaur ulang.

Terms:

- A retired manufacturer barcode is not reusable in general.
- The only exception is the Owner's controlled correction of a proven misassignment under D-051, also when the token was already retired: only the Owner; the token is a manufacturer barcode, never an internal `BK-P-…` code; the earlier product or mapping is traceable; a correction reason is mandatory; the earlier mapping history is kept and audited; the token is active on at most one product at a time; recent authentication is required. The correction moves the token to the correct product as one controlled correction and opens no general mechanism for reusing retired barcodes.
- A manufacturer barcode retired for any other reason stays unusable.
- Internal `BK-P-…` codes are never recycled.
- Retiring a token records its reason. A retired token is eligible for the correction only when its retirement is recorded as a misassignment and its earlier mapping is traceable. Recording the retirement reason is the enforcement mechanism. The retirement record is history and is not rewritten (BR-AUD-03); the correction adds a new, audited mapping fact.
- The exact list of retirement reasons and their Indonesian labels are interface copy (gate P7-G2); only the distinction between a misassignment and any other reason is a rule.

### D-053 — Hosting topology

- The framework default topology is adopted: the domain through Cloudflare, then a VPS, then Coolify (self-hosted, open source), which manages containers, routing and TLS certificates.
- Staging runs on the same VPS, with a database, storage and secrets separate from production.
- FD-06 is withdrawn: the framework topology is adopted, so no deviation is approved. R-12 is resolved.
- D-005 stays in force inside this topology: PHP-FPM and Nginx in the application container, private PostgreSQL, scheduled commands, no Redis, queue or server-side rendering, and an independent backup is mandatory. The container layout is recorded in ADR-003.
- Decided by plan decision P-2 and not asked: the provider, CPU, memory and disk, other services on the server and whether the domain is already on Cloudflare stay UNKNOWN in gate TARGET until verified.
- Fact given with the question and verified on 4 October 2026 in the official Coolify documentation: Coolify itself needs at least 2 CPU cores, 2 GB of memory and 10 GB of disk, excluding the application, its databases and staging.
- Consequences accepted with the choice: no additional cost beyond the client-funded VPS and domain; automatic TLS and deployment through one interface; a staging environment before production; the VPS must be large enough for Coolify, production and staging together (gate TARGET); staging on the same machine shares its resources and machine risk (section 5).
- Not decided here: the branch and environment model, CI, the Cloudflare and Coolify configuration and the infrastructure runbooks (RF-6, T-10); the backup destination (question 18, before RF-6); who may operate on production (question 20). No account, purchase or installation is made.
- Options declined: hand-built Compose, Nginx and manual TLS (FD-06); staging on a second VPS (an additional cost).

### D-054 — Uploaded CSV import files

- The uploaded CSV file is not kept after the import command reaches its final lifecycle outcome: committed, or refused at confirmation, or cancelled.
- The import result is kept in the audit: the products created, a row summary, the file checksum, the actor and the time.
- Between upload, preview and confirmation (D-037) the file is held only as temporary working data of that command. An upload that is never confirmed is removed by bounded housekeeping. Temporary handling and cleanup are technical details (SECURITY_MODEL section 13, ARCHITECTURE ARC-43).
- The original file is never a retained business record and cannot be downloaded again from the system; the shop keeps its own copy if it wants one. It is outside the bookkeeping rule of D-044.
- The checksum also supports the idempotency of D-037.
- Options declined: kept privately for a period, then deleted automatically; kept like a business record.
- Resolves T-17 c.

### D-055 — RF-3A authorization

The Owner's authorization begins: "I explicitly authorize RF-3A only." Its full wording is held in the Owner's Claude Project; these terms are its record in the repository.

Recorded with it:

- **Acceptance of RF-2.** The Owner accepted the RF-2 checkpoint, commit `0c4945d001daf76723568f015e69b6f6c43342fb`, on 4 October 2026, after the planning agent's review of the executor's report. RF-2 (D-041) is complete. The two non-blocking observations of that review need no remediation; the wording of SOURCE_OF_TRUTH for legacy documents 12 and 17 is harmonized in RF-3A (plan decision P-4).
- **Plan decisions** taken on the planning agent's RF-3 pre-authorization package:
  - P-1: RF-3 is split into RF-3A (architecture, security and technical-rule foundation) and RF-3B (logical schema). RF-3B needs the acceptance of the RF-3A checkpoint and a separate authorization.
  - P-2: question 7 decides only the hosting topology; server facts stay UNKNOWN in gate TARGET until verified.
  - P-3: question 18 becomes a prerequisite of RF-6. RF-3 carries only the decided backup constraints and chooses no destination, provider, cost or real key.
  - P-4: two wording cleanups go into the relevant RF-3 change: "removal in a later authorized step, not before RF-5" for legacy documents 12 and 17 in SOURCE_OF_TRUTH; the open-decision list of PRODUCT_BLUEPRINT section 14 reflects question 7 correctly.
  - P-5, with the Owner's clarification: legacy supersession is set per subject and per checkpoint. In RF-3A, `P4-ARCHITECTURE` and `02-ARCHITECTURE` are not marked fully SUPERSEDED while their schema and database-constraint parts are still the authority; those parts stay LEGACY — IN FORCE until RF-3B, and the final status is settled after RF-3B is accepted. No legacy file is deleted.
- **Decisions D-048 to D-054**, the Owner's answers to questions 25, 6, 5, 26, 31, 7 and 30.

Scope of RF-3A:

- Baseline: `0c4945d001daf76723568f015e69b6f6c43342fb` on `plan/bantukasir-pos-v1`.
- Twelve new CURRENT files: `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/STACK_AND_MODULES.md`, `docs/05-security/SECURITY_MODEL.md`, `docs/06-api-performance/TECHNICAL_RULES.md`, `docs/adr/README.md` and ADR-001 to ADR-007.
- Changes, only in the parts that concern RF-3A, to: SOURCE_OF_TRUTH, LEGACY_INDEX, OPEN_ITEMS, this log, REFOUNDATION_PLAN, FRAMEWORK_DEVIATIONS, COST_POLICY, CURRENT_HANDOFF, the root README, PRODUCT_BLUEPRINT, REQUIREMENTS, DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY and COSTING (not COSTING section 10); GLOSSARY and SALES_CASH only where a statement becomes false.
- Legacy supersession per P-5: `04-AUTH-RBAC-SECURITY` and `P5-SECURITY` SUPERSEDED by SECURITY_MODEL; `P6-CONCURRENCY-API` SUPERSEDED by TECHNICAL_RULES; `02-ARCHITECTURE` and `P4-ARCHITECTURE` marked section by section ([LEGACY_INDEX](LEGACY_INDEX.md) section 8). Nothing under any `legacy/` folder changes.
- For question 31 the approved rule is kept in substance: retired manufacturer barcodes are not generally reusable; re-attachment is only an Owner-controlled, verified misassignment correction with a traceable prior mapping, a mandatory reason, immutable history and audit, recent authentication and at most one active mapping; manufacturer barcodes retired for another reason stay unusable; internal `BK-P-…` codes are never reused. Recording a retirement reason is an acceptable enforcement mechanism; exact Indonesian reason labels stay P7 copy.
- For question 30, temporary handling needed for upload, preview and confirmation, and bounded cleanup, are technical detail. The original CSV must not become a retained business record after the command reaches its final lifecycle outcome.

Boundary. RF-3A must not create or write `docs/04-architecture/SCHEMA.md`; must not create ADR-008; must not decide or implement the representation of cost allocations (T-16, including its part (c)); must not perform the invariant-to-schema work of RF-3B; must not resolve T-17 e, g or i; and must not fully supersede the schema and database-constraint parts of `P4-ARCHITECTURE` or `02-ARCHITECTURE`. No schema or migration, application code, version pin, P7 work, route or interaction contract, test-suite rewrite or Task repartition.

Execution method: planning agent Opus 5.5, effort XHigh, new chat; the RF-3A working tree is written without staging or committing; current official documentation is verified for version-sensitive Laravel, localization and Coolify details; self-review and a fresh independent adversarial review come before any executor instruction; the planning agent stops and asks exactly one Owner question when a new business meaning is required; the full RF-3A report goes to the Owner for a second review, and the executor instruction is prepared only after that review.

Holds that remain: no push, merge, rebase, pull request or change to `main`; no refresh of the safety bundle; no deletion or editing of legacy files; no application code, migrations, deployment, accounts or purchases; no RF-3B, RF-4 or later step; no real IP address, domain, provider identity, credential, backup key or backup location in the public repository.

Safety note. Commits `3c94f4f`, `77f6676`, `4af59a5`, `0c4945d` and the RF-3A checkpoint exist only in the local repository and are not in the pre-RF-1 safety bundle. This is recorded so that the risk stays visible; it is not an authorization to push or to refresh the bundle.

RF-3B is NOT AUTHORIZED. RF-4 and every later step are NOT AUTHORIZED.

### D-056 — Residual risk of Owner-issued one-time links

- Whoever holds a staff member's one-time link — including the Owner who issued it — can set that person's password and act under that person's identity until the staff member notices. The risk is inherent in the sign-in profile of D-049; the independent adversarial review of RF-3A recorded it (SECURITY_MODEL THR-21, OPEN_ITEMS N-06).
- The Owner accepts it as a residual V1 risk under the existing controls: the link is single use; it expires after 60 minutes; it is shown only once, to its issuer; it can be revoked; its issuance and use are audited; staff receive handover guidance and report a password they did not set.
- The threat stays visible in SECURITY_MODEL.
- This acceptance does not authorize email delivery, one-time codes, two-factor authentication, passkeys or any other authentication channel.

### D-057 — Second review of the RF-3A report

The Owner reviewed the RF-3A report a second time on 4 October 2026. The full wording is held in the Owner's Claude Project; these terms are its record in the repository.

- RF-3A is substantively accepted, subject to one narrow governance remediation before any executor checkpoint instruction.
- The report's interpretation of the onboarding freeze is accepted; no new rule restates it and no question 32 is asked. While onboarding is on, ordinary stock postings and Sales are refused (BR-INV-24); a RETURN_RECEIPT that restores saleable stock is an ordinary stock posting and is therefore refused; a monetary refund with no stock posting is not blocked by this rule; OPENING remains the onboarding exception already defined by the inventory rules (BR-INV-11). TECHNICAL_RULES section 7.2 applies this reading.
- The residual risk of Owner-issued one-time links is accepted (D-056).
- Scope amendment of D-055, to resolve OPEN_ITEMS T-18: `AGENTS.md` and `docs/00-governance/PROJECT_RULES.md` may be changed, only in their stale localization wording, to match D-050 — translation keys from the first implementation Task; V1 ships Bahasa Indonesia only; no language switcher in V1; English is a later scope addition that needs its own authorization; hard-coded user-facing copy stays prohibited. No other rule in those files changes.
- After the remediation, the planning agent re-runs the consistency checks and a targeted independent verification and returns the amended RF-3A report for a final second review. The executor checkpoint instruction is prepared only after that review. Nothing is staged or committed in the meantime.
- RF-3B is NOT AUTHORIZED. RF-4 and every later step are NOT AUTHORIZED.

### D-058 — Acceptance of the RF-3A checkpoint

The Owner's statement begins: "I accept the RF-3A checkpoint at commit: ee67b3aac58f24198fd8f617410e824bc7ca1119". Its full wording is held in the Owner's Claude Project; these terms are its record in the repository.

- The RF-3A checkpoint, commit `ee67b3aac58f24198fd8f617410e824bc7ca1119` (parent `0c4945d001daf76723568f015e69b6f6c43342fb`), is accepted on 5 October 2026, after the planning agent's review of the executor's report. RF-3A is accepted and complete at this checkpoint (D-055, D-057; [REFOUNDATION_PLAN](REFOUNDATION_PLAN.md) section 7).
- The Owner's final second review of the amended RF-3A report, on 5 October 2026, was PASS: the working-tree content after the D-056 and D-057 remediation was accepted, and exactly one checkpoint instruction was authorized; the executor carried it out as that commit.
- Process note on D-031: Opus 5.5 is the default planning model and Fable 5.1 is used only for escalation. Future handoffs and reviews use Opus 5.5 unless escalation is materially required and its reason is recorded. The note does not invalidate the accepted checkpoint.
- Model provenance: the Owner stated that the session that reviewed the RF-3A checkpoint and prepared the RF-3B pre-authorization package used Opus 5.5 at effort XHigh; that statement is the recorded provenance. No escalation and no escalation reason are recorded for that session.
- The acceptance was recorded first in the Owner's Claude Project only, at the Owner's instruction, and is carried into the repository in the RF-3B working tree (D-059), so that the file hashes of the RF-3A report stayed valid for its checkpoint.
- With the acceptance, P5 and P6 are complete, and P4 is complete in its architecture part; the logical schema is RF-3B.

### D-059 — RF-3B authorization

The Owner's authorization begins: "I explicitly authorize RF-3B only." Its full wording is held in the Owner's Claude Project; these terms are its record in the repository.

- Baseline: `ee67b3aac58f24198fd8f617410e824bc7ca1119` on `plan/bantukasir-pos-v1`, the accepted RF-3A checkpoint (D-058).
- Controlling scope: the planning agent's RF-3B pre-authorization package, revision 2, and the Owner's recorded answers to questions 33, 34 and 32 (D-060 to D-062).
- Plan decisions taken on the package and approved:
  - P-6: the final supersession status of `02-ARCHITECTURE` and `P4-ARCHITECTURE` is written in the RF-3B checkpoint and takes effect when the Owner accepts it. `02-ARCHITECTURE` becomes LEGACY — SUPERSEDED as a whole. `P4-ARCHITECTURE` becomes LEGACY — SUPERSEDED except §Testing implications and §Deployment, operasi dan maintenance (LEGACY — INPUT ONLY for RF-6) and §Sumber primer yang diverifikasi saat planning (RECORD). No legacy file is deleted. This completes P-5.
  - P-7: PostgreSQL DDL may appear in `docs/04-architecture/SCHEMA.md` only as specification text for constraints; it is not a migration and is never executed.
  - P-8: ADR-008 is limited to the representation of issue-cost allocations, including the rounding residue of T-17 e; there is no ADR-009. Its prerequisite — the Owner's explicit decision of T-16 (a) and (b) before ADR-008 freezes their representation — is met by D-060 and D-061.
  - P-9: RF-3B is written by Opus 5.5 in a fresh session and reviewed adversarially by a fresh Opus 5.5 session; Fable 5.1 only for escalation with a written reason; model provenance is recorded accurately (D-031).
- The Owner accepted items 3 to 6 of section 5.2 of the package — the T-17 e rounding rule, the T-17 g SKU normalization, the T-17 i mark and its clearing, and writing that mark after the rollback — as technical interpretations for RF-3B planning, provided the adversarial review shows that they change no business meaning. Items 1 and 2 (T-16 a and b) were not accepted as silent interpretations and became questions 33 and 34.
- Scope:
  - create `docs/04-architecture/SCHEMA.md` and `docs/adr/ADR-008-cost-allocation-representation.md`;
  - resolve T-03, T-16 (c), T-17 e, g and i, and the CURRENT statements assigned to RF-3B;
  - apply questions 33 and 34 in ADR-008 and the logical schema;
  - map persistent invariants to database constraints, ordered locks, application guards and required tests;
  - finalize the legacy P4 supersession status under P-6;
  - record D-058 to D-062.
- Retention distinction, to be preserved: retained immutable business and audit facts are not hard-deleted or rewritten; lifecycle-governed ephemeral technical data may expire or be cleaned up only where an existing decision permits or requires it.
- Numbering clarification (part of D-062): "monotonically increasing" means allocation or sequence order and does not require strict chronological commit ordering between concurrent transactions; no global cross-cashier serialization lock is introduced merely to make receipt-number order match commit order; gaps after aborted or rolled-back work are valid and are not errors. If an implementation would need stronger ordering semantics, the planning agent stops and asks the Owner.
- T-17 i remains a stop condition if its clearing semantics would introduce new business meaning. If any table, column, constraint or enforcement rule needs business meaning not already decided, the planning agent stops and asks exactly one Owner question.
- Execution method set by the Owner: planning agent Opus 5.5, effort Max, in a fresh session; verify the baseline and current official PostgreSQL and Laravel documentation first; write the working tree only, with no staging or commit; self-review and a fresh independent adversarial review focused on schema constraints, concurrency, the UNKNOWN cost state, issue and return replay, the boundary between immutable facts and projections, and database privileges; run the complete validation plan of the package; return the RF-3B report and the adversarial-review evidence for the Owner's second review. No executor checkpoint instruction is prepared yet.
- Holds: no push, merge, rebase, pull request or change to `main`; no refresh of the safety bundle; no editing or deletion of legacy files; no application code, migration, executed SQL, installation, deployment, production operation, account or purchase; no ADR other than ADR-008; no RF-4 or later work; questions 18, 20 and 29, item N-01 and the other deferred items are not answered; no real IP address, domain, provider identity, credential, key or backup location.
- RF-4 and every later step are NOT AUTHORIZED.

### D-060 — Issue-cost allocation at commit (question 33)

The Owner's choice on the question card: **"A — Fakta asli + proyeksi (Rekomendasi)"** — "Alokasi saat jual disimpan sebagai fakta immutable (bisa UNKNOWN); hasil replay disimpan terpisah sebagai nilai turunan yang diganti tiap replay; laporan memakai nilai turunan. Audit bisa menampilkan biaya saat jual dan biaya setelah koreksi; tidak ada sejarah yang ditulis ulang; dua nilai per alokasi."

- The issue-cost allocation recorded when a Sale commits is an immutable original fact, including its UNKNOWN status when the cost is not yet known. The same applies to the allocations of MANUAL_ISSUE and negative ADJUSTMENT, which reduce inventory value but never enter cost of goods sold.
- The result of a correction replay is stored separately as a derived value — the projection — which each replay replaces and publishes atomically. Owner reports use the projection (D-034: corrected figures based on the latest cost evidence).
- Consequences accepted with the choice: the audit can show, for every line, the cost known at the time of sale and the cost after correction; no historical record is rewritten; each allocation holds two values, so slightly more storage.
- Unchanged: profit reports use the latest cost evidence (D-034); receipts and session reports never change; Sale, StockMovement and correction facts stay immutable; UNKNOWN is never zero.
- Options declined: B — a derived value only, overwritten in place (the per-line history is lost; close to rewriting history); C — every replay version kept (not required by D-034 or BR-COST-29; the most storage and complexity).
- Resolves T-16 (a). The representation is ADR-008.

### D-061 — Cost restored by a saleable return after a later correction (question 34)

The Owner's choice on the question card: **"A — Tertaut, dihitung ulang (Rekomendasi)"** — "Retur merujuk tautan ke alokasi issue asli; saat replay koreksi, issue dan retur yang terhubung dihitung ulang bersama. Pemulihan tetap tepat satu kali, tanpa pemulihan ganda, valuasi konsisten (invarian 6, BR-COST-18). Nilai saat retur tersimpan sebagai fakta. Di dalam D-034/D-035."

- A saleable return references a link to the original issue allocation rather than copying its value.
- When a correction replay runs, the issue allocation and the linked return allocation are recomputed together: the cost tied to the original sale issue is restored exactly once, no cost is recovered twice and the valuation stays consistent (COSTING section 10, invariant 6; BR-COST-18).
- The value recorded when the return committed stays as an immutable fact, consistent with D-060.
- A return of an issue whose cost was UNKNOWN becomes complete through the same replay when the cost is completed (BR-COST-22).
- Worked example given with the question: 10 units issued at Rp100, 2 returned, the cost later corrected to Rp120. The return's recovery is recomputed from Rp200 to Rp240, net cost of goods sold is Rp960 (8 × Rp120) and the two returned units are valued at Rp240.
- Consequence accepted with the choice: the profit of the period in which the return happened is corrected along with the sale, which is the D-034 principle. No existing decision changes; D-034 and D-035 stay in force.
- Options declined: B — the recovery frozen at its value at return time (inconsistent valuation; D-034 and D-035 would need revalidation); C — the return valued at the moving average at return time, detached from the issue (changes the meaning of cost of goods sold).
- Resolves T-16 (b). The representation, the enforcement of "exactly once" and the rounding of several partial returns of one line (T-17 e) are ADR-008.

### D-062 — Sale and cash session numbering (question 32)

The Owner's choice on the question card: **"A — Unik, naik, boleh bercelah (Rekomendasi)"**.

- A Sale number is unique per installation, generated by the server inside the Sale transaction, always increasing, with gaps allowed after a rolled-back transaction, and carries the Asia/Jakarta business date as its readable part (for example `YYYYMMDD-nnnnnn`, such as `20261005-000123`), with no branch prefix.
- Cash session numbers follow the same pattern.
- The document prefix or label stays with the BusinessProfile (BR-CFG-01, document identity).
- Consequences accepted with the choice: two cashiers never wait on one number lock; a cancelled or failed checkout can leave a gap in the sequence; the numbers are still unique and in order.
- Clarification given with the RF-3B authorization (D-059): "monotonically increasing" means allocation or sequence order; it does not require strict chronological commit ordering between concurrent transactions; no global cross-cashier serialization lock is introduced to make receipt-number order match commit order; gaps after aborted or rolled-back work are valid and are not errors.
- Unchanged: every Sale has exactly one number, immutable once committed (BR-DOC-01; legacy L-17 and L-02); V1 documents are not tax invoices (BR-DOC-03); the session report keeps its meaning (BR-CASH-20).
- Options declined: B — a gapless sequence per business day, serializing every checkout on one point; C — a gapless sequence per register.
- How a number is generated is [SCHEMA](../04-architecture/SCHEMA.md) section 6.

### D-063 — Second review of the RF-3B report

The Owner's statement begins: "Final second review result: RF-3B is substantively PASS at the working-tree level, with the following Owner clarifications accepted before checkpoint preparation." Its full wording is held in the Owner's Claude Project; these terms are its record in the repository.

- **Result.** The RF-3B working tree (D-059) passes the Owner's second review, with the clarifications below. The planning agent prepares exactly one checkpoint instruction for the Owner's review; it is not executed until the Owner sends it.
- **The "reporting incomplete" mark (T-17 i) — accepted interpretation of D-034.**
  - A cost correction or completion that cannot obtain the product guard before its replay starts is answered `SERVICE_BUSY`. It is not a completed `REPLAY_REJECTED` decision, writes no mark, and is repeated or resolved with the same command identity (TECHNICAL_RULES TR-RETRY-02; ADR-007).
  - A refused reversal writes no mark.
  - A cost correction refused because the product is already reconciliation-blocked writes no further mark; the reconciliation block remains the controlling incident (BR-INV-45).
  - This settles D-034's "unresolved conflict or concurrency issue" for pre-replay lock contention: the mark follows only a replay that ran and was refused (SCHEMA section 5.8; COSTING BR-COST-27).
- **Reconciliation-blocked products.** Cost corrections and reversals stay refused while a product is reconciliation-blocked. They resume only after the Owner has completed the controlled recovery and reconciliation path and reopened the product (BR-INV-46). No replay is published on top of a projection already found unsafe (SCHEMA guard G-11).
- **Numbering (D-062).** The installation-wide sequence is the monotonic authority. The readable Asia/Jakarta date part is not a global chronological sort key: around midnight, concurrent transactions may produce numbers whose date parts appear out of chronological order. No global cross-cashier serialization lock is introduced (SCHEMA SCH-25).
- **Question 35** — whether repeated occurrences of the same product in one Sale are stored as separate lines or combined — is deferred to before RF-5. RF-3B stays schema-neutral on it and decides no receipt or cart behaviour.
- **Process.** The independent reviewer's execution of SQL against a disposable PostgreSQL sandbox instance — no BantuKasir database or production data touched, no statement of SCHEMA.md executed, no RF-3B conclusion depending on it — is accepted as a non-blocking process deviation for this run only. It does not broaden D-059: future planning and review work executes no SQL unless the Owner explicitly authorizes it.
- No schema semantics change beyond these clarifications, which the working tree already applied. RF-4 and every later step are NOT AUTHORIZED.
