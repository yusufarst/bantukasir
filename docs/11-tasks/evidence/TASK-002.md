# TASK-002 evidence — P7 and copy change control, batch 1

| | |
| --- | --- |
| Task | [TASK-002](../tasks/TASK-002-p7-change-control-1.md), size M, review class O, lane D |
| Status | `IN_PROGRESS`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
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
