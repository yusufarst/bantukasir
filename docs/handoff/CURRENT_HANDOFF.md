# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 4 October 2026 (Asia/Jakarta) by the planning agent for the RF-3A checkpoint (D-055). It describes the repository once that checkpoint is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-3A, architecture, security and technical-rule foundation (D-055) — its checkpoint is the commit that contains this file; waiting for the planning agent's review and the Owner's acceptance
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0, RF-1 and RF-2 are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb`, accepted by the Owner on 4 October 2026 after the planning agent's review (recorded in D-055).
- **RF-3A checkpoint.** The commit that contains this version of this file. Its parent is `0c4945d001daf76723568f015e69b6f6c43342fb`. It adds twelve CURRENT files — `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/STACK_AND_MODULES.md`, `docs/05-security/SECURITY_MODEL.md`, `docs/06-api-performance/TECHNICAL_RULES.md`, `docs/adr/README.md` and ADR-001 to ADR-007 — records D-048 to D-057, updates the governance, product and domain documents in the parts that concern RF-3A, and corrects the localization wording of `AGENTS.md` and `docs/00-governance/PROJECT_RULES.md` (D-057). Legacy documents are marked per section through SOURCE_OF_TRUTH and LEGACY_INDEX without being changed. It exists only in this local repository. To confirm, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - normally the first line is the RF-3A checkpoint and the second line is `0c4945d001daf76723568f015e69b6f6c43342fb`;
  - if the first line is `0c4945d001daf76723568f015e69b6f6c43342fb`, the checkpoint is not committed yet: the twelve new files are untracked or staged and the changed files are modified or staged in the working tree. That is the state before or during the executor's checkpoint, not damage. Do not repair it.
- **After the checkpoint.** The planning agent reviews the executor's report against the repository, and the Owner accepts or rejects the result. **RF-3B** (logical schema) needs the Owner's acceptance of the RF-3A checkpoint **and** a separate, explicit authorization (plan decision P-1). No later step is authorized.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-3A checkpoint | The commit that contains this file; parent `0c4945d001daf76723568f015e69b6f6c43342fb` |
| Earlier commits | `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `d41543d` — the local branch is ahead; nothing has been pushed since 21 September 2026 |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| Safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit |
| **Risk — local-only commits** | `3c94f4f`, `77f6676`, `4af59a5`, `0c4945d` and the RF-3A checkpoint exist only in this local repository: they are not pushed and not in the safety bundle, which sits on the same physical drive as the repository (D-027). This is recorded so that the risk stays visible (D-055). It is not an authorization to push or to refresh the bundle |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub (OF-01); see OPEN_ITEMS N-01 before any push. RF-3A documents name no real server address, domain, provider identity, credential or production backup location; the local path of the RF-1 safety bundle above was recorded before RF-3A (D-027), and whether it stays in a public repository falls under N-01 |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified.

## 3. Authorized now

1. For the implementation executor: the RF-3A checkpoint commit, only while it is not yet committed (section 1) and only when the Owner sends its instruction. The planning agent prepares that instruction only after the Owner's final second review of the amended RF-3A report (D-057).
2. For the planning agent: review of the executor's report on the RF-3A checkpoint.

Nothing else. Once the RF-3A checkpoint is committed and reviewed, no work is authorized until the Owner authorizes the next step.

## 4. Not authorized

- RF-3B, RF-4 or any later re-foundation step. In particular: `docs/04-architecture/SCHEMA.md`, any ADR beyond ADR-007, the representation of cost allocations (T-16), items T-17 e, g and i, and the mapping of invariants to the schema.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the RF-3A checkpoint and the recorded decisions.
- Design work or any access to the design tool.
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, version pins, dependency installation, infrastructure or database work; accounts or purchases of any service.
- Push, merge, pull request, deployment, history rewrite, any change to `main`, or refreshing the safety bundle.
- Deleting or editing any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 18, 20 or 29, items N-01, N-03, N-04, N-05, T-14, T-15, T-16 or T-17, any gate, or any first-store fact.

## 5. Decisions

Latest Owner decisions (4 October 2026), recorded in [DECISIONS](../00-governance/DECISIONS.md):

- D-048 — exactly one active Owner account; Owner recovery and replacement by documented operator procedure (question 25);
- D-049 — sign-in profile: email and password, no email sent, no Google, Owner-issued one-time links, operator recovery, AICWDF-COMPAT-8, no two-factor authentication; FD-03 and FD-04 approved (question 6);
- D-050 — translation keys from the first Task; V1 Indonesian only, no language switcher; FD-02 approved (question 5);
- D-051 — the Owner may move a misassigned manufacturer barcode (question 26);
- D-052 — a retired manufacturer barcode is re-attached only as the Owner's verified misassignment correction (question 31);
- D-053 — hosting topology: Cloudflare, VPS, Coolify, staging on the same VPS; FD-06 withdrawn (question 7);
- D-054 — uploaded CSV import files are not kept after the command's final outcome (question 30);
- D-055 — RF-3A authorized; RF-2 accepted; plan decisions P-1 to P-5; RF-3B not authorized;
- D-056 — residual risk of Owner-issued one-time links accepted for V1 (N-06); no other authentication channel authorized;
- D-057 — second review of the RF-3A report: substantively accepted subject to the T-18 wording remediation; onboarding-return interpretation accepted; RF-3B not authorized.

D-019 to D-047 remain as recorded.

Decisions pending: Owner questions 18 (before RF-6), 20 (before RF-6) and 29 (before RF-5); items N-01, N-03, N-04 and N-05 in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 is re-founded in part in RF-3A — architecture, stack, modules and ADRs — with the logical schema to follow in RF-3B, while the legacy schema and constraint parts stay in force; P5 and P6 are re-founded in RF-3A and are complete on the Owner's acceptance of the RF-3A checkpoint; P3 rests on legacy workflows that remain in force as amended by D-035, D-036, D-037 and D-040, with the CURRENT documents prevailing where they disagree; P7 is to be rebuilt and has no approved design; P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices and the actual server: all open (OPEN_ITEMS section 3).
- No logical schema (T-03, RF-3B), route contracts, interaction contracts, page inventory or navigation contracts exist (OPEN_ITEMS section 5).
- T-16 (representation of issue-cost allocations) and T-17 e, g and i are open for RF-3B; T-17 a, b, d, f and h for RF-5.
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository (D-027); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- The local-only commits listed in section 2 are not pushed and not in the safety bundle.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; production DB touched: NO
AUTH:        LARAVEL_NATIVE; AICWDF-COMPAT-8; Google sign-in OFF (SECURITY_MODEL section 2)
UI / UX:     no approved design; V1 Indonesian only with translation keys from the first Task (D-050, ADR-005)
HOSTING:     Cloudflare, VPS, Coolify; staging on the same server (D-053, ADR-003); server facts UNKNOWN
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the RF-3A checkpoint is not yet committed (section 1): the Owner completes the final second review of the amended RF-3A report (D-057); the planning agent then prepares one executor instruction; the Owner sends it to the executor.

Once it is committed: the Owner returns the executor's report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the result. After that, no step is authorized until the Owner gives a separate, explicit authorization. Until then, wait.

## 10. Do not

Do not start RF-3B. Do not create `SCHEMA.md`. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push.
