# ADR-009 — Command request receipts and reconciled outcomes

```text
ID:               ADR-009
TITLE:            Command request receipts and reconciled outcomes
STATUS:           ACCEPTED
DATE:             18 October 2026 (Asia/Jakarta), written in RF-5 (D-087)
DECISION SOURCE:  D-073 (Owner: server-authoritative recovery for NC-13; the status check stays available for
                  EPOCH_STALE; a reference is released only by a verified outcome or an audited restore
                  reconciliation with a safe fence); D-074 (Owner: RF-5 proposes the backend mitigation of duplicate
                  actions across devices, with tests; a new server refusal needs the Owner's decision first);
                  ADR-007 section 5 (who confirms the conclusion of a lost reference is designed in RF-5);
                  D-087 (Owner: RF-5 authorization; the planner's choices recorded there);
                  D-088 (Owner: acceptance of RF-5 and the decision on N-07 that section 6 records)
EXTENDS:          ADR-007, which stays in force unchanged
```

## Context

ADR-007 gives every command an identity and resolves an uncertain outcome through the same identity. Three gaps remained for RF-5:

1. **Duplicate actions across devices** (D-074, OPEN_ITEMS N-07). NAVIGATION_CONTRACTS NC-13 blocks a new command of a family only in the browser that holds the unresolved reference. On another device, in another browser or after the browser's data are cleared, nothing blocks a new draft, and the server sees a new intent.
2. **Requests from before a restore** (D-073). They are refused `EPOCH_STALE`, and a result missing from restored data is no proof that they did not commit (BR-CMD-06). The block of their family must end only through a verified outcome or an audited reconciliation with a safe fence.
3. **Lost references** (ADR-007 section 5). Who confirms that an intent whose reference was lost did not commit, and how, was left to RF-5; so was the Owner's resolution of another user's uncertain request (INTERACTION_INVENTORY UXI-058) and, through it, BR-CASH-16 in an Owner-assisted close.

## Decision

### 1. Request receipts

Every request to a command route is recorded on arrival as a **command request receipt**: actor, operation, source intent, idempotency key, recovery epoch and received time — never the payload, an amount or a product. It is written in a short transaction of its own, after authentication, CSRF, rate limit, capability and recent authentication have passed and before the command's transaction starts; if it cannot be written, the request is refused `SERVICE_BUSY` and nothing runs. So every user command that runs has a receipt written before it (guard G-25).

After the command ends, the receipt's outcome is set: COMMITTED, REJECTED (a definite refusal), HELD (`IDEMPOTENCY_CONFLICT` or `EPOCH_STALE`) or NOT_COMMITTED (a resolution's fence). A receipt left PENDING, because the process ended before marking it, is resolved like any other: the command identity of ADR-007, not the receipt, is the authority on whether the command committed. Receipts are ephemeral technical data (SCHEMA class E): housekeeping removes a resolved receipt after the retention RF-6 sets, and never an unresolved one.

### 2. Listing and the block across devices

The server lists a user's unresolved receipts — PENDING or HELD — to that user, and every user's to the Owner (ROUTE_CONTRACTS RD-04). The client applies NC-13 to the union of its local references and these receipts, so the block of a family acts on every device and browser of the user for every request that reached the server. RP-006 shows both, and the Owner can check another user's request there (UXI-058). No server refusal is added: the block remains a client rule, now fed by the server.

### 3. Requests from an earlier epoch

- A status check of an earlier-epoch intent answers **COMMITTED** when the intent's command identity is present in the restored data: it committed before the backup point, a verified outcome.
- Otherwise the check — like any command with an earlier epoch — is refused `EPOCH_STALE`, and the server records, if absent, a **stale-intent report** of the operation, intent, actor and epoch. The reports are the work list of the restore reconciliation.
- Under the restore runbook (RF-6) the Owner examines each report against the surviving records and the gap review, and the operator procedure SEC-OP-05 records the Owner's decision as an **intent reconciliation**: not committed in the restored data, with a **fence** of that operation and intent written in the current epoch (`command_identities`, kind `FENCE`), so that the intent can never commit afterwards. The procedure writes an audit event. The status check then answers NOT_COMMITTED and the client releases the reference.
- The runbook reconciles the Owner's own user-management requests first, so that NC-13 never prevents the re-application of SEC-OP-04 (D-073).
- The same procedure records the Owner's reconciliation of an onboarding restore hold (D-072): released before the restore, or never released.

### 4. Lost references and the Owner's resolution

- A browser reference lost with the browser's data no longer loses the intent: its receipt is listed on any device until it is resolved.
- When neither the receipts nor the records show an action the user believes was sent, the request never reached the server or ended without a fact. After the record-check wait of TECHNICAL_RULES TR-TIME-03, counted from the last moment the request could have been sent, no request can still arrive; the actor, for their own action, or the Owner, for any, may conclude that it did not commit and start a new draft. No separate record is written: the receipts are the server's record of what reached it.
- The Owner resolves another user's unresolved request with the ordinary status check (CAP-34); a fence it writes names the Owner as its actor.
- **BR-CASH-16 in an Owner-assisted close.** The close preview lists the responsible user's unresolved checkout receipts; the client keeps the count closed until the Owner has resolved each. The close itself stays protected by the session lock and the activity version.

### 5. Storage

| Table (SCHEMA section 5.3) | Class | Holds |
| --- | --- | --- |
| `command_request_receipts` | E | One row per request: `actor_user_id`, `operation`, `source_intent_id`, `idempotency_key`, `recovery_epoch`, `received_at`, `outcome` (`PENDING`, `COMMITTED`, `REJECTED`, `HELD`, `NOT_COMMITTED`), `outcome_at` |
| `stale_intent_reports` | F | `UNIQUE (operation, source_intent_id)`; `actor_user_id`, `reported_epoch`, `first_reported_at` |
| `intent_reconciliations` | F | Primary key `report_id`; `outcome` (`NOT_COMMITTED`); `fence_command_id` → the fence; `decided_by_user_id` (the Owner); `operator_note`; `recorded_at` |

### 6. Not decided here — decided by D-088

A **server refusal** of a new command while the same user has an unresolved receipt of its family would close the remaining window — a request still on its way to the server when the user starts again elsewhere — and would also enforce BR-CASH-16 on the server. It is a new refusal, so it needs the Owner's decision (D-074). It is offered to the Owner with the RF-5 report; until the Owner decides, it is not specified.

**Decided by D-088 (18 October 2026), the Owner's decision on N-07 at the acceptance of RF-5.** No general server refusal is added, for checkout or any other family: the receipts of sections 1 and 2 and the existing identity and recovery mechanisms stay the mitigation. One refusal is added: a close, `cash_session.close` or `cash_session.close_assisted`, is refused while the session's responsible user has a `sale.checkout` receipt that is PENDING or HELD, so BR-CASH-16 is enforced on the server (SCHEMA guard G-27; TECHNICAL_RULES RACE-35; INTERACTION_CONTRACTS section 2.5). It uses the existing code `RECOUNT_REQUIRED`. The narrow risk of a request still on its way to the server when the user starts the same action on another device is accepted; a duplicate that results is handled by an audited correction (DECISIONS section 5).

## Alternatives considered

| Alternative | Why not |
| --- | --- |
| Keep NC-13 in the browser only | Leaves the gap of D-074 open; not an accepted risk |
| Refuse a new command on the server while a receipt is unresolved | A new refusal; needs the Owner (section 6). Declined by the Owner except for the close (D-088) |
| Store receipts inside the command's transaction | A rejected or rolled-back command would leave no receipt, and a request that never committed could not be listed |
| Let the server conclude NOT_COMMITTED for an earlier-epoch intent on its own | Forbidden by BR-CMD-06 and D-073: missing data is no proof |
| Record the Owner's reconciliation through an interface page | Needs new pages and copy, and the restore runs before the shop reopens; the operator procedure records the Owner's decision under the runbook |

## Consequences

- One more short write per command request and one table that housekeeping trims.
- The block of NC-13 follows the user across devices; RP-006 and the Owner-assisted close show requests from every device.
- The restore runbook (RF-6) gains its reconciliation step with an audited procedure; TECHNICAL_RULES gains RACE-33 and RACE-34; SCHEMA gains guard G-25.

## Framework conformance

```text
ADR REQUIRED:      YES (extends the command identity of ADR-007)
DEVIATION:         NONE — AICWDF §17 requires explicit idempotency and recovery
OWNER APPROVAL:    GIVEN — D-073 and D-074 set the rules; D-087 authorizes RF-5; D-088 decides section 6: no
                   general refusal, one refusal of a close while a checkout receipt is unresolved
REASON:            no duplicate business action across devices without a new refusal; a safe exit for
                   requests from before a restore
RISK:              the window of section 6, accepted by the Owner with audited corrections (D-088); a receipt
                   write that fails refuses the request (SERVICE_BUSY)
MIGRATION IMPACT:  none — no implementation exists
ROLLBACK IMPACT:   none
```

## Verified sources

No version-sensitive framework fact is used. The behaviour relied on — a unique write waits for a concurrent conflicting write and then succeeds or fails — is that of ADR-007.

## Related

[ADR-007](ADR-007-command-identity-and-idempotency.md); [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) RT-11, RD-04, RC-50, OP-04, OP-05; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md) sections 1.4 and 2; [SCHEMA](../04-architecture/SCHEMA.md) section 5.3; [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) SEC-CH-05, SEC-OP-04, SEC-OP-05; [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md) NC-13; OPEN_ITEMS N-07.
