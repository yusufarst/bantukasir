# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 3 October 2026 (Asia/Jakarta) by the planning architect for the RF-1 close-out.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale, one business, one stock pool
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         none — RF-1 is complete (D-030); RF-2 is not authorized
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 and RF-1 are done.** RF-1 (governance and structure) is commit `3c94f4f5d45223468ae85844da6d37561faeab3e`. The planning architect checked the executor's report against the repository, and the Owner accepted the result (D-030).
- **RF-1 close-out.** After the acceptance, the planning architect updated five documents: this handoff, DECISIONS (D-029, D-030), SOURCE_OF_TRUTH and REFOUNDATION_PLAN (one wording correction and the RF-1 status), and OPEN_ITEMS (T-12). The executor commits them as one commit. Run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - if the second line is `3c94f4f5d45223468ae85844da6d37561faeab3e`, the close-out is committed;
  - if the first line is `3c94f4f5d45223468ae85844da6d37561faeab3e`, the close-out is not yet committed and the working tree shows five modified documents. That is expected, not damage. Do not repair it.
- **Next step: RF-2, product and domain.** Not authorized. It needs the Owner's answers to questions 8 to 15 and 19 and an explicit authorization.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-1 checkpoint | `3c94f4f5d45223468ae85844da6d37561faeab3e`, parent `604d9254f6a88958a1f1e36d54beabff54efdb96` |
| `origin/plan/bantukasir-pos-v1` | `d41543d` — the local branch is ahead; nothing has been pushed since 21 September 2026 |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only; `upstream` was removed in RF-1 (D-022) |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| Safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash. It does not hold the RF-1 checkpoint or any later commit |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub (OF-01); see OPEN_ITEMS N-01 before any push |

Some tracked files have CRLF in the working tree and LF in the index. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: the RF-1 close-out commit, only while it is not yet committed (section 1) and only when the Owner sends its instruction.
2. For the planning architect: review of the executor's close-out report.

Nothing else. Once the close-out is committed and reviewed, no work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-2 or any later re-foundation step.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, dependency installation, infrastructure or database work.
- Push, merge, pull request, deployment, or any change to `main`.
- Deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.

## 5. Decisions

Latest Owner decisions (3 October 2026) in [DECISIONS](../00-governance/DECISIONS.md): D-029, the RF-1 Part B instruction was authorized for execution; D-030, RF-1 Part B was accepted, the close-out was authorized, and the patch and the stash stay preserved until a later explicit Owner decision. D-019 to D-028 remain as recorded.

Decisions pending: Owner questions 5 to 15 and 18 to 20, and items N-01, N-03, N-04, N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Questions 8 to 15 and 19 are needed before RF-2.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 to P6 rest on legacy documents that remain in force pending revalidation; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices, the actual server: all open (OPEN_ITEMS section 3).
- No route contracts, interaction contracts, page inventory, navigation contracts, glossary, lifecycle matrix, logical schema or authentication profile exist (OPEN_ITEMS section 5).
- Accepted risk: the RF-1 safety bundle is on the same physical drive as the repository (D-027).
- The RF-1 checkpoint and the close-out commit exist only in this local repository: they are not pushed and are not in the safety bundle.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; production DB touched: NO
UI / UX:     no approved design; interface Indonesian first; localization for V1 undecided
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the close-out is not yet committed (section 1): the Owner reviews the close-out and its instruction, sends the instruction to Claude Code Desktop (Claude Opus 5.5, effort xhigh), and returns the executor's report to the planning architect for review.

After that: the Owner answers questions 8 to 15 and 19 and decides whether to authorize RF-2. Until then, wait.

## 10. Do not

Do not start RF-2. Do not edit files under `legacy/`. Do not tidy the working tree with reset, clean or restore. Do not push.
