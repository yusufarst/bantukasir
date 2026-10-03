# Decision log

The single log of decisions for BantuKasir. A decision made in conversation does not exist for the project until it is written here.

## 1. How to use this log

- Identifiers are permanent and continue the original numbering. The next free identifier is **D-041**.
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
| D-004 | Planner | Modular monolith on Laravel, Inertia, React with TypeScript, PostgreSQL and Nginx, chosen by comparison with a full-TypeScript alternative. Versions are pinned at the foundation unit. | **IN FORCE.** Identical to the AICWDF-4.3 default stack. To be recorded as an ADR in RF-3. |
| D-005 | Planner | Minimum infrastructure: database-backed sessions and rate limiter; Vite at build time; PHP-FPM, Nginx and private PostgreSQL; scheduled commands. No SSR Node, Redis, queue, Octane or microservices by default. An independent backup is mandatory. | **IN FORCE.** Hosting topology under **REVALIDATION** (R-12). To be recorded as an ADR in RF-3. |
| D-006 | Planner | Integrity: immutable stock ledger with a synchronous non-negative balance; the whole sale is one atomic transaction including snapshot, audit and result; exact decimal cost with explicit UNKNOWN; command identity by source intent, key, fingerprint and recovery epoch; ordered guards and bounded retries. | **IN FORCE.** |
| D-007 | Planner | Authority and privacy: policies, object scope and actor and configuration checks; per-role data allowlists on every output channel. Purchase cost entered by the Operations role is write-only. The Cashier has no master-data management. | Allowlists and server authorization **IN FORCE**. Write-only cost **SUPERSEDED by D-033**. R-03 resolved. |
| D-008 | Planner | Corrections: the original is preserved; the money refund bound and the physical return quantity bound are separate; historical cost is restored once; Owner reason and recent authentication; cost revisions with bounded all-or-nothing replay; unsafe chronology is rejected. | **IN FORCE, AMENDED**: earlier derived valuations by D-034; the source of refund money by D-035. Preserved originals, separate money and quantity bounds, one-time restoration of historical cost, Owner reason and recent authentication, bounded all-or-nothing replay and rejection of unsafe chronology stay. R-04 and R-09 resolved. |
| D-009 | Planner | Cash accountability: one open session per register and per cashier; everyone, including the Owner and for transfer-only sales, sells in their own session; opening cash is explicit, zero allowed; race-safe close with an immutable report. No reopening, no change of responsibility, no shared drawer, no paid-out. | **IN FORCE, AMENDED by D-035**: a drawer-funded CASH refund linked to the original Sale is the one permitted drawer outflow; no general paid-in or paid-out. R-04 resolved. R-14 resolved by D-039. |
| D-010 | Owner (direction), Planner (contract) | Design pipeline: an exact Owner-approved revision in the BantuKasir design project, then repository semantic tokens, then native shadcn/ui with React and Inertia. "BantuKasir" and its logo are a codename; client identity comes from a versioned runtime business profile. The MULTIPLECORP design project must never be touched. Observing a design context is not approval. | Pipeline **ADAPTED by D-024 and D-025**. Runtime client identity **IN FORCE, confirmed and scoped by D-038** (one business per installation). R-10 resolved. MULTIPLECORP prohibition **IN FORCE**. |
| D-011 | Owner | Executor: Claude Opus 5.5, effort xhigh. Fable 5.1, effort xhigh, selectively for bounded high-risk problems. The labels are a request, not a claim of availability; work units must be portable without chat memory. | **SUPERSEDED by D-031** as to models and effort. Portability of work without chat memory stays in force, and a model name remains a request, not a claim of availability (AGENTS.md section 6). |
| D-012 | Planner | Delivery plan of 27 Build Units with hour estimates, 73 Core requirement entries and 39 acceptance suites. | **NOT EXECUTABLE.** To be superseded by the Task baseline in RF-7. The Build Units are input only. |
| D-013 | Planner | Security support: threat-path model; Argon2id; session regeneration and revocation; CSRF; headers and CSP; upload rules; advisory checks. Technical defaults: recent authentication 15 minutes, inactivity 30 minutes, absolute session 12 hours, stated rate limits. | **IN FORCE.** Session timeouts confirmed by D-039 (R-14 resolved). The upload rules also cover CSV product import and product photos (D-037). Password rule to be set by the authentication profile in RF-3 (R-02). |
| D-014 | Planner | Review, fix and validation come before any freeze. P7 must be complete before P8 to P11 are frozen. Client, hardware, backup and release holds stay open. Affected work is reopened when evidence changes. | **IN FORCE** as to review before freeze and the order P7 before P8 to P11. The form of P7 approval is now **D-025**. |
| D-015 | Owner | One local planning commit with an exact, safe path scope; no Build Unit executed; push, deploy, purchase and destruction need separate authorization. | **CONSUMED.** The authorization was used; it grants nothing today. The safety limits are restated in AGENTS.md. |
| D-016 | Planner | Preservation: Plan 2.0 inputs remain in Git and its 40 task cards are archived in the tree; an unrelated untracked patch is preserved; protected sources, assets and evidence are kept byte-identical; no clean-up of unrelated work. | **SUPERSEDED by D-019** as to keeping legacy trees in the working tree. Preservation of the patch and the stash continues under **D-022**. |
| D-017 | Owner | P7 is actual design work in the BantuKasir design project, completed by the Owner's visual approval of one exact revision and a normalized design contract. P8 to P11 are finalized only after P7 is complete. All coding stays unauthorized. The separate design Build Unit is removed. | P7 as real design work and the order P7 before P8 to P11: **IN FORCE.** The single exact-revision approval: **SUPERSEDED by D-025.** The former candidate path: **SUPERSEDED by D-024.** |
| D-018 | Owner | Four notes on the former design candidate: dates with the full Indonesian month name and dotted time (for example `01 Oktober 2026, 09.15.00`); example product photos with fallbacks, without any uploader or storage; an explicit "Barang keluar" menu for the Owner leading to non-sale manual issue; interactive trend-chart points with date and value. Not a visual approval. | Date format and the Owner's "Barang keluar" entry: **IN FORCE** as requirements for the P7 rebuild. Chart behaviour: carried as input to RF-4. Example product photos without an uploader: **SUPERSEDED by D-037** (one optional primary photo per product is a V1 feature). R-08 resolved. |

## 3. Decisions under the re-foundation (D-019 onward)

All by the Owner (Asia/Jakarta). D-019 to D-025 answer the questions of the [re-foundation analysis report](records/REFOUNDATION_ANALYSIS_2026-10-03.md) (3 October 2026). D-026 to D-030 authorize and accept RF-1 (3 October 2026). D-031 to D-040 come from the Owner decision session held after RF-1 (3 and 4 October 2026); their full terms are in section 6.

| ID | Decision | Effect |
| --- | --- | --- |
| D-019 | Full restructure of the repository to the AICWDF-4.3 layout. | Framework deviation FD-01 approved. Supersedes D-001 (framework) and D-016 (legacy trees). AICWDF-4.3 as the operating framework is stated in the Owner's Project instructions. Adopting the framework does not by itself approve a framework rule that changes business, authorization or release meaning; each such change is a revalidation item in [OPEN_ITEMS](OPEN_ITEMS.md) until the Owner decides it. |
| D-020 | Agent-facing repository documents are in English. Indonesian remains the primary interface language. Localization for V1 will be finalized separately. | Resolves R-15. Question 5 and deviation FD-02 stay open by the Owner's choice. |
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

## 4. Facts supplied by the Owner

| ID | Date | Fact | Consequence |
| --- | --- | --- | --- |
| OF-01 | 3 October 2026 | The GitHub repository `yusufarst/bantukasir` is public. | Anything pushed is published. See open item N-01. |
| OF-02 | 4 October 2026 | The first client has confirmed that its cashiers will use both a PC and a tablet in production. | Recorded in D-039. The devices themselves are not yet verified (gate DEVICES). |

## 5. Accepted risks

| Risk | Accepted by | Reference |
| --- | --- | --- |
| The RF-1 safety bundle is on the same physical drive as the repository. | Owner | D-027 |

## 6. Terms of D-031 to D-040

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
