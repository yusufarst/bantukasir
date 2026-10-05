# ADR-007 — Command identity and idempotency

```text
ID:               ADR-007
TITLE:            Command identity and idempotency
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-006 (Planner: command identity by source intent, key, fingerprint and recovery epoch;
                  ordered guards and bounded retries); D-040 (Owner: no duplicate Sale or Payment, uncertain
                  outcomes recovered through the same identity); DOMAIN_MODEL BR-CMD-01 to BR-CMD-06
```

## Context

At a shop counter a double tap, a lost response, a reload or a flaky connection must never create a second Sale, a second refund or a second stock movement, and must never make the cashier ask the customer to pay again (D-040, BR-SALE-18, BR-SALE-20). A lost response must not be read as a failure (BR-CMD-03). After a restore from backup, old browser intents must not run as new work (BR-CMD-06). The legacy architecture (02-ARCHITECTURE §Kontrak command dan idempotency) defined the parts; this ADR fixes how they work together.

## Decision

### 1. Every state-changing request is a command with an identity

Every request that changes business state carries a command envelope. Authentication endpoints (sign-in, sign-out, password confirmation, own password change, one-time link consumption) and operator procedures do not; their own rules protect them ([TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) TR-CMD-07). The envelope's fields:

| Field | Set by | Meaning |
| --- | --- | --- |
| `operation` and its contract version | Client, from a fixed server list | What is being done, for example a Sale checkout |
| `sourceIntentId` | Client, a random UUID created once for the business intent, when the draft is frozen at the final confirmation, and kept with the frozen draft | The business intent; one per cart checkout, per receiving confirmation, per refund, and so on. It survives a new request series for the same frozen draft |
| `idempotencyKey` | Client, a random UUID for one request series, normally created together with the intent | Every retry of the same frozen request reuses it. A client that starts a new series for the same frozen draft (for example after a client defect lost the key) creates a new key and keeps the intent, so the server still sees one business intent (BR-CMD-02) |
| `recoveryEpoch` | Server value given to the page; client sends it back | Detects intents created before a restore |
| Expected versions | Client, from what the user reviewed | For example product versions, the price revision, a cash session's activity version |
| Business payload | Client | Validated and normalized by the server; never trusted for the actor, prices, totals, the responsible cashier or cost scope (BR-DOM-06) |
| Actor | Server, from the sign-in session | Never accepted from the client |
| Fingerprint | Server | SHA-256 over a canonical serialization of the operation, its version, the actor and the client-supplied payload with its expected versions, after a normalization that depends only on those values and never on current server state; canonical number strings as in ADR-006; an uploaded file is represented by its content checksum, never by its storage key or file name. Key, intent, epoch and timestamps are excluded. |

The commands that the domain documents call critical — Sale checkout; cash session open, close and Owner-assisted close; refund and return; receiving confirmation; manual issue; opening stock; onboarding set and release; adjustment; reversal; cost correction or completion; CSV import confirmation; barcode misassignment correction — must also have the client recovery behaviour of section 5. Other commands (master data, configuration, user management) use the same envelope and server rules; how much recovery interface they show is set in RF-5.

### 2. The durable result is written with the facts

The server records the command's durable result in the **same transaction** as the business facts (BR-CMD-01, BR-DOM-04): actor, operation, key, intent, fingerprint, epoch, outcome, the facts it created and the time. The command's identity is the **first** write of the transaction ([TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 7, step 1), so that a second request with the same identity waits for the first one instead of running beside it; the outcome and the created facts are linked to that identity before commit. Logically, the pair (actor, operation, key) is unique and the pair (operation, intent) is unique. How the identity, the outcome and the links are stored — within the immutability and privilege rules of [ARCHITECTURE](../04-architecture/ARCHITECTURE.md) section 9 — is [SCHEMA](../04-architecture/SCHEMA.md) section 5.3 (RF-3B). Command results are kept as long as the facts they belong to (DOMAIN_MODEL section 11.3).

A **definite rejection** (validation or domain refusal) writes no business fact, audit event or command result: the transaction, including the identity record, rolls back (BR-SALE-19). A security log entry may be written outside it. The mark that affected reporting is incomplete after a cost correction refused because its replay cannot be done safely is written after the rollback, with its audit event, in a transaction of its own (SCHEMA section 5.8; T-17 i). The client never resubmits a rejected identity; after the user reviews, a new confirmation creates a new intent and key.

### 3. Outcomes

| Case | Server outcome |
| --- | --- |
| New key and new intent | The command runs once. |
| Same key, same fingerprint, original committed | The original result is returned; nothing new is written. |
| Same key, different fingerprint | Conflict `IDEMPOTENCY_CONFLICT`; nothing is written. |
| Different key, same intent, same fingerprint | The original result is returned; nothing new is written (BR-CMD-02). |
| Different key, same intent, different fingerprint | Conflict `IDEMPOTENCY_CONFLICT`; nothing is written. |
| Same identity while the original is still running | The second request waits for the first, within the lock timeout. If the first commits, the second returns its result as above; if the first rolls back, the second runs as a new attempt of the same identity. If the wait exceeds the bound, the answer is `SERVICE_BUSY` and the client stays UNCERTAIN. |
| Intent fenced by a resolution (section 4) | Conflict `INTENT_ABANDONED`; nothing is written. |
| Recovery epoch different from the server's current epoch | Conflict `EPOCH_STALE`; nothing is written. |
| An expected version no longer matches | Conflict (`PRICE_CHANGED`, `STALE_VERSION`, `RECOUNT_REQUIRED`); nothing is written; the user reviews (BR-SALE-19, BR-CASH-14). |

Returning an original result checks the actor's **current** authorization and object scope again; a result is never returned to someone who may no longer see it (BR-CMD-03).

### 4. Resolving an uncertain outcome when the request cannot be repeated

The preferred recovery is to repeat the same frozen request (same key, intent and payload), which is always safe. When the client no longer holds the frozen payload (for example after a reload), it asks the server to **resolve** the intent. Resolution, by the same actor or by the Owner, does one of three things:

- **COMMITTED** — the original committed; its result is returned under the current authorization.
- **IN_PROGRESS** — the original is still running; the client waits and resolves again.
- **NOT_COMMITTED** — no original committed and none is running. The server records, in its own transaction, a fence for that operation and intent (outcome ABANDONED). Because the pair (operation, intent) is unique, an original that arrives later is refused with `INTENT_ABANDONED` before any fact. Only after NOT_COMMITTED may the user start again with a new draft. This keeps BR-CMD-03: resolution is the status request of the same identity, and after NOT_COMMITTED the command is no longer uncertain, because its original can never commit; the new draft is not a replacement created because a response was lost. A missing result alone never permits a new draft; the only other path is the lost-reference rule of section 5, which also needs the hard time bound of TECHNICAL_RULES TR-TIME-03 (legacy L-04 and L-P6; [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 8).

Mechanically, the fence is an attempt to write the identity record for that operation and intent: if an original holds it in a running transaction, the resolution waits for that transaction and reports its outcome; if none exists, the fence is written. A missing result is therefore never read as "not committed" on its own; NOT_COMMITTED is a server fact that also guarantees the original can never commit afterwards. This applies within one recovery epoch. After a restore, a result missing from the restored data is **not** proof that the original never committed; such intents are refused as `EPOCH_STALE` and handled by the restore runbook, which also reviews the business records that may be missing after the backup point (the legacy gap review; BR-CMD-06; RF-6).

### 5. Client behaviour

- **SUBMITTING.** Pressing the final action freezes the operation, intent, key, payload and expected versions; edits and further submissions are blocked (D-040, BR-SALE-16, BR-SALE-18).
- **COMMITTED** after a success response; **REJECTED** after a definite rejection, including `INTENT_ABANDONED`; **UNCERTAIN** after a timeout, a network error, any 5xx response, `SERVICE_BUSY` or an unreadable response; **HELD** after `IDEMPOTENCY_CONFLICT` or `EPOCH_STALE`: a form of UNCERTAIN, shown to the user as uncertain, in which the original may exist and a repeat would conflict again, so the request is never repeated. A 5xx response is never a definite failure.
- In UNCERTAIN the only actions are: repeat the same frozen request, or resolve the intent. In HELD the client does not repeat: an `IDEMPOTENCY_CONFLICT` is resolved by the intent's actor or the Owner (CAP-34), and an `EPOCH_STALE` intent is checked by the Owner under the restore runbook (RF-6). In neither state is a replacement command created, and the customer is never asked to pay again before the original is resolved (BR-SALE-20). For a drawer-funded refund, no cash is taken from the drawer until the refund is COMMITTED (D-046, BR-RFD-17).
- Across a reload, a closed tab, a sign-out or a change of user, the browser keeps only a minimal reference (operation, intent, key, time) in local storage under the user's identity until it is resolved — never a payload, a secret or a cost value — and recovers through resolution ([SECURITY_MODEL](../05-security/SECURITY_MODEL.md) SEC-CH-05; BR-SALE-22). A reference is removed once the server reports its intent resolved. If a reference is lost (for example because the browser's data was cleared), the intent cannot be fenced. The outcome may then be concluded from the committed records — the actor's Sales, refunds or movements, which the Owner can read — only after the record-check wait of TECHNICAL_RULES TR-TIME-03, when no original can still commit; until then BR-SALE-20 applies. Who confirms that conclusion and how it is recorded are designed with the workflow recovery contracts (RF-5).

### 6. Recovery epoch

The server holds one recovery epoch per installation. The restore procedure (RF-6) replaces it with a value never issued before — a random value of at least 128 bits — and revokes every sign-in session (BR-CMD-06, REQ-CMD-03). Every command carries the epoch it was created under; a command whose epoch differs from the current one is refused before any business fact is written. The epoch is shown to pages through the Inertia shared props.

### 7. Internal retries

A deadlock or serialization failure inside the transaction may be retried by the server with the same identity, at most three attempts in total, with a short bounded backoff and within the retry window of TECHNICAL_RULES section 9 (a bounded heavy command is not retried); human, payment and printer effects are never repeated, because none happens inside the transaction (BR-CMD-05; TECHNICAL_RULES section 9).

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| Idempotency key only, without a source intent | A reload or a client defect that loses the key would allow a second Sale for the same cart (legacy L-02 §Kontrak command). |
| An idempotency cache with expiry (for example in Redis) | Results must be durable with the facts; no cache platform exists (ADR-002). |
| Treating "no result found" as failure after a timeout | Unsafe: the original may still commit. The fence makes NOT_COMMITTED a guarantee instead of a guess. |
| A counter as the recovery epoch | Restoring the same backup twice would issue the same value again, and intents created between the two restores would not be detected. |

## Consequences

- One shared command component (the Commands module, [STACK_AND_MODULES](../04-architecture/STACK_AND_MODULES.md)) validates envelopes, computes fingerprints, writes identities and serves resolution; Actions do not re-implement it.
- A fence record is kept like a command result; it carries no business fact.
- Recovery interfaces (UNCERTAIN, resolve, review after rejection) are designed per workflow in RF-4 and RF-5.

## Framework conformance

```text
ADR REQUIRED:      YES (records D-006 and D-040 behaviour for every command)
DEVIATION:         NONE — AICWDF §17 requires explicit idempotency, retries and timeouts
OWNER APPROVAL:    NOT REQUIRED — D-006 is a planner decision in force; D-040 (Owner) sets the user-visible rule
REASON:            no duplicate money or stock facts under retries and lost responses
RISK:              a client that discards the frozen request while UNCERTAIN; mitigated by the resolution fence
MIGRATION IMPACT:  none — the storage of command results is SCHEMA section 5.3 (RF-3B)
ROLLBACK IMPACT:   none
```

## Verified sources

No version-sensitive framework fact is used in this ADR. PostgreSQL behaviour relied on (a unique write waits for a concurrent uncommitted conflicting write and then succeeds or fails) is general PostgreSQL behaviour, proven by the real-PostgreSQL concurrency tests required by REQ-CMD-01 and REQ-SALE-06.

## Related

- [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) sections 3, 4, 7, 8 and 9; [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 7; [SALES_CASH](../02-domain/SALES_CASH.md) section 4; [SCHEMA](../04-architecture/SCHEMA.md) section 5.3.
- Superseded legacy content: `docs/04-architecture/legacy/02-ARCHITECTURE.md` §Kontrak command dan idempotency; `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Transaksi, locking dan original-result recovery (identity part); `docs/06-api-performance/legacy/P6-CONCURRENCY-API.md` §Kontrak HTTP dan command (envelope part).
