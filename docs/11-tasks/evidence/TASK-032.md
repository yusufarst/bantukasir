# TASK-032 evidence — Owner-assisted close and session review notes

| | |
| --- | --- |
| Task | [TASK-032](../tasks/TASK-032-assisted-close-notes.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-032-assisted-close-notes`, from `296998f1ddfa8dc0caef675e831c232dc6c6ca95` (origin/develop, the squash merge of TASK-031) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; no branch or pull request of TASK-032 and no open pull request; the executor state at `BUILD` for TASK-032 with this session's lock; TASK-030 and TASK-011 `DONE`, no gate. PHP, `tsc` and ESLint do not run on this machine, so every check but the guard runs in CI (D-108, D-117) |
| Owner's directives in force | The host forwarded them at the start (13 November 2026): the contract's scope only; fix only proven failures from the log; no weakened test, baseline or timeout; a new CVE is a stop; the next free decision D-129 (none taken) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint`, PHP's checks | Local | — | — | `NOT_RUN`: neither PHP nor `tsc` and `eslint` are on the path of this machine; the fast runs run them |
| Guard preview, then guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `bf3e8b2` | 13 Nov, 17:36 to 17:39 | about 3 min | `PASS`, no candidate |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. `cash_session_review_notes` (class F): one row per note, its key to `cash_session_closes`, so that only a session with its close has notes; the text of 1 to 2,000 characters, the time, the Owner with the name frozen, the command unique; no update or delete for any role; the indexes of SCHEMA section 11 on its two foreign-key columns not led by a key; the grants of section 10 and the privilege matrix | `database/migrations/2026_11_13_000001_create_cash_session_review_notes_table.php`; `database/privileges.php` |
| 2. RC-04 `cash_session.close_assisted`: CAP-23 and recent authentication (`password.confirm`) at the route; the identity; the actor share-locked (2.1); the BusinessProfile pointer share-locked (2.4); the session FOR UPDATE (4), found only when the actor is not its responsible user, else NOT_FOUND; under that lock the session OPEN (`SESSION_NOT_OPEN`), the activity version the one reviewed (`RECOUNT_REQUIRED`), then the responsible user's `sale.checkout` receipts, any PENDING or HELD refusing `RECOUNT_REQUIRED` (G-27); expected cash from the session's facts; at step 8 the session CLOSED, the close of kind `OWNER_ASSISTED` with the Owner as actor and the reason, the report with the reason, the audit event `CASH_SESSION_CLOSE_ASSISTED` with the reason in its column, the result; the responsible user unchanged | `App\Actions\Cash\CloseCashSessionAssisted`; `App\Domain\Cash\Sessions::lockForAssistedClose`, `ClosingSession` (with its responsible user), `SessionReports::record`/`freeze` (the reason); `App\Http\Requests\Cash\CloseCashSessionAssistedRequest`; `App\Http\Controllers\Cash\CloseCashSessionAssistedController`; `App\Actions\Commands\CommandActions` |
| 3. RP-207 (UXP-207): the header with the responsible user and the register; the close preview as a deferred prop — the activity version, the running figures with expected cash, the responsible user's unresolved checkouts one per intent with the Owner's "Cek status" (CAP-34); "Lanjut hitung" disabled with its reason while one is listed; the count with "Uang fisik di laci" and "Alasan"; "Tinjau"; the review naming the responsible user who stays; "Tutup sesi" after the password confirmation; `RECOUNT_REQUIRED` back to the preview; UXS-30; the panels; "Batal" and "Kembali ke Detail sesi kas" with the unsaved-changes guard; the Owner's own session and a CLOSED one 302 to RP-206 | `resources/js/pages/cash/AssistedClose.tsx`; `App\Http\Controllers\Cash\AssistedCloseController`; `App\Queries\Cash\SessionRecords::forAssistedClose`; `App\Domain\Commands\RequestReceipts::unresolvedIntentsOf`; `resources/js/lib/commands/RowCheck.tsx` (UXP-006's status check of a row, moved there for both pages) |
| 4. RC-43 `review_note.add`: CAP-22 at the route, no recent authentication; the session FOR SHARE (4), NOT_FOUND when missing, `STALE_VERSION` when not CLOSED; the note, the audit event `REVIEW_NOTE_ADD`, the result; several notes per session, never edited | `App\Actions\Cash\AddReviewNote`; `App\Domain\Cash\ReviewNotes`, `Sessions::lockForNote`; `App\Http\Requests\Cash\AddReviewNoteRequest`; `App\Http\Controllers\Cash\AddReviewNoteController` |
| 5. RP-206 for the Owner: "Tutup sesi oleh Pemilik" (UXI-140) on another user's OPEN session, disabled while he holds an unresolved close request; the section "Catatan tinjauan Pemilik" (UXI-144) of a CLOSED session as a deferred prop, every note in order with its author and time, with "Tambah catatan tinjauan" (UXI-145), the inline form "Catatan" and "Simpan catatan" (UXI-146) and "Batal" (UXI-147); RP-604's "Tutup sesi oleh Pemilik" beside "Detail sesi kas" (UXI-340); "Lihat hasil" of UXP-006 for an assisted close. The drawer-funded refunds beside the variance wait for TASK-034 (scope item 5) | `resources/js/pages/cash/Session.tsx`, `resources/js/pages/access/User.tsx`, `resources/js/pages/commands/Requests.tsx`; `resources/js/lib/cash/Parts.tsx` (`CloseSessionEntry` with a page and label), `sessions.ts`; `resources/js/components/ui/Field.tsx` (`TextArea`), `resources/css/components.css`; `App\Http\Controllers\Cash\SessionsController` |
| 6. The cash-session parts of deactivation (D-069 (d)) and of OP-03 (D-087): the session stays OPEN and unchanged until the Owner-assisted close | Proven by `AssistedCloseTest` and `AssistedCloseRaceTest` (section 5); no code of RC-38 or OP-03 changes |
| Copy | `lang/id/cash.php` (COPY_DECK sections 4.1, 7.11, 7.12 and 15), `resources/js/types/translation-keys.ts` |
| Routes | RP-207 `GET /cash-sessions/{session}/assisted-close` and RC-04 `POST /commands/cash_session.close_assisted` (CAP-23); RC-43 `POST /commands/review_note.add` (CAP-22) in `routes/web.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| RC-04's gate "the responsible user is not the Owner" is read as "not the actor": the Owner closes his own session with RC-03, and the outgoing Owner of OP-03, a deactivated SUPER_ADMIN, is not the Owner. The Owner's own session answers NOT_FOUND under RC-04's lock, as another user's does under RC-03's, and RP-207 leads it to RP-206 | INTERACTION_CONTRACTS RC-04; ROUTE_CONTRACTS RP-207; G-19; SEC-OP-03 |
| RC-04 copies RC-03's steps in its own Action rather than sharing a base with it, so that RC-03, merged by TASK-030, keeps its code; both use the same domain services (`Sessions`, `SessionReports`, `CashCount`, `SessionSales`). The result's type is the close's, `cash_session_close`, for both | TR §7.2 family Close; STK-12 |
| The reason of an assisted close is the audit event's `reason` column, not its change data, as SCHEMA section 5.4 gives the column | SCHEMA section 5.4 |
| RP-207's preview lists the responsible user's unresolved checkouts one row per intent, with the time of its first receipt, at most 100 — RD-04's page — read after the version as RP-105's count is; the Owner's "Cek status" of a row is UXP-006's (`lib/commands/RowCheck.tsx`), sent with the current epoch as UXP-006 sends another user's, and a conclusive answer reads the preview again | ADR-009 section 4; INTERACTION_CONTRACTS sections 2.1, 2.5 and 3.6 |
| The review notes are a deferred prop of RP-206 for the Owner of a CLOSED session only, read again by name after "Simpan catatan", so that the page's props stay those of TASK-030 for every role and the notes' channel is inspected apart (`Allowlists::DEFERRED`). At most the 200 newest notes of a session are read, in order | INTERACTION_CONTRACTS UXI-144; TECHNICAL_RULES section 12 |
| A note keeps its line breaks: the request allows `\n` as the only control character, the page shows the text with `white-space: pre-wrap` (`.bk-text-lines`), and the field is a new `TextArea` of the field component, since none existed | TR-VAL-05; DESIGN_SYSTEM 8.3 |
| Each "Tambah catatan tinjauan" opens a new form with its own command, so that every note is a new intent, as UXP-604's dialogs are | D-069 (f); NC-13 |
| `review_note.add` is the family of the command platform's probe (TASK-009): a probe's unresolved request in a browser now blocks the Owner's note there, as any request of the family would. The harness's comment says so; no test sends a probe and a note in one browser | `tests/Harness/Commands/Probe.php` |
| RC-04 has no own drawer-funded refunds yet: expected cash is the opening cash plus the applied CASH, as RC-03's, until TASK-034 | D-035; TASK-030 |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L1 RP-207's path, RC-04 in the close's family with recent authentication, RC-43 in the review note's without it, a session closed meanwhile under the assisted preview | `tests/Unit/Cash/sessions.test.ts` |
| L1 the matrix holds the new table; the built Actions | `tests/Unit/Database/PrivilegeMatrixTest.php`, `tests/Unit/Commands/OperationsTest.php` |
| L2 the notes table: the constraint family — the text's bounds, the frozen name, the keys to the close, the author and the command, one note per command, several per session, line breaks and 2,000 characters of two bytes; the privilege family — no update, delete or truncate for the runtime or backup role, the grants equal the matrix; the retention family — later notes leave every earlier note, close and report; the indexes and the keys as declared | `tests/Feature/Cash/ReviewNotesTableTest.php` |
| L2 failure injection after each write step: RC-04's five, RC-43's three | `AssistedCloseTest`, `ReviewNoteTest` |
| L3 RACE-09, the Owner-assisted close against the user's own close in both orders; RACE-28, a deactivation holding her account against her checkout, the session OPEN and unchanged, then the Owner-assisted close | `tests/Concurrency/AssistedCloseRaceTest.php` |
| L4 RC-04: the close with its count and reason, the responsible user kept, the Owner the close actor, the report and the audit; 423 without recent authentication and no receipt; validation; the Owner's own and a missing session NOT_FOUND, a CLOSED one SESSION_NOT_OPEN, a moved version RECOUNT_REQUIRED; G-27 refused until the Owner's status check settles the checkout; repeats; a deactivated cashier's session (D-069 (d)) and an outgoing Owner's after OP-03 (D-087) OPEN and unchanged until this close; the other roles at the gate and the guard | `tests/Feature/Cash/AssistedCloseTest.php` |
| L4 RC-43: two notes, line breaks kept, nothing else changed; STALE_VERSION for an OPEN session, NOT_FOUND; the text's bounds; repeats; the other roles | `tests/Feature/Cash/ReviewNoteTest.php` |
| L4 RP-207 and RP-206: exactly the allowlisted props and deferred props for the Owner, the unresolved checkouts one per intent and only the responsible user's checkouts, the redirects, NOT_FOUND, the preview null once closed, the notes for the Owner of a CLOSED session only; the route inventory and the capability matrix | `tests/Feature/Cash/AssistedClosePagesTest.php`, `SessionPagesTest`, `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`, `tests/Harness/Channels/Allowlists.php` |
| L5 RP-207 and the notes of RP-206: at every width of KEY_SCREENS section 4 and 1440 px the preview, the count, the review and the report with axe, targets, contrast and no sideways scrolling; at the first width the unresolved checkout settled by the Owner's "Cek status", the unsaved-changes guard, and two notes with "Batal" | `tests/Browser/cash/assisted-close.spec.ts` |
| Fixtures through the Actions (EXC-45) | `TokoContoh::reviewNote` (RC-43's Action); the sessions of RC-02, RC-01 and RC-03; OP-03's Action in the test |

## 6. Acceptance criteria

The PostgreSQL files that this branch changes run in its fast runs; the whole suites and the browser suite run in CI on the pull request's final head (CE-44); until they have run there, each criterion that rests on them is met by its test as written, and its result is that run's.

| Criterion | Test | Result |
| --- | --- | --- |
| AC-032-01 Capability, recent authentication, count and reason; the responsible user stays, the close actor recorded; RACE-09 gives one CLOSED session | `AssistedCloseTest` (the first four tests and the roles), `AssistedCloseRaceTest` RACE-09 | See section 1 |
| AC-032-02 Refused while the responsible user has an unresolved checkout receipt; after the Owner's status check, the close succeeds | `AssistedCloseTest` "the responsible user's unresolved checkout…", `AssistedClosePagesTest` (the list), `assisted-close.spec.ts` | See section 1 |
| AC-032-03 A deactivated user's session, or an OP-03 Owner's, OPEN and unchanged until the Owner-assisted close (RACE-28) | `AssistedCloseTest` (the deactivation and OP-03 tests), `AssistedCloseRaceTest` RACE-28 | See section 1 |
| AC-032-04 Notes append-only, several per session, the report unchanged | `ReviewNoteTest`, `ReviewNotesTableTest` | See section 1 |
| AC-032-05 RP-207 and the Owner's sections of RP-206 follow the approved artifacts | `AssistedClosePagesTest`, `assisted-close.spec.ts`; the copy verbatim from COPY_DECK sections 7.11, 7.12 and 15 | L5 on the final head |

## 7. Database (EXC-41 to EXC-47)

| Item | Record |
| --- | --- |
| Migration (EXC-41, EXC-46) | `2026_11_13_000001_create_cash_session_review_notes_table`, additive, its REL-20 metadata in its comment; no earlier migration edited; rehearsal on the benchmark dataset waits for TASK-043 |
| SQL where the builder falls short (EXC-42) | The checks, the keys and the indexes as explicit statements, proven on PostgreSQL by `ReviewNotesTableTest` |
| Grants with the table (EXC-43) | Runtime `SELECT, INSERT`, backup `SELECT` on the table and its identity's sequence; `database/privileges.php` (class F) and `PrivilegeMatrixTest`; `PrivilegeCheck` empty in `ReviewNotesTableTest` |
| Keys across Tasks (EXC-44) | None completed: no earlier table names the notes |
| Tests on the database (EXC-45) | The constraint, privilege and retention families as the runtime role; the table has no deferred key |
| Backup impact (EXC-47) | Class F, so the manifest's counts include it through `database/privileges.php` (`BackupRunner::COUNTED_CLASSES`); no stored file |
