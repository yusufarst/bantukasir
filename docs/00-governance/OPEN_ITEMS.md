# Open items

Everything that is undecided, unverified or waiting. This document replaces the legacy `GAPS-CHANGES.md`. When an item is resolved, record the decision in [DECISIONS](DECISIONS.md) and change the item's status here; do not delete the row.

## 1. Owner questions

Numbers 1 to 19 are those of the re-foundation analysis report (section 39), kept so that references stay stable. Number 20 was added afterwards.

| No. | Question | Status | Needed before |
| --- | --- | --- | --- |
| 1 | Depth of re-foundation | ANSWERED — D-019 | — |
| 2 | Language of repository documents | ANSWERED — D-020 | — |
| 3 | Who applies the changes | ANSWERED — D-021 | — |
| 4 | Housekeeping (patch, stash, `upstream` remote, visibility, push and `main`, executor effort) | ANSWERED — D-022, D-023, OF-01 | — |
| 5 | **English in the interface.** (A) Translation keys from the first Task, Indonesian and English both shipped and tested in V1 — what the framework requires. (B) Translation keys from the first Task, Indonesian only in V1, English later — deviation FD-02. (C) No localization architecture — not recommended. | DEFERRED by the Owner (D-020) | RF-3 |
| 6 | **Sign-in.** Confirm: email and password only, no Google sign-in; accounts created by the Owner; the Owner issues one-time reset links to staff; the Owner's own recovery is an operator command on the server; password rule is the framework's 8-character profile. | OPEN | RF-3 |
| 7 | **Server.** Provider and size of the VPS (CPU, memory, disk)? Anything else running on it? May Coolify be installed, and may a staging copy run on the same machine? Is the domain behind Cloudflare? | OPEN | RF-3 |
| 8 | **Roles per person.** Will one person ever need to both sell and manage stock? (A) Never: one role per account, as now. (B) Yes: an account may hold more than one of the three roles. | OPEN | RF-2 |
| 9 | **Purchase cost and the Operations role.** (A) Keep write-only. (B) Operations can see and correct cost on the receiving records they handle, but never profit, margin or cost reports — planner's recommendation. (C) Operations never enters cost; the Owner completes it afterwards. | OPEN | RF-2 |
| 10 | **Correcting a cost later.** (A) The system recalculates and keeps every earlier version of the affected profit figures, as now. (B) The system recalculates, corrected figures replace the old ones, and an audit entry records who changed which cost and when — planner's recommendation. | OPEN | RF-2 |
| 11 | **Refunds and returns.** Who handles a customer return at the counter, and from which money? (A) Only the Owner, from the Owner's own funds, never from the drawer, as now. (B) The cashier, from the drawer, with the Owner's approval on the spot. (C) Only the Owner, but paid from the drawer. If B or C, cash paid out must enter the session's expected cash. | OPEN | RF-2 |
| 12 | **Payment and price at the till.** (a) Is static QRIS accepted, and should it appear as its own method, confirmed by the cashier, with no gateway? (b) Confirm there are no discounts and no price changes at the till in V1. | OPEN | RF-2 |
| 13 | **Catalogue.** About how many products at launch? Do they exist in a spreadsheet? If more than a few hundred, the planner recommends moving CSV product import into V1. Photos: (A) no product photos in V1 and the design stops using photo cards — planner's recommendation; (B) add photo upload to V1. | OPEN | RF-2 |
| 14 | **One shop or many.** Is V1 for one known shop, or a product deployed separately for several client shops? Is LATANSA the client, and should its logo remain in this repository? | OPEN | RF-2 |
| 15 | **The counter.** Cashier device (computer with USB scanner, tablet, phone)? Shared between staff? Receipt printer and paper width? Number of cash drawers? Usual opening float? Does the shop charge PPN or need tax details on the receipt? | OPEN | RF-2 |
| 16 | P7 path | ANSWERED — D-024 | — |
| 17 | Form of design approval | ANSWERED — D-025 | — |
| 18 | **Backup destination.** An independent copy outside the VPS is required. Is a small monthly cost acceptable if free options do not fit, and who holds the recovery key? | OPEN | RF-3 |
| 19 | **Confirming a sale.** (A) Keep two steps and the mandatory checkbox on every sale. (B) One payment screen showing total, method and change, with one confirm button; duplicate-charge protection stays in the system but is not shown as a step — planner's recommendation. Corrections, refunds, receiving and session close keep their review step. | OPEN | RF-2 |
| 20 | **Who may operate on production.** AICWDF-4.3 §29 says coding agents never connect to or change a production database, and an approved migration is applied by a controlled executor, not by an agent session. The earlier BantuKasir rule let an agent carry out a production release, migration or cutover once the Owner authorized that exact target, revision and operation. (A) Adopt the framework rule: agents prepare and rehearse; production changes are applied through a controlled release procedure — planner's recommendation. (B) Keep the earlier rule — a framework deviation that needs the Owner's explicit approval (FD-09). | OPEN | RF-6 |

## 2. Revalidation items (`OWNER_REVALIDATION_REQUIRED`)

Older decisions the Owner has been asked to re-confirm. Until answered, the older decision stays in force.

| ID | Existing decision | Why it needs re-confirmation | Question | Status |
| --- | --- | --- | --- | --- |
| R-01 | Exactly three roles; unclear whether one person may hold two (D-002) | In a small shop one person may sell and receive goods | 8 | OPEN |
| R-02 | Authentication details left open: password rule, Google sign-in, delivery of reset links | The framework requires one recorded authentication profile | 6 | OPEN |
| R-03 | Operations may enter a purchase cost but never see it again (D-007) | Small secrecy gain, large privacy engineering, cost retyped after any form error | 9 | OPEN |
| R-04 | Refunds and returns are Owner-only and paid from the Owner's separate funds, never the drawer (D-008, D-009) | The shop cannot serve a return when the Owner is absent; drawer refunds would change expected cash | 11 | OPEN |
| R-05 | Products entered by hand; opening stock in batches of at most 100; no import (D-002) | The legacy benchmark assumes 5,000 products | 13 | OPEN |
| R-06 | Cash or bank transfer only; no discount or price change at the till (D-002, D-003) | Static QRIS is common and operationally a transfer; "no discount" should be deliberate | 12 | OPEN |
| R-07 | Every posting action, including an ordinary sale, passes draft, review and explicit confirm (legacy P3) | Adds a second dialog and a mandatory checkbox to the most frequent task | 19 | OPEN |
| R-08 | Example product photos in the design (D-018) while photo upload is out of scope | The design promises what V1 cannot deliver | 13 | OPEN |
| R-09 | Unknown cost allowed; later cost entry replays history and publishes a versioned valuation keeping every earlier version (D-008) | The most intricate component in the plan; whether every past version is needed is a business choice | 10 | OPEN |
| R-10 | Client name, logo, receipt header and accent are runtime settings with immutable revisions (D-010) | Needed for several shops; more than one known shop needs | 14 | OPEN |
| R-11 | P7 completes only on approval of one exact design revision (D-017) | One all-or-nothing approval stalled the plan | 17 | RESOLVED — D-025 |
| R-12 | Hosting by hand-built Compose, Nginx and TLS renewal on an unnamed VPS; no staging (D-005) | Differs from the framework default; the server is unknown | 7 | OPEN |
| R-13 | Indonesian-only interface (implicit in the legacy design documents) | The framework requires English as well | 5 | DEFERRED by the Owner |
| R-14 | Sessions end after 30 minutes of inactivity and 12 hours absolute; re-authentication within 15 minutes for sensitive Owner actions (D-013) | A cashier idle on a quiet afternoon would be signed out with a drawer open | 15 | OPEN |
| R-15 | Repository documents in dense mixed Indonesian and English | Hard to read and imprecise for an executor | 2 | RESOLVED — D-020 |
| R-16 | An agent may carry out a production release, migration or cutover after the Owner's exact authorization (legacy operations and release documents; former Build Unit BU-RELEASE-002) | Conflicts with the AICWDF-4.3 zero-touch rule (§29); decides who operates production releases. Neither rule is applied as new policy until the Owner answers; see [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md) section 5 | 20 | OPEN |

## 3. Business and external gates carried over

From the legacy gap register. These are facts or approvals that only the Owner, the client or an operator can supply. None is satisfied.

| Gate | What is needed | Blocks | Related |
| --- | --- | --- | --- |
| G1 | Commercial receipt policy, tax and legal position, buyer identity on documents | Acceptance of client documents; pilot | Question 15 |
| G2 | Base units and precision, catalogue size, opening stock volume, barcodes, availability of cost evidence, need for serial numbers | Real master data and opening stock; cutover | Questions 13, 9, 10 |
| G3 | Cash drawer labels, opening float practice, single responsibility, handover, source of refund money | Physical cash and refund workflow; pilot | Questions 11, 15 |
| G4 | Independent backup destination, recovery key and access, real cost, measured recovery point and recovery time accepted by the Owner (6 hours and 4 hours are proposals only) | Final recovery acceptance; pilot | Question 18 |
| DEVICES | Real USB and Bluetooth scanners, label, thermal and A4 printers, drivers and media | Final device acceptance | Question 15 |
| TARGET | The actual server, network, non-production access, TLS, capacity | Representative benchmarks; operations evidence | Question 7 |
| EXECUTOR | Availability of the named models in the actual harness | Start of each executor instruction | D-023 |
| RELEASE | Explicit authorization of an exact target, revision and operation | Any deployment | Not authorized |
| TESTS | All acceptance suites on the current revision | Product acceptance | Nothing is built; nothing has been run |

Design gates. The legacy gate "owner visual approval of one exact revision" no longer exists. P7 has three Owner gates (D-025), all `NOT STARTED`:

| Gate | Subject |
| --- | --- |
| P7-G1 | Navigation and page structure |
| P7-G2 | Terminology and copy |
| P7-G3 | Visual direction and key screens |

The validation gaps recorded against the former candidate (browser zoom at 200 %, screen reader, on-screen keyboard, long content, full rendered contrast audit) become validation requirements for the rebuilt design. Rights to the design tool's template foundation and fonts were never verified; the rebuilt design system must rest on assets whose licence is recorded.

## 4. New items raised during the re-foundation

| ID | Item | Recommendation | Status | Needed before |
| --- | --- | --- | --- | --- |
| N-01 | **Repository visibility.** The GitHub repository is public (OF-01). The re-founded documents will include the threat model, backup design and deployment topology. | Decide whether the repository stays public; the planner recommends making it private before the next push. | OPEN — Owner | The first push after RF-1 |
| N-02 | **Off-machine safety copy.** Seven commits and the stash exist only on the Owner's computer. | Verified Git bundle before retired trees are removed. | RESOLVED — D-027 (executed in RF-1 Part B) | — |
| N-03 | **Wording in the Claude Project instructions.** They say the executor runs "at high reasoning"; D-023 says xhigh. | The Owner may update that line; agents cannot. | OPEN — Owner | When convenient |
| N-04 | **The AICWDF-4.3 text is not in this repository.** It is held in the Owner's Claude Project. The binding subset is written into this folder. | Decide whether to add the framework file to the repository, bearing N-01 in mind. | OPEN — Owner | RF-7 at the latest |
| N-05 | **Disposition of the preserved patch and stash.** Both are kept under D-022. The stash holds unique content: three of its six files differ from the copies in the ignored `.local/ui03` folder. | Reconsider only after RF-1 is validated and accepted. | OPEN — Owner | After RF-1 |

## 5. Technical items (planner or executor to resolve; no Owner decision needed)

| ID | Item | Step |
| --- | --- | --- |
| T-01 | Some tracked files have CRLF in the working tree and LF in the index; there is no `.gitattributes`. Decide and apply a line-ending policy without mixing it into content changes. | Foundation Task |
| T-02 | `.env.example` and `.gitignore` are left over from an earlier Next.js, Better Auth and Web Push stack. Rebuild for Laravel. | Foundation Task |
| T-03 | No logical database schema exists (tables, keys, unique and check constraints, append-only tables). | RF-3 |
| T-04 | Command injection is not named in the legacy threat table. Add it, with attention to backup and scheduled-command paths. | RF-3 |
| T-05 | No route contracts, interaction contracts, page inventory or navigation contracts exist. | RF-4, RF-5 |
| T-06 | No glossary and no per-entity lifecycle matrix (create, edit, archive, restore, delete, anonymize) exist. | RF-2 |
| T-07 | The legacy requirement register has 73 entries, 39 of which repeat the text of the 39 acceptance suites. Rebuild as real requirement statements. | RF-2 |
| T-08 | The design project used for the former candidate contains eight unrelated CRM template pages that travel inside every export. | RF-4 |
| T-09 | The legacy performance benchmark dataset (5,000 products) conflicts with manual-only product entry; settle with question 13. | RF-2 |
| T-10 | Branch and environment model, CI, staging and the release integrity report are undefined. | RF-6 |
| T-11 | The ignored folder `.agents/` holds a skill for a retired tool (`impeccable`), and `.local/` holds design authoring helpers. Neither is tracked or part of the workflow. | Housekeeping after RF-1 |
| T-12 | The empty directory `docs/planning/` may remain on disk after RF-1 Part A; Git does not track empty directories. Remove it in RF-1 Part B. | RF-1 Part B |
| T-13 | `assets/brand/README.md` predates the re-foundation and is not an authoritative document. RF-1 updated only its two links to documents that RF-1 moved, so that they point at the `legacy/` locations (D-028); nothing else in it was changed. It still describes branding that is under review and mentions a Build Unit that no longer exists. Resolve its content together with question 14. | With question 14 |
| T-14 | The Claude desktop app may create a folder `Claude outputs/` in the repository root that holds copies of chat deliverables (seen on 3 October 2026 with a copy of the RF-1 Part B instruction). It is untracked, is not project content and is never staged. Decide whether to delete it, keep such copies outside the repository folder, or ignore the folder in `.gitignore`. | Housekeeping after RF-1 |
| T-15 | The local repository has one ref outside branches, remotes and the stash: `refs/codex/turn-diffs/checkpoints/…`. It points at a tree object, not a commit, and was left by an earlier Codex session. It is local tool state, was never pushed and is not part of the project history. RF-1 keeps it; it is included in the safety bundle. Decide whether to delete it. | Housekeeping after RF-1 |
