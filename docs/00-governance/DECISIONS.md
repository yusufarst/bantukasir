# Decision log

The single log of decisions for BantuKasir. A decision made in conversation does not exist for the project until it is written here.

## 1. How to use this log

- Identifiers are permanent and continue the original numbering. The next free identifier is **D-029**.
- "Source" says who decided: **Owner**, or **Planner** (a planning agent acting on its own authority under the rules of the time).
- "Status" says whether the decision is in force today. `REVALIDATION PENDING` means the decision still applies but the Owner has been asked to re-confirm it; the item number refers to [OPEN_ITEMS](OPEN_ITEMS.md).
- D-001 to D-018 were recorded on 2 and 3 October 2026 in the former register, now [legacy/10-DECISIONS.md](legacy/10-DECISIONS.md). The table below summarizes them in English. For the exact meaning of D-001 to D-018 the original Indonesian wording governs; for whether a decision is in force, this log governs.
- A new decision that changes an older one names it in the "Status" of the older row. Rows are not deleted or rewritten.

## 2. Decisions carried over (D-001 to D-018)

| ID | Source | Decision (summary) | Status after re-foundation |
| --- | --- | --- | --- |
| D-001 | Owner | Adopt the governance, planning, review, quality, continuity and Build Unit framework from an earlier discussion; never copy the MULTIPLECORP domain, assets, tokens or design project. P0 to P11 may be planned without routine questions between phases. | **SUPERSEDED by D-019** as to the framework and as to planning without per-phase authorization. The prohibition on any use of MULTIPLECORP material is **IN FORCE**. |
| D-002 | Owner | V1 minimum scope: goods only; one stock pool; exactly three roles; catalogue, search and barcode; receiving, opening stock and manual issue; low and out-of-stock attention; stock ledger and balance; moving weighted average cost and owner-only gross profit; cash accountability; fully paid sale; documents; corrections; audit; recovery; runtime client branding. Everything on the "Later" list is inactive. | **IN FORCE — REVALIDATION PENDING** (R-01, R-05, R-06, R-08). |
| D-003 | Owner | One completed sale has exactly one full payment, cash or bank transfer. No mixed, split, partial or credit payment and no second instrument. A transfer is confirmed by a person. A future payment-provider boundary is architectural only: no callback, QRIS, webhook or asynchronous payment state in V1. | **IN FORCE — REVALIDATION PENDING** (R-06). |
| D-004 | Planner | Modular monolith on Laravel, Inertia, React with TypeScript, PostgreSQL and Nginx, chosen by comparison with a full-TypeScript alternative. Versions are pinned at the foundation unit. | **IN FORCE.** Identical to the AICWDF-4.3 default stack. To be recorded as an ADR in RF-3. |
| D-005 | Planner | Minimum infrastructure: database-backed sessions and rate limiter; Vite at build time; PHP-FPM, Nginx and private PostgreSQL; scheduled commands. No SSR Node, Redis, queue, Octane or microservices by default. An independent backup is mandatory. | **IN FORCE.** Hosting topology under **REVALIDATION** (R-12). To be recorded as an ADR in RF-3. |
| D-006 | Planner | Integrity: immutable stock ledger with a synchronous non-negative balance; the whole sale is one atomic transaction including snapshot, audit and result; exact decimal cost with explicit UNKNOWN; command identity by source intent, key, fingerprint and recovery epoch; ordered guards and bounded retries. | **IN FORCE.** |
| D-007 | Planner | Authority and privacy: policies, object scope and actor and configuration checks; per-role data allowlists on every output channel. Purchase cost entered by the Operations role is write-only. The Cashier has no master-data management. | Allowlists and server authorization **IN FORCE**. Write-only cost **IN FORCE — REVALIDATION PENDING** (R-03). |
| D-008 | Planner | Corrections: the original is preserved; the money refund bound and the physical return quantity bound are separate; historical cost is restored once; Owner reason and recent authentication; cost revisions with bounded all-or-nothing replay; unsafe chronology is rejected. | **IN FORCE — REVALIDATION PENDING** (R-04, R-09). |
| D-009 | Planner | Cash accountability: one open session per register and per cashier; everyone, including the Owner and for transfer-only sales, sells in their own session; opening cash is explicit, zero allowed; race-safe close with an immutable report. No reopening, no change of responsibility, no shared drawer, no paid-out. | **IN FORCE — REVALIDATION PENDING** (R-04, R-14). |
| D-010 | Owner (direction), Planner (contract) | Design pipeline: an exact Owner-approved revision in the BantuKasir design project, then repository semantic tokens, then native shadcn/ui with React and Inertia. "BantuKasir" and its logo are a codename; client identity comes from a versioned runtime business profile. The MULTIPLECORP design project must never be touched. Observing a design context is not approval. | Pipeline **ADAPTED by D-024 and D-025**. Runtime client identity **IN FORCE — REVALIDATION PENDING** (R-10). MULTIPLECORP prohibition **IN FORCE**. |
| D-011 | Owner | Executor: Claude Opus 5.5, effort xhigh. Fable 5.1, effort xhigh, selectively for bounded high-risk problems. The labels are a request, not a claim of availability; work units must be portable without chat memory. | **IN FORCE**, confirmed and extended by D-023. |
| D-012 | Planner | Delivery plan of 27 Build Units with hour estimates, 73 Core requirement entries and 39 acceptance suites. | **NOT EXECUTABLE.** To be superseded by the Task baseline in RF-7. The Build Units are input only. |
| D-013 | Planner | Security support: threat-path model; Argon2id; session regeneration and revocation; CSRF; headers and CSP; upload rules; advisory checks. Technical defaults: recent authentication 15 minutes, inactivity 30 minutes, absolute session 12 hours, stated rate limits. | **IN FORCE.** Session timeouts **REVALIDATION PENDING** (R-14). Password rule to be set by the authentication profile in RF-3 (R-02). |
| D-014 | Planner | Review, fix and validation come before any freeze. P7 must be complete before P8 to P11 are frozen. Client, hardware, backup and release holds stay open. Affected work is reopened when evidence changes. | **IN FORCE** as to review before freeze and the order P7 before P8 to P11. The form of P7 approval is now **D-025**. |
| D-015 | Owner | One local planning commit with an exact, safe path scope; no Build Unit executed; push, deploy, purchase and destruction need separate authorization. | **CONSUMED.** The authorization was used; it grants nothing today. The safety limits are restated in AGENTS.md. |
| D-016 | Planner | Preservation: Plan 2.0 inputs remain in Git and its 40 task cards are archived in the tree; an unrelated untracked patch is preserved; protected sources, assets and evidence are kept byte-identical; no clean-up of unrelated work. | **SUPERSEDED by D-019** as to keeping legacy trees in the working tree. Preservation of the patch and the stash continues under **D-022**. |
| D-017 | Owner | P7 is actual design work in the BantuKasir design project, completed by the Owner's visual approval of one exact revision and a normalized design contract. P8 to P11 are finalized only after P7 is complete. All coding stays unauthorized. The separate design Build Unit is removed. | P7 as real design work and the order P7 before P8 to P11: **IN FORCE.** The single exact-revision approval: **SUPERSEDED by D-025.** The former candidate path: **SUPERSEDED by D-024.** |
| D-018 | Owner | Four notes on the former design candidate: dates with the full Indonesian month name and dotted time (for example `01 Oktober 2026, 09.15.00`); example product photos with fallbacks, without any uploader or storage; an explicit "Barang keluar" menu for the Owner leading to non-sale manual issue; interactive trend-chart points with date and value. Not a visual approval. | Date format and the Owner's "Barang keluar" entry: **IN FORCE** as requirements for the P7 rebuild. Chart behaviour: carried as input to RF-4. Product photos: **REVALIDATION PENDING** (R-08). |

## 3. Decisions under the re-foundation (D-019 onward)

All by the Owner on 3 October 2026 (Asia/Jakarta), in answer to the questions of the [re-foundation analysis report](records/REFOUNDATION_ANALYSIS_2026-10-03.md).

| ID | Decision | Effect |
| --- | --- | --- |
| D-019 | Full restructure of the repository to the AICWDF-4.3 layout. | Framework deviation FD-01 approved. Supersedes D-001 (framework) and D-016 (legacy trees). AICWDF-4.3 as the operating framework is stated in the Owner's Project instructions. Adopting the framework does not by itself approve a framework rule that changes business, authorization or release meaning; each such change is a revalidation item in [OPEN_ITEMS](OPEN_ITEMS.md) until the Owner decides it. |
| D-020 | Agent-facing repository documents are in English. Indonesian remains the primary interface language. Localization for V1 will be finalized separately. | Resolves R-15. Question 5 and deviation FD-02 stay open by the Owner's choice. |
| D-021 | The planning architect may write planning and re-foundation documents directly into the working tree, with no commits and no other Git mutations. Claude Opus 5.5 xhigh on Windows independently validates and creates the checkpoint commit. | Defines how re-foundation changes are applied. |
| D-022 | Housekeeping. (a) `plan-p0-p11-review.patch` is not deleted during RF-1; it is kept until RF-1 and the legacy mapping are independently validated. (b) `stash@{0}` is not dropped during RF-1; it is kept until its unique content is verified as safely recoverable elsewhere. (c) The `upstream` remote pointing at `latansa-inventory` is unrelated legacy state and is removed by the Windows executor during checkpoint work. (d) No push during RF-1; `main` is not modified; no merge. After the re-founded baseline is reviewed and explicitly approved, a later pull request may replace the obsolete `main`. | Applied in RF-1. |
| D-023 | Planning architect: Fable 5.1, effort xhigh. Implementation executor: Claude Opus 5.5, effort xhigh. | Confirms D-011. |
| D-024 | P7 is rebuilt from information architecture, user tasks and plain-language copy, retaining useful visual tokens. | The former candidate `sha256-bef01d649ea91b2b` will not be approved. There is no approved design revision. |
| D-025 | The single all-or-nothing P7 approval is replaced by three Owner gates: (1) navigation and page structure; (2) terminology and copy; (3) visual direction and key screens. | Supersedes the approval mechanism of D-017. Resolves R-11. |
| D-026 | RF-1 is authorized exactly as scoped in section 40 of the analysis report, revision 2. The planner performs Part A only (file writes and moves, no Git mutation, no deletion). Part B is sent to the executor only after the Owner has reviewed the planner's Part A report and the executor instruction. RF-2 and later steps are not authorized. Implementation is not authorized. | Authorizes RF-1. |
| D-027 | A safety copy is made in RF-1 Part B: the executor creates and verifies a Git bundle at `C:\Backups\BantuKasir` before any retired tree is removed. **Accepted risk:** that location is on the same physical drive as the repository; the copy protects against repository or Git mistakes, not against loss of the disk. | Resolves N-02. |
| D-028 | RF-1 must not knowingly commit a broken link in a tracked document. The two relative links in `assets/brand/README.md` whose targets RF-1 moved are updated to point at the new `legacy/` locations. Nothing else in that file, and no brand asset, is changed. | Amends the RF-1 scope of D-026 by this one file. Applied by the planner in Part A. Does not decide question 14 (branding), which stays open. |

## 4. Facts supplied by the Owner

| ID | Date | Fact | Consequence |
| --- | --- | --- | --- |
| OF-01 | 3 October 2026 | The GitHub repository `yusufarst/bantukasir` is public. | Anything pushed is published. See open item N-01. |

## 5. Accepted risks

| Risk | Accepted by | Reference |
| --- | --- | --- |
| The RF-1 safety bundle is on the same physical drive as the repository. | Owner | D-027 |
