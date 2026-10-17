# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 17 October 2026 (Asia/Jakarta) by the implementation executor in the RF-4B correction (D-084), on the accepted RF-4B checkpoint `c4bf8e9`; the same change commits it. Section 1 shows how to confirm that the correction is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-4B — correction of COPY_DECK before gate P7-G2 (D-084), documentation only, one local commit on c4bf8e9, the accepted RF-4B checkpoint — next: the planning agent's review of the executor's report and the Owner's acceptance; then the Owner's review and his decision on gate P7-G2; gate P7-G1 is APPROVED (D-082); gate P7-G2 is NOT APPROVED; RF-4C is not authorized
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 to RF-3B are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb` (accepted, D-055). RF-3A is commit `ee67b3aac58f24198fd8f617410e824bc7ca1119` (accepted, D-058). RF-3B is commit `b238fad4a9fde9f363124b365d088367a56d8168` (accepted, D-064).
- **Bookkeeping checkpoint.** Commit `86822d5620dd031c6998cf0f0be24b7715b3b887` records D-064 and D-065. The Owner accepted it on 11 October 2026 (D-066); its SSD bundle was verified and the plan branch was pushed to it by fast-forward.
- **RF-4A is done.** It wrote the six structure documents of [`docs/07-ux-design/`](../07-ux-design/) for gate P7-G1 and records D-066 to D-076. The executor committed checkpoint `c8dc234c891228ec5a7f1d808f4231d2b8d89620` on 16 October 2026, and on 17 October 2026 the Owner accepted it with its SSD safety bundle (D-078). REFOUNDATION_PLAN section 9 has the detail; the review records and reports are in the Owner's Claude Project.
- **The Q38 amendment is done.** V1 supports CASH and BANK_TRANSFER only; STATIC_QRIS is removed from V1 (D-077). Checkpoint `d4c0594f7b190677fa70b920ddc749c0bf8d980e` amends four of the six UX documents; the Owner accepted it with its SSD safety bundle `bantukasir-Q38-d4c0594.bundle` (D-080). REFOUNDATION_PLAN section 10 has the detail.
- **D-080 bookkeeping commit.** Commit `cdb8361f8c8258af447abe7c1e5f04ae031e72f1` records D-080. The Owner accepted it on 17 October 2026 (D-081). It is not pushed and is in no SSD bundle. The wording issue R-2, which D-080 had deferred, is closed administratively as no longer applicable: its target is absent from the repository, and nothing is corrected (D-081).
- **Gate P7-G1 is APPROVED** (D-082, 17 October 2026): the six documents as committed in `d4c0594`, each named in D-082 by its Git blob, size and SHA-256, which the executor measured from that commit. The documents are unchanged and must stay byte-identical; their own gate rows still read "NOT APPROVED", and D-082 prevails (SOURCE_OF_TRUTH section 1). Their Indonesian labels are working labels; the wording is COPY_DECK's.
- **RF-4B (D-083).** The Owner's instruction of 17 October 2026 authorized it and set its method: the implementation executor writes the change and commits it as one local commit. Its checkpoint `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, on `cdb8361`, adds [COPY_DECK](../07-ux-design/COPY_DECK.md), finalizes the shop terms of [GLOSSARY](../02-domain/GLOSSARY.md), records D-081 to D-083 and brings the registers in line; the six P7-G1 documents are not touched. The Owner accepted it on 17 October 2026 (D-084). It is not pushed and is in no SSD bundle.
- **RF-4B correction (D-084).** The same day the Owner authorized one narrow correction of COPY_DECK before gate P7-G2, written and committed by the implementation executor as one local commit on `c4bf8e9`. A status check that gets no answer leaves the request in the state it was in, so a held request stays UXS-15 with "Cek status" only and is never offered "Kirim ulang" (COPY_DECK section 14.2); `commands.resolution.no_answer` gets the Owner's text; finding F-05 of COPY_DECK goes to RF-5 (section 21.1); the retired-barcode message (section 13.2) and the category text of UXI-208 (section 8.9) are corrected. The change also records D-084 in DECISIONS and brings REQUIREMENTS, SOURCE_OF_TRUTH, OPEN_ITEMS, REFOUNDATION_PLAN and this file in line: seven files, none new. The six P7-G1 documents and GLOSSARY are not touched. The review that the instruction names, `RF-4B-Checkpoint-Review.md`, is not in the repository and was not available to the executor; the executor identified the targets of the barcode and category corrections in the repository, and the Owner chose that it proceed on them (D-084). To confirm the state, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - if the second line is `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, the first line is the correction commit: the change is committed;
  - if the first line is `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, the change is not committed and its files are modified in the working tree. Stop and report to the Owner; do not repair it.
- **Gate P7-G2 is NOT APPROVED.** Its artifacts are GLOSSARY and COPY_DECK. The approval is a later Owner decision, after his review, that names the commit that contains the RF-4B correction and the hash of both documents, measured from that commit (D-025, P-10, D-084).
- **Next steps.** The Owner returns the executor's report of the correction to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the correction commit. His review of the two documents and his decision on gate P7-G2 follow. An SSD bundle of the RF-4B checkpoint and its correction, any push, RF-4C and every later step each need a separate, explicit authorization (D-065).

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-4B correction | One local commit on `c4bf8e9` that changes COPY_DECK, DECISIONS, REQUIREMENTS, SOURCE_OF_TRUTH, OPEN_ITEMS, REFOUNDATION_PLAN and this file, and nothing else; it is HEAD once committed (section 1); not yet accepted |
| RF-4B checkpoint | `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, accepted (D-084); parent `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, tree `2bbb2b2dc55d4f591d11c344a4139d420c9f3d2a` |
| D-080 bookkeeping commit | `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, accepted (D-081); parent `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, tree `261e443522d9a589f8dce20d04f45a455b47ab7f` |
| Q38 checkpoint | `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, accepted with its SSD safety bundle (D-080); parent `c8dc234c891228ec5a7f1d808f4231d2b8d89620`, tree `14c66f89e1d3e0c0e614a3b397dbc8175cdc7923`; the commit that gate P7-G1 names (D-082) |
| Earlier commits | `c8dc234` RF-4A checkpoint (D-078), on `86822d5` bookkeeping checkpoint (D-066), on `b238fad` RF-3B checkpoint, on `ee67b3a` RF-3A checkpoint, on `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `86822d5` (pushed 11 October 2026, D-066), as the remote-tracking ref of this repository shows; the executor of the RF-4B correction did not contact GitHub. Not pushed: the RF-4A checkpoint, the Q38 checkpoint, the bookkeeping commit, the RF-4B checkpoint and its correction. A push needs its own authorization and, before the first one, N-09 and the N-10 audit; the publication guard of D-076 runs immediately before it. Check with `git ls-remote origin` |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| RF-1 safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit; it is on the same physical drive as the repository (D-027) |
| SSD safety copies | On the Owner's external SSD, a different physical disk; the location is held by the Owner and not recorded here (D-065). Latest: `bantukasir-Q38-d4c0594.bundle`, 33,603,697 bytes, SHA-256 `2f38e04437e973fb6eb5d15c7342ee4ef17916c73674c5aea4f4b0c0ea72b549`, holding `refs/heads/plan/bantukasir-pos-v1` at `d4c0594`, `refs/heads/main` at `f7cecfe` and `refs/stash` at `f4c98df` (D-080). Kept: `bantukasir-RF4A-c8dc234.bundle` (D-078), `bantukasir-D065-bookkeeping.bundle` (D-066), the earlier `bantukasir-RF3B-b238fad.bundle` and the verified copy of `plan-p0-p11-review.patch`. The bookkeeping commit `cdb8361`, the RF-4B checkpoint and its correction are in no bundle |
| Safety-copy policy | A new SSD bundle after every accepted RF checkpoint; earlier bundles and copies are kept and never deleted or overwritten automatically (D-065). Each SSD operation needs its own authorization |
| **Residual risks** | No technical branch protection is configured on GitHub (available for a public repository; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08); the bookkeeping commit `cdb8361`, the RF-4B checkpoint and its correction are in no SSD bundle; the SSD protects against loss of the computer only while stored apart from it; published content cannot be withdrawn: what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076) |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub, intentionally, by the Owner's decision of 12 October 2026 (D-076), which supersedes the private visibility of D-065 (6 October 2026). Anything pushed is published; before any push the publication guard of D-076 applies (section 4). The rule of SECURITY_MODEL SEC-SECRET-05 stays: no document names a real server address, domain, provider identity, credential, key or production backup location, and the SSD location is not written in the repository |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified. The tracked text files are stored with LF line endings.

## 3. Authorized now

Once the RF-4B correction is committed (section 1):

1. For the planning agent: its review of the executor's report of the RF-4B correction against the repository.
2. For the Owner: accepting or rejecting the correction commit; his review of GLOSSARY and COPY_DECK and his decision on gate P7-G2.
3. For the implementation executor: nothing.

Nothing else. An SSD bundle of the RF-4B checkpoint and its correction (D-065), the N-10 audit, any push and every later step each need the Owner's separate, explicit authorization. No work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-4C, RF-5, RF-6, RF-7 or any later re-foundation step. In particular: the design system and key screens (RF-4C); HTTP routes, the full interaction contracts, the document contracts and the P3 workflow contracts (RF-5); any ADR beyond ADR-008; the test strategy, backup, runbooks and user guidance; the Task baseline.
- Treating gate P7-G2 as approved before the Owner's separate decision.
- Any change to the six P7-G1 documents, which D-082 approved by their hashes; any change to GLOSSARY or COPY_DECK beyond the RF-4B checkpoint and its correction (D-084) before the Owner authorizes it.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the recorded decisions.
- Design work, any access to the design tool, or any change to the CRM template pages (T-08). Adopting the "Golden Frontend Reference" named in D-077 (N-11).
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, executing any SQL (the DDL in SCHEMA is specification text only, plan decision P-7), version pins, dependency installation, infrastructure or database work; accounts or purchases of any service.
- Any push without its own explicit authorization; before the first push, N-09 and the N-10 audit; and any push without the publication guard of D-076 immediately before it (a secret scan, a privacy check and a client-data check of everything the push would publish; a finding stops the push); never `--force` or `--force-with-lease`, and never a push of `main`, a tag, the stash or a `refs/codex/` ref.
- Merge, rebase, pull request, deployment, history rewrite, or any change to `main`.
- Any bundle or other operation on the SSD without its own authorization; deleting or overwriting any safety bundle or safety copy; any destructive operation on the SSD.
- Deleting or editing any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 18 or 20, T-17 b, the application of D-069 (d) to the Owner replacement procedure (SEC-OP-03), items N-03, N-04, N-05, N-08, N-09, N-10, N-11, T-14 or T-15, any gate, or any first-store fact.

## 5. Decisions

Latest Owner decisions (11 to 17 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md):

- D-066 — the bookkeeping checkpoint `86822d5` is accepted, with its SSD bundle and push; every executor instruction runs as Claude Opus 5.5 at effort Max;
- D-067 — question 35, option A: one Sale line per product; the cart combines repeated additions; RF-5 specifies the backend enforcement;
- D-068 — question 29, option A: the variance stays, a linked Owner review note explains it, and the refund cash is handed over before the next opening count; its residual risk is recorded in its terms, not as an accepted risk;
- D-069 — T-17 a, d (staff accounts), f and h decided; RF-5 specifies their backend enforcement; T-17 b stays OPEN;
- D-070 — RF-4A is authorized, with plan decisions P-10 to P-15;
- D-071 — question 36, option A: initial stock onboarding is allowed only once per business-data lifecycle, also after a reinstallation or a server migration; later corrections use adjustment, reversal and cost correction; RF-5 specifies the race-safe, durable backend enforcement; RF-4A resumed;
- D-072 — question 37: once onboarding has actually been released, restoring an older backup does not reopen or continue it; a period never released may continue as the same original period; an unverifiable state is held until the Owner's restore reconciliation; RF-5 and RF-6 specify the enforcement and recovery;
- D-073 — NC-13 after a restore, option A, server-authoritative recovery: the status check stays available for EPOCH_STALE; a reference is released only by a verified outcome or an audited restore reconciliation with a safe fence; never cleared or repeated silently; the re-application of SEC-OP-04 stays possible; RF-5 and RF-6 specify the mechanisms;
- D-074 — the gap of NC-13 on another device, in another browser or after cleared browser data is not an accepted risk; RF-5 proposes the backend mitigation, tests and any Owner revalidation (OPEN_ITEMS N-07); RF-4A adds no server refusal;
- D-075 — remediation of the independent final review: the handling of question 37 is a documented noncompliance; the D-071 pointers in INVENTORY, DOMAIN_MODEL and SCHEMA are removed, the TECHNICAL_RULES section 14 reference stays; the earlier subagent reviews are supporting evidence only and the corrections need a targeted re-verification in a separate session; the combined account's Stok home, no role switcher and "Tunai" preselected are accepted, the shortcuts and the 600 and 1024 CSS px breakpoints as provisional; RF-4A resumed for the remediation only; on 12 October 2026 the remediation was continued for the findings of its targeted re-verification, and the Owner confirmed that the TECHNICAL_RULES section 14 row and closing sentence stay; on the same day it was continued again for the four findings of the re-verification of those corrections;
- D-076 — repository visibility, option B: the repository is public, intentionally, superseding the private visibility of D-065; before any push a publication guard applies — a secret scan, a privacy check and a client-data check of everything the push would publish; RF-4A extended to record it (option B1);
- D-077 — the Owner's payment-method decision Q38 (12 October 2026): V1 supports CASH and BANK_TRANSFER only; STATIC_QRIS is removed from V1; QRIS, cards, e-wallets and gateways are not in V1, and a future method needs a separate Owner decision; goods only, one full payment per Sale; amends D-036 (methods only) and the wording of D-040; no money, stock or concurrency rule changes; the "Golden Frontend Reference" it mentions is not adopted (N-11);
- D-078 — the RF-4A checkpoint `c8dc234` and its SSD safety bundle `bantukasir-RF4A-c8dc234.bundle` are accepted (17 October 2026); RF-4A is DONE; not pushed;
- D-079 — the Q38 canonical amendment is authorized: one bounded, documentation-only working-tree change; no staging, commit, push, SSD access, N-10 audit, gate P7-G1 approval or other RF phase;
- D-080 — the Q38 checkpoint `d4c0594` and its SSD safety bundle `bantukasir-Q38-d4c0594.bundle` are accepted (17 October 2026); the Q38 amendment is complete; not pushed; the correction of the wording issue R-2 was deferred;
- D-081 — the D-080 bookkeeping commit `cdb8361` is accepted (17 October 2026); not pushed, in no SSD bundle; the wording issue R-2 is closed administratively as no longer applicable, because its target is absent from the repository;
- D-082 — gate P7-G1 is APPROVED (17 October 2026): the six documents of `docs/07-ux-design/` as committed in `d4c0594`, each named by its Git blob and SHA-256; kept byte-identical; their working labels are not approved wording;
- D-083 — RF-4B is authorized on `cdb8361`: the glossary's shop terms finalized and the copy deck written and committed by the implementation executor as one local documentation commit; no push, SSD access, RF-4C or implementation; gate P7-G2 stays NOT APPROVED;
- D-084 — the RF-4B checkpoint `c4bf8e9` is accepted, and one correction of COPY_DECK is authorized before gate P7-G2: when a status check gets no answer, a held request stays UXS-15 with "Cek status" only; the text of `commands.resolution.no_answer`; finding F-05 of COPY_DECK for RF-5; the retired-barcode message and the category text of UXI-208 corrected; one local documentation commit; no push, SSD access, change to the P7-G1 documents, P7-G2 approval, RF-4C or implementation.

D-019 to D-065 remain as recorded. The next free identifiers are D-085 and ADR-009.

Decisions pending: the Owner's acceptance of the RF-4B correction and his decision on gate P7-G2; Owner questions 18 (before RF-6) and 20 (before RF-6); T-17 b and the application of D-069 (d) to the Owner replacement procedure (both before RF-5); items N-03, N-04, N-05, N-08 (GitHub branch protection, when convenient), N-09 (whether the local path of the RF-1 safety bundle stays in the public repository, before the first push after the RF-4A checkpoint), N-10 (the one-time audit of the published GitHub history, authorized separately and completed before the first push after the RF-4A checkpoint) and N-11 (the provenance and any adoption of the "Golden Frontend Reference", before RF-4C is authorized) in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md); and, after RF-5's proposal, the Owner's revalidation that N-07 may need (D-074).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 was re-founded in RF-3A and RF-3B and is complete (D-064); P5 and P6 are re-founded in RF-3A and complete (D-058); P3 rests on legacy workflows that remain in force as amended by D-035, D-037 and D-040 (D-077 reverted the payment-method amendment of D-036), with the CURRENT documents prevailing where they disagree; P7 is rebuilt as RF-4A, RF-4B and RF-4C — gate P7-G1 is approved (D-082), RF-4B wrote the artifacts of gate P7-G2, which is not approved (D-083), its checkpoint is accepted and one correction of COPY_DECK is authorized (D-084), and there is no approved visual design; P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices and the actual server: all open (OPEN_ITEMS section 3).
- Route contracts, interaction contracts at route level and the P3 workflow contracts do not exist yet (RF-5). The page inventory and navigation contracts exist at capability and command level (RF-4A; OPEN_ITEMS T-05). The logical schema exists (SCHEMA); no migration exists.
- RF-5 obligations recorded so far: the backend enforcement of D-067 and D-069 and of D-071 (race-safe and durable; the texts of BR-INV-24, REQ-INV-07, DOMAIN_MODEL section 5, SCHEMA section 5.7 and TECHNICAL_RULES section 7.2 brought in line); the enforcement of D-072 after a restore; the rule-text clarifications of D-068; the existing mechanisms that back the client rule NC-13 of NAVIGATION_CONTRACTS, which adds no server refusal — a new one would first need the Owner; the mechanisms of the server-authoritative recovery of D-073; the proposed backend mitigation of the gap of NC-13 across devices, with its tests (D-074, OPEN_ITEMS N-07); how the Owner resolves another user's uncertain intent and how BR-CASH-16 is met in an Owner-assisted close (ADR-007 section 5); the CSV column list and the receipt document contract. INFORMATION_ARCHITECTURE section 14 lists them.
- Copy carried forward by RF-4B (COPY_DECK section 21): to RF-5, the CSV column headers, the document contracts with the place of the copy marker, the reason parameter of `BARCODE_NOT_ELIGIBLE` and whether the CSV preview tells an inactive category from a missing one, the list of audit action codes and the labels of the outcome codes of Riwayat keamanan, the number in an anonymized account's neutral name, whether the reference of a drawer-funded refund is typed or generated, the wording of the Owner's control for another user's uncertain request and of the lost-reference rule, and any text the Owner-assisted close needs for BR-CASH-16; to RF-4C, the look of every text, the names of the accent colours, the figures of the Beranda panel, the series of the trend chart, the receipt layout of long names and the verification of the shortcuts; to RF-6, the evidence behind the system-status items and the wording of runbooks and user guidance; the hidden correction of an onboarding entry waits for T-17 b; tax text on documents waits for gate G1, and paper widths, printers and the tablet's keyboard for gate DEVICES. Findings F-01 to F-04 about the six P7-G1 documents (their stale gate rows; the count of UXI-076 among the disabled rows; the working labels that COPY_DECK changes; the RF-4B item of INFORMATION_ARCHITECTURE section 14) change nothing until those documents are next revised under an authorization; F-02 goes to RF-5. Finding F-05 (D-084) goes to RF-5: INTERACTION_INVENTORY UXI-086 and, for UXP-006, its NAVIGATION_CONTRACTS row and its row button UXI-055 lead a status check that gets no answer to UXS-14, where a held request must stay UXS-15 with "Cek status" only (ADR-007 section 5, UI_STATES section 1); COPY_DECK section 14.2 already keeps the request's state, and the rows are brought in line at the next authorized revision of those two documents.
- RF-6 inputs: the restore runbook items of SCHEMA section 16; the restore reconciliation of D-072 (onboarding) and of D-073 (requests refused as EPOCH_STALE, which keep their command family blocked under NC-13 until it has recorded their outcome), ordered so that the Owner's own user-management references are resolved before the security changes of SECURITY_MODEL SEC-OP-04 are re-applied; the operational rule of D-068 in the user guidance.
- Known gap, not an accepted risk: NC-13 blocks only in the browser that holds the unresolved request; on another device, in another browser or after cleared browser data a duplicate business action is not blocked until RF-5 mitigates it (D-074, OPEN_ITEMS N-07).
- G1 proposals accepted by the Owner (D-075): the combined account's home on Stok without an OPEN session, no role switcher, Tunai preselected on the payment screen; the keyboard shortcuts and the 600 and 1024 CSS px breakpoints as provisional, verified in RF-4C and at gate DEVICES.
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository, a risk reduced by the SSD bundles and the push (D-027, D-065); no technical branch protection is configured on GitHub (D-065; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08), and published content cannot be withdrawn — what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- Between accepted RF checkpoints, new commits are not yet in an SSD bundle (D-065); today the bookkeeping commit `cdb8361`, the RF-4B checkpoint and its correction are in none.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; logical schema only (SCHEMA); production DB touched: NO
AUTH:        LARAVEL_NATIVE; AICWDF-COMPAT-8; Google sign-in OFF (SECURITY_MODEL section 2)
UI / UX:     RF-4A structure documents in docs/07-ux-design, gate P7-G1 APPROVED (D-082); COPY_DECK and the GLOSSARY shop terms written in RF-4B for gate P7-G2, NOT APPROVED (D-083); COPY_DECK corrected under D-084; no visual design yet; V1 Indonesian only with translation keys from the first Task (D-050, ADR-005)
HOSTING:     Cloudflare, VPS, Coolify; staging on the same server (D-053, ADR-003); server facts UNKNOWN
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the RF-4B correction is committed (section 1): the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the correction commit. Then come his review of GLOSSARY and COPY_DECK and his decision on gate P7-G2, which names the commit that contains the correction and the hash of both documents (D-025, P-10, D-084). An SSD bundle of the RF-4B checkpoint and its correction needs his separate authorization (D-065). Before the first push: N-09, the N-10 audit and the publication guard of D-076. Before RF-4C is authorized: N-11 and the read-only design-tool check of P-14.

If it is not committed, or a step that its instruction includes is not complete: stop and report to the Owner.

No step is authorized until the Owner gives a separate, explicit authorization for it. Until then, wait.

## 10. Do not

Do not start RF-4C or any later step. Do not use the design tool. Do not change the six P7-G1 documents. Do not create migrations or execute the DDL in SCHEMA. Do not create an ADR beyond ADR-008. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push, except as an instruction that the Owner authorizes allows, and only after N-09, the N-10 audit and the publication guard of D-076. Do not adopt the "Golden Frontend Reference" (N-11). Do not delete or overwrite any safety bundle or copy.
