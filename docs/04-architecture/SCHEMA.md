# Logical database schema

| | |
| --- | --- |
| Status | CURRENT — written in RF-3B under D-059, 5 October 2026 (Asia/Jakarta) |
| Phase | P4 — logical database schema; with [ARCHITECTURE](ARCHITECTURE.md) and [STACK_AND_MODULES](STACK_AND_MODULES.md) it completes P4 |
| Replaces | `docs/04-architecture/legacy/02-ARCHITECTURE.md` §Constraint database and the schema-delivery part of §Payment extension boundary dan schema delivery; `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Schema, immutability dan constraints ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 9) |
| Decisions | D-006, D-032 to D-038, D-042 to D-054, D-060 to D-062; [ADR-006](../adr/ADR-006-exact-numbers.md), [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md), [ADR-008](../adr/ADR-008-cost-allocation-representation.md) |
| Related | [ARCHITECTURE](ARCHITECTURE.md) section 9, [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) sections 7 and 8, [SECURITY_MODEL](../05-security/SECURITY_MODEL.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md) |

The logical PostgreSQL schema of BantuKasir: its tables, columns, keys and constraints; which records are immutable facts and which are replaceable projections; the retention class of every table; the database roles and their privileges; the indexes; and the mapping of every persistent invariant to the constraint, lock, guard and test that enforce it. It enforces the product and domain rules of P1 and P2 and adds no business meaning. Every rule names its source: a decision `D-…`, a CURRENT document, or a legacy source key from [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 2. Rules are numbered `SCH-nn`; guards `G-nn`; identifiers are permanent.

PostgreSQL DDL appears in this document **only as specification text** (plan decision P-7, D-059). It is not a migration and is never executed as it stands. The foundation and module Tasks write forward migrations from it once implementation is authorized (section 13).

## 1. How to read this document

- **Tables.** Section 5 describes every table by module ([STACK_AND_MODULES](STACK_AND_MODULES.md) section 2). For each table: its class (section 4), the entity it represents ([DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 4), its columns, its constraints and the privileges of the runtime role. A column is `NOT NULL` unless its row says `null`.
- **Types.** `bigint`, `integer`, `smallint`, `numeric(p,s)`, `text`, `boolean`, `date`, `timestamptz`, `uuid`, `bytea`, `jsonb`. Section 3 gives the type and bounds of each kind of number.
- **DDL blocks** are marked `-- specification text, not a migration (P-7)`. Where a table row and a DDL block both state a constraint, they state the same constraint.
- **Names.** Table and column names are part of the contract. A Task may change a name only by updating this document in the same change (as STK-22 does for the directory layout).
- **Locks** refer to the steps of [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 7.1; section 7 below says which row each step locks and in which mode.
- **Tests** name the legacy acceptance suites of `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` (references until RF-6) or `NEW`. Section 9.4 defines the test families that every constraint and privilege needs.
- **Roles.** "Runtime" is the database role of the application, "migration" the role of the controlled migration job, "backup" the role of the backup process (ARCHITECTURE ARC-70, ARC-71; section 10).

## 2. Principles

| ID | Rule | Source |
| --- | --- | --- |
| SCH-01 | **One source of truth per fact.** Every business fact is stored once. A projection (a balance, a valuation, a current allocation value, a running total, the active barcode mapping) is declared as such, is written by the same transaction as the facts it summarizes, and is rebuilt only from facts by a controlled procedure. Receipt and session-report snapshots are accountability documents, never a source of totals. | BR-DOM-03, AGENTS rule 7 |
| SCH-02 | **Facts are append-only.** A fact table is insert-only for the runtime role. A correction is a new, linked row. No column of a fact is ever updated, and no fact is ever deleted by any product function. | BR-DOM-02, D-034, ARC-70, PDS §3 |
| SCH-03 | **Constraint first.** Every rule that one row, one unique index or one foreign key can hold is held by the database, so that a defect in application code cannot store a row that breaks it. A rule over several rows is held by a guard under the locks of TECHNICAL_RULES section 7 (section 8). A check constraint never reads another row or table, because PostgreSQL requires check constraints to depend only on the row being checked. | BR-DOM-05, D-006; PostgreSQL documentation (section 15) |
| SCH-04 | **Exact numbers.** Money is `bigint` whole rupiah; quantities and cost values are `numeric` with a fixed scale; every numeric column has an explicit lower and upper bound; no floating-point type is used (section 3.2). | ADR-006, BR-NUM-01 to BR-NUM-04 |
| SCH-05 | **One installation, one business.** No table has a tenant, outlet, branch, warehouse, location or reservation column. | BR-DOM-01, D-038 |
| SCH-06 | **No logic in the database.** No trigger, rule, stored procedure or `SECURITY DEFINER` function enforces this schema or carries business behaviour. Enforcement is by constraints, privileges and guards in the Actions, so that every effect is visible in application code and migrations. | ARC-04, TECHNICAL_RULES TR-TX-02 |
| SCH-07 | **Time.** Every fact time is `timestamptz` set by the server from the application clock in UTC; a business date is a `date` in Asia/Jakarta computed by the server; a client-supplied time is never a fact. | BR-NUM-05, ARC-50, TR-HTTP-08 |
| SCH-08 | **Retention by class.** Retained business and audit facts are never hard-deleted or rewritten. Only the ephemeral technical data whose lifecycle an existing decision sets may expire or be cleaned up (section 4). | D-042, D-044, D-054, BR-KEEP-01 to BR-KEEP-08, PDS §1 |
| SCH-09 | **Foreign keys follow the domain relationships.** A foreign key may point across module boundaries in either direction (for example a stock movement to the SaleLine it issues). It creates no code dependency: only the owning module writes its tables, and Actions orchestrate modules. | DOMAIN_MODEL section 4, STK-11, STK-12 |
| SCH-10 | **Proven, not assumed.** Every constraint, privilege and guard in this document is proven by a test on real PostgreSQL with independent connections and committed fixtures (section 9.4). | STK-20, SEC-PR-06, REQ-OPS-02 |

## 3. Conventions

### 3.1 Keys

- Every table has an internal primary key `id bigint`, generated by the database, unless the table is keyed by its parent (for example `stock_balances.product_id`). Internal keys are never business numbers and are never shown as such; document numbers are separate columns (section 6).
- Locks on several rows of one table are taken in ascending internal key order (TR-LOCK-01).
- A composite unique key such as `UNIQUE (id, product_id)` exists only as the target of a composite foreign key. Composite foreign keys tie a row to the exact values it depends on — for example a SaleLine to the price revision of its own product and amount — so that the database refuses an inconsistent combination.

### 3.2 Numbers

| Kind | Type | Check constraint | Source |
| --- | --- | --- | --- |
| Money: price, line total, Sale total, applied payment, tendered cash, change, refund, opening, physical and expected cash | `bigint` | `BETWEEN 0 AND 999999999999` | BR-NUM-01, TR §11.1 |
| Cash variance | `bigint` | `BETWEEN -999999999999 AND 999999999999` | BR-CASH-12, TR §11.1 |
| Quantity of a line | `numeric(13,3)` | `> 0 AND <= 99999999.999`, and no more decimals than the product's precision | BR-NUM-02, TR §11.1 |
| Quantity of a stock movement (signed) | `numeric(13,3)` | `<> 0 AND abs(quantity) <= 99999999.999`, sign by movement type, and the product's precision | BR-INV-02, TR §11.1 |
| Stock balance, minimum stock | `numeric(13,3)` | `BETWEEN 0 AND 9999999999.999` | BR-INV-04, BR-CAT-11, TR §11.1 |
| Acquisition cost of one evidence; a corrected cost | `numeric(18,6)` | `BETWEEN 0 AND 999999999999.999999` | BR-NUM-03, TR §11.1 |
| Issue allocation, return allocation, inventory value | `numeric(21,6)` | `BETWEEN 0 AND 999999999999999.999999` | BR-NUM-03, TR §11.1 |

- **Precision.** A column that holds a product quantity is checked against the product's precision with `quantity = round(quantity, quantity_precision)` where the precision is on the same row; elsewhere the guard checks it (G-11).
- **Excess scale.** PostgreSQL rounds a value with more fractional digits than the column's scale; the application refuses such a value before it reaches the database (ADR-006 decision 3).
- **NaN and infinity.** A column declared with precision and scale cannot hold infinity, and PostgreSQL orders `NaN` above every number, so every upper bound above also refuses `NaN` (section 15).
- **UNKNOWN cost** is a status column next to an amount column, never a number (BR-DOM-08, BR-COST-03). The pattern, used for every cost value:

```sql
-- specification text, not a migration (P-7)
cost_status  text NOT NULL CHECK (cost_status IN ('KNOWN', 'UNKNOWN')),
cost_amount  numeric(18,6) NULL CHECK (cost_amount BETWEEN 0 AND 999999999999.999999),
CHECK ((cost_status = 'KNOWN') = (cost_amount IS NOT NULL))
```

### 3.3 Text, enumerations and time

- Text is `text` with a `char_length` check matching TECHNICAL_RULES section 11.3. Trimming of outer whitespace and refusal of control characters happen at validation (TR-VAL-05); the database additionally checks the format of identifiers (SKU, barcode token, storage key, document numbers).
- An enumeration is `text` with `CHECK (column IN (…))` listing exactly the server allowlist of TR-VAL-04. Adding a value is a migration.
- Fact times are `*_at timestamptz`; business dates are `business_date date` (SCH-07).

### 3.4 Actors

Every record that names who acted uses the same three columns (DOMAIN_MODEL section 4; SECURITY_MODEL section 14):

```sql
-- specification text, not a migration (P-7)
actor_kind     text   NOT NULL CHECK (actor_kind IN ('USER', 'OPERATOR_PROCEDURE', 'SCHEDULED_COMMAND')),
actor_user_id  bigint NULL REFERENCES users (id),
actor_process  text   NULL CHECK (actor_process ~ '^[a-z][a-z0-9_]{0,63}$'),
CHECK ((actor_kind = 'USER' AND actor_user_id IS NOT NULL AND actor_process IS NULL)
    OR (actor_kind <> 'USER' AND actor_user_id IS NULL AND actor_process IS NOT NULL))
```

- `actor_process` names the operator procedure (`bootstrap`, `owner_recovery`, `owner_replacement`, `after_restore`, and the reviewed cost recovery procedure of RF-6) or the scheduled command (`reconciliation`, `housekeeping`); the list is an allowlist in code. A one-time link consumption is recorded with `actor_kind = 'USER'` and the account that consumed it (SECURITY_MODEL section 14).
- The full pattern is used where a non-user can act: `audit_events`, `command_identities`, `security_log_entries`, `inventory_posting_blocks` and `cost_incomplete_mark_clearances`. `security_log_entries` extends `actor_kind` with `ANONYMOUS`, for which both actor columns are null (section 5.4). Every other table that records an actor names a user and carries `actor_user_id NOT NULL` alone.

### 3.5 Frozen display information

Every record that names a user as actor, cashier or responsible person also records that user's display name **at that moment**, in `*_display_name text NOT NULL CHECK (char_length(…) BETWEEN 1 AND 100)`, copied from `users.display_name` in the same transaction: Sales, sale corrections, cash sessions and their closes, documents of every stock posting, price revisions, barcode mappings and retirements, cost evidence and corrections, review notes and audit events. Receipt and session-report snapshots freeze every name they print. Anonymizing a user changes only the `users` row; no frozen copy is ever rewritten (BR-USR-12, D-043; TECHNICAL_RULES RACE-16).

### 3.6 Foreign keys

- Unless stated otherwise a foreign key is `ON DELETE RESTRICT ON UPDATE RESTRICT`. Nothing is deleted, so the delete rule only refuses a mistaken delete; the update rule makes a referenced key value unchangeable, which the schema uses on purpose — for example a product's unit and precision cannot change once a movement references them (section 5.7).
- A foreign key marked **deferred** is `DEFERRABLE INITIALLY DEFERRED` (with `NO ACTION`, the only rule that can be deferred): it is checked when the transaction commits, so that a record and the records that must accompany it are inserted in one transaction in any order, and a transaction that misses one cannot commit.
- A composite foreign key that contains a nullable column is checked only when all its columns are set (PostgreSQL's default `MATCH SIMPLE`). The nullable column is then a **discriminator**: the key applies exactly when it is set, and a check constraint makes every other column of the key non-null whenever it is set. A composite key whose columns must be all set or all null is declared `MATCH FULL`, so that one null can never switch it off (SCH-13).
- Every foreign-key column is indexed (section 11).

### 3.7 Presence keys

Some records must be accompanied by a dependent record only when they are of a certain kind — for example a stock movement needs an issue allocation only if it is an issue. Such a record carries a **presence key**: a stored generated column equal to the row's own `id` exactly when the dependent record is required and null otherwise, with a deferred foreign key to the dependent record's unique key. The database computes it from the row, so the application never writes it and a fact table needs no `UPDATE` privilege for it.

```sql
-- specification text, not a migration (P-7) — example from stock_movements
issue_allocation_presence bigint GENERATED ALWAYS AS (
    CASE WHEN movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE')
           OR (movement_type = 'ADJUSTMENT' AND quantity < 0)
         THEN id END) STORED,
FOREIGN KEY (issue_allocation_presence) REFERENCES issue_cost_allocations (movement_id)
    DEFERRABLE INITIALLY DEFERRED
```

A transaction that inserts an issue movement without its allocation therefore cannot commit (BR-INV-08, BR-SALE-17).

- PostgreSQL's documentation lets a stored generated column refer to the other columns of its row, except other generated columns, through immutable functions, and states no restriction on referring to an identity column or on a plain foreign key from a generated column (section 15). The foundation Task proves both on the pinned version (SCH-52).
- If the pinned version refuses either, the fallback is fixed here: the identity column becomes `GENERATED BY DEFAULT`; the Action takes the row's `id` in advance with `nextval` on that column's sequence (the runtime role then receives `USAGE` on that sequence only, SCH-44) and inserts it explicitly; and an ordinary nullable column with the checks `(<condition>) = (presence IS NOT NULL)` and `presence IS NULL OR presence = id` replaces the generated column. Neither path ever gives the runtime role `UPDATE` on a fact table of class F. The one row that reaches its required state by an update is a cash session closing (class FS); under the fallback the runtime role would also receive `UPDATE` of `cash_sessions.close_presence`, and section 10 would change with it. Under the fallback an identity `GENERATED BY DEFAULT` would accept an explicit id; the Actions only ever insert an id they took from the sequence.

### 3.8 JSON

`jsonb` is used only for frozen document content (receipt lines, session-report lists), for the safe change data of audit events and for the references of a command result. Money, quantity and cost values inside JSON are canonical strings, never JSON numbers (ADR-006). JSON never holds a secret, a token, a password or a full request payload (BR-AUD-05).

### 3.9 Nulls and text comparison

| ID | Rule | Source |
| --- | --- | --- |
| SCH-13 | **A null never passes a rule by accident.** Every column is `NOT NULL` unless its table says null. PostgreSQL treats a check constraint that evaluates to null as satisfied, so a check that involves a nullable column states `IS NOT NULL` in every branch that requires the value, or compares with `IS [NOT] DISTINCT FROM`. Composite foreign keys follow section 3.6. The Constraint test family inserts a null into every nullable column that a check or a composite key involves (section 9.4). | PostgreSQL documentation (section 15); SCH-03 |
| SCH-14 | **Text comparison.** Uniqueness without regard to letter case (SKU, email, cash register code) uses PostgreSQL's `lower()` in the unique index, which is the only authority for it: the application maps the unique violation to its refusal and never decides that equality itself. The database is created with UTF-8 encoding; the foundation Task records its collation provider and locale with the pinned versions and proves the case folding with test vectors that include non-ASCII letters. "Control character" and "outer whitespace" mean what `[[:cntrl:]]` and `[[:space:]]` match in the database's character classification; the application's validation (TR-VAL-05) uses the same sets, proven with vectors that include a no-break space. An upgrade of the collation library is followed by the reindex step of the RF-6 runbook (section 16). | TR-VAL-05, BR-CAT-05, SEC-ACC-03; PostgreSQL documentation |

## 4. Data classes, mutability and retention

| Class | Meaning | Runtime privileges | Retention |
| --- | --- | --- | --- |
| **F — retained fact** | A business or audit fact: never updated, never deleted | `SELECT`, `INSERT` | Retained. No hard delete and no rewrite. Records that form the basis of bookkeeping are kept at least ten years, and V1 has no function that deletes any of them (D-044, BR-KEEP-01 to BR-KEEP-05). Deleting one is a destructive production operation that needs the Owner's approval of that exact operation (PDS §1, BR-KEEP-02) |
| **FS — retained fact with state columns** | A fact whose identity columns never change and whose few state columns change only through its own commands (`cash_sessions`) | `SELECT`, `INSERT`, `UPDATE` of the listed state columns only | As F |
| **M — master data and configuration** | Mutable current data | `SELECT`, `INSERT`, `UPDATE` of the listed columns only | Never deleted, softly or permanently (D-042, BR-KEEP-06) |
| **S — singleton state** | One row of installation state | `SELECT`, `INSERT`, `UPDATE` of the listed columns only | Never deleted |
| **P — projection** | Derived from facts, written in the same transaction as the facts, rebuilt only from facts by a controlled procedure (BR-INV-46) | `SELECT`, `INSERT`, `UPDATE` of the listed columns; `DELETE` only where stated | Not a record of its own. Superseded projected values are not kept (D-034, BR-COST-29) |
| **L — security log** | Security events written outside the business transaction | `SELECT`, `INSERT` | Kept until RF-6 sets its retention (SEC-LOG-05); nothing removes an entry before then |
| **E — ephemeral technical data** | Data whose lifecycle an existing decision sets | `SELECT`, `INSERT`, `UPDATE`, `DELETE` | Expires or is cleaned up as its decision says: sign-in sessions end and are removed (D-039, ADR-002, SEC-SESS-04; BR-KEEP-07); one-time link tokens are deleted when consumed or revoked and removed by housekeeping when expired (SEC-ACC-04, SEC-ACC-06, SEC-ACC-07); rate-limiter counters and scheduler locks expire (ADR-002) |

Two kinds of ephemeral data are kept **outside the database** and have no table: an uploaded CSV import file, held in the private temporary area and removed at the command's final outcome and in any case after 24 hours (D-054, ARC-43); and a stored file left unreferenced by a rolled-back command, removed by housekeeping after a grace period of at least one hour (TR-TX-05). A stored file that a record references is never deleted or overwritten (D-042, BR-CFG-03).

| ID | Rule | Source |
| --- | --- | --- |
| SCH-11 | No table of class F, FS, M, S or L is ever the target of a cleanup, purge, expiry or archival job. The only cleanup jobs are those for class E and for the two areas outside the database above, each tied to the decision that requires it. Removing a projection row inside the command that changes its facts — the active barcode mapping of a retired mapping, whose retirement fact keeps the history — is projection maintenance, not cleanup. | D-044, D-054, SEC-ACC-07, ADR-002, TR-TX-05 |
| SCH-12 | Every table's class is stated in section 5, and the runtime privileges of section 10 follow from it. A table may change class only with an update of this document and of section 10 in the same change. | ARC-70, ARC-72 |

## 5. Tables

### 5.1 Access

**`users`** — class M, with restricted columns. Entity: User.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | Primary key; the stable internal identity that every record references (SEC-AUTH-12) |
| `display_name` | `text` | 1–100 characters |
| `email` | `text` | 1–254 characters; the sign-in identifier, stored trimmed; never verified, never sent to (D-049) |
| `password` | `text` null | The framework's Argon2id hash. Null means no usable password: a new staff account before its first-password link is consumed, or an anonymized account (SEC-ACC-02, SEC-ACC-09) |
| `role_super_admin`, `role_operations_admin`, `role_cashier` | `boolean` | The three fixed roles (BR-USR-01) |
| `status` | `text` | `ACTIVE`, `DEACTIVATED`, `ANONYMIZED` |
| `deactivated_at`, `anonymized_at` | `timestamptz` null | Set with the status |
| `created_at`, `updated_at` | `timestamptz` | |

```sql
-- specification text, not a migration (P-7)
CHECK (role_super_admin OR role_operations_admin OR role_cashier),
CHECK (NOT role_super_admin OR (NOT role_operations_admin AND NOT role_cashier)),          -- BR-USR-02, D-032
CHECK ((status = 'ACTIVE'      AND deactivated_at IS NULL     AND anonymized_at IS NULL)
    OR (status = 'DEACTIVATED' AND deactivated_at IS NOT NULL AND anonymized_at IS NULL)
    OR (status = 'ANONYMIZED'  AND deactivated_at IS NOT NULL AND anonymized_at IS NOT NULL
                               AND password IS NULL)),                                    -- D-043
CREATE UNIQUE INDEX users_email_ci_uniq ON users (lower(email));                          -- SEC-ACC-03
CREATE UNIQUE INDEX users_one_active_owner ON users (role_super_admin)
    WHERE role_super_admin AND status = 'ACTIVE';                                         -- D-048, RACE-30
```

- **At most one active Owner** is a database guarantee: every row in `users_one_active_owner` has `role_super_admin = true`, so the index admits one. Reactivating a former Owner account while another is active is refused by it.
- **SUPER_ADMIN is never assigned or removed on an existing account.** The runtime role has no `UPDATE` privilege on `role_super_admin` (section 10). A SUPER_ADMIN row is created only by `INSERT`, which only the bootstrap and Owner-replacement procedures perform (SEC-ACC-01, CAP-33); the replacement procedure deactivates the outgoing account before it inserts the new one, so the partial unique index never sees two active Owners. The database does not itself stop the runtime role from inserting a SUPER_ADMIN row while no Owner is active; that is the application's rule (CAP-33, G-08), and the index still admits at most one active Owner.
- **At least one active Owner after bootstrap** cannot be held by one constraint; guard G-08 holds it.
- There is no `remember_token` column (remember-me is disabled, SEC-AUTH-07) and no `email_verified_at` column (the email is never verified, D-049).
- Anonymization replaces `display_name` with the neutral label, `email` with the unique `.invalid` placeholder and `password` with null (SEC-ACC-09); every frozen copy of the old name elsewhere stays (section 3.5).
- Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `display_name`, `email`, `password`, `role_operations_admin`, `role_cashier`, `status`, `deactivated_at`, `anonymized_at`, `updated_at`.

**`sessions`** — class E, framework table. Entity: Sign-in session. The table of the framework's database session driver (ADR-002), with the framework's columns (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`). The sign-in time and the time of the last user-initiated request are kept in the encrypted payload (SEC-SESS-02). Rows are removed when a session ends or is revoked (SEC-SESS-04) and by the framework's expiry. Runtime privileges: all four.

**`password_reset_tokens`** — class E, framework table. It holds the one-time links (SECURITY_MODEL section 3) and represents no entity of DOMAIN_MODEL section 4. The table of the framework's password broker (ADR-004): `email` (primary key), `token` (stored hashed), `created_at`.

- The primary key on `email` gives at most one active link per account, because an email identifies at most one account (SEC-ACC-03). The link names the account by its internal identity; the server reads that account's email and checks the token against that row only, and the link is revoked before an email change (SEC-ACC-04).
- A link's purpose (FIRST_PASSWORD or RESET) is recorded in the audit event of its issuance; the consumption page derives its wording from whether the account already has a usable password. No column is added to the framework table.
- A consumed or revoked link's row is deleted in the transaction that consumes or revokes it (SEC-ACC-06); expired rows are removed by the scheduled housekeeping command (SEC-ACC-07). Runtime privileges: all four.

### 5.2 Configuration

**`business_profile_revisions`** — class F. Entity: BusinessProfileRevision.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | Primary key |
| `shop_name` | `text` | 1–100 |
| `address`, `contact` | `text` null | up to 500; up to 200 |
| `email` | `text` null | up to 254 (BR-CFG-01: optional email) |
| `document_header`, `document_footer` | `text` null | up to 500 each |
| `document_identity` | `text` null | up to 100. TECHNICAL_RULES section 11.3 sets no length for it; this column bound is the limit until RF-5 sets the document content |
| `accent_token` | `text` null | up to 32; a value of the constrained set defined with the design system (RF-4), which then adds its allowlist check |
| `logo_file_id` | `bigint` null | → `stored_files` |
| `created_at`; `actor_user_id`, `actor_display_name`; `command_id` | | The Owner and the command (BR-CFG-02) |

Runtime privileges: `SELECT`, `INSERT`. A revision is never changed or deleted (BR-CFG-02, D-042).

**`business_profile_current`** — class S. The current pointer (BR-CFG-02).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `smallint` | Primary key; `CHECK (id = 1)` |
| `revision_id` | `bigint` | → `business_profile_revisions` |
| `changed_at` | `timestamptz` | |

- The row is inserted with the first revision. A command that freezes the shop identity in a snapshot takes a share lock on it, and a BusinessProfile change an update lock (TR §7.1 step 2.4; RACE-25).
- Until the first revision exists, a command that must freeze the shop identity (Sale checkout, session close) is refused before any fact: a receipt or session report must hold the identity frozen from a revision (BR-DOC-01, BR-CASH-20). Opening a cash session is refused under the same condition, because a session that is opened must be closable and its close freezes the identity. The refusal code and the setup workflow are RF-5. This states a consequence of existing rules, not a new one.
- Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `revision_id`, `changed_at`.

### 5.3 Commands

**`recovery_epoch`** — class S. The installation's recovery epoch (ADR-007 section 6).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `smallint` | Primary key; `CHECK (id = 1)` |
| `epoch` | `bytea` | `CHECK (octet_length(epoch) >= 16)`: at least 128 random bits from the server's secure random source |
| `set_at` | `timestamptz` | |

- Inserted by the bootstrap procedure; replaced only by the after-restore procedure with a value never issued before (SEC-OP-01, SEC-OP-04).
- Every enveloped command compares its epoch with this value inside its transaction and is refused `EPOCH_STALE` on a difference, before any fact (ADR-007 section 6). The comparison needs no lock: the epoch changes only in the after-restore procedure, which the restore runbook runs before the application accepts traffic again (input to RF-6, section 16).
- Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `epoch`, `set_at`.

**`command_identities`** — class F. Entity: Command result, together with `command_results` (ADR-007 sections 2 to 4).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | Primary key |
| `kind` | `text` | `COMMAND` (a command's identity) or `FENCE` (the ABANDONED outcome written by a resolution, ADR-007 section 4) |
| `operation` | `text` | `^[a-z][a-z0-9_.]{0,63}$`; a value from the server's fixed operation list |
| `operation_version` | `smallint` null | Required for `COMMAND`; null for `FENCE` |
| `source_intent_id` | `uuid` | |
| `idempotency_key` | `uuid` null | Required for `COMMAND` |
| `actor_kind`, `actor_user_id`, `actor_process` | | Section 3.4. For `FENCE`, the user who resolved (CAP-34) |
| `fingerprint` | `bytea` null | SHA-256 (32 bytes); required for `COMMAND` |
| `recovery_epoch` | `bytea` | The epoch the request carried (a fence: the current epoch) |
| `received_at` | `timestamptz` | When the complete request reached the application's web server (TR-TIME-03) |
| `recorded_at` | `timestamptz` | |
| `result_presence` | `bigint`, stored generated | Presence key (section 3.7): equal to `id` for `COMMAND`, null for `FENCE` |

```sql
-- specification text, not a migration (P-7)
CHECK ((kind = 'COMMAND' AND operation_version IS NOT NULL AND idempotency_key IS NOT NULL
                         AND fingerprint IS NOT NULL AND octet_length(fingerprint) = 32)
    OR (kind = 'FENCE'   AND actor_kind = 'USER' AND operation_version IS NULL
                         AND idempotency_key IS NULL AND fingerprint IS NULL)),
result_presence bigint GENERATED ALWAYS AS (CASE WHEN kind = 'COMMAND' THEN id END) STORED,
UNIQUE (operation, source_intent_id),                                       -- ADR-007: (operation, intent)
UNIQUE (id, kind),                                                          -- target for command_results
FOREIGN KEY (result_presence) REFERENCES command_results (command_id) DEFERRABLE INITIALLY DEFERRED;
CREATE UNIQUE INDEX command_identities_user_key ON command_identities (actor_user_id, operation, idempotency_key)
    WHERE kind = 'COMMAND' AND actor_kind = 'USER';                         -- ADR-007: (actor, operation, key)
CREATE UNIQUE INDEX command_identities_process_key ON command_identities (actor_process, operation, idempotency_key)
    WHERE kind = 'COMMAND' AND actor_kind <> 'USER';
```

- The identity is the **first write** of every enveloped command (TR §7.1 step 1). A second request with the same identity blocks on the unique index until the first transaction ends, then fails on the conflict if the first committed or proceeds if it rolled back; the conflict is handled after rollback by reading the original identity and result (TR-TX-08; ADR-007 section 3; RACE-01, RACE-02).
- A definite rejection rolls back the identity with everything else (ADR-007 section 2). A committed `COMMAND` identity always has its result, because the deferred foreign key on `result_presence` refuses a commit without it.
- A `FENCE` row occupies (operation, intent), so a late original fails on `UNIQUE (operation, source_intent_id)` and is answered `INTENT_ABANDONED` (RACE-27). A fence carries no business fact.
- A scheduled command that uses an envelope (the reconciliation posting block, TR §7.2) derives its intent deterministically from its run and product, so that a repeated run does not block twice.
- Runtime privileges: `SELECT`, `INSERT`.

**`command_results`** — class F.

| Column | Type | Rule |
| --- | --- | --- |
| `command_id` | `bigint` | Primary key |
| `identity_kind` | `text` | `CHECK (identity_kind = 'COMMAND')`; with `command_id`, a foreign key to `command_identities (id, kind)`, so a fence never has a result |
| `result_type` | `text` | `^[a-z][a-z0-9_.]{0,63}$` |
| `result_ref` | `bigint` null | The internal key of the primary record created |
| `result_detail` | `jsonb` | References only (internal keys, document numbers); never cost or personal data. The response is rebuilt from these references under the actor's current authorization (ADR-007 section 3; SEC-AZ-06) |
| `committed_at` | `timestamptz` | |

Runtime privileges: `SELECT`, `INSERT`. Every fact row that an enveloped command creates carries `command_id` (→ `command_identities`), so the facts of a command can be found from its identity.

### 5.4 Audit

**`audit_events`** — class F. Entity: AuditEvent.

| Column | Type | Rule |
| --- | --- | --- |
| `id`, `occurred_at` | | |
| `category` | `text` | `BUSINESS` or `SECURITY` (the security-relevant business events of SEC-LOG-02; Owner-only, BR-AUD-04) |
| `action` | `text` | `^[A-Z][A-Z0-9_]{0,63}$` |
| `actor_kind`, `actor_user_id`, `actor_process`; `actor_display_name` | | Section 3.4; for a process, its name |
| `operator_note` | `text` null | up to 1,000; only when `actor_kind = 'OPERATOR_PROCEDURE'` (SECURITY_MODEL section 14) |
| `command_id` | `bigint` null | Null for authentication endpoints and operator procedures, which have no envelope (TR-CMD-07) |
| `subject_type`, `subject_id` | `text`, `bigint` null | The primary record; both null or both set |
| `product_id`, `cash_session_id` | `bigint` null | Filters (BR-AUD-04) |
| `reference` | `text` null | up to 100; a document number or reference, for filtering |
| `reason` | `text` null | up to 1,000 |
| `change_data` | `jsonb` | Safe change data that every reader in scope may see |
| `owner_only_data` | `jsonb` null | Cost details, the reason for a cost correction and anything else only the Owner may read (BR-AUD-02, D-047); never selected for a staff DTO |

- Written in the transaction of the change it records, and rolled back with it (BR-AUD-01). The only events written outside a command's transaction are those of section 5.8 (`cost_incomplete_marks`) and of operator procedures, which have no envelope.
- Never contains a password, link token, secret, session identifier or full payload (BR-AUD-05).
- Runtime privileges: `SELECT`, `INSERT` (SEC-LOG-04).

**`security_log_entries`** — class L. The security log (SEC-LOG-03).

| Column | Type | Rule |
| --- | --- | --- |
| `id`, `occurred_at` | | |
| `event_type` | `text` | `SIGN_IN_SUCCEEDED`, `SIGN_IN_FAILED`, `THROTTLED`, `LINK_FAILED`, `PASSWORD_CONFIRMATION_FAILED`, `CSRF_FAILED`, `AUTHORIZATION_REFUSED`, `SESSION_REVOKED`, `OPERATOR_PROCEDURE_RUN` |
| `actor_kind` | `text` | `USER`, `ANONYMOUS` (before sign-in), `OPERATOR_PROCEDURE`, `SCHEDULED_COMMAND` |
| `actor_user_id`, `actor_process` | null | As section 3.4, both null for `ANONYMOUS` |
| `subject_user_id` | `bigint` null | The account the event concerns, when known |
| `request_id` | `text` | up to 64 |
| `route_name` | `text` null | up to 200 |
| `outcome_code` | `text` | `^[A-Z][A-Z0-9_]{0,63}$` |
| `client_address` | `inet` null | The client address, taken only from the trusted proxy chain (ADR-003 item 10; SECURITY_MODEL section 11); null when no request carries the event (an operator procedure or a scheduled command) |

- Written by the request after its business transaction has ended, committed or rolled back, and never inside it (SEC-LOG-03, TR-TX-02).
- It stores no typed identifier, password, token, cookie, session identifier, user agent or payload (SEC-LOG-03, SEC-CH-08). The client address is personal data and is kept only as long as the log itself (SEC-LOG-05). Only the Owner reads it (CAP-32).
- Runtime privileges: `SELECT`, `INSERT`. Its retention is set in RF-6 (SEC-LOG-05).

### 5.5 Media

**`stored_files`** — class F. The reference record of every stored logo, product photo and thumbnail (ARCHITECTURE section 6).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `storage_key` | `text` | Unique; `^[A-Za-z0-9_-]{32,128}$`; random, never derived from a file name or other input (THR-12, SEC-UP-01) |
| `purpose` | `text` | `LOGO`, `PRODUCT_PHOTO`, `PRODUCT_THUMBNAIL` |
| `media_type` | `text` | `image/png`, `image/jpeg`, `image/webp` (SECURITY_MODEL section 13) |
| `byte_size` | `bigint` | `> 0` |
| `width`, `height` | `integer` | `> 0` |
| `sha256` | `bytea` | `octet_length = 32`: the checksum that backup and restore use to detect a missing or corrupt file (SECURITY_MODEL section 13) |
| `derived_from_file_id` | `bigint` null | → `stored_files`; set exactly for a thumbnail |
| `created_at`; `actor_user_id`; `command_id` | | |

- The file is written under its new key before the command's transaction; the transaction inserts this row and the reference to it (TR-TX-05). A rolled-back command leaves only an unreferenced file, which housekeeping removes (section 4). A referenced file is never overwritten or deleted, including the file of a replaced or removed product photo (D-042, BR-CFG-03).
- Guard G-20 checks that a reference uses a file of the right purpose.
- Runtime privileges: `SELECT`, `INSERT`.

### 5.6 Catalog

**`categories`** — class M. Entity: Category.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `name` | `text` | 1–100 |
| `active` | `boolean` | (D-042) |
| `version` | `integer` | `>= 1`; the expected version of an edit |
| `created_at`, `updated_at` | `timestamptz` | |

No uniqueness of category names is enforced: no decision sets one. How a CSV import row names a category is set with T-17 h in RF-5. Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `name`, `active`, `version`, `updated_at`.

**`products`** — class M. Entity: Product.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `sku` | `text` | 1–64 characters; outer whitespace removed before storage; no control character; letter case as typed; unique without regard to letter case (T-17 g; BR-CAT-05) |
| `name` | `text` | 1–200 |
| `category_id` | `bigint` | → `categories` |
| `brand` | `text` null | 1–100; a free-text attribute used as a search filter, not a managed list (BR-CAT-10) |
| `base_unit` | `text` | 1–32 |
| `quantity_precision` | `smallint` | 0–3 |
| `current_price_revision_id` | `bigint` | The selling price in force (deferred foreign key below) |
| `minimum_stock` | `numeric(13,3)` | Section 3.2; within the product's precision |
| `active` | `boolean` | |
| `photo_file_id` | `bigint` null | → `stored_files`; the one optional primary photo (D-037) |
| `version` | `integer` | `>= 1` |
| `created_command_id` | `bigint` | → `command_identities`; the creating command, including a CSV import |
| `created_at`, `updated_at` | `timestamptz` | |

```sql
-- specification text, not a migration (P-7)
CHECK (sku !~ '[[:cntrl:]]' AND sku !~ '^[[:space:]]' AND sku !~ '[[:space:]]$'),
CHECK (minimum_stock = round(minimum_stock, quantity_precision)),
UNIQUE (id, base_unit, quantity_precision),        -- target for stock_movements: unit and precision lock
FOREIGN KEY (current_price_revision_id, id)
    REFERENCES selling_price_revisions (id, product_id) DEFERRABLE INITIALLY DEFERRED;
CREATE UNIQUE INDEX products_sku_ci_uniq ON products (lower(sku));                       -- BR-CAT-05, T-17 g
```

- **Unit and precision lock.** Every stock movement references `(product_id, base_unit, quantity_precision)` with `ON UPDATE RESTRICT`. Once a product has a movement, its unit and precision cannot change, whatever the application does (BR-CAT-07, RACE-05).
- **Current price.** The pointer must name a revision of the same product. A product and its first revision are inserted in one transaction (deferred check).
- Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `sku`, `name`, `category_id`, `brand`, `base_unit`, `quantity_precision`, `current_price_revision_id`, `minimum_stock`, `active`, `photo_file_id`, `version`, `updated_at`.

**`selling_price_revisions`** — class F. Entity: SellingPriceRevision.

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `product_id` | `bigint` | → `products` |
| `amount` | `bigint` | Money (section 3.2) |
| `created_at`; `actor_user_id`, `actor_display_name`; `command_id` | | (BR-CAT-13) |

`UNIQUE (id, product_id)` and `UNIQUE (id, product_id, amount)` are composite-key targets. Runtime privileges: `SELECT`, `INSERT`.

**Barcodes** (D-051, D-052). A token is registered once; each attachment of a token to a product is a mapping; each retirement is a separate fact; the active mapping is a projection. Entity: Barcode.

**`barcode_tokens`** — class F.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `token` | `text` | Unique, compared exactly: leading zeros, case and punctuation are kept (BR-CAT-17) |
| `kind` | `text` | `MANUFACTURER` or `INTERNAL` |
| `symbology` | `text` null | `EAN_13`, `EAN_8`, `UPC_A`, `UPC_E` when declared; only for `MANUFACTURER` |
| `created_at` | `timestamptz` | |

```sql
-- specification text, not a migration (P-7)
CHECK (token ~ '^[ -~]{1,128}$' AND token !~ '^ ' AND token !~ ' $'),                 -- BR-CAT-17
CHECK ((kind = 'INTERNAL'     AND token ~ '^BK-P-[0-9A-HJKMNP-TV-Z]{16}$')
    OR (kind = 'MANUFACTURER' AND token !~ '^BK-')),                                  -- BR-CAT-18
CHECK (symbology IS NULL OR (kind = 'MANUFACTURER' AND (
          (symbology = 'EAN_13' AND token ~ '^[0-9]{13}$')
       OR (symbology = 'EAN_8'  AND token ~ '^[0-9]{8}$')
       OR (symbology = 'UPC_A'  AND token ~ '^[0-9]{12}$')
       OR (symbology = 'UPC_E'  AND token ~ '^[0-9]{8}$')))),
UNIQUE (token),
UNIQUE (id, kind)
```

The check digit of a declared EAN or UPC token is validated by the guard (G-09). Runtime privileges: `SELECT`, `INSERT`.

**`barcode_mappings`** — class F. One row per attachment of a token to a product.

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `token_id`; `token_kind` | | `(token_id, token_kind)` → `barcode_tokens (id, kind)` |
| `product_id` | `bigint` | → `products` |
| `attach_kind` | `text` | `ORIGINAL` (an ordinary add or a generated internal code) or `MISASSIGNMENT_CORRECTION` (the Owner's correction, D-051, D-052) |
| `corrects_mapping_id` | `bigint` null | The earlier mapping that was misassigned — the traceable prior mapping |
| `corrected_reason_class` | `text` null | `'MISASSIGNMENT'` exactly for a correction (composite-key column) |
| `reason` | `text` null | 1–1,000; mandatory for a correction |
| `attached_at`; `actor_user_id`, `actor_display_name`; `command_id` | | |

```sql
-- specification text, not a migration (P-7)
CHECK ((attach_kind = 'ORIGINAL' AND corrects_mapping_id IS NULL AND corrected_reason_class IS NULL)
    OR (attach_kind = 'MISASSIGNMENT_CORRECTION' AND corrects_mapping_id IS NOT NULL
        AND corrected_reason_class IS NOT NULL AND corrected_reason_class = 'MISASSIGNMENT'
        AND token_kind = 'MANUFACTURER' AND reason IS NOT NULL)),
FOREIGN KEY (token_id, token_kind) REFERENCES barcode_tokens (id, kind),
FOREIGN KEY (corrects_mapping_id, token_id) REFERENCES barcode_mappings (id, token_id),         -- same token
FOREIGN KEY (corrects_mapping_id, corrected_reason_class)
    REFERENCES barcode_mapping_retirements (mapping_id, reason_class) MATCH FULL,              -- retired as misassignment
UNIQUE (corrects_mapping_id),                                                                  -- corrected once
UNIQUE (id, token_id),
UNIQUE (id, token_id, product_id);
CREATE UNIQUE INDEX barcode_mappings_one_original ON barcode_mappings (token_id)
    WHERE attach_kind = 'ORIGINAL';                                                            -- BR-CAT-19
```

- **A token is attached in the ordinary way once, ever.** The partial unique index refuses any second `ORIGINAL` mapping of a token, so a retired token can never be added again by an ordinary add, and a `BK-P-…` code can never be reused, because a correction is allowed only for a manufacturer token (BR-CAT-18, BR-CAT-19, D-052).
- **A correction is traceable and eligible by construction.** It must reference an earlier mapping of the same token that was retired as a misassignment, and each such retirement can be corrected only once (BR-CAT-50, BR-CAT-51). A manufacturer token retired for another reason can never be attached again (D-052).
- **A move** (D-051) is one command: the retirement of the wrong mapping as a misassignment, the removal of its active row, the correction mapping and its active row. A re-attachment of a token already retired as a misassignment is the correction mapping and its active row (D-052).
- Runtime privileges: `SELECT`, `INSERT`.

**`barcode_mapping_retirements`** — class F.

| Column | Type | Rule |
| --- | --- | --- |
| `mapping_id` | `bigint` | Primary key; → `barcode_mappings` |
| `reason_class` | `text` | `MISASSIGNMENT` or `OTHER`. The exact reasons and their labels are interface copy (P7-G2); only this distinction is a rule (D-052, BR-CAT-52) |
| `retired_at`; `actor_user_id`, `actor_display_name`; `command_id` | | |

`UNIQUE (mapping_id, reason_class)` is the target of the correction's foreign key. A retirement is history and is never rewritten (BR-AUD-03). Runtime privileges: `SELECT`, `INSERT`.

**`barcode_active_mappings`** — class P. The active mapping of each token.

| Column | Type | Rule |
| --- | --- | --- |
| `token_id` | `bigint` | Primary key; → `barcode_tokens` |
| `mapping_id` | `bigint` | Unique |
| `product_id` | `bigint` | → `products` |

`FOREIGN KEY (mapping_id, token_id, product_id) REFERENCES barcode_mappings (id, token_id, product_id)`.

- The primary key on `token_id` is the guarantee that a token is active on at most one product (BR-CAT-16, D-051; RACE-17, RACE-19).
- A row is inserted with each mapping and deleted in the transaction that retires that mapping. A mapping that has a retirement never has an active row (G-09). The daily reconciliation recomputes the projection from the facts — for each token, its mapping without a retirement, of which the facts allow at most one — and reports any difference (G-03).
- A scan lookup reads `barcode_tokens` by exact token, then this table (BR-CAT-24); a correction changes both in one transaction, so a lookup sees the product before or after it, never none and never both (RACE-18).
- Runtime privileges: `SELECT`, `INSERT`, `DELETE`; no `UPDATE`.

**`csv_product_imports`** — class F. Entity: CSV product import (D-037, D-054).

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `command_id` | `bigint` | `command_id` unique |
| `file_sha256` | `bytea` | `octet_length = 32`; the checksum of the confirmed file, which is also part of the command's fingerprint (ADR-007; SEC-UP-04) |
| `data_row_count` | `integer` | 1–5,000 (BR-CAT-39) |
| `products_created` | `integer` | `= data_row_count` (all rows or none, BR-CAT-38) |
| `committed_at`; `actor_user_id`, `actor_display_name` | | |

The products it creates carry its command in `products.created_command_id`; the row summary is in the audit event (D-054). The uploaded file itself is never stored in the database (section 4). Runtime privileges: `SELECT`, `INSERT`.

### 5.7 Inventory

**`onboarding_state`** — class S. Entity: onboarding state (BR-INV-24).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `smallint` | Primary key; `CHECK (id = 1)` |
| `active` | `boolean` | Inserted `false` by the bootstrap procedure |
| `changed_at` | `timestamptz` | |
| `changed_by_user_id` | `bigint` null | Null only for the bootstrap row |

Postings take a share lock on it, setting and releasing it an update lock (TR §7.1 step 2.3; RACE-04). The documented cutover is recorded in the audit event of the change. Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `active`, `changed_at`, `changed_by_user_id`.

**`stock_movements`** — class F. Entity: StockMovement (BR-INV-02).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `product_id`, `base_unit`, `quantity_precision` | | → `products (id, base_unit, quantity_precision)`, `ON UPDATE RESTRICT` |
| `product_sequence` | `bigint` | `>= 1`; the posting order within the product, taken from `stock_balances.last_sequence` under the product's locks (BR-INV-05) |
| `movement_type` | `text` | `OPENING`, `RECEIPT`, `SALE_ISSUE`, `MANUAL_ISSUE`, `ADJUSTMENT`, `REVERSAL`, `RETURN_RECEIPT` |
| `quantity` | `numeric(13,3)` | Signed (section 3.2) |
| `opening_line_id`, `receiving_line_id`, `sale_line_id`, `manual_issue_line_id`, `adjustment_line_id`, `reversal_id`, `return_line_id` | `bigint` null | The one source (DOMAIN_MODEL section 4). Each source line key is a discriminator (section 3.6): with `product_id` and `line_quantity` it references its line's `(id, product_id, quantity)`, so the movement moves exactly the line's product and quantity; a reversal is tied to its source by `reversals` |
| `line_quantity` | `numeric(13,3)` null | The quantity of the source line: `quantity` for OPENING, RECEIPT, ADJUSTMENT and RETURN_RECEIPT, `-quantity` for SALE_ISSUE and MANUAL_ISSUE; null exactly for REVERSAL (composite-key column) |
| `return_condition` | `text` null | `'SALEABLE'` exactly for `RETURN_RECEIPT` (composite-key column) |
| `cost_evidence_presence`, `issue_allocation_presence`, `return_allocation_presence` | `bigint`, stored generated | Presence keys (section 3.7) |
| `recorded_at`; `actor_user_id`; `command_id` | | |

```sql
-- specification text, not a migration (P-7)
CHECK (quantity <> 0 AND abs(quantity) <= 99999999.999),
CHECK (quantity = round(quantity, quantity_precision)),                                   -- BR-NUM-02
CHECK ((movement_type IN ('OPENING', 'RECEIPT', 'RETURN_RECEIPT') AND quantity > 0)
    OR (movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE') AND quantity < 0)
    OR  movement_type IN ('ADJUSTMENT', 'REVERSAL')),
CHECK (num_nonnulls(opening_line_id, receiving_line_id, sale_line_id, manual_issue_line_id,
                    adjustment_line_id, reversal_id, return_line_id) = 1
   AND (movement_type = 'OPENING')        = (opening_line_id IS NOT NULL)
   AND (movement_type = 'RECEIPT')        = (receiving_line_id IS NOT NULL)
   AND (movement_type = 'SALE_ISSUE')     = (sale_line_id IS NOT NULL)
   AND (movement_type = 'MANUAL_ISSUE')   = (manual_issue_line_id IS NOT NULL)
   AND (movement_type = 'ADJUSTMENT')     = (adjustment_line_id IS NOT NULL)
   AND (movement_type = 'REVERSAL')       = (reversal_id IS NOT NULL)
   AND (movement_type = 'RETURN_RECEIPT') = (return_line_id IS NOT NULL)),
CHECK ((movement_type = 'RETURN_RECEIPT') = (return_condition IS NOT NULL)
   AND (return_condition IS NULL OR return_condition = 'SALEABLE')),
CHECK (movement_type <> 'OPENING' OR product_sequence = 1),                              -- BR-INV-26
CHECK ((movement_type = 'REVERSAL') = (line_quantity IS NULL)
   AND (line_quantity IS NULL OR line_quantity =
        CASE WHEN movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE') THEN -quantity ELSE quantity END)),
cost_evidence_presence     bigint GENERATED ALWAYS AS (CASE WHEN movement_type IN ('OPENING', 'RECEIPT')
                               OR (movement_type = 'ADJUSTMENT' AND quantity > 0) THEN id END) STORED,
issue_allocation_presence  bigint GENERATED ALWAYS AS (CASE WHEN movement_type IN ('SALE_ISSUE', 'MANUAL_ISSUE')
                               OR (movement_type = 'ADJUSTMENT' AND quantity < 0) THEN id END) STORED,
return_allocation_presence bigint GENERATED ALWAYS AS (CASE WHEN movement_type = 'RETURN_RECEIPT'
                               THEN id END) STORED,
UNIQUE (product_id, product_sequence),
UNIQUE (opening_line_id), UNIQUE (receiving_line_id), UNIQUE (sale_line_id), UNIQUE (manual_issue_line_id),
UNIQUE (adjustment_line_id), UNIQUE (reversal_id), UNIQUE (return_line_id),             -- one movement per source
UNIQUE (id, product_id, movement_type, quantity),
UNIQUE (id, product_id, movement_type, quantity, product_sequence),
UNIQUE (id, product_id, quantity, product_sequence),
UNIQUE (id, sale_line_id),
UNIQUE (id, return_line_id),
FOREIGN KEY (product_id, base_unit, quantity_precision)
    REFERENCES products (id, base_unit, quantity_precision) ON UPDATE RESTRICT,           -- BR-CAT-07
FOREIGN KEY (opening_line_id, product_id, line_quantity)
    REFERENCES opening_stock_lines (id, product_id, quantity),
FOREIGN KEY (receiving_line_id, product_id, line_quantity)
    REFERENCES receiving_lines (id, product_id, quantity),
FOREIGN KEY (sale_line_id, product_id, line_quantity)
    REFERENCES sale_lines (id, product_id, quantity),
FOREIGN KEY (manual_issue_line_id, product_id, line_quantity)
    REFERENCES manual_issue_lines (id, product_id, quantity),
FOREIGN KEY (adjustment_line_id, product_id, line_quantity)
    REFERENCES adjustment_lines (id, product_id, quantity),
FOREIGN KEY (return_line_id, return_condition, product_id, line_quantity)
    REFERENCES return_lines (id, condition, product_id, quantity),                         -- BR-INV-39
FOREIGN KEY (reversal_id, product_id, quantity)
    REFERENCES reversals (id, product_id, inverse_quantity),                              -- exact inverse
FOREIGN KEY (cost_evidence_presence) REFERENCES acquisition_cost_evidence (movement_id)
    DEFERRABLE INITIALLY DEFERRED,
FOREIGN KEY (issue_allocation_presence) REFERENCES issue_cost_allocations (movement_id)
    DEFERRABLE INITIALLY DEFERRED,
FOREIGN KEY (return_allocation_presence) REFERENCES return_cost_allocations (movement_id)
    DEFERRABLE INITIALLY DEFERRED
```

- **OPENING once per product and first.** An OPENING must have sequence 1 and sequences are unique per product, so a product has at most one OPENING and only before any other movement (BR-INV-26). `opening_stock_lines` also keeps a product unique across opening entries.
- **A source posts at most once**, and a REVERSAL is the exact inverse of its source (`reversals` below).
- **A movement moves its line's product and quantity.** The composite keys from each source line key, with `product_id` and `line_quantity`, make it impossible to store a movement of another product or quantity than its line, including the issue of a SaleLine and the receipt of a return line. A return's cost therefore always lands on the product whose issue it is linked to (ADR-008 section 7).
- **Cost records accompany every posting** that COSTING requires (BR-INV-08): acquisition cost evidence for OPENING, RECEIPT and positive ADJUSTMENT; an issue allocation for SALE_ISSUE, MANUAL_ISSUE and negative ADJUSTMENT; a return allocation for RETURN_RECEIPT. REVERSAL has none; the replay treats it (ADR-008).
- Runtime privileges: `SELECT`, `INSERT`.

**`stock_balances`** — class P. Entity: StockBalance.

| Column | Type | Rule |
| --- | --- | --- |
| `product_id` | `bigint` | Primary key; → `products` |
| `on_hand` | `numeric(13,3)` | `BETWEEN 0 AND 9999999999.999`: stock is never negative (BR-INV-04) |
| `last_sequence` | `bigint` | `>= 1`; the latest movement's `product_sequence`; it serves as the balance's version |
| `updated_at` | `timestamptz` | |

Inserted with the product's first movement and updated with each later one, in the same transaction (BR-INV-03). That `on_hand` equals the sum of the product's movements, and `last_sequence` their highest sequence, is held by guard G-03 and checked daily (BR-INV-45). Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `on_hand`, `last_sequence`, `updated_at`.

**`receiving_documents`** — class F. Entity: Receiving document ("Barang Masuk").

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `reference` | `text` null | up to 100; the optional source or reference (BR-INV-12) |
| `document_date` | `date` null | Optional; never backdates a fact (BR-INV-05) |
| `line_count` | `smallint` | 1–500 (TR §11.2) |
| `confirmed_at`; `actor_user_id`, `actor_display_name`; `command_id` | | `command_id` unique |

A receiving document exists in the database only once confirmed. Whether an unconfirmed document is held on the server is T-17 a (RF-5); if it is, it gets its own draft table, never rows in this one, because no stock or cost fact exists before confirmation (BR-INV-17). Runtime privileges: `SELECT`, `INSERT`.

**`receiving_lines`** — class F.

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `receiving_document_id` | `bigint` | |
| `line_no` | `smallint` | 1–500; `UNIQUE (receiving_document_id, line_no)` |
| `product_id` | `bigint` | |
| `quantity` | `numeric(13,3)` | Line quantity (section 3.2) |

`FOREIGN KEY (id) REFERENCES stock_movements (receiving_line_id) DEFERRABLE INITIALLY DEFERRED`: every line posts its RECEIPT. `UNIQUE (id, product_id, quantity)` is the target of its movement's key. The line has no cost column: its purchase cost is the acquisition cost evidence of its movement and the corrections of that evidence (section 5.8), which is also what OPERATIONS_ADMIN sees on the document (D-033, D-047). Runtime privileges: `SELECT`, `INSERT`.

**`manual_issues`** and **`manual_issue_lines`** — class F. Entity: Manual issue ("Barang Keluar").

| Table | Columns |
| --- | --- |
| `manual_issues` | `id`; `reason_code` (`DAMAGED`, `INTERNAL_USE`, `LOST`, `SAMPLE`, `OTHER`, BR-INV-19); `note` (null, up to 1,000); `line_count` (1–200); `confirmed_at`; `actor_user_id`, `actor_display_name`; `command_id` (unique) |
| `manual_issue_lines` | `id`; `manual_issue_id`; `line_no` (1–200, unique per issue); `product_id`; `quantity` (line quantity); deferred foreign key `(id)` → `stock_movements (manual_issue_line_id)`; `UNIQUE (id, product_id, quantity)`, the target of its movement's key |

Runtime privileges on both: `SELECT`, `INSERT`.

**`opening_stock_entries`**, **`opening_stock_lines`** and **`onboarding_zero_counts`** — class F. Entity: Opening stock entry and onboarding state.

| Table | Columns |
| --- | --- |
| `opening_stock_entries` | `id`; `row_count` (1–100, BR-INV-25); `confirmed_at`; `actor_user_id`, `actor_display_name`; `command_id` (unique) |
| `opening_stock_lines` | `id`; `opening_stock_entry_id`; `row_no` (1–100, unique per entry); `product_id` (**unique across all opening lines**, BR-INV-26); `quantity` (line quantity); `count_reference` (1–100, mandatory, BR-INV-25); deferred foreign key `(id)` → `stock_movements (opening_line_id)`; `UNIQUE (id, product_id, quantity)`, the target of its movement's key |
| `onboarding_zero_counts` | `id`; `product_id`; `count_reference` (null, up to 100: BR-INV-25 requires a count reference only for positive rows); `recorded_at`; `actor_user_id`, `actor_display_name`; `command_id` — a zero count creates no movement (BR-INV-27) |

How a checklist entry or a wrong positive OPENING is corrected before the onboarding flag is released is T-17 b (RF-5). Until then these tables are insert-only; any correction mechanism RF-5 defines is added without rewriting them. Runtime privileges on all three: `SELECT`, `INSERT`.

**`adjustments`** and **`adjustment_lines`** — class F. Entity: Adjustment (Owner only, BR-INV-31).

| Table | Columns |
| --- | --- |
| `adjustments` | `id`; `reason` (1–1,000); `count_reference` (1–100: the count); `reference` (1–100: BR-INV-31 requires a reason, a count and a reference); `line_count` (1–200); `confirmed_at`; `actor_user_id`, `actor_display_name`; `command_id` (unique) |
| `adjustment_lines` | `id`; `adjustment_id`; `line_no` (1–200, unique per adjustment); `product_id`; `quantity` (signed, `<> 0`, absolute value at most 99,999,999.999); deferred foreign key `(id)` → `stock_movements (adjustment_line_id)`; `UNIQUE (id, product_id, quantity)`, the target of its movement's key |

Runtime privileges on both: `SELECT`, `INSERT`.

**`reversals`** — class F. Entity: Reversal (Owner only, BR-INV-34).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `original_movement_id` | `bigint` | **Unique**: a source is reversed at most once (BR-INV-35; RACE-10) |
| `product_id` | `bigint` | |
| `original_type` | `text` | `OPENING`, `RECEIPT` or `MANUAL_ISSUE` only (BR-INV-34, BR-INV-36) |
| `original_quantity`, `inverse_quantity` | `numeric(13,3)` | `CHECK (inverse_quantity = -original_quantity)` |
| `reason` | `text` | 1–1,000 |
| `recorded_at`; `actor_user_id`, `actor_display_name`; `command_id` | | `command_id` unique |

`FOREIGN KEY (original_movement_id, product_id, original_type, original_quantity) REFERENCES stock_movements (id, product_id, movement_type, quantity)`; `UNIQUE (id, product_id, inverse_quantity)` is the target of the REVERSAL movement's key and `UNIQUE (id, original_movement_id)` that of a mark clearance (section 5.8), and `FOREIGN KEY (id) REFERENCES stock_movements (reversal_id) DEFERRABLE INITIALLY DEFERRED` requires the movement. The reversal is therefore the full, exact inverse of one eligible source and the Owner never enters a free quantity (BR-INV-34). Whether the history stays valid and the replay is safe is guard G-07 (BR-INV-37). Runtime privileges: `SELECT`, `INSERT`.

**`inventory_posting_blocks`** and **`inventory_posting_block_reopenings`** — class F. The posting block of the daily reconciliation (BR-INV-45, BR-INV-46).

| Table | Columns |
| --- | --- |
| `inventory_posting_blocks` | `id`; `product_id`; `blocked_at`; `actor_kind`, `actor_user_id`, `actor_process` (normally the `reconciliation` scheduled command); `detail_code` (`^[A-Z][A-Z0-9_]{0,63}$`); `command_id` |
| `inventory_posting_block_reopenings` | `block_id` (primary key, → the block); `reopened_at`; `actor_user_id`, `actor_display_name` (the Owner, with recent authentication, CAP-18); `reason` (null, up to 1,000); `command_id` |

A product is blocked while it has a block without a reopening. Both writers take the product guard (TR §7.1 step 6), so a block and a posting of the same product serialize (RACE-24); a posting refuses `POSTING_BLOCKED` under that guard (G-11). Runtime privileges on both: `SELECT`, `INSERT`.

### 5.8 Costing

The representation of issue-cost allocations, the replay and the rounding rule are [ADR-008](../adr/ADR-008-cost-allocation-representation.md) (D-060, D-061). This section gives the tables.

**`acquisition_cost_evidence`** — class F. Entity: AcquisitionCostEvidence (BR-COST-02).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `movement_id` | `bigint` | Unique |
| `product_id`, `movement_type`, `quantity` | | `movement_type IN ('OPENING', 'RECEIPT', 'ADJUSTMENT')`; `quantity > 0`; with `movement_id`, → `stock_movements (id, product_id, movement_type, quantity)`: the evidence belongs to one positive increase of that product and quantity |
| `cost_status`, `total_amount` | | Section 3.2 pattern with `numeric(18,6)`: the total landed acquisition amount, or UNKNOWN (BR-COST-02, BR-COST-03) |
| `zero_cost_note` | `text` null | 1–1,000; `CHECK (total_amount IS DISTINCT FROM 0 OR zero_cost_note IS NOT NULL)`: an explicit zero carries its evidence or reason (BR-COST-04). How the Owner confirms a zero entered by OPERATIONS_ADMIN is a workflow step of RF-5 |
| `reference` | `text` null | up to 100 |
| `recorded_at`; `actor_user_id`, `actor_display_name`; `command_id` | | The Owner or OPERATIONS_ADMIN who entered it (BR-COST-05) |

This row is the cost at confirmation that D-047 shows to OPERATIONS_ADMIN. `UNIQUE (id, product_id)` and `UNIQUE (id, product_id, movement_id)` are composite-key targets. Runtime privileges: `SELECT`, `INSERT`.

**`cost_corrections`** — class F. Entity: Cost correction (D-034; BR-COST-20 to BR-COST-22).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `evidence_id`, `product_id` | `bigint` | → `acquisition_cost_evidence (id, product_id)` |
| `correction_no` | `integer` | `>= 1`; `UNIQUE (evidence_id, correction_no)` |
| `old_status`, `old_amount` | | Section 3.2 pattern: the effective value before this correction (the original, or the previous correction's new amount); UNKNOWN when an UNKNOWN cost is completed |
| `new_amount` | `numeric(18,6)` | **Required**, `BETWEEN 0 AND 999999999999.999999`: a correction always records a new value (D-034 "old value, new value"); UNKNOWN is a status, not a value (BR-COST-03), so no correction makes a cost unknown |
| `zero_cost_note` | `text` null | Required when `new_amount = 0` (BR-COST-04) |
| `reason` | `text` | 1–1,000 |
| `recorded_at`; `actor_user_id`, `actor_display_name`; `command_id` | | The Owner (CAP-19); `command_id` unique |

- The **effective amount** of an evidence is the `new_amount` of its correction with the highest `correction_no`, or its original amount when it has none. It is read from these rows, never stored a second time (SCH-01).
- That `old_status` and `old_amount` equal the effective value before, and that `correction_no` is the next number, is guard G-15, under the product guard; the unique key refuses a second correction with the same number.
- `UNIQUE (id, evidence_id)` is a composite-key target. Runtime privileges: `SELECT`, `INSERT`.

**`inventory_valuations`** — class P. Entity: Inventory valuation (the current moving weighted average of a product).

| Column | Type | Rule |
| --- | --- | --- |
| `product_id` | `bigint` | Primary key |
| `value_status`, `value` | | Section 3.2 pattern with `numeric(21,6)` |
| `publication_no` | `bigint` | `>= 1`; increases with every change of the product's valuation, by a posting or a replay publication (ADR-008 section 4.3) |
| `last_sequence` | `bigint` | The movement it reflects |
| `published_at` | `timestamptz` | |

Inserted with the product's first movement; changed by every posting and by every replay publication, in that command's transaction (D-034; BR-COST-25). The quantity is not stored here: it is `stock_balances.on_hand` (SCH-01). Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `value_status`, `value`, `publication_no`, `last_sequence`, `published_at`.

**`issue_cost_allocations`** — class F. The issue allocation fact (ADR-008 section 2; D-060).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `movement_id` | `bigint` | Unique |
| `product_id`, `issue_type`, `movement_quantity`, `product_sequence` | | `issue_type IN ('SALE_ISSUE', 'MANUAL_ISSUE', 'ADJUSTMENT')`; `movement_quantity < 0`; with `movement_id`, → `stock_movements (id, product_id, movement_type, quantity, product_sequence)`: the copied type, quantity and position are those of the movement |
| `issued_quantity` | `numeric(13,3)` | `= -movement_quantity` |
| `sale_line_id` | `bigint` null | Set exactly for `SALE_ISSUE`; `(movement_id, sale_line_id)` → `stock_movements (id, sale_line_id)` and `(sale_line_id, issued_quantity)` → `sale_lines (id, quantity)` |
| `at_commit_status`, `at_commit_amount` | | Section 3.2 pattern with `numeric(21,6)`: the allocation as it was when the issue committed, or UNKNOWN (BR-COST-16) |
| `based_on_publication_no` | `bigint` | The valuation publication the allocation was computed from: the cost-evidence state it depended on |
| `recorded_at` | `timestamptz` | |

`UNIQUE (id, issue_type)` and `UNIQUE (id, sale_line_id, product_id)` are composite-key targets. `FOREIGN KEY (id) REFERENCES issue_cost_allocation_projections (allocation_id) DEFERRABLE INITIALLY DEFERRED`: every fact has its projection. Runtime privileges: `SELECT`, `INSERT`.

**`issue_cost_allocation_projections`** — class P. The current corrected value of each issue allocation (ADR-008 section 2).

| Column | Type | Rule |
| --- | --- | --- |
| `allocation_id` | `bigint` | Primary key |
| `issue_type` | `text` | With `allocation_id`, → `issue_cost_allocations (id, issue_type)` |
| `current_status` | `text` | `KNOWN`, `UNKNOWN` or `CANCELLED`; `CHECK (current_status <> 'CANCELLED' OR issue_type = 'MANUAL_ISSUE')` — only a manual issue can be logically cancelled by a reversal (BR-COST-32, BR-INV-36) |
| `current_amount` | `numeric(21,6)` null | `BETWEEN 0 AND 999999999999999.999999`; `CHECK ((current_status = 'KNOWN') = (current_amount IS NOT NULL))` |
| `publication_no` | `bigint` | The publication that last wrote it |
| `updated_at` | `timestamptz` | |

Inserted with its fact and equal to it (G-14); afterwards written only by a replay publication (ADR-008 section 4.3). Owner reports read it (ARC-24). Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `current_status`, `current_amount`, `publication_no`, `updated_at`.

**`return_cost_allocations`** — class F. The return allocation fact of a saleable return line, linked to the original issue allocation (ADR-008 section 3; D-061).

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `return_line_id` | `bigint` | Unique: one return allocation per return line |
| `movement_id` | `bigint` | Unique; `(movement_id, return_line_id)` → `stock_movements (id, return_line_id)` and `(movement_id, product_id, returned_quantity, product_sequence)` → `stock_movements (id, product_id, quantity, product_sequence)` |
| `product_id`, `sale_line_id` | `bigint` | |
| `return_condition` | `text` | `CHECK (return_condition = 'SALEABLE')`; `(return_line_id, sale_line_id, return_condition, returned_quantity)` → `return_lines (id, sale_line_id, condition, quantity)`: only a saleable return line has a return allocation (BR-COST-19) |
| `issue_allocation_id` | `bigint` | `(issue_allocation_id, sale_line_id, product_id)` → `issue_cost_allocations (id, sale_line_id, product_id)`: the link names the SALE_ISSUE allocation of the same SaleLine and product (D-061) |
| `returned_quantity` | `numeric(13,3)` | `> 0` |
| `at_commit_status`, `at_commit_amount` | | Section 3.2 pattern with `numeric(21,6)`: the cost restored when the return committed, by the cumulative rule of ADR-008 section 5 |
| `product_sequence`; `based_on_publication_no`; `recorded_at` | | As for issue allocations |

`FOREIGN KEY (id) REFERENCES return_cost_allocation_projections (allocation_id) DEFERRABLE INITIALLY DEFERRED`. Runtime privileges: `SELECT`, `INSERT`.

**`return_cost_allocation_projections`** — class P. Columns `allocation_id` (primary key, → `return_cost_allocations`), `current_status` (`KNOWN` or `UNKNOWN`), `current_amount` (`numeric(21,6)` null, `BETWEEN 0 AND 999999999999999.999999`, present exactly when `KNOWN`), `publication_no`, `updated_at`. Recomputed together with the linked issue projection by every replay that reaches it (D-061). Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `current_status`, `current_amount`, `publication_no`, `updated_at`.

**`cost_incomplete_marks`** and **`cost_incomplete_mark_clearances`** — class F. The mark that affected reporting is incomplete after a refused cost correction (D-034; BR-COST-27; T-17 i).

| Table | Column | Rule |
| --- | --- | --- |
| `cost_incomplete_marks` | `id` | `UNIQUE (id, evidence_id, evidence_movement_id)` is the target of the clearance's key |
| | `evidence_id`, `product_id`, `evidence_movement_id` | → `acquisition_cost_evidence (id, product_id, movement_id)`: the cost source whose correction was refused, and its movement. The scope starts at that movement's `recorded_at` and `product_sequence`, read from the movement, never copied |
| | `rejected_operation`, `rejected_source_intent_id` | The refused command's operation and intent; `UNIQUE (rejected_operation, rejected_source_intent_id)` |
| | `rejection_code` | `MOVEMENT_CAP`, `TIME_BUDGET` or `UNSAFE_HISTORY` (TECHNICAL_RULES TR-TIME-01; BR-COST-24) |
| | `corrections_seen` | `integer >= 0`: how many corrections of that evidence the refused attempt saw |
| | `recorded_at`; `actor_user_id`, `actor_display_name` | The Owner who attempted the correction |
| `cost_incomplete_mark_clearances` | `mark_id` | Primary key |
| | `evidence_id`, `evidence_movement_id` | `(mark_id, evidence_id, evidence_movement_id)` → `cost_incomplete_marks (id, evidence_id, evidence_movement_id)`: the clearance belongs to the mark's cost source |
| | `clearing_kind` | `LATER_CORRECTION`, `SOURCE_REVERSED` or `RECOVERY_PROCEDURE` |
| | `clearing_correction_id` | Set exactly for `LATER_CORRECTION` (a discriminator, section 3.6); `(clearing_correction_id, evidence_id)` → `cost_corrections (id, evidence_id)`: a correction or completion of the same cost source |
| | `clearing_reversal_id` | Set exactly for `SOURCE_REVERSED`; `(clearing_reversal_id, evidence_movement_id)` → `reversals (id, original_movement_id)`: the reversal of that source's movement |
| | `cleared_at`; `actor_kind`, `actor_user_id`, `actor_process`; `actor_display_name`; `command_id` (null) | The transaction that wrote the clearance: the later correction or reversal, the mark's own transaction in the race below, or the recovery procedure |

```sql
-- specification text, not a migration (P-7) — cost_incomplete_mark_clearances
CHECK (clearing_kind IN ('LATER_CORRECTION', 'SOURCE_REVERSED', 'RECOVERY_PROCEDURE')),
CHECK ((clearing_kind = 'LATER_CORRECTION') = (clearing_correction_id IS NOT NULL)
   AND (clearing_kind = 'SOURCE_REVERSED')  = (clearing_reversal_id IS NOT NULL))
```

- **Recorded** when a cost correction or completion is refused `REPLAY_REJECTED` because its replay cannot be done safely (BR-COST-27), and only then; the Owner accepted this interpretation of D-034 in D-063. D-034's causes map as follows: an unresolved conflict in the history is `UNSAFE_HISTORY`; the set capacity limit is `MOVEMENT_CAP`; a timeout of the replay is `TIME_BUDGET`; a result outside the ranges of section 3.2 is `UNSAFE_HISTORY`. A concurrency issue is not expected to interrupt a replay once it runs, because every writer of the product's valuation and projections holds the product guard first (section 7); should an unforeseen deadlock still end it, the bounded heavy command is not retried and the answer is `SERVICE_BUSY` with no mark (TECHNICAL_RULES TR-RETRY-01). A command that cannot obtain that guard within the lock timeout has not started its replay and is answered `SERVICE_BUSY`: the client stays UNCERTAIN and repeats the same identity (TECHNICAL_RULES TR-RETRY-02), and no mark is written. An ordinary validation refusal writes no mark; neither does a correction refused because the product is blocked (`POSTING_BLOCKED`, guard G-11), which never starts its replay and whose block is already an incident raised to the Owner (BR-INV-45); and neither does a refused reversal: the reversal is refused with no change, like any other refused posting (BR-INV-37). The mark carries no amount; the attempted value is kept only in the `owner_only_data` of its audit event.
- **Written after the rollback.** The refused correction's transaction writes nothing (TECHNICAL_RULES section 1). The mark and an audit event `COST_CORRECTION_REFUSED` are then written in one small transaction of their own, which takes TR §7.1 steps 2.1 (the actor, share lock), 6 (the product, update lock) and 8, and inserts the mark only if no mark exists for the refused intent, so that a retry of the same intent writes one mark. The server answers `REPLAY_REJECTED` only after that transaction has committed; if it fails, the answer is `SERVICE_BUSY` and the client stays UNCERTAIN, so a refused correction is never reported without its mark (TR-TIME-01). If the Owner then abandons that intent, or a resolution fences it (ADR-007 section 4), no correction and no mark exist: the reports reflect exactly the published evidence, and the Owner can submit the correction again.
- **Race with a later correction or a reversal.** Under the product guard, the mark's transaction checks whether the evidence has received more corrections than `corrections_seen` — a later correction published newer evidence after the refused attempt — or whether its source movement has a reversal. If so, it writes the mark together with its clearance, naming that correction or reversal as the cause. A reversal found here may predate the refused attempt, if a correction of an already reversed source was attempted; the clearance is then immediate, which is right, because that source no longer contributes to any figure (BR-COST-32); `cleared_at` and the actor columns are those of the mark's transaction.
- **Scope.** An active mark — one without a clearance — makes incomplete every Owner cost figure of its product (cost of goods sold, gross profit, margin, inventory value) for every report period that ends after the `recorded_at` of `evidence_movement_id`. A report states that it is incomplete and how many products carry an active mark (BR-COST-38). OPERATIONS_ADMIN and CASHIER never see marks (BR-INV-15, D-033).
- **Cleared only** (a) by a later successful correction or completion of the **same cost source**, or (b) by a successful reversal of that source's movement — each a replay that later succeeds for the same source, which writes a clearance for every active mark of that evidence in its own transaction (the correction's or the reversal's) — or (c) by the reviewed recovery procedure of RF-6. Nothing else clears a mark: not a correction of another source, not time, and never editing — the runtime role cannot update or delete either table.
- **Why this adds no business meaning.** D-034 keeps the affected reporting marked incomplete "until a reviewed recovery procedure exists"; clearing (c) is that procedure. Clearings (a) and (b) are the accepted reading of item 5 of the RF-3B package (D-059): a mark is cleared by a replay that later succeeds for the same source. After (a) the source carries the latest cost evidence, so the figures it covers are, by D-034's own rule, "corrected figures based on the latest cost evidence" (BR-COST-30); after (b) the source no longer contributes to any figure (BR-COST-32). In both cases continuing to call the figures incomplete would be false (BR-COST-38). The scope follows the replay's own range: a correction affects its product from its source onward (BR-COST-23).
- Runtime privileges on both: `SELECT`, `INSERT`.

### 5.9 Sales

**`sales`** — class F. Entity: Sale.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `sale_number` | `text` | Unique; `^[0-9]{8}-[0-9]{6,}$` (section 6; D-062) |
| `sale_number_seq` | `bigint` | Unique; `>= 1`; the counter value allocated for the number |
| `business_date` | `date` | Asia/Jakarta, computed by the server at commit (BR-SALE-07) |
| `committed_at` | `timestamptz` | |
| `cash_session_id`, `actor_user_id` | `bigint` | → `cash_sessions (id, responsible_user_id)`: a Sale belongs to a session whose responsible user is the actor (BR-SALE-02; D-009, D-032) |
| `actor_display_name` | `text` | Frozen (section 3.5) |
| `total_amount` | `bigint` | Money |
| `line_count` | `smallint` | 1–200 (TR §11.2) |
| `customer_name` | `text` null | up to 100 (BR-SALE-08) |
| `command_id` | `bigint` | Unique: one Sale per command, and with `UNIQUE (operation, source_intent_id)` one per intent (BR-SALE-18) |

```sql
-- specification text, not a migration (P-7)
FOREIGN KEY (cash_session_id, actor_user_id) REFERENCES cash_sessions (id, responsible_user_id),
CHECK (sale_number = (EXTRACT(YEAR FROM business_date) * 10000 + EXTRACT(MONTH FROM business_date) * 100
                      + EXTRACT(DAY FROM business_date))::integer::text
                     || '-' || lpad(sale_number_seq::text, greatest(6, char_length(sale_number_seq::text)), '0')),
UNIQUE (id, total_amount),
UNIQUE (id, sale_number, total_amount, cash_session_id, actor_display_name),   -- target for receipt_snapshots
FOREIGN KEY (id) REFERENCES payments (sale_id) DEFERRABLE INITIALLY DEFERRED,              -- exactly one Payment
FOREIGN KEY (id) REFERENCES receipt_snapshots (sale_id) DEFERRABLE INITIALLY DEFERRED,     -- exactly one receipt
FOREIGN KEY (id) REFERENCES sale_correction_totals (sale_id) DEFERRABLE INITIALLY DEFERRED -- correction guard row
```

That the session is OPEN at commit is guard G-04 under the session lock (the status is a state column). That the total equals the sum of line totals and `line_count` the number of lines is guard G-01. The number check holds section 6's format (SCH-22). That `business_date` is the Asia/Jakarta date of `committed_at` is guard G-18, not a check. PostgreSQL could express it, but it assumes every check is immutable and checks the rows again when a logical backup is restored; the conversion depends on the server's time-zone data, and a change in that data could then refuse rows that were valid when written. Runtime privileges: `SELECT`, `INSERT`.

**`sale_lines`** — class F. Entity: SaleLine.

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `sale_id` | `bigint` | |
| `line_no` | `smallint` | 1–200 |
| `product_id` | `bigint` | |
| `selling_price_revision_id`, `unit_price` | | `(selling_price_revision_id, product_id, unit_price)` → `selling_price_revisions (id, product_id, amount)`: the applied price is a real revision of this product with this amount (BR-SALE-05, D-036) |
| `quantity` | `numeric(13,3)` | Line quantity |
| `line_total` | `bigint` | Money; `CHECK (line_total = round(quantity * unit_price))` |

```sql
-- specification text, not a migration (P-7)
CHECK (line_total = round(quantity * unit_price)),        -- BR-NUM-01: half-up once per line (values are non-negative)
UNIQUE (sale_id, line_no),
UNIQUE (id, quantity),
UNIQUE (id, line_total, quantity),
UNIQUE (id, sale_id),
UNIQUE (id, sale_id, product_id),
UNIQUE (id, product_id, quantity),                        -- target for the SALE_ISSUE movement
FOREIGN KEY (id) REFERENCES stock_movements (sale_line_id) DEFERRABLE INITIALLY DEFERRED  -- BR-SALE-09
```

PostgreSQL's `round` on `numeric` rounds ties away from zero, which for non-negative values is half-up (section 15). No uniqueness of product per Sale is enforced. Whether two lines of the same product in one checkout are stored as entered or combined into one line is not decided: it changes the receipt and, through once-per-line rounding (BR-SALE-06), can change a total, so it is OPEN_ITEMS question 35, deferred by the Owner to before RF-5 (D-063). The schema allows either answer. The stock check adds such lines together in any case (BR-INV-07; G-02). Runtime privileges: `SELECT`, `INSERT`.

**`payments`** — class F. Entity: Payment.

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `sale_id` | `bigint` | `sale_id` unique: at most one Payment per Sale; with the Sale's deferred key, exactly one (BR-PAY-01) |
| `method` | `text` | `CASH`, `BANK_TRANSFER`, `STATIC_QRIS` (D-036) |
| `applied_amount` | `bigint` | `(sale_id, applied_amount)` → `sales (id, total_amount)`: the applied amount equals the Sale total (BR-PAY-02, BR-PAY-03) |
| `tendered_amount`, `change_amount` | `bigint` null | Money |
| `reference` | `text` null | up to 100 |

```sql
-- specification text, not a migration (P-7)
CHECK ((method = 'CASH' AND tendered_amount IS NOT NULL AND tendered_amount >= applied_amount
        AND change_amount IS NOT NULL AND change_amount = tendered_amount - applied_amount
        AND reference IS NULL)
    OR (method IN ('BANK_TRANSFER', 'STATIC_QRIS') AND tendered_amount IS NULL AND change_amount IS NULL)),
UNIQUE (sale_id, method),                                  -- target for receipt_snapshots
UNIQUE (sale_id, method, tendered_amount, change_amount)    -- target for receipt_snapshots (CASH)
```

A Payment for BANK_TRANSFER or STATIC_QRIS exists only because the cashier's final action confirmed that the funds were received (D-040, BR-SALE-12); no further column records it. Runtime privileges: `SELECT`, `INSERT`.

**`receipt_snapshots`** — class F. Entity: ReceiptSnapshot (BR-DOC-01).

| Column | Type | Rule |
| --- | --- | --- |
| `id`; `sale_id` | `bigint` | `sale_id` unique |
| `sale_number`, `total_amount`, `cash_session_id`, `cashier_display_name` | | `(sale_id, sale_number, total_amount, cash_session_id, cashier_display_name)` → `sales (id, sale_number, total_amount, cash_session_id, actor_display_name)`: the printed number, total, session and cashier are the Sale's |
| `business_profile_revision_id` | `bigint` | The shop identity in force, frozen by reference to the immutable revision (BR-CFG-03) |
| `logo_file_id` | `bigint` null | → `stored_files`; the asset revision |
| `template_version` | `text` | `^[a-z0-9._-]{1,64}$`; templates live in the code base and are never changed in place (ARC-45) |
| `issued_at` | `timestamptz` | |
| `cash_session_number` | `text` | `(cash_session_id, cash_session_number)` → `cash_sessions (id, session_number)` |
| `payment_method` | `text` | `(sale_id, payment_method)` → `payments (sale_id, method)` |
| `tendered_amount`, `change_amount` | `bigint` null | `CHECK ((payment_method = 'CASH') = (tendered_amount IS NOT NULL) AND (tendered_amount IS NULL) = (change_amount IS NULL))`; for CASH, `(sale_id, payment_method, tendered_amount, change_amount)` → `payments (sale_id, method, tendered_amount, change_amount)`, a key that applies when the amounts are set (section 3.6) |
| `customer_name` | `text` null | |
| `lines` | `jsonb` | `CHECK (jsonb_typeof(lines) = 'array')`: for each line its number, SKU, product name, unit, quantity, unit price and line total as frozen strings |

It holds no cost or profit (BR-DOC-01, BR-COST-08). It is never changed; a reprint adds only an audit event (BR-DOC-04). The keys above tie its number, total, session, cashier, method and cash amounts to the records; guard G-17 checks the rest — the customer name, the lines, the frozen identity — against the Sale. Runtime privileges: `SELECT`, `INSERT`.

**`sale_corrections`**, **`refunds`**, **`refund_lines`**, **`return_lines`** — class F. Entities: Refund and Return (Owner only, D-035). One correction command may hold a refund, a return or both (BR-RFD-04).

| Table | Column | Rule |
| --- | --- | --- |
| `sale_corrections` | `id`; `sale_id` | `UNIQUE (id, sale_id)` |
| | `business_date` | The posting date, Asia/Jakarta (BR-COST-36) |
| | `reason` | 1–1,000 |
| | `recorded_at`; `actor_user_id`, `actor_display_name`; `command_id` (unique) | |
| `refunds` | `id`; `correction_id` (unique); `sale_id` | `(correction_id, sale_id)` → `sale_corrections (id, sale_id)`; `UNIQUE (id, sale_id)` |
| | `amount` | Money, `> 0` |
| | `payout_method` | `CASH` or `BANK_TRANSFER` (BR-RFD-05: no source defines a STATIC_QRIS payout) |
| | `funding_source` | `DRAWER` or `OWNER_FUNDS` for CASH; null for BANK_TRANSFER (BR-RFD-06) |
| | `cash_session_id` | Set exactly when `funding_source = 'DRAWER'` (BR-RFD-07) |
| | `payout_reference` | 1–100: BR-RFD-05 records every payout with its reference |
| `refund_lines` | `id`; `refund_id`; `sale_id`; `sale_line_id` | `(refund_id, sale_id)` → `refunds (id, sale_id)`; `(sale_line_id, sale_id)` → `sale_lines (id, sale_id)`; `UNIQUE (refund_id, sale_line_id)` |
| | `amount` | Money, `> 0` |
| `return_lines` | `id`; `correction_id`; `sale_id`; `sale_line_id`; `product_id` | `(correction_id, sale_id)` → `sale_corrections (id, sale_id)`; `(sale_line_id, sale_id, product_id)` → `sale_lines (id, sale_id, product_id)`: the returned product is the SaleLine's |
| | `quantity` | Line quantity |
| | `condition` | `SALEABLE` or `NOT_SALEABLE`; `UNIQUE (correction_id, sale_line_id, condition)` |
| | `movement_presence` | Presence key (section 3.7), stored generated: equal to `id` exactly when `SALEABLE`; deferred → `stock_movements (return_line_id)`: a saleable return line posts its RETURN_RECEIPT and a not-saleable one posts none (BR-INV-39) |

```sql
-- specification text, not a migration (P-7) — refunds
CHECK ((payout_method = 'CASH' AND funding_source IS NOT NULL
        AND funding_source IN ('DRAWER', 'OWNER_FUNDS'))
    OR (payout_method = 'BANK_TRANSFER' AND funding_source IS NULL)),
CHECK ((funding_source IS NOT DISTINCT FROM 'DRAWER') = (cash_session_id IS NOT NULL))   -- exactly one source (D-045)
```

- Every line belongs to the corrected Sale, by the composite keys above. `return_lines` keeps `UNIQUE (id, condition, product_id, quantity)` and `UNIQUE (id, sale_line_id, condition, quantity)` as composite-key targets.
- That a refund's amount is the sum of its lines is guard G-06. That a drawer-funded refund names an OPEN session and fits its expected cash is guard G-05 (D-045).
- Runtime privileges on all four: `SELECT`, `INSERT`.

**`sale_correction_totals`** and **`sale_line_correction_totals`** — class P. The cumulative bounds of BR-RFD-03 as running totals with check constraints.

| Table | Columns and checks |
| --- | --- |
| `sale_correction_totals` | `sale_id` (primary key); `sale_total`, with `sale_id` → `sales (id, total_amount)`; `refunded_amount` `CHECK (refunded_amount BETWEEN 0 AND sale_total)`; `updated_at` |
| `sale_line_correction_totals` | `sale_line_id` (primary key); `line_total` and `issued_quantity`, with `sale_line_id` → `sale_lines (id, line_total, quantity)`; `refunded_amount` `CHECK (refunded_amount BETWEEN 0 AND line_total)`; `returned_quantity` `CHECK (returned_quantity BETWEEN 0 AND issued_quantity)`; `updated_at` |

- `sale_correction_totals` is inserted with every Sale, with `refunded_amount = 0` (the Sale's deferred key requires it). It is the row that TR §7.1 step 5 locks for a refund or return: the original Sale lock (section 7). A `sale_line_correction_totals` row is inserted at the first refund or return of its line, under that lock.
- Every refund adds its amounts and every return line, saleable or not, adds its quantity in the correction's transaction. The database therefore refuses any write that would refund more than the Sale's paid value or a line's total, or return more than a line's issued quantity, also if two corrections race or the application errs (BR-RFD-03; RACE-10; ADR-008 section 3).
- Every update of a running total is a relative increment in one statement (`SET returned_quantity = returned_quantity + $1`), never an absolute value computed from an earlier read, so that the check constraint judges the true cumulative value even if a lock were missing (SCH-33).
- That the running totals equal the sums of the lines is guard G-06; the daily reconciliation recomputes them from the refund and return lines and reports any difference (G-03).
- Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `refunded_amount`, `returned_quantity` (lines only), `updated_at`.

### 5.10 Cash

**`cash_registers`** — class M. Entity: CashRegister.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `code` | `text` | 1–32; unique without regard to letter case (SCH-14): the code identifies a drawer on screens and reports. Uniqueness carries legacy L-02's "code/label unik" for CashRegister; the label is not unique |
| `label` | `text` | 1–100 |
| `active` | `boolean` | |
| `version` | `integer` | `>= 1` |
| `created_at`, `updated_at` | `timestamptz` | |

TECHNICAL_RULES section 11.3 sets no length for the code and label; these column bounds are the limits until RF-5. A change is allowed only while no session is OPEN on the register, under the register lock (BR-CASH-02; G-19; RACE-26). Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `code`, `label`, `active`, `version`, `updated_at`.

**`cash_sessions`** — class FS. Entity: CashSession.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | `bigint` | |
| `session_number` | `text` | Unique; `^[0-9]{8}-[0-9]{6,}$` (section 6; D-062) |
| `session_number_seq` | `bigint` | Unique; `>= 1` |
| `opened_business_date` | `date` | The Asia/Jakarta business date at opening; the date part of the number |
| `register_id` | `bigint` | → `cash_registers` |
| `responsible_user_id`, `responsible_display_name` | | → `users`; frozen name (section 3.5) |
| `opening_cash` | `bigint` | Money; zero or more (BR-CASH-04) |
| `opened_at` | `timestamptz` | |
| `open_command_id` | `bigint` | Unique |
| `status` | `text` | State column: `OPEN` or `CLOSED` |
| `activity_version` | `bigint` | State column: `>= 0`; plus one with every Sale and every drawer-funded refund of the session (BR-CASH-10) |
| `close_presence` | `bigint`, stored generated | Presence key (section 3.7): equal to `id` exactly when `CLOSED` |
| `updated_at` | `timestamptz` | |

```sql
-- specification text, not a migration (P-7)
close_presence bigint GENERATED ALWAYS AS (CASE WHEN status = 'CLOSED' THEN id END) STORED,
CHECK (session_number = (EXTRACT(YEAR FROM opened_business_date) * 10000
                         + EXTRACT(MONTH FROM opened_business_date) * 100
                         + EXTRACT(DAY FROM opened_business_date))::integer::text
                        || '-' || lpad(session_number_seq::text,
                                       greatest(6, char_length(session_number_seq::text)), '0')),
UNIQUE (id, responsible_user_id),                     -- target for sales
UNIQUE (id, status),                                  -- target for cash_session_closes
UNIQUE (id, session_number),                          -- target for receipt_snapshots
UNIQUE (id, session_number, opening_cash, opened_at, responsible_display_name),   -- target for reports
FOREIGN KEY (close_presence) REFERENCES cash_session_closes (session_id) DEFERRABLE INITIALLY DEFERRED;
CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON cash_sessions (register_id) WHERE status = 'OPEN';
CREATE UNIQUE INDEX cash_sessions_one_open_per_user ON cash_sessions (responsible_user_id) WHERE status = 'OPEN';
```

- **One OPEN session per register and per user** is a database guarantee, also when two opens race (BR-CASH-06; RACE-07).
- **A CLOSED session stays CLOSED.** Its close row references `(id, status)` with `status = 'CLOSED'` and `ON UPDATE RESTRICT`, so the status can never change back; the responsible user and every other identity column are outside the runtime role's `UPDATE` privilege (BR-CASH-19, D-009). `activity_version` changes only through a checkout or a drawer-funded refund, which require the session to be OPEN under its lock (G-04); the close row and the report keep the cutoff as it was counted.
- That `opened_business_date` is the Asia/Jakarta date of `opened_at` is guard G-18 (see `sales`).
- Runtime privileges: `SELECT`, `INSERT`; `UPDATE` of `status`, `activity_version`, `updated_at`.

**`cash_session_closes`** — class F. The close of a session (BR-CASH-13 to BR-CASH-19).

| Column | Type | Rule |
| --- | --- | --- |
| `session_id` | `bigint` | Primary key |
| `session_status` | `text` | `CHECK (session_status = 'CLOSED')`; `(session_id, session_status)` → `cash_sessions (id, status)` `ON UPDATE RESTRICT`. The session row is updated to `CLOSED` before this row is inserted |
| `closed_at` | `timestamptz` | |
| `close_kind` | `text` | `OWN` or `OWNER_ASSISTED` |
| `close_actor_user_id`, `close_actor_display_name` | | The person who closed (BR-CASH-18) |
| `close_reason` | `text` null | 1–1,000; `CHECK (close_kind <> 'OWNER_ASSISTED' OR close_reason IS NOT NULL)` |
| `activity_version_at_close` | `bigint` | The cutoff: the version the close was counted against (BR-CASH-13) |
| `physical_cash`, `expected_cash` | `bigint` | Money; zero or more |
| `variance` | `bigint` | `CHECK (variance = physical_cash - expected_cash)` (BR-CASH-12) |
| `command_id` | `bigint` | Unique: one close per session; a repeat finds the session CLOSED (BR-CASH-17) |

`FOREIGN KEY (session_id) REFERENCES cash_session_reports (session_id) DEFERRABLE INITIALLY DEFERRED`: a close has its report. `UNIQUE (session_id, closed_at, activity_version_at_close, physical_cash, expected_cash, variance, close_actor_display_name)` is the target of the report's key. Runtime privileges: `SELECT`, `INSERT`.

**`cash_session_reports`** — class F. Entity: CashSessionReportSnapshot (BR-CASH-20).

| Column | Rule |
| --- | --- |
| `session_id` | Primary key; → `cash_session_closes` |
| `session_number`, `opening_cash`, `opened_at`, `responsible_display_name` | `(session_id, session_number, opening_cash, opened_at, responsible_display_name)` → `cash_sessions (id, session_number, opening_cash, opened_at, responsible_display_name)`. The report is identified by its session's number: there is exactly one report per session, so BR-CASH-20's "report and session number" is one number and no separate sequence exists |
| `business_profile_revision_id`, `logo_file_id`, `template_version` | Frozen identity, asset and template |
| `register_code`, `register_label` | Frozen (G-17): the register's current values may change later |
| `closed_at`, `cutoff_activity_version`, `physical_cash`, `expected_cash`, `variance`, `close_actor_display_name` | `(session_id, closed_at, cutoff_activity_version, physical_cash, expected_cash, variance, close_actor_display_name)` → `cash_session_closes (session_id, closed_at, activity_version_at_close, physical_cash, expected_cash, variance, close_actor_display_name)`: the report shows the close as it was recorded |
| `completed_sale_count`, `completed_sale_value`, `cash_applied`, `bank_transfer_applied`, `static_qris_applied`, `drawer_refund_total` | Money and counts as closed; `CHECK (expected_cash = opening_cash + cash_applied - drawer_refund_total)` and `CHECK (variance = physical_cash - expected_cash)` |
| `drawer_refunds` | `jsonb` array: each drawer-funded refund with its Sale number, refund reference, amount, time and the Owner's frozen name (D-035) |
| `source_ids` | `jsonb`: the internal keys of the Sales, Payments and drawer-funded refunds the close derived its totals from (BR-CASH-13) |
| `close_reason`, `notes` | null; up to 1,000 each. The close reason is copied from the close and checked by guard G-17 |

It holds no cost, cost evidence, margin, profit or security data (BR-CASH-20). Runtime privileges: `SELECT`, `INSERT`.

**`cash_session_review_notes`** — class F. Entity: Owner review note (BR-CASH-19). Columns `id`; `session_id` (→ `cash_session_closes`); `note` (1–2,000); `recorded_at`; `actor_user_id`, `actor_display_name`; `command_id` (unique). Whether a note can be edited is T-17 f (RF-5); until then the table is insert-only. Runtime privileges: `SELECT`, `INSERT`.

### 5.11 Framework tables

| Table | Class | Use | Runtime privileges |
| --- | --- | --- | --- |
| `sessions` | E | Section 5.1 | All four |
| `password_reset_tokens` | E | Section 5.1 | All four |
| `cache`, `cache_locks` | E | The database cache, used only for rate-limiter counters and scheduler locks (ADR-002, TR-RUN-02) | All four |
| `migrations` | — | The framework's record of applied migrations | None; the migration role owns it |

## 6. Document numbers (D-062)

The Owner decided (D-062; question 32, option A) that Sale numbers and cash session numbers are unique per installation, generated by the server inside the committing transaction, monotonically allocated, allowed to have gaps, and carry the Asia/Jakarta business date as a readable part, with the Owner's clarification that "monotonically increasing" means allocation order, not commit order.

| ID | Rule | Source |
| --- | --- | --- |
| SCH-20 | **Two sequences.** `sale_number_seq` and `cash_session_number_seq`, each `AS bigint START 1 INCREMENT 1 NO CYCLE CACHE 1`, owned by the migration role. With a cache of one, PostgreSQL hands out values in the order they are requested across all sessions; with a larger cache it would only guarantee distinct values, so the cache must stay one. `NO CYCLE` means a sequence never wraps. | D-062; PostgreSQL documentation (section 15) |
| SCH-21 | **Allocation inside the transaction, at the last moment.** The value is taken with `nextval` inside the committing transaction, at TR §7.1 step 8, after every guard has passed and immediately before the Sale or cash session row is inserted, so that a definite rejection consumes no number. The clock is read for `committed_at` (or `opened_at`) and the business date at the same step, next to `nextval`, so that the date part and the counter are taken as close together as possible. The number reaches the browser only in the committed result. | D-062, BR-DOC-01, BR-CASH-04 |
| SCH-22 | **Format.** `YYYYMMDD-n`: `YYYYMMDD` is the record's business date in Asia/Jakarta — for a Sale the `business_date` computed at commit (BR-SALE-07), for a session `opened_business_date` — and `n` is the sequence value written in decimal, zero-padded to at least six digits. Padding never truncates: after 999999 the counter simply has more digits. The database checks `^[0-9]{8}-[0-9]{6,}$`, and a check constraint on each table (sections 5.9 and 5.10) requires the number to be exactly the record's business date and its `*_seq` value in this format, using only immutable date and text functions. Example: `20261005-000123`. | D-062 |
| SCH-23 | **One counter per installation.** The counter part does not restart each day; the date is there to be read. Option A as decided is "unique per installation, always increasing", and one installation-wide counter is the simplest reading of it that needs no lock. Option B, which the Owner declined, was a gapless sequence per business day that serializes every checkout; a per-day counter without gaps is not possible without that serialization, and a per-day counter with gaps would add a reset rule that no decision asks for. | D-062 |
| SCH-24 | **Unique and immutable.** Both the number and its counter value are unique columns. A Sale number never changes because `sales` is insert-only; a session number never changes because it is outside the runtime role's `UPDATE` privilege. | D-062, BR-DOC-01, BR-CASH-04; L-17, L-02 ("nomor unik, immutable") |
| SCH-25 | **Ordering semantics.** The counter part increases in the order the values are allocated. Two concurrent transactions may commit in the other order. Around midnight, a transaction that read the clock just before midnight may take its counter value just after another transaction that read the clock just after midnight; the number allocated later then carries the earlier date, for example `20261005-000101` after `20261006-000100`. The window is the short time between reading the clock and calling `nextval` at the same step (SCH-21). Both numbers are valid and unique, and the counter still follows allocation order, which is what D-062 requires; the full number is not a sort key across midnight (accepted by the Owner, D-063). Nothing serializes checkouts, or session opens, for numbering: no counter row, no advisory lock, no table lock. | D-062 (Owner's clarification), D-063 |
| SCH-26 | **Gaps are valid.** A value taken by a transaction that later rolls back or fails, or lost in a database crash, is never reused (PostgreSQL never rolls back `nextval`). A gap is not an error: nothing reports, explains or fills it. | D-062; PostgreSQL documentation (section 15) |
| SCH-27 | **Privileges.** The runtime role has `USAGE` on both sequences, which allows `nextval`, and not `UPDATE`, which would allow `setval`; the application can therefore never move or reset a sequence. | PDS §3; PostgreSQL documentation (section 15) |
| SCH-28 | **After a restore.** A restore brings back each sequence's value from the backup, while Sales and sessions committed after the backup point may already have numbers printed on receipts in customers' hands. The restore runbook (RF-6) therefore advances both sequences, with the migration role, past every value that may have been issued after the backup point, before the shop reopens. The jump creates a gap, which SCH-26 allows. Uniqueness inside the restored database holds in any case. The same applies to any promotion of a database copy that may lag behind the primary, and the production database runs with synchronous commit, so that a number shown after commit is durable (RF-6). | D-062, BR-CMD-06; input to RF-6 |

How the number is shown on receipts, in history and in reports is P7 (RF-4); the document label or prefix comes from the BusinessProfile's document identity (BR-CFG-01).

## 7. Locks on schema rows

TECHNICAL_RULES section 7 defines the lock order; this table says which row each step locks and with which PostgreSQL row-lock mode. "Update lock" is `FOR NO KEY UPDATE`, the mode an `UPDATE` that changes no key column takes itself; it conflicts with every other update and share lock but not with the key-share lock of a foreign-key check. Where the command changes a column that a composite foreign key references, the update lock is `FOR UPDATE`, because that `UPDATE` takes `FOR UPDATE` itself and PostgreSQL advises taking the most restrictive mode needed first. "Share lock" is `FOR SHARE`. PostgreSQL requires `UPDATE` privilege on at least one column of a table to lock its rows; every table locked below has one for the runtime role (section 10).

| TR §7.1 step | Row | Share lock | Update lock |
| --- | --- | --- | --- |
| 1. Command identity | `command_identities` | — | Not a row lock: the insert of the identity waits on the unique index for a concurrent identical identity (section 5.3) |
| 2.1 Acting user | `users` | `FOR SHARE` | `FOR NO KEY UPDATE` when the target is the actor's own account (TR-LOCK-05) |
| 2.2 Target account | `users` | — | `FOR NO KEY UPDATE` |
| 2.3 Installation state | `onboarding_state` | `FOR SHARE` (postings) | `FOR NO KEY UPDATE` (set, release) |
| 2.3 Installation state | `recovery_epoch` | Read without a lock (section 5.3) | `FOR NO KEY UPDATE` (after-restore procedure) |
| 2.3 Bootstrap | `recovery_epoch`, `onboarding_state` | — | Inserted by the bootstrap; a concurrent bootstrap waits on the primary key and then fails (RACE-30) |
| 2.4 Current BusinessProfile | `business_profile_current` | `FOR SHARE` (snapshot) | `FOR NO KEY UPDATE` (change); inserted with the first revision |
| 3. Cash register | `cash_registers` | — | `FOR NO KEY UPDATE` (open, configuration) |
| 4. Cash session | `cash_sessions` | `FOR SHARE` (review note) | `FOR NO KEY UPDATE` (checkout, drawer-funded refund); `FOR UPDATE` (close, because it changes `status`) |
| 5. Original Sale | `sale_correction_totals` of the Sale | — | `FOR NO KEY UPDATE`. `sales` is insert-only and the runtime role cannot lock its rows; the Sale's correction-totals row, created with the Sale, is its guard row |
| 6. Categories | `categories` | `FOR SHARE` (assigned) | `FOR NO KEY UPDATE` (rename, lifecycle) |
| 6. Products | `products` | — | `FOR NO KEY UPDATE`; `FOR UPDATE` when the command changes `base_unit` or `quantity_precision` (allowed only before the first movement) |
| 7. Balances and cost projections | `stock_balances`, `inventory_valuations`, and the allocation projections a replay rewrites | — | `FOR NO KEY UPDATE`, or the `INSERT` of the product's first rows |
| 8. Append | Every fact table | — | `INSERT` only |

| ID | Rule | Source |
| --- | --- | --- |
| SCH-30 | The query builder takes these modes with `lock('for no key update')`, `lockForUpdate()` and `sharedLock()`. The foundation Task verifies against the pinned framework version that each emits the intended SQL (STK-04). | Laravel API documentation (section 15) |
| SCH-31 | A guard that depends on current state reads it after the locks of its step are held (TR-VAL-08). A guard reads rows it has not locked only where this document says the value cannot change concurrently (the epoch, section 5.3; facts, which never change). | TR-VAL-08, SCH-02 |
| SCH-32 | **Unique keys in a canonical order.** Within one command, rows whose unique keys another command may insert at the same time are inserted in a fixed order — products by `lower(sku)`, then barcode tokens by `token`, then active mappings by token — so that two commands never wait on each other's unique-index entries in opposite orders (for example a CSV import and a product creation that share a SKU and a token). | TR-LOCK-01 |
| SCH-33 | **Relative increments.** The running totals of section 5.9 (`refunded_amount`, `returned_quantity`) and the session's `activity_version` are updated by a relative increment in one statement, never by writing an absolute value computed from an earlier read. A replay's publication of valuations and projections (ADR-008 section 4.3) and the balance update of a posting write values computed under the product guard and are outside this rule. | Data integrity (PROJECT_RULES section 2) |
| SCH-34 | **Lock only the intended table.** A locking query that joins tables names the locked table with `FOR … OF`, because the runtime role cannot lock rows of insert-only tables (section 10). | PostgreSQL documentation (section 15) |

## 8. Guards: invariants that no single constraint holds

A check constraint sees one row; a unique index or foreign key sees one relationship. The invariants below involve several rows or a state transition and are therefore held by guards in the Actions, under the locks of TECHNICAL_RULES section 7, and proven by tests (section 9.4). Where a projection or a constraint backs a guard up, the table says so.

| ID | Invariant | Held by, and backed by |
| --- | --- | --- |
| G-01 | A Sale's total is the sum of its line totals; `line_count` equals its lines; it has at least one line | Checkout Action, before insert. Backed by: the per-line check `line_total = round(quantity * unit_price)`; the deferred keys from every line to its movement and from the Sale to its Payment and receipt |
| G-02 | Stock is sufficient for the lines of a command, added together per product, before any write (BR-INV-07) | Posting Actions under the product guard and balance lock (steps 6, 7). Backed by `stock_balances.on_hand >= 0` |
| G-03 | `stock_balances.on_hand` equals the sum of the product's movements and `last_sequence` their highest sequence; `inventory_valuations` and every allocation projection equal what ADR-008 computes from the facts; the running totals of section 5.9 equal the sums of their lines; each active barcode mapping is its token's mapping without a retirement | Posting, correction, replay and barcode Actions in the same transaction. Checked daily by the read-only reconciliation, a scheduled command outside any request budget: it reads each product in one consistent snapshot, recomputes cost values with the application's own arithmetic, never with SQL multiplication, division or rounding (ADR-008 section 5), and blocks the product's postings on an inventory or cost mismatch (BR-INV-45). A difference in a running total or an active mapping is reported as a reconciliation incident (BR-INV-46); its handling is the incident runbook of RF-6 |
| G-04 | Checkout, a drawer-funded refund and a close act on a session that is OPEN at that moment; checkout uses the actor's own session | Actions under the session lock (step 4). Backed by the composite key Sale → session of its actor (section 5.9) |
| G-05 | Expected cash = opening cash + applied CASH payments − drawer-funded CASH refunds of the session, computed from those facts under the session lock; a drawer-funded refund is at most the selected session's expected cash, so expected cash never becomes negative (D-035, D-045) | Refund and close Actions under the session lock. Backed by `cash_session_closes.expected_cash >= 0` and the report's check `expected_cash = opening_cash + cash_applied - drawer_refund_total`. No maintained expected-cash projection exists: the session lock already serializes every writer, and a stored copy would be a second number to reconcile (ARC-27) |
| G-06 | A refund's amount is the sum of its lines; each running total of section 5.9 equals the sum of the refund and return lines it covers | Correction Action under the original Sale lock (step 5). Backed by the running totals' check constraints, which refuse any excess |
| G-07 | A replay validates the whole affected history and stays within its movement cap and time budget; a reversal leaves a valid history (BR-COST-24, BR-INV-37) | Replay under the product guard (ADR-008 section 4.3); refused `REPLAY_REJECTED` with no change |
| G-08 | After bootstrap there is always at least one ACTIVE SUPER_ADMIN account | User-management Actions never deactivate, anonymize or demote the active Owner (CAP-33); the Owner-replacement procedure deactivates the outgoing account and inserts the new one in one transaction. Backed by the partial unique index for "at most one" (section 5.1) |
| G-09 | A barcode mapping that has a retirement has no active row; a correction moves the token to a different product; a declared EAN or UPC token has a valid check digit | Barcode Actions under both products' guards (step 6). Backed by the primary key of `barcode_active_mappings` and the correction's composite keys |
| G-10 | While onboarding is on, ordinary postings and Sales are refused; OPENING only while it is on (BR-INV-11, BR-INV-24) | Posting Actions under the onboarding share lock (step 2.3) |
| G-11 | A posting refuses a product with an active posting block, and so does a cost correction or completion, because its replay would publish on projections the reconciliation found wrong and BR-INV-46 allows only a controlled repair; every quantity respects its product's precision where the precision is not on the same row | Posting and correction Actions under the product guard; corrections and reversals resume only after the Owner reopens the product (BR-INV-46; D-063) |
| G-12 | An enveloped command's epoch equals the installation's current epoch | Commands module, before any fact (section 5.3) |
| G-13 | Every allocation fact holds the value ADR-008 computes at commit | Costing services in the posting transaction; proven by the ADR-008 test list |
| G-14 | An allocation projection is inserted equal to its fact, and afterwards only a replay publication writes it | Costing services; the runtime role's privileges allow updates only of projection tables |
| G-15 | A cost correction's old values equal the effective value before it, and its number is the next one | Correction Action under the product guard. Backed by `UNIQUE (evidence_id, correction_no)` |
| G-16 | Every frozen display name equals the user's display name at the time of the transaction | Actions copy it inside the transaction from the actor row they have locked |
| G-17 | A receipt or session report snapshot agrees with the facts it freezes (number, lines, totals, revision, logo) | Checkout and close Actions. Backed by the snapshot's composite key to its record's number and the report's arithmetic checks |
| G-18 | A record's business date (`business_date`, `opened_business_date`) is the Asia/Jakarta date of its time, read at step 8 next to the number (SCH-21) | Checkout, correction and session-open Actions. The number itself is held by a check constraint (SCH-22) |
| G-19 | A cash register is changed only while no session is OPEN on it; an Owner-assisted close is by the Owner, an own close by the responsible user | Register and close Actions under the register or session lock |
| G-20 | A stored-file reference names a file of the matching purpose (a logo for a revision, a product photo for a product) | Media and catalogue Actions |
| G-21 | The cost-incomplete mark is written and cleared only as section 5.8 says | Costing Action of the refused correction; successful corrections and reversals of the same source; the RF-6 recovery procedure |

## 9. Invariant-to-enforcement matrix

Columns: **Constraint** — the database guarantee (section 5); **Lock** — the TR §7.1 steps that serialize it; **Guard** — section 8; **Test** — a legacy suite (L-08) or `NEW`, plus the test families of section 9.4, which apply to every row that names a constraint or a privilege.

### 9.1 Logical guarantees that TECHNICAL_RULES relies on

| Guarantee (TECHNICAL_RULES introduction) | Constraint | Lock | Guard | Test |
| --- | --- | --- | --- | --- |
| A barcode token is active on at most one product | Primary key of `barcode_active_mappings` on `token_id` | 6 | G-09 | SEARCH01; RACE-17 (NEW) |
| A token ever mapped is never mapped again except by the Owner's misassignment correction | `barcode_mappings_one_original` (one ordinary attachment per token, ever); a correction requires a manufacturer token, an earlier mapping of the same token retired as a misassignment, and corrects it once | 6 | G-09 | SEARCH01; RACE-19 (NEW) |
| SKUs are unique | `products_sku_ci_uniq` on `lower(sku)` (T-17 g) | — | — | SEARCH01; RACE-22 (NEW) |
| At most one cash session OPEN per register and per user | `cash_sessions_one_open_per_register`, `cash_sessions_one_open_per_user` | 3 | — | SESSION02; RACE-07 |
| At most one SUPER_ADMIN account ACTIVE | `users_one_active_owner`; no runtime `UPDATE` of `role_super_admin` | 2.2, 2.3 | G-08 | RACE-21, RACE-30 (NEW) |
| The pairs (actor, operation, key) and (operation, intent) are unique | `command_identities_user_key`, `command_identities_process_key`; `UNIQUE (operation, source_intent_id)` | 1 | — | POS01; RACE-01, RACE-02 |

### 9.2 Domain rules

Every rule of DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY, COSTING and SALES_CASH appears below, either with its enforcement or in section 9.2.6 as a rule that states no persistent invariant.

#### 9.2.1 DOMAIN_MODEL

| Rule | Constraint | Lock | Guard | Test |
| --- | --- | --- | --- | --- |
| BR-DOM-01 | No tenant, outlet, location or reservation table or column (SCH-05) | — | — | SEARCH02, INV01; NEW (schema inventory) |
| BR-DOM-02 | Classes F and FS insert-only for the runtime role (section 10) | — | — | DB01 |
| BR-DOM-03 | Projections declared and derived (SCH-01); snapshots are not totals | 7 | G-03 | INV01 |
| BR-DOM-04 | One transaction per command; deferred and presence keys refuse an incomplete commit | 1–8 | G-01 | POS01 (failure injection) |
| BR-DOM-05 | This matrix; section 9.3 | TR §7 | All | TR §8 |
| BR-DOM-06 | The applied price is a revision of the product (`sale_lines` key); applied payment equals the Sale total (`payments` key); fact times and numbers set by the server | 6 | G-01, G-18 | POS02, POS03 |
| BR-DOM-08 | Status and amount pattern (section 3.2) in every cost table | — | — | FIN02 |
| BR-DOM-09 | No table holds a draft; a receiving document exists only once confirmed | — | — | NEW (schema inventory) |
| BR-DOM-11 | Shop identity only in `business_profile_revisions`; no client identity in the schema | 2.4 | — | IDENTITY01 |
| BR-NUM-01 | Money is `bigint`; `line_total = round(quantity * unit_price)` | — | G-01 | FIN01, POS02 |
| BR-NUM-02 | `numeric(13,3)`; precision checks; the movement's key to the product's precision | 6 | G-11 | FIN01 |
| BR-NUM-03 | `numeric(18,6)` and `numeric(21,6)`; rounding of ADR-008 section 5 | 7 | G-13 | FIN01, FIN03 |
| BR-NUM-04 | Bounds of section 3.2 on every numeric column | — | Validation first (TR-VAL-06) | FIN01; NEW (boundaries) |
| BR-NUM-05 | `timestamptz` facts; `business_date` columns | — | Server clock | SESSION09 |
| BR-CMD-01 | `command_identities`, `command_results`; `command_id` on every fact | 1 | G-12 | POS01, POS03, INV01, SESSION06 |
| BR-CMD-02 | The two unique identity pairs | 1 | Fingerprint comparison after rollback (TR-TX-08) | POS01, POS03 |
| BR-CMD-03 | `FENCE` rows | 1 | Resolution (ADR-007 section 4) | POS03; RACE-27 (NEW) |
| BR-CMD-06 | `recovery_epoch`; session rows removed; sequences advanced after restore (SCH-28) | 2.3 | G-12 | REC02 |
| BR-USR-01, BR-USR-02 | Role columns and their checks | — | — | AUTH01, AUTH03 |
| BR-USR-03 | No seeded account; the bootstrap inserts the first Owner; `password` null until the first link; no verification column | 2.3 | — | AUTH01; NEW |
| BR-USR-04 | `users_one_active_owner`; no runtime `UPDATE` of `role_super_admin` | 2.1, 2.2 | G-08 | RACE-21, RACE-30 (NEW) |
| BR-USR-05 | `status`; session and link rows deleted in the deactivating transaction | 2.1, 2.2 | In-transaction actor check (SEC-AZ-04) | AUTH03; RACE-04 |
| BR-USR-06 | `sessions` (class E): the two times in the payload | — | Middleware (SEC-SESS-02) | NEW |
| BR-USR-08, BR-USR-09 | `actor_user_id` on every record; users never deleted (`ON DELETE RESTRICT`) | — | G-16 | AUDIT01 |
| BR-USR-10 | No delete privilege; `users_one_active_owner` refuses reactivating a former Owner | 2.2 | Reactivation guard | NEW (RACE-16, RACE-30) |
| BR-USR-11 | `ANONYMIZED` implies a null password; the email placeholder stays unique | 2.2 | Only after deactivation | RACE-16 (NEW) |
| BR-USR-12 | Frozen display names (section 3.5) | — | G-16 | RACE-16 (NEW) |
| BR-AUD-01, BR-AUD-03 | `audit_events` written in the command's transaction; insert-only | 8 | — | AUDIT01, DB01 |
| BR-AUD-02 | `owner_only_data` | — | DTO allowlists | AUDIT01, AUTH02 |
| BR-AUD-04 | `category`, filter columns, indexes (section 11) | — | Scope in Queries | AUDIT01 |
| BR-AUD-05 | No column for a secret; `security_log_entries` separate | — | — | SEC01 |
| BR-AUD-06 | Print, reprint and export requests are audit events only | 8 | — | SESSION08 |
| BR-CFG-01, BR-CFG-05 | `business_profile_revisions`; one pointer row | — | — | IDENTITY01 |
| BR-CFG-02 | Revisions insert-only; `business_profile_current` | 2.4 | — | IDENTITY01 |
| BR-CFG-03 | Snapshots reference the immutable revision and logo file; files never overwritten | 2.4 | G-17 | IDENTITY01; RACE-25 |
| BR-CFG-04 | `stored_files.media_type` | — | Upload validation (SECURITY_MODEL section 13) | IDENTITY01 |
| BR-KEEP-01 to BR-KEEP-08 | Data classes (section 4); no runtime `DELETE` or `TRUNCATE` on retained classes; SCH-11 | — | — | DB01; NEW (no deletion path) |

#### 9.2.2 CATALOG_BARCODE

| Rule | Constraint | Lock | Guard | Test |
| --- | --- | --- | --- | --- |
| BR-CAT-01, BR-CAT-06, BR-CAT-10 | `products` columns; one unit per product and no conversion table; `brand` free text | — | — | SEARCH02 |
| BR-CAT-03 | No balance row until the first movement | — | — | INV01 |
| BR-CAT-04, BR-CAT-05 | `products_sku_ci_uniq`; SKU format check; `barcode_tokens.token` unique | 6 | — | SEARCH01; RACE-22 (NEW) |
| BR-CAT-07 | Movement key to `(id, base_unit, quantity_precision)` with `ON UPDATE RESTRICT` | 6 (`FOR UPDATE`) | — | SEARCH02; RACE-05 |
| BR-CAT-08 | `active`; no delete privilege | 6 | Active check | POS03; RACE-23 |
| BR-CAT-09 | `categories.active` | 6 | Active check | RACE-29 (NEW) |
| BR-CAT-11 | `minimum_stock` bounds and precision | 6 | — | INV01 |
| BR-CAT-12, BR-CAT-13 | `selling_price_revisions.amount`; insert-only; current pointer of the same product | 6 | — | POS02 |
| BR-CAT-14 | Expected price revision in the envelope | 6 | Revision as reviewed | POS02; RACE-23 |
| BR-CAT-15 | No discount, price-book or override column or table | — | — | POS02 |
| BR-CAT-16 | `barcode_active_mappings` primary key | 6 | G-09 | SEARCH01; RACE-17 (NEW) |
| BR-CAT-17, BR-CAT-21 | Token format check (printable ASCII, 1–128) | — | Validation, check digit (G-09) | SEARCH01 |
| BR-CAT-18 | `BK-P-` format and `BK-` namespace check | — | Generation retried on a unique clash | SEARCH01 |
| BR-CAT-19 | `barcode_mappings_one_original`; corrections only for manufacturer tokens | 6 | G-09 | SEARCH01; RACE-19 (NEW) |
| BR-CAT-22, BR-CAT-52 | `barcode_mapping_retirements.reason_class` mandatory; insert-only | 6 | — | NEW |
| BR-CAT-24, BR-CAT-25, BR-CAT-27 | Search and paging indexes (section 11); `active` read with results | — | — | SEARCH02, PERF01 |
| BR-CAT-37, BR-CAT-38 | `csv_product_imports.products_created = data_row_count`; one transaction | 1, 6 | No movement or cost written | RACE-22 (NEW) |
| BR-CAT-39, BR-CAT-40 | `data_row_count` 1–5,000; `file_sha256`; no file stored | — | — | NEW |
| BR-CAT-44, BR-CAT-45, BR-CAT-48 | One nullable `photo_file_id`; thumbnails as derived `stored_files` | 6 | G-20 | NEW |
| BR-CAT-46, BR-CAT-47 | `stored_files` insert-only; `media_type` | 6 | G-20 | NEW |
| BR-CAT-50, BR-CAT-51 | Correction mapping with its composite keys; retirement as misassignment | 6 (both products) | G-09 | RACE-17, RACE-18, RACE-19 (NEW) |

#### 9.2.3 INVENTORY

| Rule | Constraint | Lock | Guard | Test |
| --- | --- | --- | --- | --- |
| BR-INV-01 | No location, transfer or reservation table | — | — | INV01 |
| BR-INV-02, BR-INV-05 | `stock_movements` insert-only; `UNIQUE (product_id, product_sequence)`; `document_date` separate | 6, 7 | — | INV01 |
| BR-INV-03 | `stock_balances` projection | 7 | G-03 | INV01 |
| BR-INV-04, BR-INV-20, BR-INV-33 | `on_hand BETWEEN 0 AND …` | 6, 7 | G-02 | INV02; RACE-03 |
| BR-INV-06, BR-INV-07 | — (lines of one product are added together by the stock check; whether they are stored as one line is question 35) | 2.1, 2.3, 6, 7 | G-02, G-10, G-11 | INV01, INV02 |
| BR-INV-08 | Presence keys on `stock_movements`; deferred keys from lines to movements; movement keys to each line's product and quantity | 8 | G-13 | INV01 (failure injection) |
| BR-INV-09 | Command identity; one movement per source | 1 | — | INV01 |
| BR-INV-11, BR-INV-24 | `onboarding_state`; OPENING only at sequence 1 | 2.3 | G-10 | OPEN01; RACE-04 |
| BR-INV-12, BR-INV-16 | `receiving_documents`, `receiving_lines`, evidence; separate types and tables | 6, 7 | — | INV01 |
| BR-INV-13 | Status and amount pattern | — | — | FIN02 |
| BR-INV-14, BR-INV-15 | Evidence insert-only; corrections only by the Owner's command; the purchase cost shown is the evidence and its latest correction | 6 | Capability check (CAP-12, CAP-19) | AUTH02 |
| BR-INV-17 | No receiving draft table (T-17 a) | — | — | — |
| BR-INV-18, BR-INV-19 | `manual_issues.reason_code` | 6, 7 | G-02 | INV01 |
| BR-INV-21, BR-INV-32 | Issue allocations (ADR-008) and evidence for positive adjustments | 7 | G-13 | FIN01, INV03 |
| BR-INV-22 | Separate tables: a manual issue creates no Sale or Payment | — | — | NEW |
| BR-INV-25, BR-INV-27 | `row_count` 1–100; `count_reference` mandatory for opening lines; zero counts without movement | 2.3, 6, 7 | — | OPEN01 |
| BR-INV-26 | `opening_stock_lines.product_id` unique; OPENING at sequence 1 | 6, 7 | — | OPEN01; RACE-06 |
| BR-INV-29, BR-INV-46 | No runtime update or delete of movements or balances outside commands; repair only from facts | 6 | — | DB01; NEW |
| BR-INV-31 | `adjustments` (reason, count reference and reference, all mandatory) | 6, 7 | — | INV03 |
| BR-INV-34, BR-INV-36 | `reversals` key to the original's type and quantity; `inverse_quantity = -original_quantity` | 6, 7 | — | INV03 |
| BR-INV-35 | `reversals.original_movement_id` unique | 1, 6 | — | INV03; RACE-10 |
| BR-INV-37 | — | 6, 7 | G-07 | INV03 |
| BR-INV-38, BR-INV-39 | Return allocation link and running totals; RETURN_RECEIPT only for saleable lines | 5, 6, 7 | G-06 | RET01 |
| BR-INV-40 | Stock attention is not stored; derived from balance and minimum stock (ARC-22) | — | — | INV01 |
| BR-INV-45 | `inventory_posting_blocks` | 6 | G-03, G-11 | INV01; RACE-24 |

#### 9.2.4 COSTING

| Rule | Constraint | Lock | Guard | Test |
| --- | --- | --- | --- | --- |
| BR-COST-01, BR-COST-09 to BR-COST-12 | Allocation facts and projections, valuations (ADR-008 sections 4 and 5) | 6, 7 | G-13 | FIN01 |
| BR-COST-02 to BR-COST-04 | `acquisition_cost_evidence` checks (status, amount, zero note) | — | — | FIN02 |
| BR-COST-05 | `actor_user_id` on evidence and corrections | — | Capability check | FIN02 |
| BR-COST-06 to BR-COST-08 | `owner_only_data`; no cost column in snapshots, labels or search tables | — | DTO allowlists | AUTH02 |
| BR-COST-13 to BR-COST-15 | UNKNOWN status in facts, projections and valuations (ADR-008 section 4.1) | 7 | G-13 | FIN02 |
| BR-COST-16 | `issue_cost_allocations` (fact) and its projection (D-060) | 7 | G-13, G-14 | POS01, FIN01 |
| BR-COST-17, BR-COST-19 | Return link to the same SaleLine's issue; running totals; saleable only (D-061) | 5, 6, 7 | G-06 | RET01, FIN03 |
| BR-COST-18 | Projections recomputed from the link | 6, 7 | G-07 | FIN03 |
| BR-COST-20 to BR-COST-22 | `cost_corrections` insert-only; `new_amount` required | 6 | G-15 | FIN02, INV03 |
| BR-COST-23 to BR-COST-26, BR-COST-28 | Replay writes only projections and valuations | 6, 7 | G-07 | FIN03, PERF01; RACE-11 |
| BR-COST-27 | `cost_incomplete_marks` and clearances (T-17 i) | 2.1, 6 | G-11, G-21 | NEW |
| BR-COST-29 | Projections replaced, not versioned | 7 | G-14 | FIN03 |
| BR-COST-30, BR-COST-34, BR-COST-35, BR-COST-37 | Read rules over projections, Sales and corrections (ADR-008 section 6); indexes (section 11) | — | — | FIN03 |
| BR-COST-31 | Receipt and session-report tables hold no cost | — | — | SESSION07 |
| BR-COST-32, BR-COST-33 | Logical ledger; `CANCELLED` only for manual issues | 6, 7 | G-07 | INV03 |
| BR-COST-36 | `sale_corrections.business_date` | — | Server clock | FIN03 |
| BR-COST-38 | Report snapshot (TR-TX-07); UNKNOWN rows counted with every cost total; the number of products with an active mark | — | — | FIN03 |
| BR-COST-39 to BR-COST-41 | Non-sale losses, stock attention and excluded scope are separate facts or absent tables | — | — | FIN03 |

#### 9.2.5 SALES_CASH

| Rule | Constraint | Lock | Guard | Test |
| --- | --- | --- | --- | --- |
| BR-SALE-01 | No order, deposit or reservation table; a Sale has no status | — | — | POS01 |
| BR-SALE-02, BR-CASH-08 | `sales (cash_session_id, actor_user_id)` → `cash_sessions (id, responsible_user_id)` | 4 | G-04 | POS01, SESSION03, SESSION10 |
| BR-SALE-04 | — | 2.1, 2.3, 2.4, 4, 6, 7 | G-01, G-02, G-04, G-10, G-11 | POS03 |
| BR-SALE-05 | `sale_lines` key to the price revision | 6 | Revision as reviewed | POS02; RACE-23 |
| BR-SALE-06 | `line_total` check | — | G-01 | FIN01 |
| BR-SALE-07 | `sales.business_date` | — | Server clock | SESSION09 |
| BR-SALE-08 | `customer_name` nullable | — | — | POS01 |
| BR-SALE-09 | Deferred key from each line to its SALE_ISSUE | 6, 7 | G-02 | INV02 |
| BR-SALE-16, BR-SALE-18 | Fingerprint; `UNIQUE (operation, source_intent_id)`; `sales.command_id` unique | 1 | — | POS01, POS03; RACE-01, RACE-02 |
| BR-SALE-17 | One transaction; deferred keys to Payment, receipt and correction guard row; presence keys | 4–8 | G-01 | POS01 (failure injection) |
| BR-SALE-19 | A definite rejection rolls back the identity | — | — | POS03 |
| BR-SALE-20 | Results retained; fences | 1 | — | POS03; RACE-27 (NEW) |
| BR-SALE-23 | `recovery_epoch` | 2.3 | G-12 | REC02; RACE-12 |
| BR-PAY-01 to BR-PAY-03 | `payments.sale_id` unique; deferred key from Sale; method and CASH checks; applied amount = Sale total | — | — | POS01 |
| BR-PAY-04, BR-PAY-05, BR-PAY-07 | Method allowlist; no provider, pending-status or fee table or column | — | — | PAYEXT01 (adapted) |
| BR-PAY-06 | Expected cash counts CASH only | 4 | G-05 | SESSION04 |
| BR-DOC-01 | `receipt_snapshots` unique per Sale; `sale_number` (section 6) | 2.4, 8 | G-17, G-18 | IDENTITY01, SESSION08 |
| BR-DOC-02, BR-DOC-05 | `template_version`; logo file kept | — | — | IDENTITY01 |
| BR-DOC-03 | No tax column | — | — | — |
| BR-DOC-04 | Reprint writes only an audit event | 8 | — | SESSION08 |
| BR-CASH-01, BR-CASH-05, BR-CASH-06 | Partial unique indexes on OPEN sessions | 1, 2.1, 3 | — | SESSION02; RACE-07 |
| BR-CASH-02 | — | 3 | G-19 | RACE-26 (NEW) |
| BR-CASH-03 | Any number of registers | — | — | — |
| BR-CASH-04 | `cash_sessions` columns; `opening_cash >= 0`; number (section 6) | 3 | G-18 | SESSION03 |
| BR-CASH-07 | No paid-in, paid-out or cash-pickup table; the only drawer outflow is a drawer-funded refund | 4 | G-05 | SESSION04 |
| BR-CASH-09 | A session spans midnight as one row; a new session per return to work | — | — | SESSION01 |
| BR-CASH-10, BR-RFD-08 | `activity_version` | 4 | — | SESSION05; NEW (REQ-CASH-13) |
| BR-CASH-12 | `variance = physical_cash - expected_cash`; report arithmetic | 4 | G-05 | SESSION04 |
| BR-CASH-13 to BR-CASH-15 | `cash_session_closes`, `cash_session_reports`; close takes `FOR UPDATE` | 4 | G-05, G-17 | SESSION05; RACE-08 |
| BR-CASH-17 | Close keyed by session; `command_id` unique | 1, 4 | — | SESSION06; RACE-09 |
| BR-CASH-18 | `close_kind`, mandatory reason for Owner-assisted close | 4 | G-19 | SESSION10 |
| BR-CASH-19 | Close key to `(id, status)` `ON UPDATE RESTRICT`; `cash_session_review_notes` | 4 | — | SESSION07 |
| BR-CASH-20 | `cash_session_reports`; report number = session number | 2.4, 4 | G-17 | SESSION07 |
| BR-RFD-02, BR-RFD-04, BR-RFD-12 | `sale_corrections.reason` mandatory; refund and return parts; originals insert-only | 5 | — | RET01 |
| BR-RFD-03 | Running totals with check constraints | 5, 6 | G-06 | RET01; RACE-10 |
| BR-RFD-05, BR-RFD-06, BR-RFD-16 | Payout and funding checks, null-safe; mandatory payout reference; exactly one source | — | G-05 | RET01; RACE-14 (NEW) |
| BR-RFD-07, BR-RFD-09 | `refunds.cash_session_id` for drawer-funded refunds | 4 | G-04 | SESSION07; NEW |
| BR-RFD-10 | One transaction; presence keys | 4–8 | — | RET01 (failure injection) |
| BR-RFD-11 | Condition, link and running totals | 5, 6, 7 | G-06 | RET01 |
| BR-RFD-13 | One `refunds` row feeds both reporting and the session | — | — | NEW |
| BR-RFD-15 | — | 4 | G-05 | RACE-14 (NEW) |
| BR-RPT-01 to BR-RPT-04 | Read rules over Sales, allocations, corrections and session reports (ADR-008 section 6); indexes (section 11) | — | — | FIN03, SESSION07 |

#### 9.2.6 Rules that state no persistent invariant

These rules govern authorization, interface behaviour, physical actions, presentation or operations. They are enforced by SECURITY_MODEL, TECHNICAL_RULES, the interface (RF-4, RF-5) or RF-6, not by the schema; the schema only provides the columns they read.

| Rules | Why no schema enforcement |
| --- | --- |
| BR-DOM-07, BR-USR-07, BR-CAT-02, BR-CAT-41, BR-INV-23, BR-SALE-03, BR-DOC-06, BR-CASH-21, BR-RFD-01 | Authorization and recent authentication (SECURITY_MODEL sections 5 and 6) |
| BR-DOM-10, BR-SALE-21, BR-RFD-14, BR-RFD-17 | No human, printer or network wait in a transaction; physical cash and printing after commit (TR-TX-03) |
| BR-NUM-06, BR-NUM-07, BR-CAT-23, BR-CAT-26, BR-INV-41 to BR-INV-44 | Query periods, presentation, shared search logic, result DTOs, refresh and history reading (indexes in section 11) |
| BR-CMD-04, BR-CMD-05, BR-INV-10, BR-SALE-10 to BR-SALE-15, BR-SALE-22, BR-CASH-16 | Review steps, confirmation interface, retries and client recovery (TECHNICAL_RULES sections 3 and 9; ADR-007 section 5) |
| BR-CAT-20, BR-INV-28, BR-INV-30 | Facts to be verified, checks by the Owner, and stop conditions for an Owner decision |
| BR-CAT-28 to BR-CAT-36 | Scanning and labels in the interface (RF-4, RF-5); print requests are audited under BR-AUD-06 |
| BR-CAT-42, BR-CAT-43, BR-CAT-49 | Import format, upload rules (SECURITY_MODEL section 13) and backup (question 18, RF-6) |
| BR-CASH-11 | No key links a sign-in session to a cash session; signing out changes no row of `cash_sessions` |
| BR-CASH-22 | CSV export formatting (SECURITY_MODEL SEC-CH-07) |

### 9.3 Race matrix

Each race of TECHNICAL_RULES section 8 and the schema element that serializes it.

| Race | Serialization in the schema | Constraint backing the outcome |
| --- | --- | --- |
| RACE-01, RACE-02 | Insert of the identity (step 1) | Unique identity pairs (section 5.3) |
| RACE-03 | `products` and `stock_balances` locks (steps 6, 7) | `on_hand >= 0` |
| RACE-04 | `users`, `onboarding_state`, `business_profile_current` locks (step 2) | `status`; `onboarding_state.active` read under lock |
| RACE-05 | `products` `FOR UPDATE` | Movement key with `ON UPDATE RESTRICT` |
| RACE-06 | `onboarding_state` share lock; product locks | OPENING at sequence 1; `opening_stock_lines.product_id` unique |
| RACE-07 | `cash_registers` lock (step 3) | Partial unique indexes on OPEN sessions |
| RACE-08 | `cash_sessions` lock (step 4) | `activity_version`; close row keyed by session |
| RACE-09 | Identity; `cash_sessions` `FOR UPDATE` | `cash_session_closes` primary key on `session_id`; `command_id` unique |
| RACE-10 | `sale_correction_totals` lock (step 5); product locks for reversals | Running-total checks; `reversals.original_movement_id` unique |
| RACE-11 | `products` lock held for the whole replay (step 6); projections at step 7 | Projections written in one transaction (ADR-008 section 7) |
| RACE-12 | `recovery_epoch` | Epoch comparison (G-12) |
| RACE-13, RACE-14 | `cash_sessions` lock | `activity_version`; close `expected_cash >= 0` (G-05) |
| RACE-15 | Identity and fence | `UNIQUE (operation, source_intent_id)` |
| RACE-16 | `users` lock of the target (step 2.2) | Status check; frozen names (section 3.5) |
| RACE-17, RACE-19 | Both products' locks (step 6) | `barcode_active_mappings` primary key; `barcode_mappings_one_original`; `UNIQUE (corrects_mapping_id)` |
| RACE-18 | The correction's single transaction | `barcode_active_mappings` changed in that transaction |
| RACE-20 | `users` lock of the target | `password_reset_tokens` row deleted on consumption, revocation and deactivation |
| RACE-21 | `users` lock of the outgoing Owner | `users_one_active_owner` |
| RACE-22 | Identity; category share locks | `products_sku_ci_uniq`; `barcode_tokens.token` unique; `barcode_mappings_one_original` |
| RACE-23 | `products` lock | Price key on `sale_lines`; `active` read under lock |
| RACE-24 | `products` lock | `inventory_posting_blocks` read under lock |
| RACE-25 | `business_profile_current` lock (step 2.4) | Snapshot references one revision |
| RACE-26 | `cash_registers` lock | G-19 |
| RACE-27 | Identity | `FENCE` row on `UNIQUE (operation, source_intent_id)` |
| RACE-28 | `users` lock of the target | Deactivation changes no `cash_sessions` row (T-17 d, RF-5) |
| RACE-29 | `categories` lock (step 6) | `categories.active` read under lock |
| RACE-30 | Insert of the singletons (bootstrap); `users` lock (replacement) | Primary keys of `recovery_epoch` and `onboarding_state`; `users_one_active_owner` |

### 9.4 Test families

RF-6 rebuilds the test strategy; these families are its input and are required for every row above that names a constraint, privilege or guard (SCH-10). All run on real PostgreSQL with committed fixtures and independent connections (STK-20).

| Family | What it proves |
| --- | --- |
| Constraint | For every check, unique, foreign-key and partial unique constraint of section 5, a row that violates it, written directly with the runtime role, is refused, including a row with a null in each nullable column that a check or composite key involves (SCH-13) |
| Deferred | For every deferred and presence key, a transaction that omits the required row fails at commit and leaves nothing |
| Privilege | The actual grants equal section 10: the runtime role is refused DDL, `UPDATE` and `DELETE` on retained classes, `TRUNCATE` everywhere, `UPDATE` of every column not listed, and `setval` on the number sequences; the backup role can only read |
| Race | Every RACE-01 to RACE-30 outcome with at least two connections and controlled interleaving; also the mark's transaction against a later correction or a reversal of the same source, a failing mark transaction (`SERVICE_BUSY`, no mark) and a retried refused intent (one mark) (section 5.8); and a running-total update that skips the Sale lock (the check still refuses the excess, SCH-33) |
| Failure injection | A failure after each write step of a command leaves no fact, projection, audit event or result (TECHNICAL_RULES section 8) |
| Reconciliation | Mismatches induced on a test database in balances, valuations, allocation projections, running totals and active barcode mappings are detected (G-03; BR-INV-45) |
| Numbering | Concurrent checkouts in different sessions do not wait on each other for a number; numbers are unique and follow allocation order; a rolled-back checkout leaves a gap; the format and date part are correct across midnight (section 6) |
| Costing | The ADR-008 test list |
| Retention | No product path deletes or rewrites a retained row; the class E cleanups remove only what their decisions allow |

## 10. Database roles and privileges

The three roles are logical. Their real names and credentials are set when the environments are built (RF-6) and are never written in the repository (SEC-SECRET-01, SEC-SECRET-05).

| ID | Rule | Source |
| --- | --- | --- |
| SCH-40 | **Migration role.** Owns the schema and every table, sequence and index in it, and is the only role that runs DDL. It is used only by the controlled migration job and is never available to the web runtime. | ARC-71, PDS §3, ARC-64 |
| SCH-41 | **Runtime role.** Not a superuser, not the owner of any object, without `CREATEDB`, `CREATEROLE` or `CREATE` on any schema. It has `CONNECT` on its database, `USAGE` on the application schema and exactly the table privileges of the matrix below. It is never granted `TRUNCATE`, `REFERENCES`, `TRIGGER` or `MAINTAIN`. | ARC-70, PDS §3, SEC-LOG-04 |
| SCH-42 | **Backup role.** For a logical dump: `CONNECT`, `USAGE` on the schema and `SELECT` on every table and sequence; nothing else. If RF-6 chooses another backup method (question 18), this role receives the minimum that method needs and the privilege test follows it. Restoring is a runbook step with its own controls (RF-6), never this role. | ARC-71, ADR-002, PDS §3 |
| SCH-43 | **No `PUBLIC` rights.** No role other than the migration role can create objects in the application schema; PostgreSQL grants `PUBLIC` no `CREATE` on the `public` schema in a new database (version 15 and later), and the foundation Task verifies this on the pinned version. PostgreSQL grants `PUBLIC` `CONNECT` and `TEMPORARY` on every new database; both are revoked from `PUBLIC` and granted only to the three roles as they need them, and the privilege test checks it. | PDS §3; PostgreSQL documentation (section 15) |
| SCH-44 | **Sequences.** The runtime role has `USAGE` on `sale_number_seq` and `cash_session_number_seq` and never `UPDATE` (SCH-27). For the sequences behind identity columns it receives only what the pinned PostgreSQL version requires for inserting rows, plus `USAGE` on the sequence of a table that uses the presence-key fallback of section 3.7, verified by the privilege test. | PDS §3 |
| SCH-45 | **Grants are explicit.** Each migration that creates a table grants its runtime privileges explicitly; default privileges are not relied on. The privilege test compares the database's actual grants with the matrix below and fails on any difference. | ARC-72, REQ-OPS-02 |
| SCH-46 | **Operator procedures** run as console commands in the application container with the runtime role, within these privileges (SECURITY_MODEL section 14). Advancing the number sequences after a restore (SCH-28) is a runbook step that uses the migration role. Who may run either on production is question 20 (RF-6). | SECURITY_MODEL section 14, PDS §5 |

Runtime privileges per table (S = `SELECT`, I = `INSERT`, U = `UPDATE` of the listed columns only, D = `DELETE`):

| Table | Class | S | I | U (columns) | D |
| --- | --- | --- | --- | --- | --- |
| `users` | M | ✓ | ✓ | `display_name`, `email`, `password`, `role_operations_admin`, `role_cashier`, `status`, `deactivated_at`, `anonymized_at`, `updated_at` | — |
| `sessions`, `password_reset_tokens`, `cache`, `cache_locks` | E | ✓ | ✓ | all | ✓ |
| `business_profile_revisions` | F | ✓ | ✓ | — | — |
| `business_profile_current` | S | ✓ | ✓ | `revision_id`, `changed_at` | — |
| `recovery_epoch` | S | ✓ | ✓ | `epoch`, `set_at` | — |
| `command_identities`, `command_results` | F | ✓ | ✓ | — | — |
| `audit_events` | F | ✓ | ✓ | — | — |
| `security_log_entries` | L | ✓ | ✓ | — | — |
| `stored_files` | F | ✓ | ✓ | — | — |
| `categories` | M | ✓ | ✓ | `name`, `active`, `version`, `updated_at` | — |
| `products` | M | ✓ | ✓ | `sku`, `name`, `category_id`, `brand`, `base_unit`, `quantity_precision`, `current_price_revision_id`, `minimum_stock`, `active`, `photo_file_id`, `version`, `updated_at` | — |
| `selling_price_revisions` | F | ✓ | ✓ | — | — |
| `barcode_tokens`, `barcode_mappings`, `barcode_mapping_retirements` | F | ✓ | ✓ | — | — |
| `barcode_active_mappings` | P | ✓ | ✓ | — | ✓ |
| `csv_product_imports` | F | ✓ | ✓ | — | — |
| `onboarding_state` | S | ✓ | ✓ | `active`, `changed_at`, `changed_by_user_id` | — |
| `stock_movements` | F | ✓ | ✓ | — | — |
| `stock_balances` | P | ✓ | ✓ | `on_hand`, `last_sequence`, `updated_at` | — |
| `receiving_documents`, `receiving_lines`, `manual_issues`, `manual_issue_lines`, `opening_stock_entries`, `opening_stock_lines`, `onboarding_zero_counts`, `adjustments`, `adjustment_lines`, `reversals` | F | ✓ | ✓ | — | — |
| `inventory_posting_blocks`, `inventory_posting_block_reopenings` | F | ✓ | ✓ | — | — |
| `acquisition_cost_evidence`, `cost_corrections` | F | ✓ | ✓ | — | — |
| `inventory_valuations` | P | ✓ | ✓ | `value_status`, `value`, `publication_no`, `last_sequence`, `published_at` | — |
| `issue_cost_allocations`, `return_cost_allocations` | F | ✓ | ✓ | — | — |
| `issue_cost_allocation_projections`, `return_cost_allocation_projections` | P | ✓ | ✓ | `current_status`, `current_amount`, `publication_no`, `updated_at` | — |
| `cost_incomplete_marks`, `cost_incomplete_mark_clearances` | F | ✓ | ✓ | — | — |
| `sales`, `sale_lines`, `payments`, `receipt_snapshots` | F | ✓ | ✓ | — | — |
| `sale_corrections`, `refunds`, `refund_lines`, `return_lines` | F | ✓ | ✓ | — | — |
| `sale_correction_totals` | P | ✓ | ✓ | `refunded_amount`, `updated_at` | — |
| `sale_line_correction_totals` | P | ✓ | ✓ | `refunded_amount`, `returned_quantity`, `updated_at` | — |
| `cash_registers` | M | ✓ | ✓ | `code`, `label`, `active`, `version`, `updated_at` | — |
| `cash_sessions` | FS | ✓ | ✓ | `status`, `activity_version`, `updated_at` | — |
| `cash_session_closes`, `cash_session_reports`, `cash_session_review_notes` | F | ✓ | ✓ | — | — |
| `migrations` | — | — | — | — | — |

Every table that TECHNICAL_RULES section 7 locks has at least one column in column U, because PostgreSQL requires `UPDATE` privilege to lock a row (section 7). The immutable facts of ARCHITECTURE ARC-70 — stock movements, Sales and their lines, payments, receipt snapshots, refunds, returns, cost evidence and corrections, session report snapshots, audit events and command results — are all class F above; the class also covers every other fact table of section 5.

## 11. Indexes

Primary keys, unique constraints and partial unique indexes of section 5 are indexes already. In addition:

| Purpose | Index | Source |
| --- | --- | --- |
| Exact barcode lookup | `barcode_tokens (token)` unique; then `barcode_active_mappings` by primary key | BR-CAT-24, TR §13.1 |
| SKU exact match, case-insensitive | `products_sku_ci_uniq` on `lower(sku)` | BR-CAT-24 |
| SKU prefix, case-insensitive | `products (lower(sku) text_pattern_ops)`, so that `lower(sku) LIKE 'abc%'` uses an index whatever the database collation | BR-CAT-24 |
| Name, case-insensitive, anywhere in the name | A GIN trigram index on `lower(name)` with the `pg_trgm` module supplied with PostgreSQL (a trusted extension that the migration role creates) | BR-CAT-24, TR-RUN-03, L-12 (a trigram index "bila tersedia dan dapat dibenarkan") |
| Stable result order and keyset paging | `products (name, id)` | BR-CAT-25, TR-PAGE-01, TR-PAGE-02 |
| Filters | `products (category_id)`, `products (lower(brand))`, `products (active)` | BR-CAT-24 |
| Stock history per product | `stock_movements (product_id, product_sequence)` (the unique key) | TR-PAGE-01 |
| Replay | The same key; `acquisition_cost_evidence (movement_id)`, allocation facts `(movement_id)` (unique keys); `return_cost_allocations (issue_allocation_id)`; `cost_corrections (evidence_id, correction_no)` | ADR-008 |
| Audit log | `audit_events (occurred_at, id)`; `(actor_user_id, occurred_at, id)`; `(product_id, occurred_at, id)`; `(cash_session_id, occurred_at, id)`; `(reference)` | BR-AUD-04, TR-PAGE-02 |
| Sales and receipts | `sales (committed_at, id)`; `(cash_session_id)`; `(actor_user_id, committed_at, id)`; `(business_date)`; `sale_lines (sale_id)` | TR-PAGE-02, BR-RPT-01 |
| Cash sessions | `cash_sessions (opened_at, id)`; `(responsible_user_id, opened_at, id)` | TR-PAGE-02, BR-RPT-02 |
| Expected cash | `refunds (cash_session_id) WHERE funding_source = 'DRAWER'`; `payments (sale_id)` (unique) | G-05 |
| Corrections and reports | `sale_corrections (sale_id)`; `(business_date)` | BR-COST-36 |
| Marks | `cost_incomplete_marks (product_id)`; `(evidence_id)` | Section 5.8 |
| Every foreign-key column not covered above | One B-tree index each | PostgreSQL does not index referencing columns by itself |

- The trigram index is measured at PERF01 against the provisional dataset (TECHNICAL_RULES section 13.2). The foundation Task verifies that the pinned PostgreSQL image provides `pg_trgm`. If it does not, the Task stops and reports: a fallback to prefix-only name matching would change how search behaves and is not chosen here.
- Indexes are an ordinary technical choice (PROJECT_RULES section 3): a Task may add an index with a measured reason by updating this table; removing one listed here needs the same.

## 12. Entity coverage

Every entity of DOMAIN_MODEL section 4 and the tables that represent it.

| No. | Entity | Tables |
| --- | --- | --- |
| 1 | User | `users` |
| 2 | Sign-in session | `sessions` |
| 3 | BusinessProfileRevision | `business_profile_revisions`, `business_profile_current` |
| 4 | Category | `categories` |
| 5 | Product | `products` |
| 6 | Barcode | `barcode_tokens`, `barcode_mappings`, `barcode_mapping_retirements`, `barcode_active_mappings` |
| 7 | SellingPriceRevision | `selling_price_revisions` |
| 8 | Product photo | `stored_files`; `products.photo_file_id` |
| 9 | CSV product import | `csv_product_imports` |
| 10 | StockMovement | `stock_movements` |
| 11 | StockBalance | `stock_balances` |
| 12 | Receiving document | `receiving_documents`, `receiving_lines` |
| 13 | Manual issue | `manual_issues`, `manual_issue_lines` |
| 14 | Opening stock entry and onboarding state | `opening_stock_entries`, `opening_stock_lines`, `onboarding_zero_counts`, `onboarding_state` |
| 15 | Adjustment | `adjustments`, `adjustment_lines` |
| 16 | Reversal | `reversals` |
| 17 | AcquisitionCostEvidence | `acquisition_cost_evidence` |
| 18 | Cost correction | `cost_corrections` |
| 19 | Inventory valuation | `inventory_valuations` |
| 20 | Issue-cost allocation | `issue_cost_allocations`, `issue_cost_allocation_projections`; for saleable returns `return_cost_allocations`, `return_cost_allocation_projections` |
| 21 | Sale | `sales` |
| 22 | SaleLine | `sale_lines` |
| 23 | Payment | `payments` |
| 24 | ReceiptSnapshot | `receipt_snapshots` |
| 25 | Refund | `sale_corrections`, `refunds`, `refund_lines`, `sale_correction_totals`, `sale_line_correction_totals` |
| 26 | Return | `sale_corrections`, `return_lines`, `sale_line_correction_totals` |
| 27 | CashRegister | `cash_registers` |
| 28 | CashSession | `cash_sessions`, `cash_session_closes` |
| 29 | CashSessionReportSnapshot | `cash_session_reports` |
| 30 | Owner review note | `cash_session_review_notes` |
| 31 | Command result | `command_identities`, `command_results` |
| 32 | AuditEvent | `audit_events` |

Technical tables that represent no domain entity: `password_reset_tokens` (one-time links), `security_log_entries`, `recovery_epoch`, `inventory_posting_blocks` and `inventory_posting_block_reopenings`, `cost_incomplete_marks` and `cost_incomplete_mark_clearances`, `cache`, `cache_locks`, `migrations`. Every table of section 5 is named in this section: 61 tables in all.

## 13. Boundary with implementation

| ID | Rule | Source |
| --- | --- | --- |
| SCH-50 | Migrations are written by the implementation Tasks, forward and additive, from this document. They run once, as a controlled job, never at application start-up, and are rehearsed on a populated isolated target before any release (PRODUCTION_DATA_SAFETY section 2). No migration was written in RF-3B, and no statement of this document was executed. | PDS §2, ARC-64, D-059 |
| SCH-51 | Where the framework's schema builder cannot express a constraint — partial and expression unique indexes, check constraints, composite and deferred foreign keys, `NULLS` behaviour, grants and revokes, sequences with a fixed cache — the migration uses explicit SQL statements. The SQL a migration produces is reviewed and its effect is proven on real PostgreSQL. | Laravel documentation (section 15), L-02 and L-P4 (schema delivery) |
| SCH-52 | The foundation Task pins the PostgreSQL version and verifies on it every feature this document relies on: partial and expression unique indexes; deferrable foreign keys; stored generated presence keys that refer to the identity column and carry a deferred foreign key (section 3.7); `MATCH FULL`; that every function in the number checks is marked immutable (PostgreSQL does not refuse a check that uses another kind, so the Task inspects each function's volatility); column privileges; `FOR NO KEY UPDATE` and `FOR SHARE`; sequence `CACHE 1` and `NO CYCLE`; `pg_trgm`; the `public` schema's default privileges; the sequence privileges for identity columns. A difference from this document stops the Task. | STK-01, STK-04 |
| SCH-53 | Test databases follow the same role separation, so that the privilege and constraint tests run as the runtime role (section 9.4). Concurrency, constraint and rollback tests never run inside one wrapping transaction or on SQLite (STK-20). | STK-20, REQ-OPS-02 |
| SCH-54 | A change to this schema after implementation starts follows change control (PROJECT_RULES section 6): this document first, then a forward migration; never a destructive change without the Owner's approval of that exact operation (PDS §1). | PDS §1, §2 |

## 14. Legacy disposition

| Legacy statement | Where it is carried, or why not |
| --- | --- |
| `02-ARCHITECTURE` §Constraint database: uniqueness of SKU, barcode and source intent | `products_sku_ci_uniq` (with the T-17 g normalization); `barcode_tokens.token` unique and `barcode_active_mappings` for active mappings (D-051, D-052); `UNIQUE (operation, source_intent_id)` and `sales.command_id` |
| `02-ARCHITECTURE` entity table (domain part, superseded in RF-2): CashRegister "code/label unik" | `cash_registers.code` unique without regard to letter case; the label is not unique (section 5.10) |
| `02-ARCHITECTURE` §Constraint database: one Payment and one ReceiptSnapshot per Sale | `payments.sale_id`, `receipt_snapshots.sale_id` unique, with the Sale's deferred keys requiring them |
| same: uniqueness of the movement source | One unique key per source column of `stock_movements` |
| same: a CashSession required for every Sale | `sales.cash_session_id` not null, keyed to the actor's session |
| same: one report per CLOSED session | `cash_session_reports` keyed by session; the close's deferred key requires it |
| same: partial uniqueness of OPEN sessions per register and per cashier | The two partial unique indexes |
| same: balance, opening cash and physical cash non-negative; valid quantities and amounts | Section 3.2 bounds |
| same: aggregate checks under lock bound cumulative refunds and returns | Running totals with check constraints under the original Sale lock (section 5.9) — now enforced by the database as well |
| same: constraints and guards together keep the contract under races | SCH-03; section 9 |
| `02-ARCHITECTURE` §Payment extension boundary dan schema delivery — schema delivery: raw SQL constraints, partial uniqueness, immutable-history privileges, real PostgreSQL evidence | SCH-51, sections 5 and 10, SCH-10, section 9.4 |
| `P4-ARCHITECTURE` §Schema: unique normalized SKU | `products_sku_ci_uniq`; the normalization is now concrete (T-17 g) |
| same: global barcode token that is never reused | **Changed by D-051 and D-052**: a token is unique and active on at most one product; a manufacturer token retired as a misassignment may be re-attached by the Owner's correction; `BK-P-…` codes are never reused |
| same: Sale source intent, scoped command key, fingerprint and result | Section 5.3 |
| same: movement source intent; Sale number | Movement source keys; the Sale number now has its generation rule (D-062, section 6) |
| same: one full Payment and one ReceiptSnapshot per completed Sale | As above, with the methods of D-036 |
| same: partial unique OPEN per register and per responsible cashier; every Sale in an eligible session of the actor | Partial unique indexes; the Sale's composite key to its actor's session |
| same: foreign keys and checks for sources, quantities, amounts, non-negative cash, unit semantics after the first movement, legal status and type | Section 5; the unit and precision lock is now a database guarantee |
| same: exactly one immutable report per CLOSED session, consistent sources and cutoff | `cash_session_closes`, `cash_session_reports` |
| same: cumulative refund and return bounds under the Sale lock | Section 5.9 |
| same: facts append-only; cost revisions as new facts with explicit publication | Class F; ADR-008 (corrections append-only, projections published atomically) |
| same: runtime privileges separate immutable facts from projections; no DDL; separate migration and backup credentials | Sections 4 and 10 |
| same: no single check constraint claims to hold an aggregate | Section 8 |
| same: additive forward migrations; raw SQL where the builder falls short; real PostgreSQL tests including rollback, failure injection and privilege denial; no reset or `migrate:fresh` on business data; populated rehearsal on an isolated target | SCH-50 to SCH-54; PDS |

Nothing in these legacy sections is dropped without a written reason; the only rule that changes is the barcode token rule, changed by D-051 and D-052 before RF-3B.

## 15. Verified sources

Checked on 5 and 6 October 2026. The documentation pages served as "current" were those of PostgreSQL 18 and Laravel 13.x; these are observed facts, not version pins (STK-01).

| Source | Fact used |
| --- | --- |
| https://www.postgresql.org/docs/current/ddl-constraints.html | A check constraint must depend only on the row being checked and be immutable, and is satisfied when it evaluates to null; unique constraints treat nulls as distinct unless `NULLS NOT DISTINCT`; a foreign key may reference a primary key, a unique constraint or a non-partial unique index, including a composite one; `RESTRICT` cannot be deferred, `NO ACTION` can |
| https://www.postgresql.org/docs/current/sql-createtable.html | `UNIQUE`, `PRIMARY KEY`, `EXCLUDE` and `REFERENCES` constraints may be `DEFERRABLE INITIALLY DEFERRED`; `CHECK` and `NOT NULL` cannot; identity columns; `MATCH SIMPLE` (the default) skips a composite key with any null column, `MATCH FULL` refuses a partly null key; a generated column may refer to other columns but not to other generated columns, with immutable functions only |
| https://www.postgresql.org/docs/current/ddl-generated-columns.html | Restrictions on generated columns: no default or identity on the generated column itself, no reference to another generated column, immutable functions only; `STORED` columns are computed on write; nothing is stated against referring to an identity column or against a plain foreign key, which the foundation Task proves (SCH-52) |
| https://www.postgresql.org/docs/current/indexes-partial.html | A partial unique index enforces uniqueness among the rows that satisfy its predicate |
| https://www.postgresql.org/docs/current/sql-createindex.html | Expression indexes (for example `lower(column)`) can be unique; index and predicate expressions must be immutable |
| https://www.postgresql.org/docs/current/explicit-locking.html | The four row-lock modes and their conflicts; `FOR NO KEY UPDATE` does not block `FOR KEY SHARE`; an `UPDATE` that changes a column with a unique index usable by a foreign key takes `FOR UPDATE`, other updates `FOR NO KEY UPDATE`; take locks in a consistent order and the most restrictive needed mode first |
| https://www.postgresql.org/docs/current/sql-select.html | The locking clauses require `UPDATE` privilege on at least one column of each table locked; `FOR … OF` limits them to the named tables; they cannot be used with aggregation |
| https://www.postgresql.org/docs/current/transaction-iso.html | Read Committed re-evaluates an updated row after waiting; a read-only Repeatable Read transaction sees one snapshot and never fails with a serialization error |
| https://www.postgresql.org/docs/current/sql-createsequence.html | Sequences default to `bigint`, `CACHE 1` and `NO CYCLE`; with cache one, `nextval` values are generated sequentially across sessions, with a larger cache only distinct; `nextval` is never rolled back, so sequences cannot be gapless |
| https://www.postgresql.org/docs/current/functions-sequence.html | Aborted transactions and crashes leave gaps; sequence values are not assigned in commit order |
| https://www.postgresql.org/docs/current/datatype-numeric.html | `numeric` is exact and rounds ties away from zero; excess scale is rounded and excess precision is an error; infinity cannot be stored in a constrained `numeric` column; `NaN` sorts above every number; `bigint` range |
| https://www.postgresql.org/docs/current/ddl-priv.html | Owners hold all privileges and alone alter or drop objects; `PUBLIC` receives `CONNECT` and `TEMPORARY` on databases by default; column privileges for `SELECT`, `INSERT`, `UPDATE`, `REFERENCES`; `USAGE` on a sequence allows `nextval` and `currval`, `UPDATE` allows `nextval` and `setval`; the `TRUNCATE`, `REFERENCES`, `TRIGGER` and `MAINTAIN` privileges |
| https://www.postgresql.org/docs/current/ddl-schemas.html | In a new database `PUBLIC` has `USAGE` but not `CREATE` on the `public` schema (version 15 and later) |
| https://www.postgresql.org/docs/current/pgtrgm.html | `pg_trgm` is a trusted extension; GIN and GiST trigram indexes support `LIKE` and `ILIKE` with a leading wildcard |
| https://laravel.com/docs/migrations | The schema builder offers primary, unique, ordinary, full-text, spatial and vector indexes, and decimal, big integer, UUID, `timestampTz` and `jsonb` columns; a migration may name its connection; no builder method was found for partial or expression unique indexes, check constraints or deferred keys |
| https://laravel.com/docs/database | `DB::statement` runs a general SQL statement; `DB::transaction` accepts a number of attempts for deadlocks; several connections can be configured |
| https://laravel.com/docs/queries and https://api.laravel.com/docs/13.x/Illuminate/Database/Query/Builder.html | `sharedLock()` and `lockForUpdate()`; `lock(string\|bool $value = true)` accepts a lock clause as a string |

## 16. Open items and boundaries

| Item | Subject | Step |
| --- | --- | --- |
| T-17 a | A server-held unconfirmed receiving document would be its own draft table (section 5.7) | RF-5 |
| T-17 b | Correction of a checklist entry or a wrong positive OPENING before release; the tables stay insert-only until then | RF-5 |
| T-17 d | Deactivating a user who holds an OPEN session (RACE-28) | RF-5 |
| T-17 f | Editing an Owner review note; the table stays insert-only until then | RF-5 |
| T-17 h | A CSV row that names a category that does not exist yet; category names carry no uniqueness constraint | RF-5 |
| Restore runbook | Run the after-restore procedure before the application accepts traffic (section 5.3); advance both number sequences (SCH-28); treat the promotion of any lagging database copy as a restore; run production with synchronous commit | RF-6 |
| Collation | The reindex step after an upgrade of the collation library (SCH-14) | RF-6 |
| Reconciliation incidents | How a difference in a running total or an active barcode mapping is handled (G-03; BR-INV-46); the reconciliation's schedule | RF-6 |
| Cost recovery procedure | The reviewed recovery procedure that may clear a cost-incomplete mark (section 5.8) | RF-6 |
| Question 18 | The backup destination and method; the backup role receives the minimum that method needs (SCH-42) | RF-6 |
| Question 20 | Who runs operator procedures and runbook steps on production (SCH-46) | RF-6 |
| SEC-LOG-05 | Retention of `security_log_entries` | RF-6 |
| Reconciliation and backup status | Where the daily reconciliation run and backup status for the Owner's system status are stored (REQ-OPS-04) | RF-6 |
| Question 35 | Whether the same product on two lines of one checkout is stored as two lines or combined; the schema allows either answer (section 5.9) | RF-5 |
| RF-4 | The accent colour set (`accent_token` allowlist); how numbers and names appear on screens and documents | RF-4 |
| RF-5 | Error codes for setup-dependent refusals; what the mandatory payout reference of a drawer cash refund holds — typed by the Owner or generated (BR-RFD-05); the length of the document identity and register fields if they differ from the column bounds here | RF-5 |
| RF-7 | The Tasks that implement this schema, ordered by module dependency | RF-7 |

Nothing in this document authorizes implementation, a migration, executed SQL or any later step.
