# Cost policy

## 1. Rule

Domain and server are funded by the client. The target for any **additional recurring production cost is zero**.

Choose technology in this order:

1. what the project already has;
2. a native capability of the framework or platform;
3. a free open-source package;
4. a self-hosted open-source capability on the existing server;
5. a reliable free tier of an external service;
6. a paid external service — only with the Owner's explicit approval.

A zero-cost target never justifies weaker security, weaker data integrity or skipping an independent backup.

## 2. If a paid service is proposed

Stop that decision. Explain the requirement, show the free, open-source and self-hosted alternatives and their trade-offs, estimate the recurring cost, and ask the Owner. Record the outcome in [DECISIONS](DECISIONS.md) and in section 5 below.

No paid AI service may become a production dependency unless it is a real product requirement and the Owner approves it. There is none.

For any free tier the product depends on, record its limits, behaviour at the quota, lock-in, exit path and data portability.

## 3. Production cost inventory

Nothing is deployed. The inventory lists what production is expected to need.

| Item | Funded by | Recurring cost | Status |
| --- | --- | --- | --- |
| Domain | Client | Client-funded | Not yet identified |
| Server (VPS) | Client | Client-funded | Topology decided (D-053, ADR-003); provider and size not yet identified — gate TARGET |
| Cloudflare (DNS and proxy) | — | Expected 0 (D-053) | Plan for the actual domain to be verified at gate TARGET; a paid plan needs the Owner's approval |
| Coolify (self-hosted, open source) on the same server | — | 0 | Planned (D-053, ADR-003) |
| Staging environment on the same server | — | 0 additional (D-053) | Planned; uses the production server's resources |
| Application stack (Laravel, PostgreSQL, Nginx, open-source packages) | — | 0 | Planned |
| Authentication, search, PDF and print, CSV export, barcode generation | — | 0 (native or open-source, self-hosted) | Planned |
| Email delivery | — | 0 — the application sends no email | Decided — D-049, FD-04 approved |
| Independent backup destination outside the server | — | 0 — a free allowance of 10 GB with a spending cap at Rp0, so that it never bills (D-088, question 18; ADR-010) | Decided; the account is opened at gate G4. A paid plan needs the Owner's separate approval; above 80 % of the allowance the retention is reduced first |
| Uptime check from outside the server | — | 0 — a heartbeat monitor on a free plan (ADR-011 item 11) | Decided; the checks are created at gate TARGET |
| CI | — | 0 — GitHub Actions, free for a public repository on standard runners (ADR-011 item 4) | Planned |
| Container registry for the release image | — | 0 — GitHub Container Registry, public package (ADR-011 item 5) | Planned |
| TLS certificate | — | 0 — Cloudflare Origin CA certificate (ADR-011 item 8) | Planned |

Free-tier record (AICWDF §4C.4): the limits, the behaviour at quota, the lock-in, the exit path and the data portability of Cloudflare's free plan, the backup store, the heartbeat monitor, GitHub Actions and the container registry are [ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 15, checked on 18 October 2026 and again at gate TARGET and yearly.

## 4. Development-time costs (not production dependencies)

| Item | Note |
| --- | --- |
| DesainPakeAI design tool | No longer required for P7 design work (D-085; [TOOLCHAIN](TOOLCHAIN.md) section 5). Its plan, credits and entitlements were never verified. Any further use, and anything that may consume paid credit, needs the Owner's authorization. |
| AI planning and execution agents | Chosen by the Owner (D-031). Not a runtime dependency of the product. |

## 5. Paid-service exception registry

| Service | Purpose | Recurring cost | Approved by | Decision |
| --- | --- | --- | --- | --- |
| — | — | — | — | — |

There are no approved exceptions. The backup store and the heartbeat monitor are free plans, not exceptions; a cost beyond their free terms needs an entry here first (D-088).
