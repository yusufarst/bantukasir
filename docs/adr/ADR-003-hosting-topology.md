# ADR-003 — Hosting topology

```text
ID:               ADR-003
TITLE:            Hosting topology
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-053 (Owner, question 7); plan decision P-2 (D-055); D-005 (Planner) inside it
```

## Context

Each installation needs a production environment and, before production, a staging environment (AICWDF §20, §32). The domain and the server are funded by the client; additional recurring cost must be zero (COST_POLICY). The legacy operations draft assumed a hand-built Docker Compose stack with Nginx-managed TLS renewal and no staging (OPEN_ITEMS R-12, deviation FD-06). The Owner chose the framework default topology (D-053). Under plan decision P-2 only the topology is decided; the provider, the server size, other services on the server and whether the domain is already on Cloudflare stay UNKNOWN in gate TARGET.

This repository is public (OF-01). This ADR is written generically: it names no real domain, address, provider account, credential or backup location (N-01).

## Decision

1. **Path of a request.**

   ```text
   Browser (PC or tablet at the shop; Owner's device)
     → Cloudflare (DNS and proxy for the shop's domain)
     → VPS (client-funded; provider and size UNKNOWN — gate TARGET)
     → Coolify-managed reverse proxy (TLS termination and routing)
     → application container: Nginx + PHP-FPM + Laravel (built assets included)
     → PostgreSQL container on a private network (never published)
   ```

2. **Coolify** (self-hosted, open source) manages containers, routing and TLS certificates on the VPS (D-053). Its reverse proxy is shared by the resources on the server.
3. **Application container.** One image per release containing Nginx, PHP-FPM, the Laravel application and the compiled frontend assets. Node.js is used only while building the image. The image runs no queue worker and no Redis (ADR-002). Whether the image is built by the platform's build pack or by a project Dockerfile is chosen by the foundation and operations Tasks; either way the image is versioned and immutable, and production never uses a floating `latest` tag (TOOLCHAIN).
4. **PostgreSQL** runs as a separate container per environment, on the private network only, with its own persistent volume. Public exposure stays off. Database roles follow PRODUCTION_DATA_SAFETY section 3: a runtime role, a separate migration role available only to the controlled migration job, and a separate backup role ([ARCHITECTURE](../04-architecture/ARCHITECTURE.md) section 9).
5. **Files.** Logos, product photos and their thumbnails are kept on a persistent private volume of the application environment, outside the public web root ([ARCHITECTURE](../04-architecture/ARCHITECTURE.md) section 6). Temporary CSV uploads use a separate private temporary area that is never backed up (D-054).
6. **Staging** runs on the same VPS as a separate Coolify environment with its own application resource, its own PostgreSQL resource, its own persistent volume, its own secrets and environment variables and its own hostname (D-053). Staging and production never share a database, a volume or a secret. Staging holds synthetic or anonymized data only (PRODUCTION_DATA_SAFETY section 4); copying production data into staging is a production operation and is not authorized (CURRENT_HANDOFF).
7. **Scheduled commands.** For each environment, the platform's scheduled-task feature runs `php artisan schedule:run` once a minute inside the application container; the Laravel scheduler then runs the scheduled commands of ADR-002 in the Asia/Jakarta schedule timezone, each protected against overlap.
8. **Migrations** are never run by container start-up (PRODUCTION_DATA_SAFETY section 2). How an approved migration is applied as the controlled job of an authorized release, and by whom, is designed in RF-6 together with question 20.
9. **TLS and Cloudflare.** Traffic is encrypted from the browser to Cloudflare and from Cloudflare to the origin, and the origin certificate is validated. Coolify's default certificate method is a Let's Encrypt HTTP challenge; the official Coolify documentation warns that a Cloudflare proxy can make that challenge fail and recommends a DNS challenge or not proxying. The certificate method that works with the Cloudflare proxy (for example a DNS challenge with an API token limited to the zone's DNS records) is chosen and tested in RF-6 (T-10). Any token it needs is a secret ([SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 8).
10. **Client IP and proxies.** Rate limits and the security log need the real client address. Laravel trusts only the proxy hops in front of it, never every address, and the header that carries the client address through Cloudflare and the Coolify proxy is configured and tested against spoofing in RF-6 (SECURITY_MODEL section 11).
11. **Exposure.** Only HTTPS, and HTTP for redirection and certificate challenges, are publicly reachable for the application. The database is never public. Access to the Coolify dashboard and to SSH is restricted; how is designed in RF-6.
12. **Server size.** Coolify's documentation states a minimum of 2 CPU cores, 2 GB of memory and 10 GB of disk for Coolify alone. The VPS must also carry production, staging, their databases and files. The real size is verified at gate TARGET; representative benchmarks wait for it (PERF01).

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| Hand-built Docker Compose, Nginx and manual TLS renewal (FD-06, legacy operations draft) | Declined by the Owner (D-053): more manual work and more failure points (certificate renewal, deployment). FD-06 is withdrawn. |
| Staging on a second VPS | Declined by the Owner (D-053): better isolation, but a second server is an additional recurring cost. |

## Consequences

- No additional recurring cost beyond the client-funded server and domain is expected (D-053; COST_POLICY). The Cloudflare plan for the actual domain is verified at gate TARGET.
- Staging shares the production server's resources and machine risk (accepted with D-053; DECISIONS section 5). A failure of the server affects both; the independent backup (ADR-002) is the protection against losing the server.
- Branch and environment model, CI, the Cloudflare and Coolify configuration, the certificate method, the firewall and the infrastructure runbooks are RF-6 work (T-10, P9). No account is created, nothing is purchased and nothing is installed in RF-3A.

## Framework conformance

```text
ADR REQUIRED:      YES (records the hosting topology, REFOUNDATION_PLAN; AICWDF §20)
DEVIATION:         NONE — the AICWDF §20 default topology is adopted; FD-06 is WITHDRAWN (D-053).
                   The framework's default names one VPS provider; the provider is a client-funded
                   fact that stays UNKNOWN in gate TARGET (P-2) and changes nothing in this ADR.
OWNER APPROVAL:    GIVEN — D-053
REASON:            framework default; automatic TLS and deployment through one interface; staging at no extra cost
RISK:              staging shares the machine; the server may be too small (gate TARGET); the TLS
                   certificate method must be chosen to work behind the Cloudflare proxy (RF-6)
MIGRATION IMPACT:  none — nothing is deployed
ROLLBACK IMPACT:   none before deployment; a later topology change needs a new ADR and Owner approval
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://coolify.io/docs/get-started/installation | 4 October 2026 | Coolify needs 2 CPU cores, 2 GB RAM and 10 GB disk to run; projects need more; amd64 or arm64. |
| https://coolify.io/docs/applications/laravel | 4 October 2026 | The Laravel guide's all-in-one container runs Nginx and PHP-FPM under Supervisor in the application container (Nixpacks); a Dockerfile variant exists; Laravel must be configured to trust proxies. The guide's queue-worker and Redis settings are not used (ADR-002). |
| https://coolify.io/docs/knowledge-base/proxy/traefik/overview | 4 October 2026 | Traefik is Coolify's default reverse proxy, shared by the resources on a server, with a Let's Encrypt resolver using the HTTP challenge. |
| https://coolify.io/docs/troubleshoot/dns-and-domains/lets-encrypt-not-working | 4 October 2026 | A Cloudflare proxy can interfere with the HTTP or TLS-ALPN challenge; the remedies named are a DNS challenge or not proxying. |
| https://coolify.io/docs/core/networking/proxy/traefik/dns-challenge | 4 October 2026 | The DNS challenge works with Cloudflare and needs an API token with DNS edit permission limited to the zone, stored in the proxy configuration. |
| https://coolify.io/docs/get-started/concepts | 4 October 2026 | A project groups environments and resources; several environments can exist on one server. |
| https://coolify.io/docs/core/automation/scheduled-tasks/overview | 4 October 2026 | A scheduled task runs a command inside an application or service container on a cron schedule, with a timeout and a run history; Laravel's `php artisan schedule:run` is a named example; the schedule uses the server's timezone. |
| https://coolify.io/docs/databases/postgresql | 4 October 2026 | PostgreSQL runs as a standalone container with a persistent volume, private networking by default and an optional public exposure; the built-in scheduled backup is a `pg_dump` with S3 destinations (it does not cover application files). |

## Related

- [ADR-002](ADR-002-minimum-runtime-infrastructure.md); [ARCHITECTURE](../04-architecture/ARCHITECTURE.md) section 8 (runtime constraints).
- Open: question 18 (backup destination, before RF-6), question 20 (who may operate on production, before RF-6), gate TARGET, T-10 (RF-6), N-01 (repository visibility).
- Superseded or overridden legacy content: the Compose and Nginx-managed TLS statements of `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Deployment, operasi dan maintenance (now INPUT ONLY for RF-6) and of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` §Runtime, konfigurasi, dan biaya and §Runtime selected dan TLS responsibility (LEGACY — DRAFT until RF-6; this ADR prevails where they differ).
