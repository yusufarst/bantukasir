# Current handoff

The current operational state of BantuKasir. This is the only document that says what is authorized now. Keep it short; history is in Git and in [DECISIONS](../00-governance/DECISIONS.md).

Written 18 October 2026 (Asia/Jakarta) by the implementation executor of RF-4C, at the Owner's pause of 17 October 2026 (D-086), on `6e73514`, the D-085 commit; the RF-4C work-in-progress checkpoint commits it. Section 1 shows how to confirm that the checkpoint is committed.

```text
PROJECT:              BantuKasir (codename) — goods-only point of sale; one business and one stock pool per installation
PROJECT STATE:        PARTIALLY_PLANNED — planning documents only; no application code
CURRENT STEP:         RF-4C, visual design for gate P7-G3 (D-086) — IN_PROGRESS — PAUSED BY OWNER on 17 October 2026; the work so far is in one local work-in-progress commit on 6e73514; gate P7-G1 is APPROVED (D-082); gate P7-G2 is APPROVED (D-086); gate P7-G3 is NOT APPROVED; RF-4C resumes only on the Owner's instruction
CURRENT TASK:         none — no Task baseline exists
```

## 1. Where the re-foundation stands

The steps are in [REFOUNDATION_PLAN](../00-governance/REFOUNDATION_PLAN.md) section 2.

- **RF-0 to RF-3B are done.** RF-1 is commit `3c94f4f5d45223468ae85844da6d37561faeab3e` with its close-out `77f66760f0e156775727cdbdf2bde1d431379704` (D-030). The governance checkpoint `4af59a5423298d25d672923279439951fc67ea4d` records D-031 to D-040. RF-2 is commit `0c4945d001daf76723568f015e69b6f6c43342fb` (accepted, D-055). RF-3A is commit `ee67b3aac58f24198fd8f617410e824bc7ca1119` (accepted, D-058). RF-3B is commit `b238fad4a9fde9f363124b365d088367a56d8168` (accepted, D-064).
- **Bookkeeping checkpoint.** Commit `86822d5620dd031c6998cf0f0be24b7715b3b887` records D-064 and D-065. The Owner accepted it on 11 October 2026 (D-066); its SSD bundle was verified and the plan branch was pushed to it by fast-forward.
- **RF-4A is done.** It wrote the six structure documents of [`docs/07-ux-design/`](../07-ux-design/) for gate P7-G1 and records D-066 to D-076. Its checkpoint `c8dc234c891228ec5a7f1d808f4231d2b8d89620` and its SSD safety bundle are accepted (D-078). REFOUNDATION_PLAN section 9 has the detail.
- **The Q38 amendment is done.** V1 supports CASH and BANK_TRANSFER only (D-077). Checkpoint `d4c0594f7b190677fa70b920ddc749c0bf8d980e` and its SSD safety bundle `bantukasir-Q38-d4c0594.bundle` are accepted (D-080); its bookkeeping commit `cdb8361f8c8258af447abe7c1e5f04ae031e72f1` is accepted (D-081). REFOUNDATION_PLAN section 10 has the detail.
- **Gate P7-G1 is APPROVED** (D-082): the six documents as committed in `d4c0594`, each named by its Git blob, size and SHA-256. They are unchanged and must stay byte-identical; their own gate rows still read "NOT APPROVED", and D-082 prevails.
- **RF-4B is done.** Its checkpoint `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c` added [COPY_DECK](../07-ux-design/COPY_DECK.md) and finalized the shop terms of [GLOSSARY](../02-domain/GLOSSARY.md) (D-083; accepted, D-084). Its correction `551b4eb69f17adff461eb894266031a6f9b0c6df` (D-084) is accepted (D-086). REFOUNDATION_PLAN section 11 has the detail.
- **Design-tool governance (D-085).** The Golden Frontend Reference v1.0.0 — the public repository `yusufarst/golden-frontend-reference`, tag `v1.0.0`, commit `14497d997ee7994dfa9153d41b8cea636578e24c` — is the approved reusable frontend reference; DesainPakeAI is no longer required ([TOOLCHAIN](../00-governance/TOOLCHAIN.md) section 3; AGENTS.md rules 5 and 6). The D-085 commit `6e73514564382b26bb441efaa58351b1da5290ea` is accepted (D-086).
- **Gate P7-G2 is APPROVED** (D-086, 17 October 2026): GLOSSARY and COPY_DECK as committed in `551b4eb`, each named by its Git blob, size and SHA-256 (GLOSSARY blob `00ef552d754c3fbdf8af0e3fe8ca8fbddb8bd0fa`, COPY_DECK blob `4bbb0099ec8f6777bd8e49eea96c66f28118eea2`; the SHA-256 values are in D-086). They must stay byte-identical; their own gate rows still read "NOT APPROVED", and D-086 prevails. A text that a later step needs and the deck does not name is added by change control (COPY_DECK section 1.1).
- **RF-4C (D-086) — IN_PROGRESS — PAUSED BY OWNER.** The Owner's instruction of 17 October 2026 authorized RF-4C on `6e73514`: the design system and renderable, nonproduction previews of key screens for gate P7-G3, adapted from the Golden reference, written and committed by the implementation executor. The same evening the Owner paused it ("OWNER OVERRIDE — GRACEFUL PAUSE / RF-4C CHECKPOINT") and asked for one local work-in-progress commit. REFOUNDATION_PLAN section 12 has the scope. To confirm the state, run `git log -1 --format=%H%n%P -- docs/handoff/CURRENT_HANDOFF.md`:
  - if the second line is `6e73514564382b26bb441efaa58351b1da5290ea`, the first line is the RF-4C work-in-progress commit: the pause checkpoint is committed;
  - if the first line is `6e73514564382b26bb441efaa58351b1da5290ea`, the checkpoint is not committed and its files are modified or untracked in the working tree. Stop and report to the Owner; do not repair it.
- **Next steps.** The Owner returns the executor's pause report to the planning agent, which reviews it against the repository; the Owner accepts or rejects the work-in-progress commit and decides when RF-4C resumes. An SSD bundle, any push and every later step each need a separate, explicit authorization (D-065).

### 1.1 RF-4C work at the pause

**Done (in the work-in-progress commit).**
- D-086 recorded; DECISIONS, REFOUNDATION_PLAN (section 12), SOURCE_OF_TRUTH, OPEN_ITEMS, REQUIREMENTS, the root README and this file brought in line.
- The Golden reference verified and read as D-086 records: tag and commit confirmed with `git ls-remote`; the 41 frozen files matched their scope list; only its generic layer read; no MULTIPLECORP material used.
- The preview foundation in `docs/07-ux-design/previews/assets/`:
  - `bk-tokens.css`: the tokens, with every BantuKasir value that differs from a reference default marked "BK:", and the constrained accent set in the brand slots — `green` (default and the accent of the pre-authentication pages), `teal`, `blue`, `purple`, `graphite`, values `accent_token` would hold (SCHEMA);
  - `bk-base.css`, `bk-components.css`: base layer and components;
  - `bk-copy.js`: 303 copy keys with COPY_DECK's wording, and 7 RF-4C proposals that COPY_DECK does not name (the five accent names "Hijau", "Toska", "Biru", "Ungu", "Grafit"; `reporting.finance.table_date` "Tanggal"; `ux.action.more_actions` "Tindakan lain") — proposals only, which enter COPY_DECK only by change control;
  - `bk-icons.js`: original icon drawings to the reference's icon anatomy;
  - `bk-app.js`: the shell built from one navigation table per role (INFORMATION_ARCHITECTURE section 4), dialogs that keep focus and close with Esc, the provisional shortcuts with the Ctrl+Enter repeat guard (UXA-09), and page modules for UXP-001, UXP-007, UXP-101 to UXP-105, UXP-401, UXP-405 and UXP-502.
- Three key-screen previews: `ks-03-kasir.html` (UXP-101 Kasir), `ks-04-pembayaran.html` (UXP-102 Pembayaran), `ks-05-struk.html` (UXP-103 Struk, with the 58 mm, 80 mm and A4 receipt).

**Partly done.**
- `ks-05-struk.html` is written but was not rendered or checked; the classes `bk-seg--three`, `bk-grid-2--receipt` and `bk-print-row` it uses have no styles yet, so the receipt-size choice shows two columns.
- `ks-04-pembayaran.html` was rendered only at a narrow width; its states (`short`, `transfer`, `submitting`, `uncertain`, `held`) were not reviewed.
- `bk-app.js` holds modules for seven pages whose HTML does not exist yet.
- Links to previews not yet written do not resolve yet: `ks-07-tutup-sesi.html` and `index.html#outside-set` from the HTML files, and the navigation built by `bk-app.js` to `ks-01-masuk.html`, `ks-02-beranda.html`, `ks-08-stok.html` and `ks-10-laporan-keuangan.html`.

**Not started.**
- Previews: `ks-01-masuk.html` (UXP-001), `ks-02-beranda.html` (UXP-007), `ks-06-buka-sesi.html` (UXP-104), `ks-07-tutup-sesi.html` (UXP-105), `ks-08-stok.html` (UXP-401), `ks-09-catat-barang-masuk.html` (UXP-405), `ks-10-laporan-keuangan.html` (UXP-502), the design-system specimen `ds-specimen.html`, the gallery `index.html` (the previews already link to `index.html#outside-set` for pages outside the set) and the review frame `frame.html`.
- The two gate P7-G3 documents: `docs/07-ux-design/DESIGN_SYSTEM.md` and `docs/07-ux-design/KEY_SCREENS.md`.
- The record in DECISIONS of the deviations from the reference's defaults and of RF-4C's design choices; the change of `legacy/DESIGN.md` to superseded in SOURCE_OF_TRUTH and LEGACY_INDEX once DESIGN_SYSTEM exists; the list of proposed copy for COPY_DECK change control.

**Choices made so far, to be written in DESIGN_SYSTEM and decided by gate P7-G3** (marked "BK:" in the stylesheets): the width classes 600 and 1024 CSS px (UXA-02) in place of the reference's 768 and 1025; no runtime density scaling, body text at least 14 px and secondary text at least 12 px (UXA-06); every target at least 44 × 44 px at every width and the final payment action at least 56 px (UXA-07); field and choice boundaries of 3.55:1 (UXA-18); at compact width a top bar with the menu button, the page title and the account menu, and a drawer, with no bottom navigation; side navigation with labels at medium and expanded width and an icon rail only on the till (UXA-03); the navigation badges of INFORMATION_ARCHITECTURE section 4; sub-navigation as tabs and, at compact width, a menu button; transitions removed under reduced motion (UXA-20); BantuKasir palettes in the brand slots and no lime call-to-action or signal colour; no list position numbers, toasts or command palette. Planned for the screens not yet built: the Beranda panel shows Penjualan neto, Jumlah penjualan, Pembayaran tunai and Pembayaran transfer bank with the as-of line; the trend chart shows one series, Penjualan neto per Asia/Jakarta business day; on receipts long names wrap and are never cut; the copy marker "Salinan" prints in sentence case, bold, in a box.

**Verified so far.**
- Every value of `bk-copy.js` stands on its key's line in COPY_DECK: 303 of 303 (checked again at the pause); the 7 proposals are absent from COPY_DECK.
- `node --check` passes for the three scripts.
- Contrast computed from the token values: white on the primary colour of each accent 6.69:1 to 11.80:1; focus ring at least 6.26:1 on the canvas; field boundary 3.55:1 on white; status text on its tint at least 4.99:1.
- `ks-03-kasir.html` rendered in the desktop app's browser pane at 1366 × 768, 768 × 1024 and 390 × 844, with no horizontal page scroll at 390, and the Inter font loaded.

**Still to verify.** Every screen and state at 1366 × 768, 1024 × 768, 768 × 1024, 640 × 400 (200 % zoom of 1280 × 800), 390 × 844 and 360 px; keyboard paths and the shortcuts F2, F8, Alt+1, Alt+2, Alt+U, Ctrl+Enter and Esc; dialog focus and return; accessible names, target sizes, minimum text sizes and rendered contrast; the absence of the words of GLOSSARY section 13 and of "BantuKasir"; the navigation per role against INFORMATION_ARCHITECTURE section 4; links; an independent review; the protected hashes and Git integrity at the final checkpoint.

**Viewing the previews.** Open the files in `docs/07-ux-design/previews/` directly in a desktop browser. Review parameters: `?state=…` (each file lists its states in a comment), `?peran=owner|ops|cashier|combined`, `?aksen=green|teal|blue|purple|graphite`. The previews load Inter from a pinned jsDelivr address for review only; offline they fall back to a size-adjusted Segoe UI. The desktop app's built-in browser pane opens a local file as a `data:` snapshot without its stylesheets and scripts; there the previews need a local static server. The paused session used a temporary `.claude/launch.json` and a scratch server script outside the repository; both were removed at the pause and are not committed.

**Resuming.** On the Owner's instruction to resume: confirm the branch and that HEAD is the work-in-progress commit; read D-086 and REFOUNDATION_PLAN section 12; finish the partly done and not-started items above; write DESIGN_SYSTEM and KEY_SCREENS; record the deviations and design choices in DECISIONS; run the checks still to verify; bring the registers in line and update this file last; then the RF-4C checkpoint commit. RF-4C then spans the work-in-progress commit and that checkpoint.

## 2. Git baseline

| Item | Value |
| --- | --- |
| Branch | `plan/bantukasir-pos-v1` |
| RF-4C work-in-progress checkpoint | One local commit on `6e73514` that adds the nine files of `docs/07-ux-design/previews/` (section 1.1) and changes DECISIONS, REFOUNDATION_PLAN, SOURCE_OF_TRUTH, OPEN_ITEMS, REQUIREMENTS, the root README and this file, and nothing else; it is HEAD once committed (section 1); not yet accepted |
| D-085 commit | `6e73514564382b26bb441efaa58351b1da5290ea`, accepted (D-086); parent `551b4eb69f17adff461eb894266031a6f9b0c6df`, tree `74dbac39abca42363dfe6843256311659d8aeddb` |
| RF-4B correction | `551b4eb69f17adff461eb894266031a6f9b0c6df`, accepted (D-086); parent `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, tree `f51964e6546a506457663193396d4fae70d68ba5`; the commit that gate P7-G2 names (D-086) |
| RF-4B checkpoint | `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c`, accepted (D-084); parent `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, tree `2bbb2b2dc55d4f591d11c344a4139d420c9f3d2a` |
| D-080 bookkeeping commit | `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, accepted (D-081); parent `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, tree `261e443522d9a589f8dce20d04f45a455b47ab7f` |
| Q38 checkpoint | `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, accepted with its SSD safety bundle (D-080); parent `c8dc234c891228ec5a7f1d808f4231d2b8d89620`, tree `14c66f89e1d3e0c0e614a3b397dbc8175cdc7923`; the commit that gate P7-G1 names (D-082) |
| Earlier commits | `c8dc234` RF-4A checkpoint (D-078), on `86822d5` bookkeeping checkpoint (D-066), on `b238fad` RF-3B checkpoint, on `ee67b3a` RF-3A checkpoint, on `0c4945d` RF-2 checkpoint, on `4af59a5` governance checkpoint (D-031 to D-040), on `77f6676` RF-1 close-out, on `3c94f4f` RF-1 checkpoint, on `604d925` |
| `origin/plan/bantukasir-pos-v1` | `86822d5` (pushed 11 October 2026, D-066), as the remote-tracking ref of this repository shows; the executor of RF-4C did not contact GitHub for this repository (it read the Golden repository there, read only). Not pushed: the RF-4A checkpoint, the Q38 checkpoint, the bookkeeping commit, the RF-4B checkpoint, its correction, the D-085 commit and the RF-4C work-in-progress commit. A push needs its own authorization and, before the first one, N-09 and the N-10 audit; the publication guard of D-076 runs immediately before it. Check with `git ls-remote origin` |
| `main`, `origin/main` | `f7cecfe` — obsolete baseline; must not be modified |
| Remotes | `origin` only |
| Untracked, preserved | `plan-p0-p11-review.patch` — kept until a later explicit Owner decision (D-030) |
| Untracked, not project content | `Claude outputs/`, if present (OPEN_ITEMS T-14); never staged |
| Stash, preserved | `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" — kept until a later explicit Owner decision (D-030) |
| Other local ref, preserved | `refs/codex/turn-diffs/checkpoints/…` — tool state of an earlier session, not project history (OPEN_ITEMS T-15) |
| RF-1 safety bundle | `C:\Backups\BantuKasir\bantukasir-pre-RF1-604d925.bundle`, 33,277,207 bytes, SHA-256 `dbdc889180bb2aa9e991e25017d6a5a7f3329b32d00b57c23af05c7881972cd0`. It holds every ref as it was before RF-1, including the stash, and no later commit; it is on the same physical drive as the repository (D-027) |
| SSD safety copies | On the Owner's external SSD, a different physical disk; the location is held by the Owner and not recorded here (D-065). Latest: `bantukasir-Q38-d4c0594.bundle`, 33,603,697 bytes, SHA-256 `2f38e04437e973fb6eb5d15c7342ee4ef17916c73674c5aea4f4b0c0ea72b549`, holding `refs/heads/plan/bantukasir-pos-v1` at `d4c0594`, `refs/heads/main` at `f7cecfe` and `refs/stash` at `f4c98df` (D-080). Kept: `bantukasir-RF4A-c8dc234.bundle` (D-078), `bantukasir-D065-bookkeeping.bundle` (D-066), the earlier `bantukasir-RF3B-b238fad.bundle` and the verified copy of `plan-p0-p11-review.patch`. The bookkeeping commit `cdb8361`, the RF-4B checkpoint, its correction, the D-085 commit and the RF-4C work-in-progress commit are in no bundle |
| Safety-copy policy | A new SSD bundle after every accepted RF checkpoint; earlier bundles and copies are kept and never deleted or overwritten automatically (D-065). Each SSD operation needs its own authorization |
| **Residual risks** | No technical branch protection is configured on GitHub (available for a public repository; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08); the commits from `cdb8361` to the RF-4C work-in-progress commit are in no SSD bundle; the SSD protects against loss of the computer only while stored apart from it; published content cannot be withdrawn: what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076) |
| Ignored, local only | `.local/`, `.agents/` |
| Repository visibility | Public on GitHub, intentionally, by the Owner's decision of 12 October 2026 (D-076), which supersedes the private visibility of D-065 (6 October 2026). Anything pushed is published; before any push the publication guard of D-076 applies (section 4). The rule of SECURITY_MODEL SEC-SECRET-05 stays: no document names a real server address, domain, provider identity, credential, key or production backup location, and the SSD location is not written in the repository |

In the device shell, run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true`; a plain `git status` there once left a stale `.git/index.lock`. Check for line-ending-only differences before treating a file as modified. The tracked text files are stored with LF line endings.

## 3. Authorized now

Once the RF-4C work-in-progress checkpoint is committed (section 1):

1. For the planning agent: its review of the executor's pause report against the repository.
2. For the Owner: accepting or rejecting the work-in-progress commit, and deciding when RF-4C resumes.
3. For the implementation executor: nothing until the Owner's instruction to resume RF-4C. The authorization of RF-4C (D-086) stays in force; on resumption section 1.1 says what remains.

Nothing else. An SSD bundle of the commits since `cdb8361` (D-065), the N-10 audit, any push and every later step each need the Owner's separate, explicit authorization.

## 4. Not authorized

- Resuming RF-4C before the Owner's instruction to resume it; RF-5, RF-6, RF-7 or any later re-foundation step. In particular: HTTP routes, the full interaction contracts, the document contracts and the P3 workflow contracts (RF-5); any ADR beyond ADR-008; the test strategy, backup, runbooks and user guidance; the Task baseline.
- Treating RF-4C as complete or gate P7-G3 as approved before the Owner's separate decision.
- Any change to the six P7-G1 documents, which D-082 approved by their hashes, or to GLOSSARY or COPY_DECK, which D-086 approved by their hashes, before the Owner authorizes it.
- Any change to product, domain, workflow, architecture, security, testing, operations or release content beyond the recorded decisions.
- Any use of the Golden Frontend Reference outside RF-4C or another step that the Owner authorizes; any change to the reference, or the use of another version or commit of it (D-085, TOOLCHAIN section 3). DesainPakeAI or any other design-generation tool; any access to the BantuKasir design project (TOOLCHAIN section 5). Any access to MULTIPLECORP, including the lab in which the reference was frozen (AGENTS.md rule 5).
- The Task baseline, any Task, any Build Unit.
- Application code, migrations, executing any SQL (the DDL in SCHEMA is specification text only, plan decision P-7), version pins, dependency installation, infrastructure or database work; accounts or purchases of any service.
- Any push without its own explicit authorization; before the first push, N-09 and the N-10 audit; and any push without the publication guard of D-076 immediately before it (a secret scan, a privacy check and a client-data check of everything the push would publish; a finding stops the push); never `--force` or `--force-with-lease`, and never a push of `main`, a tag, the stash or a `refs/codex/` ref.
- Merge, rebase, pull request, deployment, history rewrite, or any change to `main`.
- Any bundle or other operation on the SSD without its own authorization; deleting or overwriting any safety bundle or safety copy; any destructive operation on the SSD.
- Deleting or editing any legacy document, including the superseded ones; deleting `plan-p0-p11-review.patch` or dropping `stash@{0}` (D-030).
- Line-ending normalization.
- Settling by assumption questions 18 or 20, T-17 b, the application of D-069 (d) to the Owner replacement procedure (SEC-OP-03), items N-03, N-04, N-05, N-08, N-09, N-10, T-14 or T-15, any gate, or any first-store fact.

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
- D-086 — the D-085 commit `6e73514` and the RF-4B correction `551b4eb` are accepted; gate P7-G2 is APPROVED: GLOSSARY and COPY_DECK as committed in `551b4eb`, each named by its Git blob and SHA-256; RF-4C is authorized on `6e73514` with the Golden reference at `14497d9` and no design tool; the same day the Owner paused RF-4C with one local work-in-progress commit; RF-4C is IN_PROGRESS — PAUSED BY OWNER and gate P7-G3 is NOT APPROVED.

D-019 to D-065 remain as recorded. The next free identifiers are D-087 and ADR-009.

Decisions pending: the Owner's acceptance of the RF-4C work-in-progress commit and his instruction to resume RF-4C, then his decision on gate P7-G3 after RF-4C is complete; Owner questions 18 (before RF-6) and 20 (before RF-6); T-17 b and the application of D-069 (d) to the Owner replacement procedure (both before RF-5); items N-03, N-04, N-05, N-08 (GitHub branch protection, when convenient), N-09 (whether the local path of the RF-1 safety bundle stays in the public repository, before the first push after the RF-4A checkpoint) and N-10 (the one-time audit of the published GitHub history, authorized separately and completed before the first push after the RF-4A checkpoint) in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md); and, after RF-5's proposal, the Owner's revalidation that N-07 may need (D-074).

Open facts, not decisions: the server's provider, size and other services, and whether the domain is already on Cloudflare (gate TARGET); the number of products at go-live and whether a product list already exists (G2); whether one device is shared by several staff, the number of drawers and the usual opening float (G3); the receipt printer and paper width (DEVICES); the PKP/PPN status and tax-document needs (G1); whether LATANSA is the first installation. The first store's cashier devices are PC and tablet (OF-02), not yet verified. [PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 13 lists them.

## 6. Phase and Task status

Phase status is in REFOUNDATION_PLAN section 4. In summary: P0 was re-founded in RF-1; P1 and P2 in RF-2 (complete); P4 was re-founded in RF-3A and RF-3B and is complete (D-064); P5 and P6 are re-founded in RF-3A and complete (D-058); P3 rests on legacy workflows that remain in force as amended by D-035, D-037 and D-040 (D-077 reverted the payment-method amendment of D-036), with the CURRENT documents prevailing where they disagree; P7 is rebuilt as RF-4A, RF-4B and RF-4C — gate P7-G1 is approved (D-082), gate P7-G2 is approved (D-086), RF-4C is authorized and paused by the Owner with its work in progress committed locally (D-086), and there is no approved visual design; P8 to P10 are legacy drafts, P9 amended by ADR-002 and ADR-003; P11 is to be rebuilt.

```text
TASK PLAN
- Baseline total:  not defined (planning not complete)
- Done / Ready / In progress / Blocked / Verifying / Todo / Superseded:  none
```

## 7. Open blockers and gaps

- Business facts G1 to G4, real devices and the actual server: all open (OPEN_ITEMS section 3).
- RF-4C is paused: no design system document, no complete set of key screens and no record of its deviations exist yet (section 1.1). Nothing in `docs/07-ux-design/previews/` is authoritative.
- Route contracts, interaction contracts at route level and the P3 workflow contracts do not exist yet (RF-5). The page inventory and navigation contracts exist at capability and command level (RF-4A; OPEN_ITEMS T-05). The logical schema exists (SCHEMA); no migration exists.
- RF-5 obligations recorded so far: the backend enforcement of D-067 and D-069 and of D-071 (race-safe and durable; the texts of BR-INV-24, REQ-INV-07, DOMAIN_MODEL section 5, SCHEMA section 5.7 and TECHNICAL_RULES section 7.2 brought in line); the enforcement of D-072 after a restore; the rule-text clarifications of D-068; the existing mechanisms that back the client rule NC-13 of NAVIGATION_CONTRACTS, which adds no server refusal — a new one would first need the Owner; the mechanisms of the server-authoritative recovery of D-073; the proposed backend mitigation of the gap of NC-13 across devices, with its tests (D-074, OPEN_ITEMS N-07); how the Owner resolves another user's uncertain intent and how BR-CASH-16 is met in an Owner-assisted close (ADR-007 section 5); the CSV column list and the receipt document contract; the server-side allowlist check of the accent set that RF-4C defines (SCHEMA `accent_token`). INFORMATION_ARCHITECTURE section 14 lists the earlier ones.
- Copy carried forward by RF-4B (COPY_DECK section 21): to RF-5, the CSV column headers, the document contracts with the place of the copy marker, the reason parameter of `BARCODE_NOT_ELIGIBLE` and whether the CSV preview tells an inactive category from a missing one, the list of audit action codes and the labels of the outcome codes of Riwayat keamanan, the number in an anonymized account's neutral name, whether the reference of a drawer-funded refund is typed or generated, the wording of the Owner's control for another user's uncertain request and of the lost-reference rule, and any text the Owner-assisted close needs for BR-CASH-16; to RF-4C (in progress), the look of every text, the names of the accent colours, the figures of the Beranda panel, the series of the trend chart, the receipt layout of long names and the verification of the shortcuts; to RF-6, the evidence behind the system-status items and the wording of runbooks and user guidance; the hidden correction of an onboarding entry waits for T-17 b; tax text on documents waits for gate G1, and paper widths, printers and the tablet's keyboard for gate DEVICES. Findings F-01 to F-04 about the six P7-G1 documents change nothing until those documents are next revised under an authorization; F-02 goes to RF-5. Finding F-05 (D-084) goes to RF-5: INTERACTION_INVENTORY UXI-086 and, for UXP-006, its NAVIGATION_CONTRACTS row and its row button UXI-055 lead a status check that gets no answer to UXS-14, where a held request must stay UXS-15 with "Cek status" only; COPY_DECK section 14.2 already keeps the request's state.
- RF-6 inputs: the restore runbook items of SCHEMA section 16; the restore reconciliation of D-072 (onboarding) and of D-073 (requests refused as EPOCH_STALE, which keep their command family blocked under NC-13 until it has recorded their outcome), ordered so that the Owner's own user-management references are resolved before the security changes of SECURITY_MODEL SEC-OP-04 are re-applied; the operational rule of D-068 in the user guidance.
- Known gap, not an accepted risk: NC-13 blocks only in the browser that holds the unresolved request; on another device, in another browser or after cleared browser data a duplicate business action is not blocked until RF-5 mitigates it (D-074, OPEN_ITEMS N-07).
- G1 proposals accepted by the Owner (D-075): the combined account's home on Stok without an OPEN session, no role switcher, Tunai preselected on the payment screen; the keyboard shortcuts and the 600 and 1024 CSS px breakpoints as provisional, verified in RF-4C and at gate DEVICES.
- Accepted risks: the RF-1 safety bundle is on the same physical drive as the repository, a risk reduced by the SSD bundles and the push (D-027, D-065); no technical branch protection is configured on GitHub (D-065; whether to enable it is open for the Owner, D-076, OPEN_ITEMS N-08), and published content cannot be withdrawn — what was public before 6 October 2026 and everything on GitHub since the repository became public again, including the commits pushed while it was private (D-065, D-076); staging shares the production server (D-053); Owner lockout recovery needs a person with server access, and no account has a second factor (D-048, D-049); whoever holds a staff member's one-time link can act as that person until noticed (D-056).
- Between accepted RF checkpoints, new commits are not yet in an SSD bundle (D-065); today the commits from `cdb8361` to the RF-4C work-in-progress commit are in none.

## 8. State of the usual handoff fields

```text
TOOLCHAIN:   nothing installed; frontend reference: Golden Frontend Reference v1.0.0 at commit 14497d9, read only (D-085), verified and read in RF-4C (D-086); DesainPakeAI not used; see TOOLCHAIN.md
COST:        no recurring cost incurred; no paid exception
DATABASE:    none exists; logical schema only (SCHEMA); production DB touched: NO
AUTH:        LARAVEL_NATIVE; AICWDF-COMPAT-8; Google sign-in OFF (SECURITY_MODEL section 2)
UI / UX:     RF-4A structure documents in docs/07-ux-design, gate P7-G1 APPROVED (D-082); COPY_DECK and the GLOSSARY shop terms, gate P7-G2 APPROVED (D-086); RF-4C IN_PROGRESS — PAUSED BY OWNER, work in progress in docs/07-ux-design/previews (not authoritative); gate P7-G3 NOT APPROVED; no approved visual design; V1 Indonesian only with translation keys from the first Task (D-050, ADR-005)
HOSTING:     Cloudflare, VPS, Coolify; staging on the same server (D-053, ADR-003); server facts UNKNOWN
TESTS:       none exist; nothing has been run
```

## 9. Safe next action

If the RF-4C work-in-progress checkpoint is committed (section 1): the Owner returns the executor's pause report to the planning agent, which reviews it against the repository; the Owner then accepts or rejects the work-in-progress commit and decides when RF-4C resumes. On his instruction to resume, the implementation executor follows section 1.1 ("Resuming"). An SSD bundle of the commits since `cdb8361` needs his separate authorization (D-065). Before the first push: N-09, the N-10 audit and the publication guard of D-076.

If it is not committed, or a step that the pause instruction includes is not complete: stop and report to the Owner.

No step is authorized until the Owner gives a separate, explicit authorization for it. Until then, wait.

## 10. Do not

Do not resume RF-4C or start any later step without the Owner's instruction. Do not treat RF-4C as complete or gate P7-G3 as approved. Do not use DesainPakeAI or any other design-generation tool (D-085). Do not change the six P7-G1 documents, GLOSSARY or COPY_DECK. Do not create migrations or execute the DDL in SCHEMA. Do not create an ADR beyond ADR-008. Do not edit files under `legacy/`, and do not delete the superseded legacy documents. Do not tidy the working tree with reset, clean or restore. Do not push, except as an instruction that the Owner authorizes allows, and only after N-09, the N-10 audit and the publication guard of D-076. Do not modify the Golden Frontend Reference, use another version or commit of it, or adopt its MULTIPLECORP business examples, branding, fixtures or workflows; use it only inside a step that the Owner authorizes (D-085). Do not delete or overwrite any safety bundle or copy.
