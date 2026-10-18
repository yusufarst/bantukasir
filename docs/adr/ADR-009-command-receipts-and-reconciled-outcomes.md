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
                  D-088 (Owner: acceptance of RF-5 and the decision on N-07 that section 6 records);
                  D-089 (Owner: the correction of finding B-2 of the RF-6 review, the settlement of section 1)
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

After the command ends, its own request marks the receipt's outcome, replacing only PENDING: COMMITTED; REJECTED (a definite refusal); HELD (`IDEMPOTENCY_CONFLICT` or `EPOCH_STALE`, kept as the receipt's held code); or NOT_COMMITTED (refused `INTENT_ABANDONED` because a resolution's fence holds the intent). The command identity of ADR-007, not the receipt, is the authority on whether the command committed. A receipt left PENDING — because the process ended before marking it — or HELD is brought in line with that authority by **settlement** (D-089):

- **When.** A status check (RC-50) conclusively answers COMMITTED — the intent's `COMMAND` identity exists, also an earlier-epoch identity present in restored data — or NOT_COMMITTED from a fence: the one the check writes in the current epoch, an existing one, or the fence of a restore reconciliation (section 3). IN_PROGRESS, `EPOCH_STALE` and a check without an answer settle nothing; an `EPOCH_STALE` answer marks the intent's PENDING receipts HELD.
- **What.** Every PENDING or HELD receipt with the same operation and source intent takes that outcome, whichever authorized user ran the check — the intent's actor or the Owner (CAP-34). A COMMITTED, REJECTED or NOT_COMMITTED receipt never changes, and a request processed after its receipt was settled leaves the outcome as it is.
- **Provenance.** A settled receipt keeps its actor, key, epoch, received time and held code, so a request held by an `IDEMPOTENCY_CONFLICT` stays recognizable after its intent is settled; it also records the identity that settled it (the `COMMAND` or the `FENCE`) and the user who checked or, for a reconciliation, the Owner who decided.
- **Authorization.** Only a check that the checker may make settles anything; anyone else gets `NOT_FOUND` and changes nothing. No command, page or other request sets a receipt's outcome, and the answer is still given under the checker's current authorization (ADR-007 section 3).
- **Audit.** A fence names its actor (ADR-007 section 4), and a reconciliation writes `RESTORE_RECONCILIATION` with its fence (section 3); a settled receipt points at that fence or at the committed identity, whose facts carry their own audit events. Settlement writes no business fact.
- **Concurrency.** A fence written by the check or by a reconciliation settles the receipts in its own transaction; a settlement from an identity or a fence that already exists runs in a short transaction of its own after reading it. Either way a receipt leaves PENDING or HELD only when its intent has committed or can never commit. The settlement locks the receipts in ascending order and takes no session lock, so it never waits for a close or makes a close wait; a close that reads a receipt before its settlement is refused, and one that reads it afterwards proceeds under its other checks (SCHEMA section 5.3 and G-27; TECHNICAL_RULES RACE-35).

The status check writes no receipt of its own: it carries no command envelope. Receipts are ephemeral technical data (SCHEMA class E): housekeeping removes a resolved receipt 30 days after its outcome (D-088), and never an unresolved one.

### 2. Listing and the block across devices

The server lists a user's unresolved receipts — PENDING or HELD — to that user, and every user's to the Owner (ROUTE_CONTRACTS RD-04). The client applies NC-13 to the union of its local references and these receipts, so the block of a family acts on every device and browser of the user for every request that reached the server. RP-006 shows both, and the Owner can check another user's request there (UXI-058). No server refusal is added: the block remains a client rule, now fed by the server.

### 3. Requests from an earlier epoch

- A status check of an earlier-epoch intent answers **COMMITTED** when the intent's command identity is present in the restored data: it committed before the backup point, a verified outcome.
- Otherwise the check — like any command with an earlier epoch — is refused `EPOCH_STALE`, and the server records, if absent, a **stale-intent report** of the operation, intent, actor and epoch. The reports are the work list of the restore reconciliation.
- Under the restore runbook (RF-6) the Owner examines each report against the surviving records and the gap review, and the operator procedure SEC-OP-05 records the Owner's decision as an **intent reconciliation**: not committed in the restored data, with a **fence** of that operation and intent written in the current epoch (`command_identities`, kind `FENCE`), so that the intent can never commit afterwards. The procedure writes an audit event and, in the same transaction, settles every PENDING or HELD receipt of that operation and intent as NOT_COMMITTED (section 1). The status check then answers NOT_COMMITTED and the client releases the reference.
- The runbook reconciles the Owner's own user-management requests first, so that NC-13 never prevents the re-application of SEC-OP-04 (D-073).
- The same procedure records the Owner's reconciliation of an onboarding restore hold (D-072): released before the restore, or never released.

### 4. Lost references and the Owner's resolution

- A browser reference lost with the browser's data no longer loses the intent: its receipt is listed on any device until it is resolved.
- When neither the receipts nor the records show an action the user believes was sent, the request never reached the server or ended without a fact. After the record-check wait of TECHNICAL_RULES TR-TIME-03, counted from the last moment the request could have been sent, no request can still arrive; the actor, for their own action, or the Owner, for any, may conclude that it did not commit and start a new draft. No separate record is written: the receipts are the server's record of what reached it.
- The Owner resolves another user's unresolved request with the ordinary status check (CAP-34); a fence it writes names the Owner as its actor, and a conclusive answer settles that user's receipts of the intent at once (section 1).
- **BR-CASH-16 in an Owner-assisted close.** The close preview lists the responsible user's unresolved checkout receipts; the client keeps the count closed until the Owner has resolved each. The close itself stays protected by the session lock and the activity version.

### 5. Storage

| Table (SCHEMA section 5.3) | Class | Holds |
| --- | --- | --- |
| `command_request_receipts` | E | One row per request: `actor_user_id`, `operation`, `source_intent_id`, `idempotency_key`, `recovery_epoch`, `received_at`, `outcome` (`PENDING`, `COMMITTED`, `REJECTED`, `HELD`, `NOT_COMMITTED`), `outcome_at`; for provenance (D-089) `held_code`, `settled_by_command_id`, `settled_by_user_id` |
| `stale_intent_reports` | F | `UNIQUE (operation, source_intent_id)`; `actor_user_id`, `reported_epoch`, `first_reported_at` |
| `intent_reconciliations` | F | Primary key `report_id`; `outcome` (`NOT_COMMITTED`); `fence_command_id` → the fence; `decided_by_user_id` (the Owner); `operator_note`; `recorded_at` |

### 6. Not decided here — decided by D-088

A **server refusal** of a new command while the same user has an unresolved receipt of its family would close the remaining window — a request still on its way to the server when the user starts again elsewhere — and would also enforce BR-CASH-16 on the server. It is a new refusal, so it needs the Owner's decision (D-074). It is offered to the Owner with the RF-5 report; until the Owner decides, it is not specified.

**Decided by D-088 (18 October 2026), the Owner's decision on N-07 at the acceptance of RF-5.** No general server refusal is added, for checkout or any other family: the receipts of sections 1 and 2 and the existing identity and recovery mechanisms stay the mitigation. One refusal is added: a close, `cash_session.close` or `cash_session.close_assisted`, is refused while the session's responsible user has a `sale.checkout` receipt that is PENDING or HELD, so BR-CASH-16 is enforced on the server (SCHEMA guard G-27; TECHNICAL_RULES RACE-35; INTERACTION_CONTRACTS section 2.5). It uses the existing code `RECOUNT_REQUIRED`. A receipt settled under section 1 no longer counts for it (D-089). The narrow risk of a request still on its way to the server when the user starts the same action on another device is accepted; a duplicate that results is handled by an audited correction (DECISIONS section 5).

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
- A conclusive status check or a restore reconciliation settles every unresolved receipt of its intent, so no receipt blocks a family or a close once its intent has committed or can never commit (D-089).

## Framework conformance

```text
ADR REQUIRED:      YES (extends the command identity of ADR-007)
DEVIATION:         NONE — AICWDF §17 requires explicit idempotency and recovery
OWNER APPROVAL:    GIVEN — D-073 and D-074 set the rules; D-087 authorizes RF-5; D-088 decides section 6: no
                   general refusal, one refusal of a close while a checkout receipt is unresolved; D-089 authorizes
                   the correction of section 1 (settlement)
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
