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
| Server (VPS) | Client | Client-funded | Not yet identified — OPEN_ITEMS question 7, gate TARGET |
| Application stack (Laravel, PostgreSQL, Nginx, open-source packages) | — | 0 | Planned |
| Authentication, search, PDF and print, CSV export, barcode generation | — | 0 (native or open-source, self-hosted) | Planned |
| Email delivery | — | 0 if no mail service is used | Open — question 6, deviation FD-04 |
| Independent backup destination outside the server | To be decided | May be greater than 0 | Open — question 18, gate G4. Requires Owner approval if paid. |
| Uptime check from outside the server | To be decided | Expected 0 | To be designed in RF-6 |

## 4. Development-time costs (not production dependencies)

| Item | Note |
| --- | --- |
| DesainPakeAI design tool | Used for P7 design work. Its plan, credits and entitlements have not been verified. Anything that may consume paid credit needs the Owner's authorization. |
| AI planning and execution agents | Chosen by the Owner (D-031). Not a runtime dependency of the product. |

## 5. Paid-service exception registry

| Service | Purpose | Recurring cost | Approved by | Decision |
| --- | --- | --- | --- | --- |
| — | — | — | — | — |

There are no approved exceptions.
