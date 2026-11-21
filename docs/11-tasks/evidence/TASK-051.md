# TASK-051 evidence — Audit change values: COPY_DECK change control and their display

| | |
| --- | --- |
| Task | [TASK-051](../tasks/TASK-051-audit-change-values.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01): the selection of CE-10 at `origin/develop` `ccc02dd`; the Owner's decisions D-136 and D-137 and his directives D-130 and D-133 |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review and verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-051-audit-change-values`, from `ccc02dd019d0ed5755d347baeb07ddf9a204f1d9` (origin/develop, the squash merge of TASK-043's pull request #59) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, which this Task leaves alone; the stash untouched; no branch or pull request of TASK-051 and no open pull request; the executor state at `BUILD` for TASK-051 with this session's lock, the merge of #59 verified; TASK-042 `DONE`; GATE-CC3 is this Task's own gate before `DONE` |
| Status | **Stopped for gate GATE-CC3** (EXECUTION_CONTEXT CE-70, an approval by hash): the package of scope item 1 is written; the implementation starts only after the Owner's approval |

## 1. The package for gate GATE-CC3

The package is one artifact, COPY_DECK, changed in its sections 11.12 (UXI-378's note), 11.15 (new), 22.3 (new) and its header rows. It is committed in `52d619f8ff12a5acd25c3122a5e8dc5a89e7d995` ("TASK-051: checkpoint — COPY_DECK change control for the audit change values (GATE-CC3 package)"), parent `ccc02dd`, which changes COPY_DECK alone. Measured on 22 November 2026 at 04:26 (Asia/Jakarta): the blob that the commit records for the path (`git rev-parse`), its size (`git cat-file -s`) and the SHA-256 of its bytes (`sha256sum`). The working-tree file is the same blob (`git hash-object`) with LF line endings in the index and the tree (`git ls-files --eol`), so the SHA-256 of the file is that of the committed content.

| Document | Git blob at `52d619f` | Bytes | SHA-256 of the committed content | Against `ccc02dd` |
| --- | --- | --- | --- | --- |
| `docs/07-ux-design/COPY_DECK.md` | `83549cffd4ed741e4d7bcd29e25370c4d164fbf2` | 222,156 | `b85d1f93590427bc5fbac40e9b5c3c795333b0616b3aad4d02a169fb7e263ad6` | Changed; the base blob is `8002d473a27a7d8fa7118d3e9b3db14739c3686f`; 202 lines added, 4 changed |

No other approved artifact changes. ROUTE_CONTRACTS RP-612 names section 11.15 only after the approval, as a reference (EXC-81; COPY_DECK section 22.3).

## 2. What the package proposes

- **The rules ACV-01 to ACV-05** (COPY_DECK section 11.15.1): "Perubahan" shows only the rows named for the event's action code; no internal identifier, sequence, version, file key, hash or stored field name leaves the read; the rows from the Owner-only data reach the Owner alone (CAP-32, D-047); the reason stays under "Alasan"; the kinds of row (value, change "lama → baru", list, table of lines) and of value (money, cost, quantity, date, count, text, names, code labels, yes or no); the read bounded by its page.
- **The keys** (section 11.15.2): `audit.change.from_to`; 89 labels, 77 of rows under `audit.change.field.*` and 12 of table columns under `audit.change.column.*`; the value labels under `audit.change.value.*` for the stored codes that have no label yet; and the existing keys that label the other codes, unchanged.
- **The rows of each of the 55 action codes** of INTERACTION_CONTRACTS section 8 (section 11.15.3), with the Owner-only rows marked "Pemilik" and what no channel shows. Cost corrections show "Biaya: lama → baru", the Owner's alone.

## 3. How the rows were derived

Every field was read from the Action that writes it, not from a document: the 70 calls of `AuditLog::record` in 57 files of `app/` (`app/Actions/**`; no other class writes `audit_events`), each event's change data and Owner-only data with the shapes of the values they hold (`toCanonical()`, `valueOf()`, the line arrays `$safe`, `$costs`, `$restored`). The 55 action codes are those of INTERACTION_CONTRACTS section 8, each written by at least one Action; `PRINT` is written for three subjects (a label, a receipt, a session report) with different data.

## 4. Readings and technical choices

| Subject | Reading or choice | Why |
| --- | --- | --- |
| Internal keys | Every identifier of a record, sequence, correction, publication or version number, file key and hash is shown to no one; a record that the Owner can recognise is shown by its name (product, category, user) | The contract's "no internal key reaches any channel"; a key has no shop meaning |
| Names now | A product, a category or a user is shown by its name now, read once per page by its keys; the actor keeps the name frozen in the event (TASK-042) | The change data holds keys only; `ActivityLog` already reads names this way for the reference |
| Lines | The lines of a Sale, a correction, a receiving, a manual issue, an adjustment and an opening stock entry are a table, with existing words for their columns | A table needs no sentence built from keys (section 1.1); an adjustment has no page of its own to link to |
| Duplicates | A value that the row already shows — the Sale or session number of the reference, the reason under "Alasan" — is not repeated | ACV-02 and UXI-379 |
| Values the audit never held | PROFILE_CHANGE and DETAILS_CHANGE record which fields changed, not their values; "Perubahan" lists the fields, and the profile's values stay on UXP-607's versions | ACV-01 shows only what the event holds; nothing an Action writes changes (contract section 4.3) |
| Wording | Every label reuses the deck's approved word for the same thing where one exists (sections 3, 7, 8.1, 9.1, 10, 11); new words only where the deck has none: the reconciliation detail codes, the causes of a cost mark, the onboarding states, the restore decisions, the barcode symbologies, the link kinds | Section 2 and the precedence of section 1.5 |
| "Tanggal usaha" | The GLOSSARY term for "Business date", whose status there is "proposed" | GLOSSARY row 168 |

## 5. Findings

| ID | Finding | Disposition |
| --- | --- | --- |
| F-1 | UXP-415 Detail insiden shows the reconciliation detail code as stored (`resources/js/pages/inventory/Incident.tsx`, the row "Yang tidak cocok": `BALANCE_MISMATCH` and the others), a stored code before the Owner, as T-56 found for the audit | Outside this Task's scope (D-137, contract section 4.3); the labels `audit.change.value.mismatch_*` of this package could serve it; reported to the Owner at this stop for his decision on where it goes |

## 6. Checks

Times are Asia/Jakarta. The package is documentation only; the implementation and its tests come after the approval.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Package hashes | Local, on `52d619f` | 22 November, 04:26 | under 1 min | Section 1 |
| Guard | Local, on the checkpoints before their push | Section 7 | — | Section 7 |
| Fast run of CI | Started by the push of the checkpoints | — | — | `NOT_RUN` until it completes; the Task stops for GATE-CC3 and does not wait for it |
| The tests of contract section 7 (L1, L4, L5) and the heavy checks of CE-44 | — | — | — | `NOT_RUN`: after the approval, with the implementation |

## 7. Stop for gate GATE-CC3

The contract stops here (scope item 1; EXECUTION_CONTEXT CE-70, an approval by hash). The Owner approves the package by the blob, size and SHA-256 of section 1, or asks for changes, which give a new package with new values. His approval is recorded as the next free decision, D-138, by the run that resumes, and the implementation then starts.

| Acceptance criterion | Status |
| --- | --- |
| AC-051-01 | The amendment is written (section 2); the Owner's approval by hash is pending (GATE-CC3 OPEN) |
| AC-051-02 to AC-051-04 | `NOT_RUN`: the implementation and the targeted verification come after the approval |
