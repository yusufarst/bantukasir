# ADR-011 — Release pipeline, environments and production operation

```text
ID:               ADR-011
TITLE:            Release pipeline, environments and production operation
STATUS:           ACCEPTED
DATE:             18 October 2026 (Asia/Jakarta), written in RF-6 (D-088)
DECISION SOURCE:  D-088 (Owner, question 20: the AICWDF-4.3 rule — no AI agent connects to or changes
                  production; a human operator applies authorized production operations); D-053 and ADR-003
                  items 8 to 11 (the migration job, the certificate method, the client address and exposure
                  were left to RF-6); D-076 (public repository and publication guard); D-088 (Owner: question
                  18, ADR-010); the pipeline, branch model, certificate method and monitoring are planner choices
                  under PROJECT_RULES section 3, recorded in D-088 for the Owner's review
EXTENDS:          ADR-003, which stays in force
```

## Context

ADR-003 fixed the topology — Cloudflare, one client-funded VPS, Coolify, production and staging on the same server — and left to RF-6 how an approved migration reaches production and by whom (item 8), the certificate method behind the Cloudflare proxy (item 9), the client address through the proxies (item 10) and the restriction of the dashboard and SSH (item 11); ARCHITECTURE ARC-92 left the uptime check and monitoring to RF-6 at zero additional cost. OPEN_ITEMS T-10 also named the branch and environment model, CI and the release integrity report. PROJECT_RULES section 9 left the branch model for implementation to the rebuild of P9 and P10.

The Owner decided question 20 in D-088 with the framework rule: AI agents never connect to or change production and hold no access to the VPS, Coolify or Cloudflare — staging shares the production server, so this includes staging; agents prepare release packages, runbooks and rehearsals in isolated local environments; a human operator runs staging and production through a controlled release procedure after the Owner authorizes the exact target, revision and operation.

The repository is public (D-076): anything a workflow publishes is public, and no document names a real address, domain, provider account, credential or backup location (SECURITY_MODEL SEC-SECRET-05).

## Decision

1. **Who operates.** No AI agent connects to, deploys to, migrates, restores or runs an operator procedure on production or staging, and none holds a credential of the VPS, Coolify, Cloudflare, the backup store or the heartbeat monitor. Agents work in the repository, in CI and in local environments with synthetic data. The **operator** — a person with technical access to the server, as the introduction of SECURITY_MODEL defines it — performs every operation on staging and production. A production operation needs the Owner's authorization of its exact target, revision and operation (gate RELEASE; PRODUCTION_DATA_SAFETY section 5); routine operations under an approved runbook — the scheduled jobs, the monthly restore drill, reading status — need no further authorization each time. A destructive operation still needs its own approval (PRODUCTION_DATA_SAFETY section 1).
2. **Environments.** Local (a developer's machine), CI (ephemeral GitHub-hosted runners), staging and production (Coolify environments on the VPS, ADR-003), and the restore-drill target (temporary and isolated, BACKUP_RECOVERY section 8). Local, CI and staging hold synthetic or anonymized data only; production data exist only in production, in its backups and, during a drill, in the drill target (ENVIRONMENTS section 2).
3. **Branches.** Implementation uses `develop` as the integration branch, one branch per Task (`task/TASK-NNN-short-name`) merged into `develop` by pull request after review and passing CI, and `main` as the released line: a release candidate on `develop` that the Owner has authorized for release is brought into `main` and tagged `vMAJOR.MINOR.PATCH`; a hotfix branches from the released tag and is merged into `main` and `develop`. History is never rewritten and nothing is force-pushed (PROJECT_RULES section 9). The planning branch `plan/bantukasir-pos-v1` stays as it is until the Owner authorizes the transition; `main` is unchanged until then (D-022). `main` (`f7cecfe`) is an ancestor of the planning head, verified on 18 October 2026, so it can later be brought forward by fast-forward without rewriting history; when and how is the Owner's decision before the first implementation Task (OPEN_ITEMS N-12). Branch protection on GitHub stays the Owner's decision (N-08).
4. **CI.** GitHub Actions on the public repository, free on GitHub's standard runners. The `ci` workflow runs on every pull request and every push to `develop` and `main`: the checks of [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) section 7, with PostgreSQL of the pinned version as a service container. CI holds no secret of any environment; its only credential is the token GitHub issues to a workflow, used solely by the release workflow to publish the image. Test data are synthetic and CI logs are public, so nothing sensitive is printed. Workflows from forks never publish.
5. **The release image.** The `release` workflow runs only for a release tag the Owner has authorized: it builds one image from the tagged commit — production Composer dependencies, the Vite build, Nginx and PHP-FPM, the PostgreSQL client tools of the server's major version and age — from base images pinned by digest, runs the image checks of RELEASE_PROCESS section 4, and publishes it to the GitHub Container Registry, public like the repository, labelled with the version and the commit. Coolify runs images by digest only, never by a floating tag (STK-01). Every released image is kept, for rollback. The same image serves the application container and the backup container (ADR-010 item 6), which differ only in their process role and environment.
6. **Deployment.** The Coolify resources of each environment run the release image by digest. The operator deploys to staging first and verifies it, then deploys to production in the window the Owner authorized (RELEASE_PROCESS section 6). A container start never runs a migration (PRODUCTION_DATA_SAFETY section 2); it may cache configuration, routes and views.
7. **The migration job.** An approved migration is applied once, by the operator, as a one-off container from the release image on the environment's private network, after the pre-release backup and the write stop of the release runbook. The migration role's credential is supplied to that container at run time from the operator's password manager and is never stored in the environment of the application or backup container (SCHEMA SCH-40). The job first runs the release preflight of RELEASE_PROCESS section 5, which refuses to continue unless the operator's confirmation names the environment, the database carries that environment's marker, a successful backup less than one hour old exists (production) and the pending migrations equal the release's reviewed list.
8. **Certificates.** Cloudflare proxies the shop's hostnames on the free plan with the SSL/TLS mode Full (strict). The origin presents a **Cloudflare Origin CA certificate** — free, trusted by Cloudflare only, compatible with Full (strict) — installed in Coolify's proxy from files on the server through its dynamic configuration; the private key is never pasted into the dashboard. No Cloudflare API token is stored on the server, so a compromised server cannot change the shop's DNS. Cloudflare sends no expiry notice for these certificates; the operator records the expiry date and renews well before it (OPERATIONS_RUNBOOK RB-07). If the proxy were ever turned off, browsers would distrust the certificate; the exit path is ENVIRONMENTS section 8.
9. **Exposure.** At the provider's network firewall: ports 80 and 443 only from Cloudflare's published address ranges; SSH only by key, without password or root login, and from the operator's addresses where practical; Coolify's direct ports 8000, 6001 and 6002 closed to the public, the dashboard being reached through an SSH tunnel; the database never published. A host firewall such as ufw is not relied on, because Docker's published ports bypass it. The rules are checked from outside at gate TARGET (suite SEC02).
10. **Client address.** Coolify's proxy accepts forwarded headers only from Cloudflare's ranges, and Laravel trusts only the proxy hops in front of it, so the address used by rate limits and the security log is the visitor's, and a forged forwarding header changes nothing (SECURITY_MODEL SEC-HDR-05; suites SEC01, SEC02).
11. **Monitoring.** Laravel's health route `/up` (ROUTE_CONTRACTS RH-01) answers 200 when the application has booted and its database connection works, and an error otherwise, with no detail. An external heartbeat monitor on a free plan — **Healthchecks.io**, Hobbyist plan, as verified on 18 October 2026 — alerts the operator by email when an expected ping is missing: the web check, pinged every five minutes by SC-04 after it has fetched the public `/up` through Cloudflare; the backup check, pinged by each successful backup run (ADR-010 item 9); and the reconciliation check, pinged by each completed daily run. No error-tracking or log service is used; application logs go to the containers' standard output, rotated by size (ENVIRONMENTS section 14).

## Alternatives considered

| Alternative | Why not |
| --- | --- |
| An agent operating production or staging after the Owner's exact authorization (the earlier BantuKasir rule; it would have been FD-09) | Declined by the Owner (D-088, question 20) |
| A Let's Encrypt certificate by DNS challenge | Needs a Cloudflare token that can edit the zone, stored on the server; a compromised server could redirect the shop's domain |
| A Let's Encrypt certificate by HTTP challenge without the proxy | Exposes the origin and gives up the proxy's protection |
| Building the image on the VPS from the Git commit | Uses the shared server's CPU and memory and is harder to reproduce; kept only as the exit path if the registry becomes unsuitable |
| A tunnel from Cloudflare to the server instead of open 80 and 443 | One more agent on the server; can be adopted later by a new ADR |
| A free uptime service that probes the site from outside | The plan checked is offered for hobby and non-profit projects; a heartbeat that the server's own outside request triggers covers DNS, the proxy, TLS, the application and the database together |
| A paid monitoring or log service | An additional recurring cost (COST_POLICY) |

## Consequences

- An operator is required for every installation, and the Owner names that person before the pilot (gate RELEASE).
- Agents rehearse migrations and restores in local environments; staging rehearsals are run by the operator.
- Releases are reproducible: one image, one digest, the same in staging and production.
- The transition from the planning branch to `develop` and `main` waits for the Owner (N-12).
- The certificate's expiry and Cloudflare's address list are maintenance items of the runbook.

## Framework conformance

```text
ADR REQUIRED:      YES (records the release pipeline and the production operation rule; completes ADR-003
                   items 8 to 11; AICWDF §20, §29, §30, §32)
DEVIATION:         NONE — the AICWDF §29 zero-touch rule is adopted (D-088); FD-09 is not needed
OWNER APPROVAL:    GIVEN — D-088 (question 20); the pipeline, branch model, certificate method and
                   monitoring are planner choices recorded in D-088 for the Owner's review
REASON:            a controlled, reproducible release path that no agent can use to reach production
RISK:              one operator is a single point of failure until a second person is named; free plans
                   may change (ENVIRONMENTS section 15)
MIGRATION IMPACT:  none — nothing is deployed
ROLLBACK IMPACT:   none
```

## Verified sources

Checked on 18 October 2026.

| Source | Fact used |
| --- | --- |
| https://docs.github.com/en/billing/concepts/product-billing/github-actions | "GitHub Actions usage is free for self-hosted runners and for public repositories that use standard GitHub-hosted runners" |
| https://docs.github.com/en/billing/concepts/product-billing/github-packages | "GitHub Packages usage is free for public packages"; container image storage and bandwidth for the Container registry is currently free |
| https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/ | Origin CA certificates encrypt traffic between Cloudflare and the origin only, are compatible with Full (strict), are available on the Free plan, cause untrusted-certificate errors when the proxy is off, and carry no expiry notification |
| https://coolify.io/docs/knowledge-base/cloudflare/origin-cert and https://coolify.io/docs/core/networking/proxy/traefik/custom-ssl-certs | The certificate and key are placed under `/data/coolify/proxy/certs` and referenced from a dynamic configuration of the proxy; the key is never pasted into the dashboard; Full (Strict); the guide creates the certificate with a 15-year validity |
| https://coolify.io/docs/core/infrastructure/servers/firewall | Ports 8000, 6001 and 6002 serve the dashboard, its real-time updates and its terminal by direct address; 22 SSH; 80 and 443 the proxy; restrict SSH where possible |
| https://docs.docker.com/engine/network/packet-filtering-firewalls/ | Traffic to a published container port is diverted before ufw's rules apply |
| https://www.cloudflare.com/ips/ | Cloudflare publishes its IPv4 and IPv6 ranges, with text lists and an API; the update history shows changes |
| https://laravel.com/docs/13.x/deployment | The health route `/up` returns 200 when the application booted without exceptions, otherwise 500; the `DiagnosingHealth` event lets a listener check the database; `APP_DEBUG` false in production; configuration caching |
| https://healthchecks.io/pricing/ | Hobbyist plan at $0: 20 checks, 100 log entries per check; no commercial-use restriction stated |
| https://uptimerobot.com/pricing/ | The free plan is described as "Good for hobby and non-profit projects" |

## Related

[ADR-002](ADR-002-minimum-runtime-infrastructure.md), [ADR-003](ADR-003-hosting-topology.md), [ADR-010](ADR-010-independent-backup-and-recovery-key-custody.md); [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md), [RELEASE_PROCESS](../10-release/RELEASE_PROCESS.md), [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md), [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md); [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md) section 5; OPEN_ITEMS question 20, R-16, T-10, N-12, N-08. Superseded legacy content: the release and runtime paragraphs of `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` and `docs/10-release/legacy/P10-RELEASE-UAT.md`, and `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Deployment, operasi dan maintenance (all LEGACY — SUPERSEDED in RF-6).
