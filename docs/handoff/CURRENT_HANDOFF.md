# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 4 October 2026 (Asia/Jakarta) by the planning agent for the RF-2 checkpoint (D-041). It describes the repository once that checkpoint is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-2, product and domain documentation consolidation (D-041) — its checkpoint is the commit that contains this file; waiting for the planning agent's review and the Owner's acceptance
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 and RF-1 are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e`, and its close-out is commit `77f66760f0e156775727cdbdf2bde1d431379704` (D-030).
- **Governance checkpoint.** Commit `4af59a5423298d25d672923279439951fc67ea4d` records Owner decisions D-031 to D-040. The Owner accepted it on 4 October 2026 after the planning agent's review; D-041 uses it as the RF-2 baseline.
- **RF-2 checkpoint.** The commit that contains this version of this file. Its parent is `4af59a5423298d25d672923279439951fc67ea4d`. It adds the eight CURRENT product and domain documents (`docs/01-product/`: PRODUCT_BLUEPRINT, REQUIREMENTS; `docs/02-domain/`: GLOSSARY, DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY, COSTING, SALES_CASH), records D-041 and the decisions D-042 to D-047 taken during RF-2, updates SOURCE_OF_TRUTH, LEGACY_INDEX, OPEN_ITEMS, REFOUNDATION_PLAN, this file and the root README, and marks the superseded legacy documents through SOURCE_OF_TRUTH and LEGACY_INDEX without changing them. It exists only in this local repository. To confirm, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - normally the first line is the RF-2 checkpoint and the second line is `4af59a5423298d25d672923279439951fc67ea4d`;
  - if the first line is `4af59a5423298d25d672923279439951fc67ea4d`, the checkpoint is not committed yet: the eight new documents are untracked or staged and the seven changed files are modified or staged in the working tree. That is the state before or during the executor's checkpoint, not damage. Do not repair it.
- **After the checkpoint.** The planning agent reviews the executor's report against the repository, and the Owner accepts or rejects the result. No later step is authorized. RF-3 needs the Owner's answers to questions 5, 6, 7, 18, 25 and 26 and a separate, explicit authorization.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-2 checkpoint | The commit that contains this file; parent `4af59a5423298d25d672923279439951fc67ea4d` |
| Earlier commits | `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `d41543d` — the local branch is ahead; nothing has been pushed since 21 September 2026 |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| Safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit |
| **Risk — local-only commits** | `3c94f4f`, `77f6676`, `4af59a5` and the RF-2 checkpoint exist only in this local repository: they are not pushed and not in the safety bundle. This is recorded so that the risk stays visible (D-041). It is not an authorization to push or to refresh the bundle |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub (OF-01); see OPEN_ITEMS N-01 before any push |

Some tracked files have CRLF in the working tree and LF in the index. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: the RF-2 checkpoint commit, only while it is not yet committed (section 1) and only when the Owner sends its instruction.
2. For the planning agent: review of the executor's report on the RF-2 checkpoint.

Nothing else. Once the RF-2 checkpoint is committed and reviewed, no work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-3 or any later re-foundation step.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the RF-2 checkpoint and the recorded decisions.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, dependency installation, infrastructure or database work.
- Push, merge, pull request, deployment, history rewrite, any change to `main`, or refreshing the safety bundle.
- Deleting any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 5, 6, 7, 18, 20, 25, 26 or 29, or items N-01, N-03, N-04, N-05, T-14, T-15, T-16 or T-17.

## 5. Decisions

Latest Owner decisions (4 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md):

- D-041 — RF-2 authorized as one documentation checkpoint, with the D-031 workflow clarification (Opus 5.5 by default; effort by complexity and risk; Fable 5.1 for escalation only);
- D-042 — master data and configuration are deactivated and reactivated, never deleted (question 21);
- D-043 — the Owner may anonymize a deactivated account without rewriting any historical record (question 22);
- D-044 — business records are kept at least 10 years; V1 has no deletion feature (question 23);
- D-045 — a drawer-funded refund never takes the session's expected cash below zero (question 24);
- D-046 — for a drawer-funded refund, cash leaves the drawer only after the refund has committed (question 27);
- D-047 — after the Owner corrects or completes a receiving cost, OPERATIONS_ADMIN sees the current cost with the Owner's mark and the cost at confirmation (question 28).

D-019 to D-040 remain as recorded.

Decisions pending: Owner questions 5, 6, 7, 18, 20, 25, 26 and 29, and items N-01, N-03, N-04, N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). The retention of uploaded CSV import files (T-17 c) is also the Owner's to decide. Questions 5, 6, 7, 18, 25 and 26 are needed before RF-3; question 29 before RF-5; question 20 before RF-6. Revalidation item R-17 is question 26.

Open facts, not decisions: the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 are re-founded in RF-2 as CURRENT documents and are complete on the Owner's acceptance of the RF-2 checkpoint; P3 rests on legacy workflows that remain in force as amended by D-035, D-036, D-037 and D-040, with the CURRENT P1 and P2 documents prevailing where they disagree; P4 to P6 rest on legacy documents that remain in force; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices, the actual server: all open (OPEN_ITEMS section 3).
- No route contracts, interaction contracts, page inventory, navigation contracts, logical schema or authentication profile exist (OPEN_ITEMS section 5). The glossary and the lifecycle matrix exist since RF-2.
- T-16 (representation of issue-cost allocations: the one captured at Sale commit, and those of manual issues and negative adjustments) and T-17 (lifecycle details without business meaning, except T-17 c) are open for RF-3 and RF-5.
- Accepted risk: the RF-1 safety bundle is on the same physical drive as the repository (D-027).
- The local-only commits listed in section 2 are not pushed and not in the safety bundle.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; production DB touched: NO
UI / UX:     no approved design; interface Indonesian first; localization for V1 undecided
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the RF-2 checkpoint is not yet committed (section 1): the Owner sends its instruction to the executor (Opus 5.5, effort XHigh, new chat).

Once it is committed: the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the result. After that, no step is authorized until the Owner gives a separate, explicit authorization. Until then, wait.

## 10. Do not

Do not start RF-3. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push.
