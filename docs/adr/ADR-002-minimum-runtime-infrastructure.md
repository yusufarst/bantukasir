# ADR-002 — Minimum runtime infrastructure

```text
ID:               ADR-002
TITLE:            Minimum runtime infrastructure
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-005 (Planner), in force within the hosting topology of D-053 (Owner)
```

## Context

One installation serves one small shop (D-038): a few concurrent users, two concurrent cashiers in the legacy performance assumption, and no workload that needs background processing. The target for additional recurring production cost is zero (COST_POLICY, AICWDF §4C). AICWDF-4.3 §4 and §17 allow Valkey or Redis only when actually required. D-005 fixed a minimum infrastructure before the re-foundation; D-053 now fixes the hosting topology around it (ADR-003).

## Decision

1. **Processes.** Each environment runs one application container with Nginx and PHP-FPM serving Laravel, and one private PostgreSQL. Node.js is used only to build the frontend assets; no Node process runs in production (D-005).
2. **Sessions.** Sign-in sessions use Laravel's database session driver (D-005). Timeouts and cookie rules are in [SECURITY_MODEL](../05-security/SECURITY_MODEL.md).
3. **Cache store.** The Laravel cache uses the database store. It is used only for the rate limiter and for scheduler locks. It never holds a rendered page, a response, a report, a DTO, a cost figure or any per-user business data (legacy P4 §History Inertia, last paragraph; D-005).
4. **No queue platform.** There are no queue workers and no queued jobs, notifications or listeners. Every command runs synchronously inside its HTTP request, within the time limits of [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md). The two heaviest commands, the CSV product import (D-037) and the cost-correction replay (D-034), are bounded there; a command that cannot finish inside its bound is rejected with no partial change (BR-COST-27).
5. **Scheduled commands.** Recurring work runs as Laravel scheduled commands: daily reconciliation (BR-INV-45), removal of expired one-time links and abandoned temporary uploads (SECURITY_MODEL; D-054), and the backup job (RF-6). The scheduler is started once a minute by the hosting platform (ADR-003). Scheduled commands are bounded, take arguments only from configuration, and never pass user input to a shell (SECURITY_MODEL, threat THR-10).
6. **Not used.** Redis, Valkey, Memcached, queue workers, Horizon, Octane, server-side rendering, websockets, an external search service, a separate API gateway and microservices (D-005; AICWDF §4, §6.4).
7. **Independent backup is mandatory** (D-005, REQ-OPS-01). Whatever destination is chosen, the backup must be: encrypted before it leaves the server; stored outside the server and its volumes; written with its own credential, separate from the runtime and migration credentials (PRODUCTION_DATA_SAFETY section 3); described by a manifest with checksums that covers the database and every referenced file (logos, product photos, templates); and proven by a restore to an isolated target. The destination, its cost and the holder of the recovery key are question 18, a prerequisite of RF-6 (plan decision P-3, D-055). This ADR chooses no destination, provider, cost or key. A database dump made by the hosting platform alone does not satisfy this rule, because it does not include the files.

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| Redis or Valkey for sessions, cache and rate limiting | No measured need; one more service to secure, back up and monitor. The database store is sufficient for one shop (D-005). |
| A queue worker for the CSV import and the replay | Adds an asynchronous state that the interface must then show and recover, and a worker process to supervise. Both commands are bounded synchronously instead; a queue is reconsidered only on measured evidence (PERF01). |

## Consequences

- Session and rate-limiter writes go to PostgreSQL. For one shop this load is small; it is part of the PERF01 measurement.
- Long-running work is bounded rather than deferred. If PERF01 shows that a bounded command cannot serve the real catalogue (gate G2), the response is a new ADR proposing a change, not a silent queue.
- No background process exists that could act without a request or a scheduled command, which keeps the operational surface small.

## Framework conformance

```text
ADR REQUIRED:      YES (records D-005 as required by REFOUNDATION_PLAN)
DEVIATION:         NONE — AICWDF §4 and §17 allow a queue or cache service only when actually required
OWNER APPROVAL:    NOT REQUIRED — no deviation; D-005 is a planner decision in force; D-053 (Owner) fixes the topology around it
REASON:            smallest runtime that meets the requirements at zero additional recurring cost
RISK:              a bounded synchronous command may prove too slow for the real catalogue (gate G2, T-09)
MIGRATION IMPACT:  none before implementation; adding a queue later is additive and needs a new ADR
ROLLBACK IMPACT:   none
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://laravel.com/docs/session and https://raw.githubusercontent.com/laravel/laravel/13.x/config/session.php | 4 October 2026 | Laravel 13.x supports a `database` session driver with a `sessions` table; it is the skeleton's default driver. |
| https://laravel.com/docs/rate-limiting | 4 October 2026 | The rate limiter uses the default cache store unless a `limiter` store is set in `config/cache.php`; the database store is supported. |
| https://laravel.com/docs/scheduling | 4 October 2026 | In production the scheduler is started by running `php artisan schedule:run` every minute; `withoutOverlapping` and `onOneServer` use cache locks, which the database cache driver supports; a schedule timezone can be configured. |

## Related

- [ADR-003](ADR-003-hosting-topology.md) for where these processes run.
- [ADR-010](ADR-010-independent-backup-and-recovery-key-custody.md) (RF-6, D-088) completes item 7 — destination, cost, recovery-key custody, method — and amends item 1 with one backup container per environment.
- Superseded legacy content: the runtime sentences of `docs/04-architecture/legacy/02-ARCHITECTURE.md` (introduction, §Runtime, asset dan operasi) and the cache paragraph of `docs/04-architecture/legacy/P4-ARCHITECTURE.md`. The operations content of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` stays LEGACY — DRAFT until RF-6; where it differs from this ADR, this ADR prevails.
