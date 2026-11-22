# TASK-051 — Audit change values: COPY_DECK change control and their display

| | |
| --- | --- |
| Status | `READY`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 8 Reports and audit views · lane R, reports, audit views and release |
| Size and review | M · review class A — the change data holds data that only the Owner may see (D-047), so its display is an authorization surface ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-042](TASK-042-audit-views.md) |
| Gates | Before `DONE`: GATE-CC3, the Owner's approval by hash of the COPY_DECK change control of scope item 1 ([TASK_PLAN](../TASK_PLAN.md) section 3) — APPROVED on 22 November 2026 (D-138) |
| Branch | `task/TASK-051-audit-change-values` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort of EXECUTION_CONTEXT CE-11, under continuous execution (D-101) |
| MCP | None required. |
| Modules | Documents (COPY_DECK); Reporting (the audit view), Audit |
| Baseline | Added by D-137, 21 November 2026, the Owner's decision for OPEN_ITEMS T-56 (D-136) |

## 1. Goal

Close OPEN_ITEMS T-56 before gate RELEASE (D-136): the Owner sees, under "Perubahan" of the expanded row of UXP-612 (UXI-378), the old and new value of each field of an audit event that records a change, cost corrections included, under Bahasa Indonesia labels that he approved, with the data that only he may see staying his alone.

The Owner limited this Task to three things (D-137): the COPY_DECK change control, its implementation, and a targeted verification. It adds nothing else, except the one exception that he made in D-138: scope item 4.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE` ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21). GATE-CC3 is this Task's own gate before `DONE`: the Task writes the package that the gate approves.
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- DECISIONS D-136 and D-137; OPEN_ITEMS T-56
- The evidence of TASK-042, sections 3 (C-5), 8 and 12
- COPY_DECK section 1.1, UXI-378 and sections 11.13 and 11.14
- INTERACTION_CONTRACTS section 8 (the action codes) and the change data that each Action records (`AuditLog::record`)
- SECURITY_MODEL section 7 (the channels) and D-047 (the Owner-only data)
- The evidence of TASK-002 (how a change control is approved by hash, gate GATE-CC1)

## 4. Scope

### 4.1 In scope

1. **Change control.** An amendment of COPY_DECK, and of the audit view's rules where they need it: for each action code whose events record a change, which fields "Perubahan" shows, a key and a Bahasa Indonesia label for each, and how each value is shown (amounts, quantities, dates, codes as their labels, references); which fields are the Owner's alone (D-047); and the fields shown to no one. No copy is invented outside this amendment (EXC-51). The package is approved by the Owner by the Git blob, size and SHA-256 of each changed artifact (gate GATE-CC3), as TASK-002 was. Once the package is written, the Task stops for that approval (EXECUTION_CONTEXT CE-70, an approval by hash), and the implementation starts only after it. His approval is recorded as the next free decision.
2. **Implementation.** RP-612 sends, and UXP-612 shows under "Perubahan" of UXI-378, the old and new values of the approved fields with their approved labels, through translation keys only. The Owner-only fields reach the Owner alone (CAP-32 and D-047). No internal field name, internal key, or field that the amendment does not name reaches any channel. The reason stays as TASK-042 shows it.
3. **Targeted verification.** The tests of section 7, covering only what this Task changes.
4. **UXP-415, by the exception of D-138 to D-137.** UXP-415 Detail insiden shows under "Yang tidak cocok" the Bahasa Indonesia label that COPY_DECK section 11.15.2 approved for the block's detail code (`audit.change.value.mismatch_*`), never the stored code (finding F-1 of this Task's evidence), with its targeted verification. Nothing else of UXP-415 or RP-415 changes.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | — (RP-612 is TASK-042's; this Task extends its payload) |
| Route elements added | RP-612: the approved change values of each event |
| Pages | — (UXP-612 is TASK-042's) |
| Controls | — |
| Controls extended with a destination | — (UXI-378 gains its "Perubahan") |
| Document contracts | — |

### 4.3 Out of scope

- Anything beyond the items of section 4.1 (D-137, D-138).
- TASK-047's change control of the device results (gate GATE-CC2), which this Task neither extends nor replaces.
- Any change to the audit record itself, its tables or what an Action writes.
- Any access to sensitive data beyond what the approved amendment names; any deployment.

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-AUD-02, REQ-RPT-05 |
| Workflows | — | WF-31 |
| User tasks | — | — |
| Acceptance suites | — | AUDIT01, AUTH02 (final: TASK-044) |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-08 |
| Open items | T-56 — closed by this Task | — |

## 6. Acceptance criteria

- **AC-051-01** The COPY_DECK amendment names, for each action code that records a change, the fields shown, each with its key, label and value presentation, and the Owner-only fields. The Owner approved it by hash (GATE-CC3).
- **AC-051-02** UXP-612 shows the old and new values of the approved fields with the approved labels, cost corrections included, and no internal field name or unapproved field on any channel.
- **AC-051-03** The Owner-only fields reach the Owner alone. Every other role, and the combined account, gets neither the page nor those fields on any channel.
- **AC-051-04** The targeted verification of section 7 passes.
- **AC-051-05** UXP-415 shows the approved label of the block's detail code and never the stored code (D-138).

## 7. Tests

- L1: every approved field has its key in `lang/id`; every action code's fields are those of the amendment, and no other field leaves the read.
- L4: RP-612's payload per role, with the Owner-only fields for the Owner alone, through the channel allowlist (`Allowlists`); no field name in the payload.
- L5: UXP-612's "Perubahan" for a cost correction and one other change, at the widths, keyboard path and the automated accessibility checks of EXECUTION_CONTEXT section 10, in the browser project of signed-in people.
- Scope item 4 (D-138): the label of each detail code (L1), and UXP-415 showing the label and not the code (L5, the existing browser test of UXP-415).
- The standing obligations of EXECUTION_CONTEXT section 10 for what this Task changes.

## 8. Evidence

`docs/11-tasks/evidence/TASK-051.md` (EXC-60 to EXC-63), with the package's hashes and the Owner's approval.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 |
| UI and UX | Applies — UXI-378 as amended |
| Backend | Applies — RP-612's payload |
| Database | N/A — no migration |
| Security | Applies — the Owner-only data and the channels |
| Data integrity | N/A — writes no fact |
| Performance | Applies — the read stays bounded by its page |
| Operations | N/A |
| Verification | Applies — the targeted verification of section 7; AUDIT01's final point is TASK-044 |

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14, and the stop for the Owner's approval by hash of scope item 1.

## 11. Documents this Task updates

- COPY_DECK and the audit view's rules, through the approved change control
- OPEN_ITEMS T-56; DECISIONS, the Owner's approval
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last

## 12. For later Tasks

- TASK-044 verifies AUDIT01 on the release candidate; TASK-049 checks T-56 at RELEASE (D-136).
- Tasks that depend on this one directly: [TASK-044](TASK-044-integrated-verification.md).

## 13. Expected files

- `docs/07-ux-design/COPY_DECK.md` and the documents the amendment names
- `app/Queries/Audit/ActivityLog.php`, `resources/js/pages/audit/Activity.tsx`, `lang/id/audit.php`
- For scope item 4: `resources/js/pages/inventory/Incident.tsx` and `resources/js/lib/inventory/incidents.ts`
- Their tests
