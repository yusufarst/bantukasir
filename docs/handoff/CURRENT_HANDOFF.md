# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 7 October 2026 (Asia/Jakarta) by the planning agent for the bookkeeping checkpoint that records D-064 and D-065. It describes the repository once that checkpoint is committed and pushed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         bookkeeping checkpoint for D-064 and D-065 (not an RF step) — the commit that contains this file; then its SSD bundle and push, the planning agent's review and the Owner's acceptance; RF-3B is accepted (D-064); RF-4 is not authorized
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 to RF-3B are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb` (accepted, D-055). RF-3A is commit `ee67b3aac58f24198fd8f617410e824bc7ca1119` (accepted, D-058). RF-3B is commit `b238fad4a9fde9f363124b365d088367a56d8168`, accepted by the Owner on 6 October 2026 after the planning agent's review (D-064); P4 is complete, and the legacy P4 documents are superseded as plan decision P-6 states.
- **Repository visibility and safety copies (D-065).** The repository has been private since 6 October 2026. On 7 October 2026 the history to `b238fad` was copied to a verified bundle on the Owner's external SSD and then pushed to GitHub by fast-forward.
- **Bookkeeping checkpoint.** The commit that contains this version of this file. Its parent is `b238fad4a9fde9f363124b365d088367a56d8168`. It records D-064 and D-065, resolves N-01, updates N-04 and the accepted risks, corrects the two documentation notes of D-064 and brings REFOUNDATION_PLAN and REQUIREMENTS in line; no legacy file is changed. The same instruction that creates it then makes a new SSD bundle and pushes the plan branch by fast-forward. To confirm, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - normally the first line is the bookkeeping checkpoint and the second line is `b238fad4a9fde9f363124b365d088367a56d8168`;
  - if the first line is `b238fad4a9fde9f363124b365d088367a56d8168`, the checkpoint is not committed yet: the changed files are modified or staged in the working tree. That is the state before or during the executor's checkpoint, not damage. Do not repair it.
- **After the checkpoint.** The planning agent reviews the executor's report against the repository and the SSD, and the Owner accepts or rejects the result. **RF-4** or any other later step needs a separate, explicit authorization. No later step is authorized.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| Bookkeeping checkpoint | The commit that contains this file; parent `b238fad4a9fde9f363124b365d088367a56d8168` |
| Earlier commits | `b238fad` RF-3B checkpoint, on `ee67b3a` RF-3A checkpoint, on `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | The bookkeeping checkpoint, once its instruction has pushed it by fast-forward after the new bundle is verified. Before that push: `b238fad` (pushed 7 October 2026, D-065), with the local branch one commit ahead. Check with `git ls-remote origin` |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| RF-1 safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit; it is on the same physical drive as the repository (D-027) |
| SSD safety copies | On the Owner's external SSD, a different physical disk; the location is held by the Owner and not recorded here (D-065). `bantukasir-RF3B-b238fad.bundle`: 33,458,599 bytes, SHA-256 `eee30cdb4bdf6ecfccf82e2acb1ea52fddca7b241b650b6b42c34b5eeedd55da`, holding `refs/heads/plan/bantukasir-pos-v1` at `b238fad`, `refs/heads/main` at `f7cecfe` and `refs/stash` at `f4c98df`; a verified copy of `plan-p0-p11-review.patch`. The bundle of this bookkeeping checkpoint is made by its instruction; its hash is in the planning agent's review, because a commit cannot contain the hash of a bundle that contains it |
| Safety-copy policy | A new SSD bundle after every accepted RF checkpoint; earlier bundles and copies are kept and never deleted or overwritten automatically (D-065) |
| **Residual risks** | No technical branch protection on the private repository (GitHub Free); new commits are not in an SSD bundle until the next accepted RF checkpoint; the SSD protects against loss of the computer only while stored apart from it; content public before 6 October 2026 cannot be withdrawn (D-065) |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Private on GitHub since 6 October 2026 (D-065; N-01 resolved). The rule of SECURITY_MODEL SEC-SECRET-05 stays: no document names a real server address, domain, provider identity, credential, key or production backup location, and the SSD location is not written in the repository |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: the bookkeeping checkpoint, only while it is not yet done (section 1) and only when the Owner sends its instruction after his second review. It is one commit, then one new SSD bundle verified with a restore test, then one ordinary fast-forward push of `plan/bantukasir-pos-v1` (D-065).
2. For the planning agent: changes to the bookkeeping working tree that the Owner's second review asks for, within D-064 and D-065; then review of the executor's report.

Nothing else. Once the bookkeeping checkpoint is reviewed and accepted, no work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-4, RF-5, RF-6, RF-7 or any later re-foundation step. In particular: any ADR beyond ADR-008, route or interaction contracts, page inventory, navigation contracts, the test strategy, backup and runbooks, and the Task baseline.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the recorded decisions.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, executing any SQL (the DDL in SCHEMA is specification text only, plan decision P-7), version pins, dependency installation, infrastructure or database work; accounts or purchases of any service.
- Any push other than the one fast-forward push of the plan branch in the bookkeeping checkpoint instruction; never `--force` or `--force-with-lease`, and never a push of `main`, a tag, the stash or a `refs/codex/` ref.
- Merge, rebase, pull request, deployment, history rewrite, or any change to `main`.
- Any bundle or other operation on the SSD beyond the one new bundle of that instruction; deleting or overwriting any safety bundle or safety copy; any destructive operation on the SSD.
- Deleting or editing any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 18, 20, 29 or 35, items N-03, N-04, N-05, T-14, T-15 or T-17 a, b, d, f and h, any gate, or any first-store fact.

## 5. Decisions

Latest Owner decisions (6 and 7 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md):

- D-064 — the RF-3B checkpoint `b238fad` is accepted; P4 is complete and the legacy P4 statuses of P-6 take effect; the two documentation notes (question 35 in TECHNICAL_RULES section 14, question 18 in SCHEMA section 16) are corrected in the bookkeeping change; future checkpoint instructions restrict repository-wide file searches to Git-based commands;
- D-065 — the repository is private (N-01, option 1A); the history to `b238fad` was copied to a verified, restore-tested bundle on the Owner's external SSD with a verified copy of the patch, and then pushed by fast-forward (option 2C, order S1); a new SSD bundle follows every accepted RF checkpoint, and no safety copy is deleted or overwritten automatically; this bookkeeping checkpoint is committed, bundled and then pushed in one bounded operation; residual risks recorded.

D-019 to D-063 remain as recorded; D-058 to D-063 accept RF-3A and authorize and shape RF-3B. The next free identifiers are D-066 and ADR-009.

Decisions pending: Owner questions 18 (before RF-6), 20 (before RF-6), 29 (before RF-5) and 35 (before RF-5; the schema allows either answer); items N-03, N-04 and N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 was re-founded in RF-3A — architecture, stack, modules, ADR-001 to ADR-007 — and RF-3B — the logical schema and ADR-008 — and is complete (D-064); P5 and P6 are re-founded in RF-3A and complete (D-058); P3 rests on legacy workflows that remain in force as amended by D-035, D-036, D-037 and D-040, with the CURRENT documents prevailing where they disagree; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices and the actual server: all open (OPEN_ITEMS section 3).
- No route contracts, interaction contracts, page inventory or navigation contracts exist (OPEN_ITEMS section 5). The logical schema exists (SCHEMA); no migration exists.
- T-17 a, b, d, f and h are open for RF-5. T-03, T-16 and T-17 c, e, g and i are resolved.
- Inputs recorded in SCHEMA for RF-6: the restore runbook advances the two number sequences and keeps traffic off until the after-restore procedure has run; the reviewed cost recovery procedure that may clear a "reporting incomplete" mark; the retention of the security log (SCHEMA section 16).
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository, a risk reduced by the SSD bundles and the push (D-027, D-065); the private repository has no technical branch protection, and content public before 6 October 2026 cannot be withdrawn (D-065); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- Between accepted RF checkpoints, new commits are not yet in an SSD bundle (D-065).

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; logical schema only (SCHEMA); production DB touched: NO
AUTH:        LARAVEL_NATIVE; AICWDF-COMPAT-8; Google sign-in OFF (SECURITY_MODEL section 2)
UI / UX:     no approved design; V1 Indonesian only with translation keys from the first Task (D-050, ADR-005)
HOSTING:     Cloudflare, VPS, Coolify; staging on the same server (D-053, ADR-003); server facts UNKNOWN
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the bookkeeping checkpoint is not yet committed (section 1): the Owner reviews the planning agent's report and its one checkpoint instruction a second time and sends the instruction to the executor.

If it is committed but its bundle or push is not complete: stop and report to the Owner; do not create the bundle or push without his instruction.

Once it is committed, bundled and pushed: the Owner returns the executor's report to the planning agent, which reviews it against the repository and the SSD; the Owner then accepts or rejects the result. After that, no step is authorized until the Owner gives a separate, explicit authorization. Until then, wait.

## 10. Do not

Do not start RF-4 or any later step. Do not create migrations or execute the DDL in SCHEMA. Do not create an ADR beyond ADR-008. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push, except the one fast-forward push in the bookkeeping checkpoint instruction. Do not delete or overwrite any safety bundle or copy.
