# Restore checklists

| | |
| --- | --- |
| Status | CURRENT — written by TASK-037 (scope item 7), 15 November 2026 (Asia/Jakarta) |
| Phase | P9 — the operator's checklists of [BACKUP_RECOVERY](BACKUP_RECOVERY.md) RB-01 phases C to F and RB-02 |
| Related | [BACKUP_RECOVERY](BACKUP_RECOVERY.md) sections 8 to 13; [OPERATIONS_RUNBOOK](OPERATIONS_RUNBOOK.md) OPR-02, RB-03, RB-04, RB-10; [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) SEC-OP-02, SEC-OP-04, SEC-OP-05, SEC-OP-08; [ENVIRONMENTS](ENVIRONMENTS.md) sections 2, 9 and 16 |

The commands of RB-01 phases C to F and of RB-02, in their order, for the operator — a person, never an AI agent (D-088). BACKUP_RECOVERY is the authority for every step and its conditions; this document adds no rule. Each step is ticked in the incident record or the drill record with its time. Nothing here names a real account, host, bucket, key or location (SECURITY_MODEL SEC-SECRET-05): every `<…>` is the operator's own value, typed at run time and written nowhere.

The scripts are in `scripts/operations/restore/` of the release's commit. They run on the **operator's own machine** (BKP-07, BKP-08), which needs `bash`, `curl` 7.75 or later, `age`, `sha256sum`, OpenSSH and the PostgreSQL client tools of the server's major version. The procedures `restore:after`, `restore:reconcile`, `owner:recover`, `reconciliation:run` and `restore:record-drill` are console commands of the application, run inside the target's application container with the typed environment and the operator's note (SECURITY_MODEL section 14). `scripts/checks/restore-rehearsal.sh` rehearses RB-01 phases C and D and RB-02 in CI with synthetic data, and the tests of TASK-037 prove phases E and F; the durations it measures are in the log of its run in CI, which the record of TASK-037's merge names.

## 1. RB-01 — Restore after loss or corruption, phases C to F

Phases A and B (the Owner's authorization, writes stopped, the original preserved, the new target with the manifest's release image and new roles and marker) come first, as BACKUP_RECOVERY section 10 says.

**C. Restore**

- [ ] **Step 8 — download and decrypt, on the operator's machine.** In the operator's own shell, set `BANTUKASIR_STORE_ENDPOINT`, `BANTUKASIR_STORE_REGION`, `BANTUKASIR_STORE_BUCKET`, `BANTUKASIR_STORE_KEY_ID`, `BANTUKASIR_STORE_KEY` (the operator's storage access) and `BANTUKASIR_RECOVERY_KEY` (the file of the recovery key), then:

  ```sh
  bash scripts/operations/restore/fetch-backup.sh <backup id> <new empty work folder>
  ```

  It checks every SHA-256 against the manifest and prints `FETCHED <backup id> files=<n> manifest_sha256=<hash>`; it takes the manifest and the dump from the first tier that still holds them, so an older backup comes from its weekly or monthly copy. The endpoint is HTTPS: the script refuses plain HTTP. On `FETCH_FAILED`, destroy that work folder, which may already hold part of the backup decrypted (BKP-07), and take the next older backup into a new empty one. While a rotation of the recovery key is under way (BKP-22; the object `state/files-recipient` reads `ROTATING …`), some files are still encrypted to the old recipient: the identity file then holds both private keys, the old one from its custody, one per line, which age tries in turn.
- [ ] **Step 9 — the tunnel, the restore, the grants, the privilege test.** Open the SSH tunnel to the target database, whose port stays unpublished (ENVIRONMENTS section 9), with the target's host key verified by its fingerprint:

  ```sh
  ssh -f -N -o ExitOnForwardFailure=yes -L 127.0.0.1:<local port>:<database host>:5432 <operator>@<target>
  PGHOST=127.0.0.1 PGPORT=<local port> PGUSER=<migration role> PGDATABASE=<database> PGPASSWORD=<…> \
      pg_restore --no-owner --no-privileges --exit-on-error --single-transaction --dbname=<database> <work folder>/plain/dump.pgcustom
  ```

  Then, in a container of the release image on the target with the migration role's credential, `php artisan db:apply-grants --environment=<name>`, and in the application's container `php artisan db:check-privileges`. A difference stops the restore.
- [ ] **Step 10 — the files.** With the same psql environment:

  ```sh
  bash scripts/operations/restore/copy-files.sh <work folder> <operator>@<target> <file volume path on the target>
  ```

  It prints `COPIED files=<n> checked=<m>`: every row of `stored_files` has its file with its SHA-256.
- [ ] **Step 11 — the counts, the reconciliation, the decrypted copy destroyed.**

  ```sh
  BANTUKASIR_MANIFEST=<work folder>/plain/manifest.json \
      psql -X -v ON_ERROR_STOP=1 -v environment=<name> -f scripts/operations/restore/compare-counts.sql
  ```

  It ends with `BantuKasir counts equal to the manifest: <n> tables`, or names each table that differs. Then `php artisan reconciliation:run --operator --environment=<name>` in the application's container; a mismatch blocks the products concerned, which RB-04 handles. Then delete the work folder (BKP-07).

**D. Fence the old world, before any user**

- [ ] **Step 12 — OP-04.** `php artisan restore:after --environment=<name> --note="<the incident>"`. It prints whether a hold of onboarding waits, with its key.
- [ ] **Step 13 — the sequences.** With the highest Sale and session counters found in the gap review (section 11), 0 when none:

  ```sh
  psql -X -v ON_ERROR_STOP=1 -v environment=<name> -v sales_seen=<n> -v sessions_seen=<n> \
      -f scripts/operations/restore/advance-sequences.sql
  ```

  Record the restored and advanced values it prints in the incident record.
- [ ] **Step 14.** `psql -X -At -c 'show synchronous_commit'` answers `on`.

**E. Reconcile with the Owner, with traffic closed to everyone else**

- [ ] **Step 15.** The Owner alone signs in again on each device; each held request is checked and the earlier-epoch ones are reported. The reports still to decide, each with its key, its operation, its actor and when it was first reported, are listed read only, with the psql environment of step 9:

  ```sh
  psql -X -v ON_ERROR_STOP=1 -c "select s.id, s.operation, s.source_intent_id, s.actor_user_id, u.display_name, s.first_reported_at from stale_intent_reports s join users u on u.id = s.actor_user_id where not exists (select 1 from intent_reconciliations r where r.report_id = s.id) order by s.id"
  ```

- [ ] **Step 16 — first, the Owner's own user-management requests.** For each, on the Owner's decision: `php artisan restore:reconcile --environment=<name> --report=<key of its stale_intent_reports row> --note="<the Owner's decision>"`. The Owner's status check then answers NOT_COMMITTED and releases the block of its family.
- [ ] **Step 17.** The security-relevant account changes after the backup point are applied again through the product's own commands and procedures (OP-02 for the Owner, the Owner's links for staff).
- [ ] **Step 18 — every onboarding hold without a decision**: the one step 12 set, and any of an earlier restore that the restored data still hold, which keeps onboarding held too. They are listed read only:

  ```sh
  psql -X -v ON_ERROR_STOP=1 -c "select h.id, h.restored_state, h.set_at from onboarding_restore_holds h where not exists (select 1 from onboarding_restore_reconciliations r where r.hold_id = h.id) order by h.id"
  ```

  For each, `php artisan restore:reconcile --environment=<name> --hold=<its key> --outcome=RELEASED_BEFORE_RESTORE` or `--outcome=NEVER_RELEASED`, as the Owner decided, `--note="<the Owner's decision>"`.
- [ ] **Step 19.** The Owner's remaining reported requests, one `--report` each.

**F. Review the gap and reopen**

- [ ] **Steps 20 to 24** as BACKUP_RECOVERY section 10 says; a staff member's held request found later is recorded the same way (`--report`).

## 2. RB-02 — Restore drill

The form of the drill (BACKUP_RECOVERY section 13; D-145): **populated staging** takes staging's backup made by the release image's digest and is recorded in staging; **empty production**, at RB-23 step 9, takes production's latest backup taken after RB-23 step 8; **populated production** runs as written. No business record is added to production for any form.

- [ ] **Step 1.** The latest production backup with a stored manifest: its identifier from `backup_runs`. Populated staging: staging's latest backup whose manifest names the release image's digest. Empty production: the latest taken after RB-23 step 8.
- [ ] **Step 2.** The drill target off the VPS (section 8): the manifest's release image; the roles and the marker `drill` by the setup script of ENV-30, with new passwords.
- [ ] **Step 3.** `fetch-backup.sh` as RB-01 step 8, on the operator's machine or on the drill target itself; record the `manifest_sha256` it prints.
- [ ] **Step 4.** The restore, the grants, the privilege test and the files, as RB-01 steps 9 and 10.
- [ ] **Step 5.** `compare-counts.sql` and `reconciliation:run --operator --environment=drill`, which must be clean.
- [ ] **Step 6.** `restore:after --environment=drill` and `advance-sequences.sql` with `sales_seen=0 sessions_seen=0`.
- [ ] **Step 7.** The application started without traffic: `/up` answers 200. `php artisan owner:recover --environment=drill --note="<the drill>"` prints an Owner recovery link to the operator's terminal only; through it, set a password, sign in, and open one recent receipt (`/sales/<id>/receipt`) and one session report (`/cash-sessions/<id>`). Empty production, which has neither: open the BusinessProfile, the cash registers and the staff list, and compare each with production's.
- [ ] **Step 8.** The time of each step, from the operator's own clock.
- [ ] **Step 9.** The target's database, volume and every decrypted file destroyed on every machine that held one.
- [ ] **Step 10.** In production, `php artisan restore:record-drill --environment=production --kind=ROUTINE --backup=<identifier> --manifest-sha256=<hash> --started-at=<UTC> --finished-at=<UTC> --result=PASS|FAIL --restore-seconds=<seconds of steps 3 to 6> --note="<the durations>"`, the times as `YYYY-MM-DDTHH:MM:SSZ`. A drill that passed is recorded only with the manifest's hash as its upload recorded it. Populated staging: the same command in staging, with `--environment=staging`; it never counts as a production drill.
- [ ] **Step 11.** A failed drill is an incident of class S3 at least (RB-03), fixed and repeated.
