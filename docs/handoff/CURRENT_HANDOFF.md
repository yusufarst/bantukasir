# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 18 October 2026 (Asia/Jakarta) by the implementation executor of RF-5, on the Owner's instruction of that day (D-087), on `7318322`, the RF-4C checkpoint; the RF-5 checkpoint commits it. Section 1 shows how to confirm that the checkpoint is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-5, P3 workflow, route, interaction and document contracts (D-087) — IN_PROGRESS — written as one local checkpoint commit on 7318322; awaiting the planning agent's review, the Owner's acceptance and his revalidation of N-07; gates P7-G1, P7-G2 and P7-G3 are APPROVED (D-082, D-086, D-087)
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 to RF-3B are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb` (accepted, D-055). RF-3A is commit `ee67b3aac58f24198fd8f617410e824bc7ca1119` (accepted, D-058). RF-3B is commit `b238fad4a9fde9f363124b365d088367a56d8168` (accepted, D-064).
- **Bookkeeping checkpoint.** Commit `86822d5620dd031c6998cf0f0be24b7715b3b887` records D-064 and D-065. The Owner accepted it on 11 October 2026 (D-066); its SSD bundle was verified and the plan branch was pushed to it by fast-forward.
- **RF-4A, the Q38 amendment and RF-4B are done** (D-078, D-080, D-081, D-084, D-086); REFOUNDATION_PLAN sections 9 to 11 have the detail. The D-085 commit `6e73514564382b26bb441efaa58351b1da5290ea` is accepted (D-086).
- **RF-4C is done** (D-087). Its work-in-progress commit `3a7da1996a5ed3388e7401413fa75e6899275a35` and its checkpoint `7318322533482bde1fc1bc2d6fc2d94f69563be0` are accepted. REFOUNDATION_PLAN section 12 has the detail.
- **All three P7 gates are APPROVED,** each naming its commit and the Git blob, size and SHA-256 of every artifact: P7-G1, the six structure documents at `d4c0594` (D-082); P7-G2, GLOSSARY and COPY_DECK at `551b4eb` (D-086); P7-G3, DESIGN_SYSTEM, KEY_SCREENS and the 19 files of `docs/07-ux-design/previews/` at `7318322`, previews tree `61f48f92fba53111738b66ec3330beb8431a9f57` (D-087; the per-file table is in its terms). Every approved artifact must stay byte-identical; their own gate rows still read "NOT APPROVED", and the approving decision prevails. A later change follows change control.
- **RF-5 (D-087) — checkpoint written, not yet accepted.** Authorized on 18 October 2026 on `7318322`: "Complete executable workflow specifications, route contracts, and interaction contracts for V1." In the session the Owner decided T-17 b and the Owner replacement with an OPEN cash session (D-087). REFOUNDATION_PLAN section 13 has the scope. To confirm the state, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - if the second line is `7318322533482bde1fc1bc2d6fc2d94f69563be0`, the first line is the RF-5 checkpoint commit: the checkpoint is committed;
  - if the first line is `7318322533482bde1fc1bc2d6fc2d94f69563be0`, the checkpoint is not committed and its files are modified or untracked in the working tree. Stop and report to the Owner; do not repair it.
- **Next steps.** The Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner accepts or rejects the RF-5 checkpoint and revalidates N-07 (OPEN_ITEMS). An SSD bundle, any push, RF-6 and every later step each need a separate, explicit authorization (D-065).

### 1.1 RF-5 at the checkpoint

**New documents** (`docs/03-workflows/`, and one ADR):
- [CRITICAL_FLOWS](../03-workflows/CRITICAL_FLOWS.md) — WF-01 to WF-32 with refusals, recovery, concurrency, records and proof; the traceability of 134 requirements and 38 user tasks;
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) — conventions RT-01 to RT-12; page routes with three print views; JSON reads RD-01 to RD-07; authentication RA-01 to RA-07; RR-01 and RR-02; 48 command operations RC-01 to RC-48 and the status check RC-50; scheduled commands and operator procedures OP-01 to OP-05;
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md) — the common command contract, resolution and recovery, reads, authentication, every command's contract, audit codes, the route of each of the 385 controls of INTERACTION_INVENTORY, the rows of the approved P7 documents read with later decisions, and the copy proposals;
- [DOCUMENT_CONTRACTS](../03-workflows/DOCUMENT_CONTRACTS.md) — receipt and "Nota Penjualan" with the copy marker, session report and its CSV export, the product import file, the barcode label;
- [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md) — request receipts, reconciled outcomes of requests from before a restore, lost references.

**Amended** to apply D-067 to D-074 and D-087: INVENTORY (BR-INV-24, BR-INV-26, BR-INV-27, BR-INV-34, new BR-INV-47 and BR-INV-48), DOMAIN_MODEL, SALES_CASH (D-068 texts, BR-CASH-11, BR-RFD-05), CATALOG_BARCODE (BR-CAT-39), REQUIREMENTS (REQ-INV-07; D-087 traced), SCHEMA (receipts and reconciliation tables, onboarding states and release record, the replacement of a reversed OPENING, one line per product, guards G-22 to G-26), TECHNICAL_RULES (TR-HTTP-01, section 7.2, RACE-31 to RACE-34, limits), SECURITY_MODEL (CAP-15, CAP-34, SEC-CH-05, SEC-UP-05, SEC-OP-01, SEC-OP-03, SEC-OP-04, new SEC-OP-05), STACK_AND_MODULES, the ADR index; DECISIONS (D-087), SOURCE_OF_TRUTH, LEGACY_INDEX (section 14), OPEN_ITEMS (T-05, T-17, T-19, new T-20, N-07), REFOUNDATION_PLAN, the root README and this file. `docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md` is LEGACY — SUPERSEDED, unchanged.

**Verified.**
- Coverage: 134 of 134 requirements and 38 of 38 user tasks carried by a workflow; 385 of 385 controls with a route, "Local" or "Not provided"; 60 of 60 routable pages with a route; every capability except the operator-only CAP-33 used as a gate; no error code beyond the 32 of TECHNICAL_RULES section 4.
- Identifiers: every rule, state, capability, race, guard and decision identifier cited in the new and changed documents is defined in the repository.
- Protected files: the six P7-G1 documents, GLOSSARY, COPY_DECK and the 21 P7-G3 artifacts are byte-identical to their approved blobs; no file under `legacy/` changed.
- 373 Markdown links of the changed documents; table shape, LF only, no trailing whitespace; `git diff --check`; a scan for secrets and personal data.

**Not verified here.** The independent check of the checkpoint is the planning agent's (REFOUNDATION_PLAN section 13). Nothing was implemented or run against a database; the races and contracts are specified for RF-6 and the Tasks.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-5 checkpoint | One local commit on `7318322` that adds the four documents of `docs/03-workflows/` and ADR-009, and changes DECISIONS, SOURCE_OF_TRUTH, LEGACY_INDEX, OPEN_ITEMS, REFOUNDATION_PLAN, REQUIREMENTS, INVENTORY, DOMAIN_MODEL, SALES_CASH, CATALOG_BARCODE, SCHEMA, STACK_AND_MODULES, SECURITY_MODEL, TECHNICAL_RULES, the ADR index, the root README and this file, and nothing else; it is HEAD once committed (section 1); not yet accepted |
| RF-4C checkpoint | `7318322533482bde1fc1bc2d6fc2d94f69563be0`, accepted (D-087); parent `3a7da1996a5ed3388e7401413fa75e6899275a35`, tree `fde494bb01f0e4b2fe68473532ae6ea51489cbc2`; the commit that gate P7-G3 names (D-087) |
| RF-4C work-in-progress commit | `3a7da1996a5ed3388e7401413fa75e6899275a35`, accepted (D-087); parent `6e73514564382b26bb441efaa58351b1da5290ea`, tree `ed355d0b951a8ea5de38c99a3e0ab701a9f7ad1a` |
| D-085 commit | `6e73514564382b26bb441efaa58351b1da5290ea`, accepted (D-086); parent `551b4eb69f17adff461eb894266031a6f9b0c6df`, tree `74dbac39abca42363dfe6843256311659d8aeddb` |
| RF-4B correction | `551b4eb69f17adff461eb894266031a6f9b0c6df`, accepted (D-086); parent `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, tree `f51964e6546a506457663193396d4fae70d68ba5`; the commit that gate P7-G2 names (D-086) |
| RF-4B checkpoint | `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, accepted (D-084); parent `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, tree `2bbb2b2dc55d4f591d11c344a4139d420c9f3d2a` |
| D-080 bookkeeping commit | `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, accepted (D-081); parent `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, tree `261e443522d9a589f8dce20d04f45a455b47ab7f` |
| Q38 checkpoint | `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, accepted with its SSD safety bundle (D-080); parent `c8dc234c891228ec5a7f1d808f4231d2b8d89620`, tree `14c66f89e1d3e0c0e614a3b397dbc8175cdc7923`; the commit that gate P7-G1 names (D-082) |
| Earlier commits | `c8dc234` RF-4A checkpoint (D-078), on `86822d5` bookkeeping checkpoint (D-066), on `b238fad` RF-3B checkpoint, on `ee67b3a` RF-3A checkpoint, on `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `86822d5` (pushed 11 October 2026, D-066), as the remote-tracking ref of this repository shows; the executor of RF-5 did not contact GitHub. Not pushed: the RF-4A checkpoint, the Q38 checkpoint, the bookkeeping commit, the RF-4B checkpoint, its correction, the D-085 commit, both RF-4C commits and the RF-5 checkpoint. A push needs its own authorization and, before the first one, N-09 and the N-10 audit; the publication guard of D-076 runs immediately before it. Check with `git ls-remote origin` |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| RF-1 safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit; it is on the same physical drive as the repository (D-027) |
| SSD safety copies | On the Owner's external SSD, a different physical disk; the location is held by the Owner and not recorded here (D-065). Latest: `bantukasir-Q38-d4c0594.bundle`, 33,603,697 bytes, SHA-256 `2f38e04437e973fb6eb5d15c7342ee4ef17916c73674c5aea4f4b0c0ea72b549`, holding `refs/heads/plan/bantukasir-pos-v1` at `d4c0594`, `refs/heads/main` at `f7cecfe` and `refs/stash` at `f4c98df` (D-080). Kept: `bantukasir-RF4A-c8dc234.bundle` (D-078), `bantukasir-D065-bookkeeping.bundle` (D-066), the earlier `bantukasir-RF3B-b238fad.bundle` and the verified copy of `plan-p0-p11-review.patch`. The bookkeeping commit `cdb8361`, the RF-4B checkpoint, its correction, the D-085 commit, both RF-4C commits and the RF-5 checkpoint are in no bundle |
| Safety-copy policy | A new SSD bundle after every accepted RF checkpoint; earlier bundles and copies are kept and never deleted or overwritten automatically (D-065). Each SSD operation needs its own authorization |
| **Residual risks** | No technical branch protection is configured on GitHub (available for a public repository; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08); the commits from `cdb8361` to the RF-5 checkpoint are in no SSD bundle; the SSD protects against loss of the computer only while stored apart from it; published content cannot be withdrawn: what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076) |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub, intentionally, by the Owner's decision of 12 October 2026 (D-076), which supersedes the private visibility of D-065 (6 October 2026). Anything pushed is published; before any push the publication guard of D-076 applies (section 4). The rule of SECURITY_MODEL SEC-SECRET-05 stays: no document names a real server address, domain, provider identity, credential, key or production backup location, and the SSD location is not written in the repository |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified. The tracked text files are stored with LF line endings.

## 3. Authorized now

Once the RF-5 checkpoint is committed (section 1):

1. For the planning agent: its review of the executor's report against the repository.
2. For the Owner: accepting or rejecting the RF-5 checkpoint; his revalidation of N-07 — whether the request receipts of ADR-009 are enough or a server refusal of ADR-009 section 6 is to be specified (OPEN_ITEMS N-07).
3. For the implementation executor: nothing until the Owner's next instruction.

Nothing else. An SSD bundle of the commits since `cdb8361` (D-065), the N-10 audit, any push, RF-6 and every later step each need the Owner's separate, explicit authorization.

## 4. Not authorized

- Further RF-5 changes without the Owner's instruction; RF-6, RF-7 or any later re-foundation step. In particular: the test strategy, backup, runbooks and user guidance, environments and release documents (RF-6); the Task baseline (RF-7); any ADR beyond ADR-009.
- Treating RF-5 as complete before the Owner accepts its checkpoint; specifying a new server refusal for N-07 before the Owner decides it (D-074).
- Any change to an approved artifact — the six P7-G1 documents (D-082), GLOSSARY and COPY_DECK (D-086), DESIGN_SYSTEM, KEY_SCREENS and the previews (D-087) — before the Owner authorizes it; adding any copy proposal to COPY_DECK, or bringing the rows that INTERACTION_CONTRACTS section 10 lists into the P7 documents, outside change control (OPEN_ITEMS T-19, T-20).
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the recorded decisions.
- Any use of the Golden Frontend Reference outside a step that the Owner authorizes; any change to the reference, or the use of another version or commit of it (D-085, TOOLCHAIN section 3). DesainPakeAI or any other design-generation tool; any access to the BantuKasir design project (TOOLCHAIN section 5). Any access to MULTIPLECORP, including the lab in which the reference was frozen (AGENTS.md rule 5).
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, executing any SQL (the DDL in SCHEMA is specification text only, plan decision P-7), version pins, dependency installation, infrastructure or database work; accounts or purchases of any service; deployment.
- Any push without its own explicit authorization; before the first push, N-09 and the N-10 audit; and any push without the publication guard of D-076 immediately before it (a secret scan, a privacy check and a client-data check of everything the push would publish; a finding stops the push); never `--force` or `--force-with-lease`, and never a push of `main`, a tag, the stash or a `refs/codex/` ref.
- Merge, rebase, pull request, history rewrite, or any change to `main`.
- Any bundle or other operation on the SSD without its own authorization; deleting or overwriting any safety bundle or safety copy; any destructive operation on the SSD.
- Deleting or editing any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 18 or 20, items N-03, N-04, N-05, N-07, N-08, N-09, N-10, T-14 or T-15, any gate, or any first-store fact.

## 5. Decisions

Latest Owner decisions (11 to 18 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md):

- D-066 — the bookkeeping checkpoint `86822d5` is accepted, with its SSD bundle and push; every executor instruction runs as Claude Opus 5.5 at effort Max;
- D-067 — question 35, option A: one Sale line per product; the cart combines repeated additions; RF-5 specifies the backend enforcement;
- D-068 — question 29, option A: the variance stays, a linked Owner review note explains it, and the refund cash is handed over before the next opening count; its residual risk is recorded in its terms, not as an accepted risk;
- D-069 — T-17 a, d (staff accounts), f and h decided; RF-5 specifies their backend enforcement; T-17 b stays OPEN;
- D-070 — RF-4A is authorized, with plan decisions P-10 to P-15;
- D-071 — question 36, option A: initial stock onboarding is allowed only once per business-data lifecycle, also after a reinstallation or a server migration; later corrections use adjustment, reversal and cost correction; RF-5 specifies the race-safe, durable backend enforcement; RF-4A resumed;
- D-072 — question 37: once onboarding has actually been released, restoring an older backup does not reopen or continue it; a period never released may continue as the same original period; an unverifiable state is held until the Owner's restore reconciliation; RF-5 and RF-6 specify the enforcement and recovery;
- D-073 — NC-13 after a restore, option A, server-authoritative recovery: the status check stays available for EPOCH_STALE; a reference is released only by a verified outcome or an audited restore reconciliation with a safe fence; never cleared or repeated silently; the re-application of SEC-OP-04 stays possible; RF-5 and RF-6 specify the mechanisms;
- D-074 — the gap of NC-13 on another device, in another browser or after cleared browser data is not an accepted risk; RF-5 proposes the backend mitigation, tests and any Owner revalidation (OPEN_ITEMS N-07); RF-4A adds no server refusal;
- D-075 — remediation of the independent final review: the handling of question 37 is a documented noncompliance; the D-071 pointers in INVENTORY, DOMAIN_MODEL and SCHEMA are removed, the TECHNICAL_RULES section 14 reference stays; the earlier subagent reviews are supporting evidence only and the corrections need a targeted re-verification in a separate session; the combined account's Stok home, no role switcher and "Tunai" preselected are accepted, the shortcuts and the 600 and 1024 CSS px breakpoints as provisional; RF-4A resumed for the remediation and its continuations;
- D-076 — repository visibility, option B: the repository is public, intentionally, superseding the private visibility of D-065; before any push a publication guard applies — a secret scan, a privacy check and a client-data check of everything the push would publish; RF-4A extended to record it (option B1);
- D-077 — the Owner's payment-method decision Q38 (12 October 2026): V1 supports CASH and BANK_TRANSFER only; STATIC_QRIS is removed from V1; QRIS, cards, e-wallets and gateways are not in V1, and a future method needs a separate Owner decision; goods only, one full payment per Sale; amends D-036 (methods only) and the wording of D-040; no money, stock or concurrency rule changes;
- D-078 — the RF-4A checkpoint `c8dc234` and its SSD safety bundle `bantukasir-RF4A-c8dc234.bundle` are accepted (17 October 2026); RF-4A is DONE; not pushed;
- D-079 — the Q38 canonical amendment is authorized: one bounded, documentation-only working-tree change; no staging, commit, push, SSD access, N-10 audit, gate P7-G1 approval or other RF phase;
- D-080 — the Q38 checkpoint `d4c0594` and its SSD safety bundle `bantukasir-Q38-d4c0594.bundle` are accepted (17 October 2026); the Q38 amendment is complete; not pushed; the correction of the wording issue R-2 was deferred;
- D-081 — the D-080 bookkeeping commit `cdb8361` is accepted (17 October 2026); not pushed, in no SSD bundle; the wording issue R-2 is closed administratively as no longer applicable, because its target is absent from the repository;
- D-082 — gate P7-G1 is APPROVED (17 October 2026): the six documents of `docs/07-ux-design/` as committed in `d4c0594`, each named by its Git blob and SHA-256; kept byte-identical; their working labels are not approved wording;
- D-083 — RF-4B is authorized on `cdb8361`: the glossary's shop terms finalized and the copy deck written and committed by the implementation executor as one local documentation commit; no push, SSD access, RF-4C or implementation;
- D-084 — the RF-4B checkpoint `c4bf8e9` is accepted, and one correction of COPY_DECK is authorized before gate P7-G2: when a status check gets no answer, a held request stays UXS-15 with "Cek status" only; the text of `commands.resolution.no_answer`; finding F-05 of COPY_DECK for RF-5; the retired-barcode message and the category text of UXI-208 corrected; one local documentation commit;
- D-085 — the Golden Frontend Reference v1.0.0 (tag `v1.0.0`, commit `14497d9`) replaces the DesainPakeAI requirement as the approved reusable frontend reference: its generic tokens, components and UX patterns may be adapted into the approved stack, and its MULTIPLECORP business examples, branding, fixtures and workflows are never adopted; DesainPakeAI is no longer required;
- D-086 — the D-085 commit `6e73514` and the RF-4B correction `551b4eb` are accepted; gate P7-G2 is APPROVED: GLOSSARY and COPY_DECK as committed in `551b4eb`, each named by its Git blob and SHA-256; RF-4C is authorized on `6e73514` with the Golden reference at `14497d9` and no design tool; the same day the Owner paused RF-4C with one local work-in-progress commit; on 18 October 2026 he instructed its resumption and completion; its terms record the deviations from the reference's defaults (DV-01 to DV-08), the executor's design choices and the copy proposals; gate P7-G3 was then NOT APPROVED (approved by D-087);
- D-087 — the RF-4C commits `3a7da19` and `7318322` are accepted (RF-4C DONE); gate P7-G3 is APPROVED: DESIGN_SYSTEM, KEY_SCREENS and the previews as committed in `7318322`, each named by its Git blob and SHA-256; RF-5 is authorized on `7318322` and executed as one local checkpoint; the Owner decided T-17 b (corrections of onboarding entries before the release, append-only, by the Owner with a reason and recent authentication: a zero count is withdrawn, a wrong OPENING is reversed in full and replaced by one explicit replacement, one effective OPENING per product) and the Owner replacement procedure while the outgoing Owner holds an OPEN cash session (allowed; the session stays OPEN until an Owner-assisted close); the terms record the executor's readings and technical choices for the Owner's review.

D-019 to D-065 remain as recorded. The next free identifiers are D-088 and ADR-010.

Decisions pending: the Owner's acceptance of the RF-5 checkpoint, including the executor's readings and choices that D-087 lists; his revalidation of N-07 (the remaining window, and the option of a server refusal while a request receipt is unresolved, ADR-009 section 6); the copy proposals of DESIGN_SYSTEM section 13 (T-19) and of INTERACTION_CONTRACTS section 11 and DOCUMENT_CONTRACTS DC-04 (T-20) through COPY_DECK change control before the Tasks that use them; Owner questions 18 (before RF-6) and 20 (before RF-6); items N-03, N-04, N-05, N-08 (GitHub branch protection, when convenient), N-09 (whether the local path of the RF-1 safety bundle stays in the public repository, before the first push after the RF-4A checkpoint) and N-10 (the one-time audit of the published GitHub history, authorized separately and completed before the first push after the RF-4A checkpoint) in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 in RF-3A and RF-3B (complete, D-064); P5 and P6 in RF-3A (complete, D-058); P3 is re-founded in RF-5 — its checkpoint waits for the Owner's acceptance, and the legacy workflows are superseded; P7 is complete: gates P7-G1, P7-G2 and P7-G3 are approved (D-082, D-086, D-087); P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices and the actual server: all open (OPEN_ITEMS section 3).
- N-07: the request receipts of ADR-009 extend NC-13 to every device for every request that reached the server, without a new refusal; the window of a request still on its way, and the option of a server refusal, wait for the Owner's revalidation (D-074).
- Copy and P7 change control (T-19, T-20): the proposals of DESIGN_SYSTEM section 13, INTERACTION_CONTRACTS section 11 and the CSV headers of DOCUMENT_CONTRACTS DC-04; the rows of the approved P7 documents that later decisions change (INTERACTION_CONTRACTS section 10), with COPY_DECK findings F-01 to F-05. They are needed before the Tasks that use them.
- RF-6 inputs: the restore runbook with the after-restore procedure SEC-OP-04, the restore reconciliation SEC-OP-05 (requests reported as EPOCH_STALE and the onboarding hold), ordered so that the Owner's own user-management requests are reconciled before the security changes of SEC-OP-04 are re-applied (D-072, D-073); the retention of resolved request receipts (ADR-009); the record-check wait of TR-TIME-03; the restore runbook items of SCHEMA section 16; the operational rule of D-068 in the user guidance; the test strategy for RACE-01 to RACE-34 and the test families of SCHEMA section 9.4.
- The logical schema exists (SCHEMA, amended in RF-5); no migration exists.
- G1 proposals accepted by the Owner (D-075): the combined account's home on Stok without an OPEN session, no role switcher, Tunai preselected on the payment screen; the keyboard shortcuts and the 600 and 1024 CSS px breakpoints as provisional, verified in RF-4C in a desktop browser (KEY_SCREENS section 4) and still to be confirmed at gate DEVICES.
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository, a risk reduced by the SSD bundles and the push (D-027, D-065); no technical branch protection is configured on GitHub (D-065; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08), and published content cannot be withdrawn — what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- Between accepted RF checkpoints, new commits are not yet in an SSD bundle (D-065); today the commits from `cdb8361` to the RF-5 checkpoint are in none.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; frontend reference: Golden Frontend Reference v1.0.0 at commit 14497d9, read only (D-085), not read in RF-5; DesainPakeAI not used; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; logical schema only (SCHEMA); production DB touched: NO
AUTH:        LARAVEL_NATIVE; AICWDF-COMPAT-8; Google sign-in OFF (SECURITY_MODEL section 2)
UI / UX:     gates P7-G1 (D-082), P7-G2 (D-086) and P7-G3 (D-087) APPROVED; the approved artifacts in docs/07-ux-design and docs/02-domain/GLOSSARY.md stay byte-identical; routes of every control in docs/03-workflows/INTERACTION_CONTRACTS.md (RF-5 checkpoint); V1 Indonesian only with translation keys from the first Task (D-050, ADR-005)
HOSTING:     Cloudflare, VPS, Coolify; staging on the same server (D-053, ADR-003); server facts UNKNOWN
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the RF-5 checkpoint is committed (section 1): the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the RF-5 checkpoint and revalidates N-07. An SSD bundle of the commits since `cdb8361` needs his separate authorization (D-065). Before the first push: N-09, the N-10 audit and the publication guard of D-076.

If it is not committed, or a step that the RF-5 instruction includes is not complete: stop and report to the Owner.

No step is authorized until the Owner gives a separate, explicit authorization for it. Until then, wait.

## 10. Do not

Do not change RF-5's documents or start any later step without the Owner's instruction. Do not treat RF-5 as complete before the Owner accepts it, and do not specify a new server refusal for N-07 without his decision. Do not change any approved artifact: the six P7-G1 documents, GLOSSARY, COPY_DECK, DESIGN_SYSTEM, KEY_SCREENS or the previews. Do not use DesainPakeAI or any other design-generation tool (D-085). Do not create migrations or execute the DDL in SCHEMA. Do not create an ADR beyond ADR-009. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push, except as an instruction that the Owner authorizes allows, and only after N-09, the N-10 audit and the publication guard of D-076. Do not modify the Golden Frontend Reference, use another version or commit of it, or adopt its MULTIPLECORP business examples, branding, fixtures or workflows; use it only inside a step that the Owner authorizes (D-085). Do not delete or overwrite any safety bundle or copy.
