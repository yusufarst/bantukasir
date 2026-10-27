# Environments

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta); corrected under D-089, 18 October 2026: decryption and the restore-drill target off the VPS (ENV-01, sections 2, 3, 6 and 16; finding B-1 of the RF-6 review); the names of the settings of trusted hosts, trusted proxies and HSTS added to section 11 by TASK-007, 27 October 2026 |
| Phase | P9 — environments, access, configuration, network, certificates, scheduled tasks, logs, monitoring and the free-tier record |
| Replaces | The runtime, configuration and TLS paragraphs of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` and `docs/09-operations/legacy/P9-OPERATIONS.md`; `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Deployment, operasi dan maintenance (all LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-005, D-053, D-076, D-088, D-089; [ADR-002](../adr/ADR-002-minimum-runtime-infrastructure.md), [ADR-003](../adr/ADR-003-hosting-topology.md), [ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md), [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) |
| Related | [BACKUP_RECOVERY](BACKUP_RECOVERY.md), [OPERATIONS_RUNBOOK](OPERATIONS_RUNBOOK.md), [RELEASE_PROCESS](../10-release/RELEASE_PROCESS.md), [SECURITY_MODEL](../05-security/SECURITY_MODEL.md), [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 9, [COST_POLICY](../00-governance/COST_POLICY.md) |

Where BantuKasir runs, who may reach each place, and how each environment is configured. Rules are numbered `ENV-nn`; identifiers are permanent. This document is written for a public repository: it names no real address, domain, provider account, credential or backup location (SECURITY_MODEL SEC-SECRET-05). Those facts are recorded privately by the operator at gate TARGET. Setting names below are descriptive; the foundation Task fixes the exact names when it rebuilds `.env.example` (OPEN_ITEMS T-02). Nothing here is installed or created by RF-6.

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| ENV-01 | **Production data stay in production.** Production data exist only in the production environment, in its backups and, decrypted for the length of a drill or a restore, on the operator's own machine and on the restore-drill target of section 2, never elsewhere on the VPS (BACKUP_RECOVERY BKP-07, BKP-08). Otherwise they are never copied to a developer's machine, to CI, to staging or into the repository; no AI agent runs on a machine while it holds them; and they never appear in a test, a log, a screenshot or an evidence record. | PRODUCTION_DATA_SAFETY section 4, ADR-003 item 6, D-088, D-089 |
| ENV-02 | **Separate everything.** Each environment has its own database, file volume, temporary area, application key, secrets, hostname, backup bucket and recovery key pair. No secret is shared between environments. | D-053, SEC-SECRET-02, REQ-OPS-06, ADR-010 |
| ENV-03 | **No agent reaches the server.** Agents hold no credential of the VPS, Coolify, Cloudflare, the backup store or the heartbeat monitor; the operator performs every staging and production operation (question 20). | D-088, ADR-011 item 1 |
| ENV-04 | **Positive identification.** Every environment carries its name in configuration and the same name as a marker on its database, written by the operator when the database is created (`COMMENT ON DATABASE`). Operator procedures, the release preflight and every test-database reset read both and refuse to run when they differ or when the target is not the one named on the command line. A folder or database name alone is never proof (PRODUCTION_DATA_SAFETY section 2). | PDS §2, SECURITY_MODEL section 14 |
| ENV-05 | **One image per revision.** Staging and production run the same release image, by digest; staging runs a candidate before production does. | ADR-011 items 5 and 6, STK-01 |
| ENV-06 | **Staging is closed to the public.** Its hostname is behind an access gate at the proxy (HTTP basic authentication, credential held by the operator) in addition to the application's sign-in, it answers `X-Robots-Tag: noindex`, and it holds only synthetic or anonymized data and its own accounts. | D-053, PDS §4 |

## 2. Environments

| Environment | Where | Data | Used by | Purpose |
| --- | --- | --- | --- | --- |
| Local | A developer's machine, in containers | Synthetic fixtures and the provisional benchmark dataset (TECHNICAL_RULES section 13.2) | Agents and developers | Development; the test levels L1 to L5 of TEST_STRATEGY; rehearsal of migrations and of the restore runbook on synthetic data |
| CI | GitHub-hosted runners, created per run | Synthetic | Workflows (ADR-011 item 4) | The required checks; the release image |
| Staging | The Coolify environment `staging` on the VPS | Synthetic or anonymized only | The operator; the Owner and staff for UAT and training | Release rehearsal; rehearsal of every operator procedure (OPPROC01); UAT; its own backups and drills |
| Production | The Coolify environment `production` on the VPS | The shop's data | The shop; the operator for operations | Live use |
| Restore-drill target | Temporary, never on the VPS: the operator's own machine, or another isolated machine physically or logically separate from the VPS (BACKUP_RECOVERY section 8); no AI agent runs on it while it holds the recovery key in use or decrypted data | A decrypted copy of a production backup, during the drill only; decrypted on the operator's machine or on the target itself, and passed between machines only through an SSH tunnel | The operator | Restore drills (BACKUP_RECOVERY RB-02) and migration rehearsal on representative data (RELEASE_PROCESS section 5); wiped at the end of the drill |
| Performance target | The VPS before production data exist (gate TARGET), in staging or a temporary environment | The provisional benchmark dataset, synthetic | The operator | PERF01; after go-live PERF01 is repeated only in staging, never against production |

## 3. Access

| Who | Holds | Never holds |
| --- | --- | --- |
| Agents (planning agent, executor) | The repository, CI results, local environments | Any credential of the VPS, Coolify, Cloudflare, the backup store or the heartbeat monitor; any environment secret; any recovery key (ENV-03) |
| Operator | SSH key to the VPS; the Coolify administrator account; Cloudflare access; the backup store account and the server's storage keys; the heartbeat monitor; the secrets of staging and production; the migration role credentials; the recovery keys of production and staging, used only off the VPS (BACKUP_RECOVERY BKP-08) | — |
| The shop's Owner (SUPER_ADMIN) | The application; the sealed copy of the production recovery key and backup store access (ADR-010 item 3) | Server access, unless the Owner is also the operator |
| Staff | The application, by role | — |

- The VPS, domain and Cloudflare accounts are opened in the name agreed with the client at gate TARGET; the operator administers them. Each account's own recovery path (recovery codes, a second administrator) is recorded in the operator's private record and in the sealed copy.
- The operator enables two-factor authentication on every infrastructure account that offers it: Cloudflare, the backup store, the heartbeat monitor and Coolify. This is operator practice for infrastructure accounts; it adds no second factor to BantuKasir accounts (D-049).
- Until a second person is named, the operator is a single point of failure; the sealed copy (ADR-010) and the private record make recovery possible without that person.

## 4. Branches, CI and images

The branch model, CI, the release image and the deployment path are [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) items 3 to 6; the required checks are [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) section 7; the release runbook is [RELEASE_PROCESS](../10-release/RELEASE_PROCESS.md). The transition from the planning branch to `develop` and `main` waits for the Owner (OPEN_ITEMS N-12).

## 5. The server (gate TARGET)

| Item | Requirement |
| --- | --- |
| Operating system | A supported long-term-support Linux release that Coolify supports; automatic security updates enabled |
| Size | At least Coolify's own minimum (2 CPU cores, 2 GB memory, 10 GB disk, ADR-003 item 12) plus production, staging, two PostgreSQL servers and two backup containers. Proposed starting point, an assumption to verify with PERF01 and observed use: 2 vCPU, 4 GB memory, 40 GB SSD |
| Time | Synchronized by NTP; the system time zone is UTC. The application clock is UTC and the scheduler's time zone is Asia/Jakarta in the application's configuration (ARCHITECTURE ARC-50) |
| Accounts | A personal, non-root operator account with `sudo`; SSH by key only; password login and root login over SSH disabled |
| Docker logs | Size-based rotation set in the Docker daemon configuration (section 14) |
| Disk | Watched by the system status (OPERATIONS_RUNBOOK section 5): warning below 20 % free, critical below 10 % |

## 6. Coolify layout

One Coolify project per installation, with the environments `production` and `staging`; a restore drill never runs on the VPS (BACKUP_RECOVERY section 8). Each environment holds three resources:

| Resource | Source | Network | Storage | Hostname | Scheduled task | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Application | The release image by digest; process role `web` | The environment's private network and the proxy | The file volume, read and write; a separate temporary area for CSV uploads, never backed up (D-054) | The environment's hostname through the proxy | `php artisan schedule:run` every minute (ADR-003 item 7) | Health check on `/up` (ROUTE_CONTRACTS RH-01) |
| Backup | The same image; process role `backup`; its main process serves nothing | The private network only; no published port | The file volume, read only; a private temporary area for the run, emptied at the end of every run | None | `php artisan schedule:run` every minute; in role `backup` the scheduler registers only SC-03 | ADR-010 item 6 |
| PostgreSQL | The official image of the version the foundation Task pins | The private network only; never published | Its persistent volume | None | — | The platform's own scheduled database backup stays off: ADR-010 replaces it |

## 7. Cloudflare

| Setting | Value | Source |
| --- | --- | --- |
| Plan | Free | COST_POLICY; D-053 |
| DNS | The production and staging hostnames as proxied records. No record points at the server without the proxy, because an unproxied record would reveal its address | ADR-003 item 1 |
| SSL/TLS mode | Full (strict) | ADR-003 item 9, ADR-011 item 8 |
| Edge | Always Use HTTPS on; minimum TLS version 1.2 | SEC-HDR-04 |
| Caching | The default: HTML is not cached, authenticated responses are `no-store` (TR-HTTP-09), the built assets carry content hashes; no "cache everything" rule | TR-HTTP-09 |
| Security features | The free plan's managed rules as offered; bot-blocking features stay off unless the shop's own devices were tested with them; the request limits are the application's (SECURITY_MODEL section 11) | SECURITY_MODEL section 11 |
| Timeouts and sizes | Cloudflare waits 125 seconds for the origin by default, above the application limit of 60 seconds and the client wait of 65 seconds (TECHNICAL_RULES section 9); the free plan accepts request bodies up to 100 MB, above the largest upload of 8 MiB (RT-12) | TECHNICAL_RULES section 9, ROUTE_CONTRACTS RT-12 |

## 8. Certificates

| ID | Rule | Source |
| --- | --- | --- |
| ENV-10 | The origin presents a Cloudflare Origin CA certificate for exactly the production and staging hostnames; on the free plan a wildcard covers one level of subdomains only. The certificate and key are files in the proxy's certificate directory on the server, the key readable only by root, referenced from the proxy's dynamic configuration; the key is never pasted into the dashboard, a log or the repository. | ADR-011 item 8 |
| ENV-11 | The operator records the certificate's expiry date when it is created and renews it at least 60 days before it (OPERATIONS_RUNBOOK RB-07). Cloudflare sends no expiry notice; an expired or wrong certificate also makes the web heartbeat fail, because Cloudflare refuses the origin. | ADR-011 item 8 |
| ENV-12 | **Exit path.** Before the proxy is ever turned off for a hostname, the proxy is switched to Coolify's default Let's Encrypt certificate for it, because browsers do not trust an Origin CA certificate. | ADR-011 item 8 |

## 9. Network exposure

Rules are set in the provider's network firewall. A host firewall such as ufw is not relied on, because Docker's published ports bypass it; if the provider offers no network firewall, the rules are applied in the host's packet filter in the way Docker documents for published ports, and verified from outside.

| Port | Allowed from | Purpose |
| --- | --- | --- |
| 443/tcp | Cloudflare's published IPv4 and IPv6 ranges | HTTPS through Cloudflare |
| 80/tcp | Cloudflare's ranges | Redirect to HTTPS |
| 22/tcp | The operator's addresses where practical; otherwise any address, with key-only login | SSH |
| 8000, 6001, 6002/tcp | Nobody | Coolify's dashboard, its real-time updates and its terminal, reached through an SSH tunnel |
| 5432/tcp and every other port | Nobody | The database is never published |

| ID | Rule | Source |
| --- | --- | --- |
| ENV-20 | Outbound traffic stays open for the backup store, the heartbeat monitor, the image registry, Cloudflare's published lists and operating-system updates. | ADR-010, ADR-011 |
| ENV-21 | The Cloudflare ranges are taken from Cloudflare's published lists when the firewall is set, at every release and at least every three months; a change is applied and recorded. | ADR-011 item 9 |
| ENV-22 | The rules are verified from outside the server at gate TARGET and after every change: the database, the dashboard ports and the origin's 443 from a non-Cloudflare address are unreachable (SEC02). | THR-16 |

## 10. Client address and trusted proxies

- Coolify's proxy keeps the forwarded client chain only from Cloudflare's ranges, and Laravel trusts only the proxy hops in front of it; the address that rate limits and the security log use is the visitor's (SECURITY_MODEL SEC-HDR-05, section 11; ADR-003 item 10).
- Trusted host names are the environment's hostname only; a request for any other host is refused (SEC-HDR-05).
- Proof: SEC01 forges `X-Forwarded-For` and `CF-Connecting-IP` in application tests; SEC02 repeats it on the target from outside, through Cloudflare and directly.

## 11. Application configuration

| Setting | Application (`web`) | Backup (`backup`) | Rule |
| --- | --- | --- | --- |
| Environment name | Set | Set | Equals the database marker (ENV-04) |
| Debug | Off | Off | Off in every environment except local (TR-ERR-02) |
| Application URL, trusted hosts | Set | — | The environment's hostname (`APP_URL`, `APP_TRUSTED_HOSTS`, `config/security.php`); an HTTPS address sends plain HTTP to HTTPS (SEC-HDR-04) |
| Trusted proxy hops | Set | — | The platform's proxy and Cloudflare's published ranges, never a wildcard (`APP_TRUSTED_PROXIES`; section 10) |
| Strict-Transport-Security | Off until verified | — | Turned on by the operator once HTTPS is verified on the target; `includeSubDomains` only when every subdomain is (`APP_HSTS`, `APP_HSTS_SUBDOMAINS`; SEC-HDR-02) |
| Application key | Set | — | Per environment; previous keys listed during a rotation (section 16) |
| Runtime database credential | Set | — | SCHEMA SCH-41 |
| Backup database credential | — | Set | SCHEMA SCH-42 |
| Migration database credential | Never | Never | Supplied only to the migration job at run time (ADR-011 item 7) |
| Process role | `web` | `backup` | Selects the scheduled commands of section 13 and the settings checked at start-up |
| Clock and schedule | UTC clock; scheduler time zone Asia/Jakarta | The same | ARC-50 |
| Session and cookie | Secure, HttpOnly, `SameSite=Lax`; database driver | — | SECURITY_MODEL section 4 |
| Backup store endpoint, bucket, key identifier and key | — | Set | ADR-010 item 7 |
| Backup recipient (public key) | — | Set | ADR-010 item 2 |
| Heartbeat ping addresses | The web and reconciliation checks | The backup check | Secret: a ping address lets anyone fake a ping |
| Log level | `info` | `info` | Section 14 |

At start-up each process checks that every setting its role needs is present and not a default, without printing a value, and refuses to start otherwise (SEC-SECRET-03).

## 12. Database setup

| ID | Rule | Source |
| --- | --- | --- |
| ENV-30 | At installation the operator creates the database and its three roles from a reviewed SQL script in the repository, written by the foundation Task, which takes role names and passwords from the operator at run time and writes neither: the migration role owns the schema; the runtime and backup roles get the privileges of SCHEMA section 10; `PUBLIC` loses `CONNECT` and `TEMPORARY` (SCH-43); the environment marker of ENV-04 is set. | SCHEMA SCH-40 to SCH-45 |
| ENV-31 | Role settings: the runtime role works within the limits of TECHNICAL_RULES section 9 (`lock_timeout` 5 seconds, `idle_in_transaction_session_timeout` 30 seconds, statement limits per transaction); the backup role has no statement limit of its own and is bounded by the backup run's limit of 30 minutes; the migration role has a `lock_timeout` of 5 seconds, so that a migration never waits long behind the application. | TECHNICAL_RULES section 9 |
| ENV-32 | Production runs with `synchronous_commit` on, PostgreSQL's default, so that a number shown after a commit is durable; the release preflight checks it. V1 has no replica; promoting a database copy that may lag is treated as a restore. | SCHEMA SCH-28 |
| ENV-33 | The database server's time zone is UTC; `pg_trgm` is created by the migration role (SCHEMA section 11). | SCHEMA section 11 |

## 13. Scheduled tasks

| ID | Container | Command | When (Asia/Jakarta) | Result |
| --- | --- | --- | --- | --- |
| SC-01 | Application | `reconciliation:run` | Daily at 03.00 | Writes a `reconciliation_runs` row; blocks mismatching products (BR-INV-45); pings the reconciliation check when the run completes |
| SC-02 | Application | `housekeeping:run` | Hourly | Removes, in bounded batches: expired one-time link tokens; unreferenced stored files older than one hour; temporary CSV files older than 24 hours; resolved request receipts older than 30 days; security log entries older than 365 days (section 14). Never a business record and never an unresolved receipt |
| SC-03 | Backup | `backup:run` | 00.00, 06.00, 12.00 and 18.00; the Sunday 00.00 run also writes the weekly copy and re-verifies the latest weekly copy; the 00.00 run on the 1st of the month also writes the monthly copy | ADR-010; writes a `backup_runs` row; pings the backup check after success |
| SC-04 | Application | `monitor:heartbeat` | Every 5 minutes | Fetches the environment's public `/up` through Cloudflare; on success pings the web check |

Every scheduled command runs without overlap, takes its arguments from configuration only and never passes stored data to a process (TR-RUN-04, THR-10). ROUTE_CONTRACTS section 7 lists them.

## 14. Logs and retention

| Record | Where | Kept | Source |
| --- | --- | --- | --- |
| Application and access log lines | The containers' standard output, collected by Docker and shown in Coolify | Size-based rotation: at most five files of 10 MB per container | ARC-90 |
| Security log | `security_log_entries`, and a copy on standard output | 365 days from `occurred_at` in the database, then removed by SC-02; the standard-output copy as above | SEC-LOG-05, D-088 |
| Business audit | `audit_events` | Retained, never removed (class F) | D-044 |
| Request receipts | `command_request_receipts` | A resolved receipt 30 days after `outcome_at`; an unresolved receipt is never removed | ADR-009 section 1 |
| Backup runs, restore drills, reconciliation runs | SCHEMA section 5.12 | Retained (class F) | REQ-OPS-04 |
| Heartbeat pings | The external monitor | Its plan's last 100 pings per check | ADR-011 item 11 |

No log line holds a password, token, cookie, session identifier, cost for staff, payload or production identity (SEC-CH-08). There is no external log or error-tracking service (ARCHITECTURE ARC-92).

## 15. Free-tier record

COST_POLICY section 2 requires this record for every free tier the product depends on. Checked on 18 October 2026; the operator checks it again at gate TARGET and once a year.

| Service | Free terms used | At the limit | Lock-in | Exit path | Data held |
| --- | --- | --- | --- | --- | --- |
| Cloudflare, Free plan | DNS, proxy, Full (strict), Origin CA certificates, request bodies up to 100 MB, 125-second origin timeout | Not expected to be reached by one shop | DNS hosting | Move the DNS elsewhere; switch the certificate first (ENV-12) | Traffic in transit only |
| Backblaze B2, first 10 GB free (ADR-010) | Storage up to 10 GB, free download up to three times the stored amount, caps at Rp0, Object Lock, lifecycle rules | An upload is refused with `cap_exceeded`; the backup run fails, the status shows it and the heartbeat alerts | None: S3 API, standard formats (age, `pg_dump`) | Create a bucket at another S3-compatible store, change the backup container's settings, run a backup; the old copies expire by their lifecycle | Encrypted backups only |
| Healthchecks.io, Hobbyist (ADR-011) | 20 checks, 100 log entries per check, email alerts | No further check can be added | None | Create the checks elsewhere and change the ping addresses | Ping times only |
| GitHub Actions, public repository | Standard runners free for public repositories | If the repository became private, the private-repository minute allowance would apply | Workflow syntax | A self-hosted runner, also free | Synthetic test data only |
| GitHub Container Registry, public package | Storage and bandwidth currently free | — | Image format is standard | Build on the server from the release tag (ADR-011, alternatives) | The application image, as public as the repository |

## 16. Secrets and rotation

| Secret | Held by | Rotate when | How |
| --- | --- | --- | --- |
| Application key, per environment | The application container | Suspected leak | New key with the old one listed as a previous key for one session lifetime, then removed; every sign-in session ends at the latest then |
| Runtime and backup database passwords | Their containers | Leak; change of operator; at least yearly | The operator changes the role's password, updates the setting and redeploys |
| Migration role password | The operator's password manager only | After each use by another person; leak | As above, never stored in an environment |
| PostgreSQL superuser | The platform's database resource | Leak | Through the platform |
| Storage key of the server | The backup container | Leak; change of operator | The account owner creates a new key with the same capabilities, updates the setting, then deletes the old key |
| Storage account access and recovery key | The operator, off the VPS (BACKUP_RECOVERY BKP-08), and the sealed copy (ADR-010 item 3) | Leak; change of operator or Owner | BACKUP_RECOVERY section 9 |
| Heartbeat ping addresses | The containers | Leak | New addresses in the monitor; update the settings |
| Origin certificate key | The proxy's certificate directory | Leak; renewal | Revoke at Cloudflare; issue and install a new certificate (RB-07) |
| Staging access-gate credential | The operator | Leak; change of operator | Change at the proxy |
| SSH keys, Coolify, Cloudflare, store and monitor accounts | The operator | Leak; change of operator | Replace the key or password; keep two-factor authentication on |

A suspected leak is an incident: OPERATIONS_RUNBOOK RB-05 (SEC-SECRET-04).

## 17. Gate TARGET

Recorded by the operator. Facts that identify the server, the domain, an account or a backup location stay in the operator's private record; the repository records only that each item is verified, with measurements that identify nothing (sizes, versions, durations, dates).

1. The server's provider, size, region and other services, and the domain on Cloudflare (OPEN_ITEMS gate TARGET).
2. Section 5 met; Coolify installed and its version recorded.
3. The firewall of section 9 verified from outside (SEC02); the certificate of section 8 installed, Full (strict) verified, expiry date recorded.
4. Production and staging created as in section 6, separated as ENV-02 requires (SEC02, REQ-OPS-06).
5. Database roles and privileges created and proven on the target (ENV-30; DB01 privilege family).
6. The backup store, its buckets, lifecycle rules, Object Lock, caps at Rp0 and keys created; the first backup run and the first drill passed (BACKUP_RECOVERY; gate G4).
7. The heartbeat checks created and each alert received once in a test.
8. PERF01 run on the server with the provisional dataset (TECHNICAL_RULES section 13).
9. This free-tier record checked again.

## 18. Open items

| Item | Subject | Step |
| --- | --- | --- |
| Gate TARGET | The real server, domain and accounts; section 17 | Before the pilot |
| N-12 | The transition from the planning branch to `develop` and `main` (ADR-011 item 3) | Before the first implementation Task |
| N-08 | GitHub branch protection | The Owner, when convenient |
| T-02 | `.env.example` rebuilt with the settings of section 11, placeholders only | Foundation Task |
