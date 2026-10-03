# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 3 October 2026 (Asia/Jakarta) by the planning architect at the end of RF-1 Part A.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale, one business, one stock pool
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-1 — Governance and structure (re-foundation plan, step 2 of 8)
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where RF-1 stands

RF-1 has two parts (see [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 5).

- **Part A — done.** The planning architect rewrote the three root documents, wrote twelve new documents, moved 72 legacy documents unchanged and updated two link targets in `assets/brand/README.md` (D-028). No Git mutation was performed.
- **Part B — the executor's checkpoint.** It is complete if, and only if, both of these are true:
  1. `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md` shows a commit whose parent is `604d9254f6a88958a1f1e36d54beabff54efdb96`;
  2. the paths `docs/archive/`, `prototypes/` and `.impeccable/` are absent from the working tree and `git remote` lists only `origin`.

  If this file is not yet committed, Part B has not been done: the working tree then shows 72 deleted paths, 84 new untracked files, four modified files (the three root documents and `assets/brand/README.md`) and the retired trees still present. That is the expected state between Part A and Part B, not damage. Do not repair it.
- **After Part B:** the planning architect checks the executor's report against the repository, and the Owner accepts or rejects the result. Only then is RF-1 complete.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| Parent baseline | `604d9254f6a88958a1f1e36d54beabff54efdb96` |
| `origin/plan/bantukasir-pos-v1` | `d41543d` — the local branch is ahead; nothing has been pushed since 21 September 2026 |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Untracked, preserved | `plan-p0-p11-review.patch` |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub (OF-01) |

Some tracked files have CRLF in the working tree and LF in the index. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: RF-1 Part B, and only when the Owner sends its instruction.
2. For the planning architect: review of the executor's Part B report.

## 4. Not authorized

- RF-2 or any later re-foundation step.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, dependency installation, infrastructure or database work.
- Push, merge, pull request, deployment, or any change to `main`.
- Deleting `plan-p0-p11-review.patch` or dropping `stash@{0}`.
- Line-ending normalization.

## 5. Decisions

Latest Owner decisions: D-019 to D-028 (3 October 2026) in [DECISIONS](../00-governance/DECISIONS.md). In short: full restructure on AICWDF-4.3; English repository documents; planner writes documents, executor validates and commits; patch and stash kept; no push; P7 rebuilt with three Owner gates; RF-1 authorized; safety bundle at `C:\Backups\BantuKasir`; RF-1 commits no broken link (two link targets in `assets/brand/README.md` updated).

Decisions pending: Owner questions 5 to 15 and 18 to 20, and items N-01, N-03, N-04, N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). None of them blocks RF-1. Questions 8 to 15 and 19 are needed before RF-2.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 is being re-founded; P1 to P6 rest on legacy documents that remain in force pending revalidation; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices, the actual server: all open (OPEN_ITEMS section 3).
- No route contracts, interaction contracts, page inventory, navigation contracts, glossary, lifecycle matrix, logical schema or authentication profile exist (OPEN_ITEMS section 5).
- Accepted risk: the RF-1 safety bundle is on the same physical drive as the repository (D-027).

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; production DB touched: NO
UI / UX:     no approved design; interface Indonesian first; localization for V1 undecided
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

The Owner reviews the Part A report and the Part B instruction, then sends the Part B instruction to Claude Code Desktop (Claude Opus 5.5, effort xhigh). After the executor reports, the Owner returns the report to the planning architect for review.

## 10. Do not

Do not start RF-2. Do not edit files under `legacy/`. Do not tidy the working tree with reset, clean or restore. Do not push.
