# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 5 and 6 October 2026 (Asia/Jakarta) by the planning agent for the RF-3B checkpoint (D-059). It describes the repository once that checkpoint is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-3B, logical database schema (D-059) — its checkpoint is the commit that contains this file; waiting for the planning agent's review and the Owner's acceptance
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 to RF-3A are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb` (accepted, D-055). RF-3A is commit `ee67b3aac58f24198fd8f617410e824bc7ca1119`, accepted by the Owner on 5 October 2026 after the planning agent's review (D-058).
- **RF-3B checkpoint.** The commit that contains this version of this file. Its parent is `ee67b3aac58f24198fd8f617410e824bc7ca1119`. It adds two CURRENT files — `docs/04-architecture/SCHEMA.md` and `docs/adr/ADR-008-cost-allocation-representation.md` — records D-058 to D-063, and updates the governance, product, domain, architecture, security and technical-rule documents in the parts that concern RF-3B. It writes the final status of the legacy documents `02-ARCHITECTURE` and `P4-ARCHITECTURE`, which takes effect when the Owner accepts the checkpoint (plan decision P-6); no legacy file is changed. It exists only in this local repository. To confirm, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - normally the first line is the RF-3B checkpoint and the second line is `ee67b3aac58f24198fd8f617410e824bc7ca1119`;
  - if the first line is `ee67b3aac58f24198fd8f617410e824bc7ca1119`, the checkpoint is not committed yet: the two new files are untracked or staged and the changed files are modified or staged in the working tree. That is the state before or during the executor's checkpoint, not damage. Do not repair it.
- **After the checkpoint.** The planning agent reviews the executor's report against the repository, and the Owner accepts or rejects the result. On acceptance P4 is complete and the legacy P4 documents are superseded as P-6 states. **RF-4** or any other later step needs a separate, explicit authorization. No later step is authorized.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-3B checkpoint | The commit that contains this file; parent `ee67b3aac58f24198fd8f617410e824bc7ca1119` |
| Earlier commits | `ee67b3a` RF-3A checkpoint, on `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `d41543d` — the local branch is ahead; nothing has been pushed since 21 September 2026 |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| Safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit |
| **Risk — local-only commits** | `3c94f4f`, `77f6676`, `4af59a5`, `0c4945d`, `ee67b3a` and the RF-3B checkpoint exist only in this local repository: they are not pushed and not in the safety bundle, which sits on the same physical drive as the repository (D-027). This is recorded so that the risk stays visible (D-055). It is not an authorization to push or to refresh the bundle |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub (OF-01); see OPEN_ITEMS N-01 before any push. The RF-3A and RF-3B documents name no real server address, domain, provider identity, credential, key or production backup location; the local path of the RF-1 safety bundle above was recorded before RF-3A (D-027), and whether it stays in a public repository falls under N-01 |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: the RF-3B checkpoint commit, only while it is not yet committed (section 1) and only when the Owner sends its instruction. The Owner's second review of the RF-3B report passed (D-063); the planning agent prepares that one instruction, and the Owner reviews it before sending it.
2. For the planning agent: changes to the RF-3B working tree that the Owner's second review asks for, within D-059; then review of the executor's report on the RF-3B checkpoint.

Nothing else. Once the RF-3B checkpoint is committed and reviewed, no work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-4, RF-5, RF-6, RF-7 or any later re-foundation step. In particular: any ADR beyond ADR-008, route or interaction contracts, page inventory, navigation contracts, the test strategy, backup and runbooks, and the Task baseline.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the RF-3B checkpoint and the recorded decisions.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, executing any SQL (the DDL in SCHEMA is specification text only, plan decision P-7), version pins, dependency installation, infrastructure or database work; accounts or purchases of any service.
- Push, merge, rebase, pull request, deployment, history rewrite, any change to `main`, or refreshing the safety bundle.
- Deleting or editing any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 18, 20, 29 or 35, items N-01, N-03, N-04, N-05, T-14, T-15 or T-17 a, b, d, f and h, any gate, or any first-store fact.

## 5. Decisions

Latest Owner decisions (5 and 6 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md):

- D-058 — the RF-3A checkpoint `ee67b3a` is accepted; the final second review of the RF-3A report was PASS; process note on D-031 (Opus 5.5 by default, Fable 5.1 only for escalation with a recorded reason);
- D-059 — RF-3B authorized on `ee67b3a`, with plan decisions P-6 to P-9; RF-4 not authorized;
- D-060 — the issue-cost allocation recorded at commit is an immutable fact, and the corrected value is a separate projection that each replay replaces (question 33);
- D-061 — a saleable return is linked to the original issue allocation and recomputed with it; cost restored exactly once (question 34);
- D-062 — Sale and cash session numbers are unique, allocated in increasing order with gaps allowed, and carry the Asia/Jakarta business date; allocation order, not commit order (question 32);
- D-063 — second review of the RF-3B report: PASS with clarifications — no "reporting incomplete" mark after pre-replay lock contention (`SERVICE_BUSY`), a refused reversal or a correction refused on a reconciliation-blocked product; corrections and reversals refused while a product is reconciliation-blocked; the number's date part is not a sort key; question 35 deferred to before RF-5; no executed SQL in future planning or review work unless the Owner authorizes it.

D-019 to D-057 remain as recorded. The next free identifiers are D-064 and ADR-009.

Decisions pending: Owner questions 18 (before RF-6), 20 (before RF-6), 29 (before RF-5) and 35 (before RF-5; raised in RF-3B, the schema allows either answer); items N-01, N-03, N-04 and N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 is re-founded in RF-3A — architecture, stack, modules, ADR-001 to ADR-007 — and RF-3B — the logical schema and ADR-008 — and is complete on the Owner's acceptance of the RF-3B checkpoint; P5 and P6 are re-founded in RF-3A and complete (D-058); P3 rests on legacy workflows that remain in force as amended by D-035, D-036, D-037 and D-040, with the CURRENT documents prevailing where they disagree; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

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
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository (D-027); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- The local-only commits listed in section 2 are not pushed and not in the safety bundle.

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

If the RF-3B checkpoint is not yet committed (section 1): the Owner's second review of the RF-3B report passed (D-063); the planning agent prepares one executor instruction; the Owner reviews it and sends it to the executor.

Once it is committed: the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the result. After that, no step is authorized until the Owner gives a separate, explicit authorization. Until then, wait.

## 10. Do not

Do not start RF-4 or any later step. Do not create migrations or execute the DDL in SCHEMA. Do not create an ADR beyond ADR-008. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push.
