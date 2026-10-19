# TASK-002 evidence — P7 and copy change control, batch 1

| | |
| --- | --- |
| Task | [TASK-002](../tasks/TASK-002-p7-change-control-1.md), size M, review class O, lane D |
| Status | `VERIFYING`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Instruction | The Owner's instruction of 19 October 2026, "TASK-002: P7 and copy change control, batch 1 — executor instruction (parallel to TASK-003)", prepared by the planning agent the same day and sent by the Owner with his approval of its whole content |
| Branch | `task/TASK-002-p7-change-control-1`, created from `develop` (EXC-22) in a linked worktree, the folder `bantukasir-task002` beside the main working tree, which is left to TASK-003 |
| Base commit | `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, the D-098 bookkeeping commit on `develop` |
| Executor | Implementation executor in Claude Code Desktop on the Owner's Windows machine; Claude Opus 5.5 (`claude-opus-5-5`); effort High, as the Owner's instruction names it (D-031, D-095), and the desktop app's record of the session shows effort `high`; a new session dedicated to TASK-002, separate from the TASK-003 session |
| Gates | GATE-P11 APPROVED (D-092); GATE-BRANCH SATISFIED (D-094); GATE-PUBLISH SATISFIED (D-096, D-097); EXECUTOR checked at the start; GATE-CC1 is the Owner's approval of this package by hash, after this Task |
| Authorized beyond local commits | The publication guard, non-force pushes of the Task branch, one pull request into `develop` marked as awaiting GATE-CC1, and watching its CI. No merge |

Times are Asia/Jakarta. Statuses use the vocabulary of [TEST_STRATEGY](../../08-testing/TEST_STRATEGY.md) section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`).

## 1. Start of the Task (EXC-20 to EXC-24)

- **Session start.** 19 October 2026 at 22:04.
- **Git state**, checked read-only in the main working tree before the worktree was created: branch `develop`; `develop` and `origin/develop` at `fc0055b49e9d179b5b7ed7d766121150dbd5bf66`; nothing staged or modified; untracked exactly `Claude outputs/` and `plan-p0-p11-review.patch`; `stash@{0}` present; one worktree; no branch `task/TASK-002-…` locally or on GitHub (only `task/TASK-001-toolchain-ci` there); the folder `bantukasir-task002` beside it absent. The main working tree was still on `develop`, so the TASK-003 session had not created its branch yet; it was not touched.
- **Prerequisites (EXC-21).** TASK-002 depends on no Task; its row was `READY`; GATE-P11 APPROVED, GATE-BRANCH and GATE-PUBLISH SATISFIED.
- **Worktree.** `git -C <main working tree> worktree add -b task/TASK-002-p7-change-control-1 <the sibling folder bantukasir-task002> fc0055b49e9d179b5b7ed7d766121150dbd5bf66`, at 22:05.
- **Start recorded (EXC-24).** The Task's first commit sets TASK-002 `IN_PROGRESS` in TASK_PLAN section 4 (totals recounted from the 50 rows: `TODO` 47, `READY` 1, `IN_PROGRESS` 1, `DONE` 1) and creates this file.

## 2. The Owner's answers (contract item 2; AC-002-04)

Asked in the executor's session in Bahasa Indonesia, one question at a time, on 19 October 2026 after the start commit, both answered by 22:13, through the desktop app's question dialog. Each question is quoted as asked; each answer is the Owner's selection, quoted verbatim with the preview he selected.

### 2.1 Question 1 — the close-refusal message (D-088), with the GLOSSARY gate row

Question 1, as asked: "Saat penutupan sesi kas ditolak karena masih ada checkout yang belum selesai (PENDING atau HELD), apakah kasir perlu pesan khusus, atau cukup pesan yang sudah ada: 'Ada transaksi baru; hitung ulang kas'? (D-088)"

Options offered: "A — Pesan khusus" (a variant message inside UXS-19, not a new state, code or button; the page chooses it when the reloaded close preview shows an unresolved checkout; the proposed wording in the preview, for the Owner to confirm or change) and "B — Pesan yang ada".

The Owner's answer, verbatim: "A — Pesan khusus", with the selected preview:

```text
Kunci: ux.state.recount.unresolved_checkouts

Judul:
  Ada penjualan yang statusnya belum pasti

Isi:
  Sesi kas masih dibuka karena ada penjualan yang
  statusnya belum pasti. Cek status penjualan itu
  dulu, lalu hitung ulang uang di laci dan tutup lagi.

Tindakan: — (tetap di langkah 1 dengan pratinjau baru;
  penjualan yang belum pasti tampil dengan "Cek status")

Sumber: D-088, G-27, RACE-35, INTERACTION_CONTRACTS 2.5
```

The question on GLOSSARY's gate row, asked with question 1 as the instruction requires: "Baris Gate di GLOSSARY masih tertulis 'artifacts ready for the Owner's review — NOT APPROVED', padahal D-086 sudah menyetujui P7-G2. Apakah baris itu diperbarui dalam paket ini? (Jika ya, hash GLOSSARY berubah dan ikut disetujui ulang di GATE-CC1.)"

The Owner's answer, verbatim: "Ya, perbarui".

### 2.2 Question 2 — the backup store's usage on the system status

Question 2, as asked: "Apakah halaman Status sistem menampilkan pemakaian penyimpanan backup? (ADR-010 item 11 menyatakan ya; OPERATIONS_RUNBOOK bagian 5 menyatakan itu bukan item tersendiri.)"

Options offered: "A — Tampilkan" (applied in COPY_DECK, a new key and text, and in OPERATIONS_RUNBOOK section 5, the evidence and threshold) and "B — Jangan tampilkan".

The Owner's answer, verbatim: "A — Tampilkan", with the selected preview:

```text
Usulan (dikonfirmasi lewat GATE-CC1):

Item:  config.system_status.backup_store
       Penyimpanan cadangan
Nilai: config.system_status.backup_store_usage
       Terpakai :ukuran dari :total

Berhasil: pemakaian di bawah 80 % kuota
Gagal:    80 % kuota atau lebih (retensi dikurangi
          oleh operator, BACKUP_RECOVERY bagian 6)
Belum Terverifikasi: pemakaian tidak dapat diukur

Sumber: ADR-010 item 11, REQ-OPS-04
```

## 3. The package (contract items 1, 3, 4 and 5)

Built on 19 October 2026 between 22:13 and 22:36 and committed in two change commits: `7a37b9e916f1c6d0ab3964926efb4de4d69b5fdf` ("TASK-002: apply change-control batch 1 to COPY_DECK, GLOSSARY, the P7-G1 documents and OPERATIONS_RUNBOOK"), parent `a30809ffc995cbe576b009cbff169639c6ccd200`, the start commit; and `1af8473008496b754b811d3ca7a88d82de09f146` ("TASK-002: cite a source decision for every change-control entry of COPY_DECK"), which the self-review added because entry CC-20 cited no decision (AC-002-03). The package commit is `1af8473`.

The change list with its sources is [COPY_DECK](../../07-ux-design/COPY_DECK.md) section 22.1, entries CC-01 to CC-20, each with its reason and source decision; section 22.2 lists what is read with the later decisions but left for a later change control, because neither this Task's contract nor INTERACTION_CONTRACTS section 10 names it. In short:

| Document | Changed |
| --- | --- |
| COPY_DECK | Gate, status and decisions rows (F-01); section 1.5 (F-04, closed by D-086); the percent format, `ux.action.more_actions`, the accent names, `reporting.period.label` and `reporting.finance.table_date` (T-19); the keys of INTERACTION_CONTRACTS section 11 and the DC-04 headers (T-20); the Owner's two answers (sections 11.11, 12 and 13.1); section 15 (F-02); sections 19 to 21 (F-03, coverage, findings); the new section 22 |
| USER_TASKS, INFORMATION_ARCHITECTURE, NAVIGATION_CONTRACTS, INTERACTION_INVENTORY, UI_STATES, ADAPTIVE_AND_ACCESSIBILITY | Gate and status rows (F-01); the working labels of COPY_DECK section 19 (F-03); in INTERACTION_INVENTORY and NAVIGATION_CONTRACTS the rows of INTERACTION_CONTRACTS section 10 (F-02 and F-05 included) and INTERACTION_INVENTORY sections 3, 5 and 6 |
| GLOSSARY | Gate and status rows only, at the Owner's request (section 2.1) |
| OPERATIONS_RUNBOOK | Status row and section 5: the fifth item of the system status, by the Owner's answer (section 2.2) |

Not changed: DESIGN_SYSTEM, KEY_SCREENS and the previews (D-087); DECISIONS, OPEN_ITEMS T-19 and T-20 and SOURCE_OF_TRUTH section 3, whose P7 rows record approvals and so belong to the bookkeeping of gate GATE-CC1 after the Owner's approval; every file outside `docs/`.

## 4. Checks (contract section 7; AC-002-05, AC-002-06)

Run by the implementation executor on 19 October 2026, with Node.js 24.18.0 on the host and Git 2.55.0.windows.3, in the worktree, on the documents as committed in `7a37b9e` and again, after the correction of CC-20, in `1af8473`; the results below are those at `1af8473`. The scripts are the executor's own and are kept outside the repository; each reads the committed files and prints its result.

| Check | Method | Result |
| --- | --- | --- |
| Hashes of the approved artifacts | For each of the 29 artifacts in the hash tables of D-082, D-086 and D-087, read from DECISIONS at the commit measured: the blob of the path at the commit, its size and the SHA-256 of its bytes, compared with the approved values. Sanity run on the base `fc0055b` first: 29 of 29 equal | `PASS` — at `1af8473`: the 21 unchanged artifacts are byte-identical to their approved blobs (DESIGN_SYSTEM, KEY_SCREENS and the 19 preview files; the previews' tree is still `61f48f92fba53111738b66ec3330beb8431a9f57`); the 8 changed artifacts are measured in section 5 |
| Links | Every relative Markdown link outside code spans and blocks resolves to an existing file, and its heading anchor, if any, to a heading of that file | `PASS` — 0 broken, on the 11 files this Task changes |
| Table shape | Every row of each table has the header's cell count, escaped pipes and pipes inside code spans excluded | `PASS` — 0 defects |
| Line endings and whitespace | No CR byte; no trailing whitespace; a final newline; no other control character | `PASS` — 0 defects |
| The checker's negative sample | A file with a CR, a trailing space, a row with an extra cell, a broken link and a broken anchor, written in a temporary folder outside the repository | `PASS` — all five defects reported |
| Key coverage | Every dotted key in backticks of a Task contract (`docs/11-tasks/tasks/`) whose first segment is a translation file of COPY_DECK section 1.1 exists in COPY_DECK: literally, as the parent of a named key, or inside a named family (`ux.format.*`) | `PASS` — 14 names found: 13 copy keys, all present; `sales.id` (TASK-028, "Keys completed") is a database column, not a copy key. On the base `fc0055b` the same check reports 11 missing: the 10 keys that TASK-002's own contract names and `ux.disabled.unresolved_checkouts` of TASK-030 |
| Interaction coverage | Every UXI-001 to UXI-385 named in COPY_DECK sections 4 to 11; the rows with "No copy" | `PASS` — 385 of 385; "No copy" only for UXI-244 and UXI-348 |
| Working labels left | Each working label of COPY_DECK section 19 searched in quotation marks in the six P7-G1 documents | `PASS` — none left; the remaining matches are the page title "Status sistem", which section 19 keeps, and the user-task names of USER_TASKS, which are not interface text |
| INTERACTION_INVENTORY section 3 | Re-derived from the 385 rows of section 2: the status column, and as "disabled with a reason" a Working row whose Condition states a disabled condition or UXS-13. The same rule on the approved blob reproduces its list of 48 exactly | `PASS` — 383 working, 49 of them disabled with a reason (UXI-076 out by F-02; UXI-150 and UXI-228 in), 0 hidden by design, 2 not provided, 385 rows |
| Every label replacement | Each replacement asserted its expected number of occurrences before anything was written | `PASS` — no mismatch |
| Standing obligations of EXECUTION_CONTEXT section 10 | This Task adds no route, table, command, read, page, procedure or document | `N/A` |

**Re-run, fail-closed (19 October 2026, 22:46).** On the Owner's instruction, every check above ran again on the 12 files changed since `fc0055b` (the package and the documents of the close), each script exiting non-zero on any failure and each exit code read on its own, with no pipe after a check. The run also covered: the worktree path in CURRENT_HANDOFF section 2, compared byte by byte (an earlier edit of that row had lost its backslashes and left a control character; corrected before any commit); no control character in any changed file; the package files in the working tree equal to `1af8473`; INTERACTION_INVENTORY section 3 equal to the derivation, list included, with a negative sample that must fail; the TASK_PLAN totals equal to its 50 rows; no changed file outside the documents of contract section 11; in TASK_PLAN only the TASK-002 row and the totals changed; `origin/develop` still `fc0055b`. Result: 16 of 16 `PASS`. Two failures of the run were defects of the executor's own checks, not of the documents, each fixed and re-run: the list comparison of the section 3 assertion kept the table's closing pipe, and a pattern lost its backslashes in the shell.

## 5. Hashes at the package commit (contract item 6; AC-002-05)

Measured on `1af8473008496b754b811d3ca7a88d82de09f146` on 19 October 2026 at 22:36: the blob that the commit records for the path, its size and the SHA-256 of its bytes. The working-tree files are byte-identical to these blobs.

### 5.1 Changed artifacts: the package for gate GATE-CC1

| Document | Approved by | Git blob at `1af8473` | Bytes | SHA-256 of the committed content |
| --- | --- | --- | --- | --- |
| `docs/07-ux-design/USER_TASKS.md` | D-082 | `46578840baf44fdfc20d464a93441c411a9c136a` | 19,281 | `07016e5c55eb2fcb01a932bf17c40d8ebeae51394cb8c102327bead5cc7f595b` |
| `docs/07-ux-design/INFORMATION_ARCHITECTURE.md` | D-082 | `a8c5656d2924a08a97c2e0d1c9a21ddd26f4f75a` | 67,912 | `616c4fb33762a5f05329d77b7fcff9074eaa64f8592202775f21e11825fd8a46` |
| `docs/07-ux-design/NAVIGATION_CONTRACTS.md` | D-082 | `660792f9ee804e0d82221655e365f83bf4b980b5` | 41,838 | `61a472e7263c29370aec40f779aa820d44007e30aa00027e455800f3b84d7be3` |
| `docs/07-ux-design/INTERACTION_INVENTORY.md` | D-082 | `e9ff14be7c70d32ae5905225e09362bc769527fa` | 171,475 | `3fefb52eea0a6694d0666ebf9e4d921b8a41cfaeb71efe8c21a44eaceaad96d9` |
| `docs/07-ux-design/UI_STATES.md` | D-082 | `99108c805260526ddb6233dc633b439aab8c63ae` | 39,768 | `b64424a625b128cfbba5befde317703a6c5eaa5886ad475b85a429bfc20266db` |
| `docs/07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md` | D-082 | `aaee7044e1f7a8e682ad05d5f4bbb947736cf744` | 18,394 | `54f8e206090a02847db3f802bb290f3665c89b1816ae1fdc6da01ae8a8045f70` |
| `docs/02-domain/GLOSSARY.md` | D-086 | `fa002797363d01a1071f3c2bbf9c332839e02497` | 27,435 | `524afefbd639b1d5d71b28197eefe2d0ffc569381e4dbdac4a531dd36a295bf8` |
| `docs/07-ux-design/COPY_DECK.md` | D-086 | `9fbb5f6b59dcdd2df3c7be090bbbe4a690c13a62` | 196,929 | `c146a9a8b5065ba4c93e914f8777b33e9dc3bd70fcbe383dcd848dd0b930aa48` |

OPERATIONS_RUNBOOK is not an approved P7 artifact; its section 5 changes only by the Owner's answer. The seven other documents have the same values at `7a37b9e`; COPY_DECK there had blob `35f91e41fe0f2b20f6b67cb247244c23a9c363a9`, 196,876 bytes, SHA-256 `ebb09727da31612dce7bd1a79bdef9ad806767d9278fae783495598b7d46d54f`, superseded by the correction. For the record, OPERATIONS_RUNBOOK at `1af8473`: blob `ab503187e08e98d0d785896aa27db6eb6de0ca56`, 21,733 bytes, SHA-256 `2694267a8335e391de22b98f67901b762c279494e397187678c26b5d8fed2797`.

### 5.2 Unchanged approved artifacts

Each is byte-identical at `1af8473` to the blob, size and SHA-256 that D-087 approved: `docs/07-ux-design/DESIGN_SYSTEM.md` (`35f756723d6518359daf24a9b78f33e250360780`), `docs/07-ux-design/KEY_SCREENS.md` (`2bbb7513f161bab13fe4144c1901fe0bae6a3f15`) and the 19 files of `docs/07-ux-design/previews/`, whose tree is `61f48f92fba53111738b66ec3330beb8431a9f57` as at `7318322`. D-087's table holds every value, unchanged.

## 6. Proposed record of the approval (D-099)

A proposal in the form of D-082, D-086 and D-087, for the Owner's decision. It is not in DECISIONS and approves nothing until the Owner gives it (contract item 6).

> **D-099 — Approval of gate GATE-CC1, P7 and copy change control batch 1.** The Owner approves the package of TASK-002 as committed in `1af8473008496b754b811d3ca7a88d82de09f146` on `task/TASK-002-p7-change-control-1`: the eight documents of section 5.1, each named by its Git blob, size and SHA-256; the Owner's answers recorded in section 2 (the dedicated close-refusal message `ux.state.recount.unresolved_checkouts` with its wording; the backup store's usage as the fifth system-status item; GLOSSARY's gate row updated); the change list of COPY_DECK section 22.1, CC-01 to CC-20. DESIGN_SYSTEM, KEY_SCREENS and the previews stay as D-087 approved them. The texts added become approved copy (COPY_DECK section 1.1), and OPEN_ITEMS T-19 and T-20 are resolved, except the shortcuts and breakpoints of gate DEVICES (TASK-047, gate GATE-CC2). Before the merge into `develop`, `develop` is merged into the branch and every artifact is measured again on the commit to be merged; a value that differs is approved again (EXECUTION_CONTEXT EXC-37; contract item 7).

## 7. Readings and technical choices

- **The close-refusal message** is a second text of UXS-19 under its parent key (`ux.state.recount.unresolved_checkouts`), in the pattern of `ux.state.session_closed.till`. The page chooses it when the close preview reloaded on `RECOUNT_REQUIRED` lists an unresolved checkout (INTERACTION_CONTRACTS RC-03, its client row). No state, code or control is added.
- **`ux.disabled.unresolved_checkouts` replaces `cash.close.disabled_unresolved_checkout`** for UXI-103, because INTERACTION_CONTRACTS section 11 maps the proposal to UXI-103 and UXI-150; no Task contract names the old key, and no key is implemented yet.
- **Two "Alasan" labels** (`inventory.onboarding.withdraw_zero_review.reason`, `inventory.opening_entry.replace_reason`) are added beyond the proposals of INTERACTION_CONTRACTS section 11, because RC-14 and RC-48 carry a `reason`. The sub-keys of the two corrections follow the deck's existing pattern (`inventory.onboarding.release_review.*`, `inventory.onboarding.released`).
- **The fifth system-status item** reads `store_bytes` of the newest successful `backup_runs` row (SCHEMA section 5.12), which BACKUP_RECOVERY section 6 already records; its threshold is the 80 % of ADR-010 item 11. INFORMATION_ARCHITECTURE's purpose of UXP-611 ("Backup, restore, disk space and reconciliation health") and `config.settings.status_desc` cover it without change.
- **"Change only the rows and labels named"** is read strictly. The rows of the six documents that later decisions also make stale, but that neither the contract nor INTERACTION_CONTRACTS section 10 names, are listed in COPY_DECK section 22.2 and left unchanged; placing them is the planning agent's. Inside INTERACTION_INVENTORY, the decision-trace rows of section 5 and the command row of UXI-291 in section 6 follow from the rows applied and change with them.
- **The user-task names** of USER_TASKS ("Penyesuaian stok", "Pembatalan mutasi" and others) are not interface text (COPY_DECK section 20) and are kept; only the control label in its step budget, "Tutup sesi kas", changed.
- **GLOSSARY's gate row** changed because the Owner asked for it; its terms are unchanged.

## 8. Acceptance criteria

| Criterion | Status | Evidence |
| --- | --- | --- |
| AC-002-01 | `PASS`, with the Owner's approval of the texts pending at gate GATE-CC1 | Every proposal of DESIGN_SYSTEM section 13 and INTERACTION_CONTRACTS section 11 and every DC-04 header has its key, text and source in COPY_DECK (CC-03 to CC-12); the percent format is in section 1.3; key coverage `PASS` (section 4). The texts are approved copy only when the Owner approves the package by hash |
| AC-002-02 | `PASS` | Every row of INTERACTION_CONTRACTS section 10 applied (CC-17); F-01, F-02, F-03 and F-05 applied, F-04 recorded as needing no change because D-086 closed it (CC-02); INTERACTION_INVENTORY section 3 re-derived from its rows (CC-18; section 4) |
| AC-002-03 | `PASS` | No page, control, state, navigation path, business rule or visual design beyond those rows: the close-refusal message is a second text of UXS-19, the fifth status item is the Owner's answer, and no P7-G3 artifact changed (section 5.2); every entry of COPY_DECK section 22.1 cites a decision (after `1af8473`) |
| AC-002-04 | `PASS` | Both answers verbatim in section 2 and applied: CC-13, CC-14; GLOSSARY's gate row at the Owner's request (CC-01) |
| AC-002-05 | `PASS` | Section 5: 21 unchanged approved artifacts byte-identical to their approved blobs; 8 changed artifacts listed with blob, size and SHA-256 at the package commit |
| AC-002-06 | `PASS` | Section 4: links, table shape, LF and trailing whitespace on all 11 changed files, with the checker's negative sample |
| AC-002-07 | `NOT_RUN` | Waits for the merge into `develop`, which is not authorized by this instruction (contract item 7; EXC-36, EXC-37) |

## 9. Definition of Done

| Dimension | Status | Reason |
| --- | --- | --- |
| Business | `N/A` | A documentation Task; it adds no rule. The Owner's two answers apply existing decisions (D-088, ADR-010) |
| UI and UX | `PASS` | The approved P7 artifacts change only as contract section 4.1 says (sections 3 and 4) |
| Backend | `N/A` | No code |
| Database | `N/A` | No migration; the fifth status item reads a column SCHEMA already has |
| Security | `PASS` | No secret, credential, address or personal datum in the changed documents; the publication guard before the push checks the range again (section 10) |
| Data integrity | `N/A` | Writes no business fact |
| Performance | `N/A` | No code |
| Operations | `PASS` | The Owner's answer applied to OPERATIONS_RUNBOOK section 5; no backup or runbook procedure changes |
| Verification | `PASS` for the checks; the Owner's approval by hash pending | Hashes, links, table shape and key coverage checked (section 4); gate GATE-CC1 is the Owner's decision on section 5.1 |

The Task stays `VERIFYING` until the planning agent's review, the Owner's approval by hash (GATE-CC1) and the merge into `develop` (AC-002-07), each on a separate instruction.

## 10. Self-review, and what is not verified

- **Self-review** of the whole diff against the contract (EXC-70): it found one defect, entry CC-20 of COPY_DECK section 22.1 without a source decision, corrected in `1af8473`, after which every check of section 4 ran again. The row rewrites were compared with INTERACTION_CONTRACTS sections 2, 9 and 10 and with CAP-15 and CAP-17 of SECURITY_MODEL.
- **Not verified:** whether the readings left for a later change control (COPY_DECK section 22.2) are complete — they were found while applying the named rows, not by a full review of the six documents against every later decision; the wording itself, which is the Owner's to approve; nothing was rendered, because no preview changed.
- **Not done here, each on its own instruction:** the planning agent's review; the Owner's approval by hash and its record as D-099; the bookkeeping of the approval in DECISIONS, OPEN_ITEMS T-19 and T-20, SOURCE_OF_TRUTH section 3, TASK_PLAN and CURRENT_HANDOFF; the merge of `develop` into the branch and the re-measurement (contract item 7); the merge into `develop`.
- **The main working tree** was read only, before the worktree was created; no file there was changed by this Task.

## 11. Publication (contract section 4.1 item 6; EXC-31, EXC-32)

Commit identifiers in sections 11.1 and 11.2 are those of the original local commits, which section 11.4 replaces; none of them was ever pushed.

### 11.1 Publication guard, first run (19 October 2026, 22:50)

Over the four commits then on the branch, `d9e22d0`, `205fc60`, `9b83d95` and `8bdacbb`, against the baseline of D-097 as D-098 extends it:

- **Repository secret scan:** `scripts/checks/secret-scan.sh` in the existing image `bantukasir-app:local` (Gitleaks 8.30.1), by `docker run --rm --network none`, with the worktree mounted read-only and, because a linked worktree keeps its Git data in the main repository, that repository's `.git` mounted read-only too; Git was pointed at them through environment variables only. Tree and commits: no leaks.
- **Gitleaks with its default rules only** (a temporary configuration inside the container holding only `useDefault = true`, so that `.gitleaks.toml` is not loaded): no leaks.
- **Privacy and client-data check:** one finding outside the baseline. The executor had written the local paths of the worktree and of the main working tree, without a user name, in seven places of this evidence and of CURRENT_HANDOFF; D-097 accepts such paths only where they already are (baseline item 4; N10-03). The push was stopped and the seven places were replaced by neutral references in a further local commit, `9b61d98`.
- No binary file; the Owner's identity on every commit; no trailer and no AI attribution in a message.

### 11.2 Publication guard, second run (19 October 2026, 23:00): push stopped

With every validation of section 4 run again fail-closed (17 of 17 `PASS`) and the privacy check reading every line that any commit adds, not only the net difference, the guard failed: the two paths were still added by `d9e22d0` (3 lines) and `8bdacbb` (4 lines), although `9b61d98` removed them from the current text. The push was stopped; the stop was recorded in a local commit, `cf2fac8`; the Owner was asked to accept the paths as published history or to authorize a rewrite of the unpublished commits.

### 11.3 The Owner's authorization

Given in the executor's session on 19 October 2026, verbatim:

1. "Saya mengizinkan penulisan ulang commit TASK-002 yang belum dipublikasikan agar dua path lokal hilang dari riwayat. Jelaskan dulu langkah persisnya untuk saya setujui sebelum dijalankan."
2. After the executor's plan of steps 0 to 6: "Saya menyetujui rencana penulisan ulang langkah 0 sampai 6 persis seperti dijelaskan. Jalankan secara fail-closed; push dan buat PR hanya jika semua pemeriksaan PASS. Berhenti di VERIFYING, jangan merge."

### 11.4 The rewrite (19 October 2026, 23:07 to 23:10)

- **Step 0, safety copy.** A Git bundle of `origin/develop..cf2fac8`, created outside the repository and verified with `git bundle verify` (it holds `cf2fac8` and requires `fc0055b`), SHA-256 `5561be90650dc5a0c3eadd383b0de26a46e0bd861aa40c33b917d7a9b3a185bd`. No ref or branch was added to the repository; the original commits also stay in the local reflog.
- **Step 1, rebuilt commits.** With plumbing only (`git hash-object -w`, a temporary index, `git write-tree`, `git commit-tree`), each original commit was rebuilt on `fc0055b` in order: its tree, with the local paths in this file and in CURRENT_HANDOFF replaced by the neutral references of `9b61d98`; in the rebuilt `8bdacbb`, the identifiers of the three earlier commits replaced by those of their rebuilt commits, so that sections 3 to 6 and CURRENT_HANDOFF name commits of the branch. Messages, author names, addresses and dates unchanged; the committer is the Owner's identity. `9b61d98` and `cf2fac8` are not carried over: this section replaces their content.
- **Step 2, branch and worktree.** `git update-ref` of `refs/heads/task/TASK-002-p7-change-control-1` from `cf2fac8` to the rebuilt `8bdacbb`, with the old value checked, then `git read-tree -m -u` from the old tree to the new one; the worktree was clean before and after.
- **Step 3, verification**, fail-closed: 28 of 28 `PASS` — each rebuilt commit differs from its original only in this file and CURRENT_HANDOFF; message, author and author date identical; no local path in the lines any rebuilt commit adds; the eight artifacts of the package in `1af8473` identical to those in `9b83d95`, so every blob, size and SHA-256 of section 5 is unchanged; the working tree equal to HEAD.

| Original commit (local, never pushed) | Rebuilt commit | Subject |
| --- | --- | --- |
| `d9e22d0e98058d2023900f0a4ada77ae0f73b4b7` | `a30809ffc995cbe576b009cbff169639c6ccd200` | TASK-002: start |
| `205fc601cb1253b9a834bc548533b52ab572cab5` | `7a37b9e916f1c6d0ab3964926efb4de4d69b5fdf` | TASK-002: apply change-control batch 1 to COPY_DECK, GLOSSARY, the P7-G1 documents and OPERATIONS_RUNBOOK |
| `9b83d9591057f4ea96c0e0430a48e20dee6e8827` | `1af8473008496b754b811d3ca7a88d82de09f146` | TASK-002: cite a source decision for every change-control entry of COPY_DECK — the package commit |
| `8bdacbb62698fbdb4d07bd922102d22b2f8c5d06` | `84018a475e8d420bbbe9f92648d50535be0e19c5` | TASK-002: record the evidence and set the Task to VERIFYING |
| `9b61d98ce11c56d28004bd5fbe68571ffa4775f6` | — | Its neutral references are in every rebuilt commit |
| `cf2fac8c34cc2c57fefdd021d675c9146aad379b` | — | Its record is this section |

The guard over the rebuilt branch, the push, the pull request and its CI are recorded in a later section of this file, after the push.
