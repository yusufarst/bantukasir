# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 17 October 2026 (Asia/Jakarta) by the implementation executor in the bookkeeping change that records D-080, on the accepted Q38 checkpoint `d4c0594`; the same change commits it. The working tree of the Q38 amendment wrote this file earlier that day (D-077 to D-079). Section 1 shows how to confirm that the bookkeeping change is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         bookkeeping of the accepted Q38 checkpoint d4c0594 and its SSD bundle (D-080), documentation only, one local commit on d4c0594 — next: the planning agent's review of the executor's report and the Owner's acceptance; the correction of R-2 is deferred; gate P7-G1 is NOT APPROVED; RF-4B is not authorized
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 to RF-3B are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb` (accepted, D-055). RF-3A is commit `ee67b3aac58f24198fd8f617410e824bc7ca1119` (accepted, D-058). RF-3B is commit `b238fad4a9fde9f363124b365d088367a56d8168` (accepted, D-064).
- **Bookkeeping checkpoint.** Commit `86822d5620dd031c6998cf0f0be24b7715b3b887` records D-064 and D-065. The Owner accepted it on 11 October 2026 (D-066); its SSD bundle was verified and the plan branch was pushed to it by fast-forward.
- **RF-4A is done.** It is the first of the three P7 checkpoints (D-070; plan decision P-10). It wrote the six CURRENT documents of [`docs/07-ux-design/`](../07-ux-design/) for gate P7-G1 and the consequential changes of the RF-4A package (revision 3), and records D-066 to D-076. On 11 October 2026 the planning agent stopped it once, on finding F-03 of its adversarial review (question 36); the Owner answered with D-071 and resumed it. The adversarial review and four targeted re-verifications were subagents of the planning session and are supporting evidence only (D-075). A separate Claude session reviewed the working tree (independent final review: HOLD); the Owner decided D-072 to D-075, and the remediation and its corrections passed targeted re-verifications in separate sessions on 12 October 2026. The Owner's visibility decision was recorded in the same working tree (D-076); its separate-session review, the corrections and their re-verifications passed by 16 October 2026, and the Owner completed his second review the same day (D-076, N-10). The executor committed checkpoint `c8dc234c891228ec5a7f1d808f4231d2b8d89620` on 16 October 2026; the planning agent reviewed the executor's report against the repository; on 17 October 2026 the Owner accepted the checkpoint and its SSD safety bundle (D-078). REFOUNDATION_PLAN section 9 has the detail; the review records and reports are in the Owner's Claude Project.
- **The Q38 amendment is done.** The Owner's payment-method decision Q38 of 12 October 2026 (D-077): V1 supports CASH and BANK_TRANSFER only; STATIC_QRIS is removed from V1. D-079 authorized one bounded, documentation-only amendment that records D-077 to D-079 and removes STATIC_QRIS from the CURRENT requirements, specifications and UX contracts (REFOUNDATION_PLAN section 10). Its targeted verification and the re-verification of its corrections, each in a separate session, found PASS WITH NITS, and the findings that concern the repository were corrected within the same files (D-079). The executor committed checkpoint `d4c0594f7b190677fa70b920ddc749c0bf8d980e` on 17 October 2026. It modifies 19 files: DECISIONS, OPEN_ITEMS, SOURCE_OF_TRUTH, LEGACY_INDEX, REFOUNDATION_PLAN, PRODUCT_BLUEPRINT, REQUIREMENTS, SALES_CASH, COSTING, DOMAIN_MODEL, GLOSSARY, ARCHITECTURE, SCHEMA, TECHNICAL_RULES, USER_TASKS, INFORMATION_ARCHITECTURE, INTERACTION_INVENTORY, ADAPTIVE_AND_ACCESSIBILITY and this file; NAVIGATION_CONTRACTS and UI_STATES are unchanged. The same day the Owner accepted the checkpoint and its SSD safety bundle `bantukasir-Q38-d4c0594.bundle` (D-080). Of the steps that follow the amendment in REFOUNDATION_PLAN section 10, the targeted verification (1) and the checkpoint with its SSD bundle (2) are done; the Owner's comprehensive UX review and his decision on gate P7-G1 (3) remain.
- **D-080 bookkeeping change.** It records D-080 in DECISIONS, brings the decision trace and counts of REQUIREMENTS in line with it, as the Owner approved, and updates this file last. The implementation executor wrote it and committed it as one local commit on `d4c0594`. The correction of the wording issue R-2, which the Owner had accepted earlier and whose text is not in the repository, is deferred by the Owner to a later change that he authorizes separately (D-080). To confirm the state, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - if the second line is `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, the first line is the bookkeeping commit: the change is committed;
  - if the first line is `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, the change is not committed and its three files are modified in the working tree. Stop and report to the Owner; do not repair it.
- **Gate P7-G1** is NOT APPROVED. The approval is a later Owner decision that names the checkpoint commit containing the Q38 amendment, `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, and the hash of each of the six documents, measured from that commit rather than copied from a report (D-025, P-10, D-079). The Owner's comprehensive review of the UX documents comes before that decision (D-076). The bookkeeping change does not touch the six documents.
- **Next steps.** The Owner returns the executor's report of the bookkeeping change to the planning agent, which reviews it against the repository; the Owner then accepts or rejects it. An SSD bundle of the bookkeeping commit, the correction of R-2, and **RF-4B** or any other later step each need a separate, explicit authorization (D-065, D-080).

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| Q38 checkpoint | `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, accepted by the Owner with its SSD safety bundle (D-080); parent `c8dc234c891228ec5a7f1d808f4231d2b8d89620`, tree `14c66f89e1d3e0c0e614a3b397dbc8175cdc7923` |
| D-080 bookkeeping commit | One local commit on `d4c0594` that changes DECISIONS, REQUIREMENTS and this file and nothing else; it is HEAD once committed (section 1); not yet accepted |
| Earlier commits | `c8dc234` RF-4A checkpoint (D-078), on `86822d5` bookkeeping checkpoint (D-066), on `b238fad` RF-3B checkpoint, on `ee67b3a` RF-3A checkpoint, on `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `86822d5` (pushed 11 October 2026, D-066; confirmed with `git ls-remote origin` on 17 October 2026). The RF-4A checkpoint, the Q38 checkpoint and the bookkeeping commit are not pushed. A push needs its own authorization and, before the first one, N-09 and the N-10 audit; the publication guard of D-076 runs immediately before it. Check with `git ls-remote origin` |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| RF-1 safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit; it is on the same physical drive as the repository (D-027) |
| SSD safety copies | On the Owner's external SSD, a different physical disk; the location is held by the Owner and not recorded here (D-065). Latest: `bantukasir-Q38-d4c0594.bundle`, 33,603,697 bytes, SHA-256 `2f38e04437e973fb6eb5d15c7342ee4ef17916c73674c5aea4f4b0c0ea72b549`, holding `refs/heads/plan/bantukasir-pos-v1` at `d4c0594`, `refs/heads/main` at `f7cecfe` and `refs/stash` at `f4c98df`; verified and restored, as its executor reported (D-080). Kept: `bantukasir-RF4A-c8dc234.bundle`, 33,584,233 bytes, SHA-256 `804c1dc89d4a97691f7d089c5a8193df6e888dffdbc63b2b6989d4e1d87f0357`, holding the same refs with the plan branch at `c8dc234` (D-078); `bantukasir-D065-bookkeeping.bundle`, 33,466,241 bytes, SHA-256 `6c6f1f413b2222f21e0b85c432949e4b2253ecb2198803a893122fb93def17ed`, with the plan branch at `86822d5` (D-066); the earlier `bantukasir-RF3B-b238fad.bundle`; and the verified copy of `plan-p0-p11-review.patch` |
| Safety-copy policy | A new SSD bundle after every accepted RF checkpoint; earlier bundles and copies are kept and never deleted or overwritten automatically (D-065) |
| **Residual risks** | No technical branch protection is configured on GitHub (available for a public repository; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08); new commits are not in an SSD bundle until the next accepted RF checkpoint, and the D-080 bookkeeping commit is in none; the SSD protects against loss of the computer only while stored apart from it; published content cannot be withdrawn: what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076) |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub, intentionally, by the Owner's decision of 12 October 2026 (D-076), which supersedes the private visibility of D-065 (6 October 2026). Anything pushed is published; before any push the publication guard of D-076 applies (section 4). The rule of SECURITY_MODEL SEC-SECRET-05 stays: no document names a real server address, domain, provider identity, credential, key or production backup location, and the SSD location is not written in the repository |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

Once the D-080 bookkeeping change is committed (section 1):

1. For the planning agent: its review of the executor's report of that change against the repository.
2. For the Owner: accepting or rejecting the bookkeeping commit.
3. For the implementation executor: nothing.

Nothing else. The correction of R-2 (D-080), an SSD bundle of the bookkeeping commit (D-065) and every later step each need the Owner's separate, explicit authorization. His comprehensive UX review and his decision on gate P7-G1 are his to make (D-076). No work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-4B, RF-4C, RF-5, RF-6, RF-7 or any later re-foundation step. In particular: final copy and the glossary's shop terms (RF-4B); the design system and key screens (RF-4C); HTTP routes, the full interaction contracts and the P3 workflow contracts (RF-5); any ADR beyond ADR-008; the test strategy, backup and runbooks; the Task baseline.
- Treating gate P7-G1 as approved before the Owner's separate decision.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the recorded decisions.
- Design work, any access to the design tool, or any change to the CRM template pages (T-08). Adopting the "Golden Frontend Reference" named in D-077 (N-11).
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, executing any SQL (the DDL in SCHEMA is specification text only, plan decision P-7), version pins, dependency installation, infrastructure or database work; accounts or purchases of any service.
- The correction of the wording issue R-2, or any other change to the repository beyond the D-080 bookkeeping change, before the Owner authorizes it (D-080).
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
- D-080 — the Q38 checkpoint `d4c0594` and its SSD safety bundle `bantukasir-Q38-d4c0594.bundle` are accepted (17 October 2026); the Q38 amendment is complete; not pushed; the correction of the wording issue R-2 is deferred to a later change that the Owner authorizes separately.

D-019 to D-065 remain as recorded. The next free identifiers are D-081 and ADR-009.

Decisions pending: Owner questions 18 (before RF-6) and 20 (before RF-6); T-17 b and the application of D-069 (d) to the Owner replacement procedure (both before RF-5); items N-03, N-04, N-05, N-08 (GitHub branch protection, when convenient), N-09 (whether the local path of the RF-1 safety bundle stays in the public repository, before the first push after the RF-4A checkpoint), N-10 (the one-time audit of the published GitHub history, authorized separately and completed before the first push after the RF-4A checkpoint) and N-11 (the provenance and any adoption of the "Golden Frontend Reference", before RF-4C is authorized) in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md); the Owner's decision on gate P7-G1, after his comprehensive UX review (the Q38 checkpoint is accepted, D-080); the authorization of the deferred correction of R-2 (D-080); and, after RF-5's proposal, the Owner's revalidation that N-07 may need (D-074).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 was re-founded in RF-3A and RF-3B and is complete (D-064); P5 and P6 are re-founded in RF-3A and complete (D-058); P3 rests on legacy workflows that remain in force as amended by D-035, D-037 and D-040 (D-077 reverted the payment-method amendment of D-036), with the CURRENT documents prevailing where they disagree; P7 is rebuilt as RF-4A, RF-4B and RF-4C — RF-4A (checkpoint `c8dc234`, accepted, D-078) wrote the structure documents for gate P7-G1, which is not approved, and four of them are amended for Q38 (D-079; checkpoint `d4c0594`, accepted, D-080), and there is no approved visual design; P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices and the actual server: all open (OPEN_ITEMS section 3).
- Route contracts, interaction contracts at route level and the P3 workflow contracts do not exist yet (RF-5). The page inventory and navigation contracts exist at capability and command level (RF-4A; OPEN_ITEMS T-05). The logical schema exists (SCHEMA); no migration exists.
- RF-5 obligations recorded so far: the backend enforcement of D-067 and D-069 and of D-071 (race-safe and durable; the texts of BR-INV-24, REQ-INV-07, DOMAIN_MODEL section 5, SCHEMA section 5.7 and TECHNICAL_RULES section 7.2 brought in line); the enforcement of D-072 after a restore; the rule-text clarifications of D-068; the existing mechanisms that back the client rule NC-13 of NAVIGATION_CONTRACTS, which adds no server refusal — a new one would first need the Owner; the mechanisms of the server-authoritative recovery of D-073; the proposed backend mitigation of the gap of NC-13 across devices, with its tests (D-074, OPEN_ITEMS N-07); how the Owner resolves another user's uncertain intent and how BR-CASH-16 is met in an Owner-assisted close (ADR-007 section 5); the CSV column list and the receipt document contract. INFORMATION_ARCHITECTURE section 14 lists them with the items left to RF-4B and RF-4C.
- RF-6 inputs: the restore runbook items of SCHEMA section 16; the restore reconciliation of D-072 (onboarding) and of D-073 (requests refused as EPOCH_STALE, which keep their command family blocked under NC-13 until it has recorded their outcome), ordered so that the Owner's own user-management references are resolved before the security changes of SECURITY_MODEL SEC-OP-04 are re-applied; the operational rule of D-068 in the user guidance.
- Known gap, not an accepted risk: NC-13 blocks only in the browser that holds the unresolved request; on another device, in another browser or after cleared browser data a duplicate business action is not blocked until RF-5 mitigates it (D-074, OPEN_ITEMS N-07).
- G1 proposals accepted by the Owner (D-075): the combined account's home on Stok without an OPEN session, no role switcher, Tunai preselected on the payment screen; the keyboard shortcuts and the 600 and 1024 CSS px breakpoints as provisional, verified in RF-4C and at gate DEVICES; working labels provisional until P7-G2.
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository, a risk reduced by the SSD bundles and the push (D-027, D-065); no technical branch protection is configured on GitHub (D-065; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08), and published content cannot be withdrawn — what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- The correction of the wording issue R-2, which the Owner accepted before the D-080 bookkeeping change, is deferred; its text is not in the repository (D-080).
- Between accepted RF checkpoints, new commits are not yet in an SSD bundle (D-065).

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; logical schema only (SCHEMA); production DB touched: NO
AUTH:        LARAVEL_NATIVE; AICWDF-COMPAT-8; Google sign-in OFF (SECURITY_MODEL section 2)
UI / UX:     RF-4A structure documents in docs/07-ux-design, checkpoint c8dc234 accepted; four amended for Q38 (D-079; checkpoint d4c0594 accepted, D-080); gate P7-G1 NOT APPROVED; no copy deck and no visual design yet; V1 Indonesian only with translation keys from the first Task (D-050, ADR-005)
HOSTING:     Cloudflare, VPS, Coolify; staging on the same server (D-053, ADR-003); server facts UNKNOWN
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the D-080 bookkeeping change is committed (section 1): the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects it. Then come his comprehensive review of the UX documents and his decision on gate P7-G1, which names checkpoint `d4c0594` and the hash of each of the six documents (D-070, D-076, D-079). The deferred correction of R-2 and any SSD bundle of the bookkeeping commit each need his separate authorization (D-065, D-080). Before the first push: N-09, the N-10 audit and the publication guard of D-076.

If it is not committed, or a step that its instruction includes is not complete: stop and report to the Owner.

No step is authorized until the Owner gives a separate, explicit authorization for it. Until then, wait.

## 10. Do not

Do not start RF-4B or any later step. Do not use the design tool. Do not create migrations or execute the DDL in SCHEMA. Do not create an ADR beyond ADR-008. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push, except as an instruction that the Owner authorizes allows, and only after N-09, the N-10 audit and the publication guard of D-076. Do not adopt the "Golden Frontend Reference" (N-11). Do not delete or overwrite any safety bundle or copy. Do not correct R-2 before the Owner authorizes it.
