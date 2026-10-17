# Architecture decision records

| | |
| --- | --- |
| Status | CURRENT — written in RF-3A under D-055, 4 October 2026 (Asia/Jakarta); index extended in RF-3B under D-059, 5 October 2026, in RF-5 under D-087 and in RF-6 under D-088, 18 October 2026 |
| Phase | P0 policy and P4 index |
| Framework | AICWDF-4.3 §4 (deviation record), §11 (ADR policy in P0), §15 (ADRs for major changes) |
| Authority | Rank 4 in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md) section 1, below the decisions in [DECISIONS](../00-governance/DECISIONS.md) and the governance documents |

An architecture decision record (ADR) explains one architectural decision: the context, the decision, the alternatives, the consequences and how it relates to AICWDF-4.3. It never creates business meaning. The decision itself is recorded in DECISIONS; the ADR records the architectural reasoning and the limits an executor must respect.

## 1. When an ADR is required

An ADR is written, in the same piece of work, when any of the following happens:

1. a material deviation from the AICWDF-4.3 default stack or architecture is proposed (AICWDF §4: ADR required, Owner approval required);
2. a decision fixes the stack, the runtime infrastructure, the hosting topology, the authentication implementation, the localization mechanism, the representation of exact numbers, the identity of commands, or another choice that later Tasks must not redesign (AICWDF §4A.1, §15);
3. an accepted ADR has to change. The change is a new ADR that supersedes the old one; an accepted ADR is not rewritten in substance.

Ordinary technical choices inside a recorded decision (an index, a helper class, a test layout) need no ADR (PROJECT_RULES section 3).

## 2. Required fields

Every ADR has these fields, in this order:

```text
ID:                ADR-NNN
TITLE:
STATUS:            PROPOSED / ACCEPTED / SUPERSEDED by ADR-NNN / WITHDRAWN
DATE:
DECISION SOURCE:   the D-… numbers that make the decision binding, and who decided (Owner or Planner)
CONTEXT:
DECISION:
ALTERNATIVES CONSIDERED:
CONSEQUENCES:
FRAMEWORK CONFORMANCE (AICWDF §4 form):
  ADR REQUIRED:        YES
  DEVIATION:           NONE / the FD-… number
  OWNER APPROVAL:      REQUIRED / NOT REQUIRED, with the decision that gives it
  REASON:
  RISK:
  MIGRATION IMPACT:
  ROLLBACK IMPACT:
VERIFIED SOURCES:  for every version-sensitive fact: source URL, date checked, fact used
RELATED:           CURRENT documents, open items, superseded legacy content
```

Rules for the fields:

- **No version pins.** Versions are pinned, with lockfiles, by the foundation Task (D-004, [TOOLCHAIN](../00-governance/TOOLCHAIN.md)). A version number found in official documentation is recorded under VERIFIED SOURCES as an observed fact with its date, never as a pin.
- **No real identities.** The repository is public (OF-01, OPEN_ITEMS N-01). An ADR never names a real IP address, domain, provider account, credential, backup key or backup location.
- **Business meaning.** If writing an ADR shows that a business rule is needed that no decision gives, the work stops and the Owner is asked (PROJECT_RULES section 3).

## 3. Statuses

| Status | Meaning |
| --- | --- |
| PROPOSED | Written but not yet in force. The work it governs waits. |
| ACCEPTED | In force. The decision it records is an Owner decision, or a planner decision under PROJECT_RULES section 3, recorded in DECISIONS. An ADR written in a re-foundation step forms part of that step's checkpoint, which the Owner accepts or rejects. |
| SUPERSEDED | Replaced by the named later ADR. Kept unchanged for history. |
| WITHDRAWN | Abandoned before acceptance, or no longer applicable without a replacement. Kept unchanged for history. |

## 4. Numbering

- Identifiers are `ADR-NNN`, three digits, permanent and never reused.
- The file name is `ADR-NNN-short-title.md` in this folder.
- The next free identifier is **ADR-012**. ADR-009 was written in RF-5 under D-087; ADR-010 and ADR-011 were written in RF-6 under D-088; no further ADR is authorized until a later step is authorized.

## 5. Index

| ID | Title | Status | Decision source |
| --- | --- | --- | --- |
| [ADR-001](ADR-001-stack-and-modular-monolith.md) | Stack and modular monolith | ACCEPTED | D-004 (Planner); D-019 |
| [ADR-002](ADR-002-minimum-runtime-infrastructure.md) | Minimum runtime infrastructure | ACCEPTED | D-005 (Planner), within D-053 |
| [ADR-003](ADR-003-hosting-topology.md) | Hosting topology | ACCEPTED | D-053 (Owner) |
| [ADR-004](ADR-004-authentication.md) | Authentication | ACCEPTED | D-048, D-049 (Owner); D-013 |
| [ADR-005](ADR-005-localization-architecture.md) | Localization architecture | ACCEPTED | D-050 (Owner); D-020 |
| [ADR-006](ADR-006-exact-numbers.md) | Exact numbers | ACCEPTED | D-006 (Planner); AGENTS rule 7 |
| [ADR-007](ADR-007-command-identity-and-idempotency.md) | Command identity and idempotency | ACCEPTED | D-006 (Planner), D-040 (Owner) |
| [ADR-008](ADR-008-cost-allocation-representation.md) | Issue-cost allocation representation | ACCEPTED | D-060, D-061 (Owner); D-034, D-035 |
| [ADR-009](ADR-009-command-receipts-and-reconciled-outcomes.md) | Command request receipts and reconciled outcomes | ACCEPTED | D-073, D-074 (Owner); D-087; section 6 decided by D-088 |
| [ADR-010](ADR-010-independent-backup-and-recovery-key-custody.md) | Independent backup and recovery-key custody | ACCEPTED | D-088 (Owner, question 18); D-005 |
| [ADR-011](ADR-011-release-pipeline-environments-and-production-operation.md) | Release pipeline, environments and production operation | ACCEPTED | D-088 (Owner, question 20); D-053 |

ADR-001 to ADR-007 were written in RF-3A (D-055). They record decisions that were already in force or that the Owner took before RF-3A was authorized. ADR-008 was written in RF-3B (D-059) and records the representation that follows from the Owner's decisions D-060 and D-061. ADR-009 was written in RF-5 (D-087); it extends ADR-007 with the mechanisms that the Owner's decisions D-073 and D-074 required, and left a new server refusal to the Owner, who decided it in D-088 (a close only). ADR-010 and ADR-011 were written in RF-6 (D-088); they record the Owner's answers to questions 18 and 20 and the backup method, release pipeline, certificate method and monitoring chosen under them. None adds business meaning.
