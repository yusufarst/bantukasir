# Framework deviations

Departures from AICWDF-4.3, in the form required by [PROJECT_RULES](PROJECT_RULES.md) section 1. A framework requirement that is not listed here applies.

Status values: `APPROVED` (Owner approved), `RECORDED` (no Owner approval required), `PROPOSED` (awaiting an Owner answer; the framework rule applies until then), `WITHDRAWN`.

| ID | Subject | Status |
| --- | --- | --- |
| FD-01 | Restructure the repository instead of preserving its layout | APPROVED — D-019 |
| FD-02 | English interface strings deferred beyond V1 | APPROVED — D-050 |
| FD-03 | Google sign-in off | APPROVED — D-049 |
| FD-04 | No email-based password reset or verification | APPROVED — D-049 |
| FD-05 | Point-of-sale screen designed for PC and tablet first | APPROVED — D-039 (product and design decision; not device acceptance) |
| FD-06 | Hosting other than the framework default | WITHDRAWN — D-053 adopted the framework topology |
| FD-07 | Dedicated final-verification Tasks kept | RECORDED |
| FD-08 | Build Unit identifiers replaced by `TASK-NNN` | RECORDED |

Question numbers refer to [OPEN_ITEMS](OPEN_ITEMS.md).

## FD-01

```text
FRAMEWORK DEVIATION: Restructure the repository instead of preserving its layout
AICWDF RULE: §1, §1.1, §1.2, §33 — reuse existing structure; do not reorganize a mature
  repository cosmetically; no documentation reset unless the owner requests it
BANTUKASIR REASON: No code exists; five superseded planning baselines coexisted with the live
  one; two parallel document families covered the same subjects; the Owner asked for
  re-foundation
BENEFIT: One authoritative document per subject; a much smaller executor read path
RISK: Old identifiers lose their original files; mitigated by LEGACY_INDEX and Git history
OWNER APPROVAL REQUIRED: YES — approved, D-019
```

## FD-02

```text
FRAMEWORK DEVIATION: English interface strings deferred beyond V1
AICWDF RULE: §4B.2, §18.11, §41 — English mandatory as second language with a switcher
BANTUKASIR REASON: One Indonesian shop with Indonesian-speaking staff
BENEFIT: No translation or second-language layout testing on every interface Task
RISK: Later retrofit; mitigated by building translation keys and a bilingual glossary from
  the first Task
OWNER APPROVAL REQUIRED: YES — approved, D-050: V1 Indonesian only, translation keys from the
  first Task, no language switcher; English later as a separate scope decision; mechanism in
  ADR-005
```

## FD-03

```text
FRAMEWORK DEVIATION: Google sign-in off
AICWDF RULE: §4A.3 — on by default for ordinary internet-connected web applications; may be
  skipped for a closed internal system where it adds complication without benefit
BANTUKASIR REASON: Invite-only staff accounts on a shared counter device; staff may have no
  Google account, or would leave a personal Google session signed in on the shop computer;
  the till should not depend on an external identity service
BENEFIT: Simpler authentication; no Google Cloud credentials or account-linking rules
RISK: Loss of a convenience for the Owner; can be added later behind the authentication
  boundary
OWNER APPROVAL REQUIRED: YES — approved, D-049
```

## FD-04

```text
FRAMEWORK DEVIATION: No email-based password reset or verification
AICWDF RULE: §4A.2 — password reset and email verification in the default core
BANTUKASIR REASON: Zero recurring cost means no mail service; accounts are created by the
  Owner; the Owner issues one-time reset links to staff; the Owner's own recovery is an
  operator command on the server
BENEFIT: No mail dependency or cost
RISK: If the Owner is locked out, recovery needs server access; the procedure must be
  documented and rehearsed
OWNER APPROVAL REQUIRED: YES — approved, D-049; the Owner recovery procedure is designed in
  SECURITY_MODEL section 14 and rehearsed before the pilot
```

## FD-05

```text
FRAMEWORK DEVIATION: Point-of-sale screen designed for PC and tablet first
AICWDF RULE: §18, §27 — mobile-first
BANTUKASIR REASON: The first client's cashiers will use a PC and a tablet in production (OF-02).
  The PC is used with a physical keyboard and a USB HID barcode scanner; the tablet runs the
  full primary cashier workflow with touch and Bluetooth HID scanners (D-039)
BENEFIT: Fastest path for the most frequent task on the devices the shop actually uses
RISK: The phone is not a primary cashier device. Owner and Operations screens follow the
  framework rule. This approval is a product and design decision only: real PC, tablet,
  scanner, printer and browser compatibility must still pass the DEVICES gate
OWNER APPROVAL REQUIRED: YES — approved by D-039
```

## FD-06

```text
FRAMEWORK DEVIATION: Hosting other than the framework default
AICWDF RULE: §10, §20, §32 — Cloudflare, Hostinger VPS, Coolify; develop → staging → main
BANTUKASIR REASON: The legacy operations documents assume a generic VPS with hand-built
  Compose, Nginx and TLS renewal, and define no staging environment
BENEFIT: None by default; the planner proposes adopting the framework topology unless the
  server cannot support it
RISK: Hand-built TLS and deployment are extra work and extra failure points
OWNER APPROVAL REQUIRED: YES if deviating — not needed: the Owner adopted the framework topology
  (D-053, ADR-003); this deviation is WITHDRAWN
```

## FD-07

```text
FRAMEWORK DEVIATION: Dedicated final-verification Tasks kept
AICWDF RULE: §19 — testing is inside each Task
BANTUKASIR REASON: Restore drills, real scanners and printers, and server benchmarks cannot
  be done inside a feature Task
BENEFIT: Honest evidence for physical and operational proof; matches §28 layer 3
RISK: None, provided feature Tasks still carry their own tests
OWNER APPROVAL REQUIRED: NO
```

## FD-08

```text
FRAMEWORK DEVIATION: Build Unit identifiers replaced by TASK-NNN
AICWDF RULE: §22.4 — Task IDs are permanent
BANTUKASIR REASON: No Build Unit was ever started; the units are to be re-cut as vertical
  slices in P11
BENEFIT: Framework-standard identifiers and checklist from the first executed Task
RISK: Lost references; mitigated by a Build Unit to Task mapping table in P11
OWNER APPROVAL REQUIRED: NO
```

## Conformance actions (not deviations)

Changes that bring the project into line with the framework. They are planning actions for the step shown and are not yet applied to the legacy documents.

| Action | Framework rule | Step |
| --- | --- | --- |
| Adopt password profile AICWDF-COMPAT-8 in place of the 12-character minimum shown in the former design candidate, unless an external binding requirement applies | §4A.5 | Applied in RF-3A (D-049; SECURITY_MODEL section 2, ADR-004) |
| Zero-touch production rule: coding agents never connect to or run anything against production; a controlled executor applies approved migrations | §29, §30 | **Not applied — pending Owner decision** (question 20, R-16; see [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md) section 5). It changes who operates a production release, so it is not a planner decision. If the Owner keeps the earlier rule instead, that is a deviation needing explicit approval (§41) and is recorded here as FD-09. |
| Use the framework Task statuses in place of the legacy status vocabularies | §19 | RF-6, RF-7 |
| Defer Graphify until code exists | §6.4 | Recorded in [TOOLCHAIN](TOOLCHAIN.md) |
| Add Context7 as the current-documentation capability | §5.1 | Recorded in TOOLCHAIN |
