# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 4 October 2026 (Asia/Jakarta) by the planning agent for the governance checkpoint that records Owner decisions D-031 to D-040. It describes the repository once that checkpoint is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         none — RF-1 is complete (D-030); Owner decisions D-031 to D-040 are recorded by the governance checkpoint, the commit that contains this file; RF-2 is not authorized
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 and RF-1 are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e`, and its close-out is commit `77f66760f0e156775727cdbdf2bde1d431379704` (D-030).
- **Governance checkpoint.** The commit that contains this version of this file records Owner decisions D-031 to D-040 (DECISIONS sections 3 and 6), updates the governance documents, rewrites `assets/brand/README.md` and removes the LATANSA mark `assets/brand/source/latansa-official-colored-mark.png` from the current tree (D-038). Its parent is `77f66760f0e156775727cdbdf2bde1d431379704`. It exists only in this local repository. To confirm, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - normally the first line is the governance checkpoint and the second line is `77f66760f0e156775727cdbdf2bde1d431379704`;
  - if the first line is `77f66760f0e156775727cdbdf2bde1d431379704`, the checkpoint is not committed yet: the nine files it changes are modified in the working tree or staged, and the LATANSA mark may still be present. That is the state before or during the executor's checkpoint, not damage. Do not repair it.
- **Review of the checkpoint.** The planning agent reviews the executor's report against the repository, and the Owner accepts or rejects the result.
- **Next step: RF-2, product and domain.** The answers it needed are given. It needs the Owner's explicit authorization and is not authorized.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| Governance checkpoint | The commit that contains this file; parent `77f66760f0e156775727cdbdf2bde1d431379704` |
| Earlier commits | `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `d41543d` — the local branch is ahead; nothing has been pushed since 21 September 2026 |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| Safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub (OF-01); see OPEN_ITEMS N-01 before any push. Removing the LATANSA mark from the tree does not remove copies already published |

Some tracked files have CRLF in the working tree and LF in the index. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: the governance checkpoint commit, only while it is not yet committed (section 1) and only when the Owner sends its instruction.
2. For the planning agent: review of the executor's report on the governance checkpoint.

Nothing else. Once the governance checkpoint is committed and reviewed, no work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-2 or any later re-foundation step.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the recorded decisions.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, dependency installation, infrastructure or database work.
- Push, merge, pull request, deployment, history rewrite, or any change to `main`.
- Deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling questions 5, 6, 7, 18 or 20, or items N-01, N-03, N-04, N-05, T-14 or T-15, by assumption.

## 5. Decisions

Latest Owner decisions (3 and 4 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md) by the governance checkpoint:

- D-031 — agent model, effort and handoff convention (Opus 5.5 by default; Fable 5.1 for escalation only);
- D-032 — one account may hold CASHIER and OPERATIONS_ADMIN;
- D-033 — Operations sees and corrects purchase cost until confirmation;
- D-034 — Owner-only cost correction with replay; original facts and correction history retained;
- D-035 — Owner-only refunds, paid from an OPEN drawer or the Owner's own funds;
- D-036 — CASH, BANK_TRANSFER and STATIC_QRIS; no discount or price change at the till;
- D-037 — CSV import of new products; one optional photo per product;
- D-038 — one business per installation; client-neutral code; LATANSA mark out of the current tree;
- D-039 — PC and tablet as cashier devices; print templates; no tax calculation; sign-in timeouts;
- D-040 — one payment screen with one final action for an ordinary sale.

D-019 to D-030 remain as recorded.

Decisions pending: Owner questions 5, 6, 7, 18 and 20, and items N-01, N-03, N-04, N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Questions 5, 6, 7 and 18 are needed before RF-3; question 20 before RF-6. Product photos (D-037) add to the data to be backed up; that is input to question 18, not a decision on it.

Open facts, not decisions: the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 to P3 rest on legacy documents that remain in force as amended by D-032 to D-040; P4 to P6 rest on legacy documents that remain in force; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices, the actual server: all open (OPEN_ITEMS section 3).
- No route contracts, interaction contracts, page inventory, navigation contracts, glossary, lifecycle matrix, logical schema or authentication profile exist (OPEN_ITEMS section 5).
- Accepted risk: the RF-1 safety bundle is on the same physical drive as the repository (D-027).
- The RF-1 checkpoint, its close-out and the governance checkpoint exist only in this local repository: they are not pushed and are not in the safety bundle.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; production DB touched: NO
UI / UX:     no approved design; interface Indonesian first; localization for V1 undecided
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the governance checkpoint is not yet committed (section 1): the Owner sends its instruction to the executor (Opus 5.5, effort XHigh, new chat).

Once it is committed: the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the result. RF-2 needs a separate, explicit authorization from the Owner. Until then, wait.

## 10. Do not

Do not start RF-2. Do not edit files under `legacy/`. Do not tidy the working tree with reset, clean or restore. Do not push.
