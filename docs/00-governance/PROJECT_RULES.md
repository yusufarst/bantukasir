# Project rules

Governance rules for BantuKasir. [AGENTS.md](../../AGENTS.md) is the short contract every agent reads first; this document gives the detail behind it. It replaces the legacy `P0-GOVERNANCE.md`.

## 1. Framework

BantuKasir follows AICWDF-4.3 (AI-First Complex Web Application Delivery Framework, version 4.3), adopted by the Owner in D-019. The framework is an operating method, not a feature list: nothing is added to the product merely because the framework mentions it.

Where a project-specific departure is better, it is recorded in [FRAMEWORK_DEVIATIONS](FRAMEWORK_DEVIATIONS.md) in this form, and a framework requirement is never dropped silently:

```text
FRAMEWORK DEVIATION:
AICWDF RULE:
BANTUKASIR REASON:
BENEFIT:
RISK:
OWNER APPROVAL REQUIRED: YES/NO
```

## 2. Optimization order

When goals compete, prefer, in this order: data integrity; maintainability; security; correctness; clarity and traceability; operational simplicity; user-friendliness; executor efficiency; performance; developer convenience; architectural novelty.

## 3. Who decides what

| Class | Who decides | Examples | Record |
| --- | --- | --- | --- |
| Owner decision | The Owner. Work that depends on it stops until answered. | Business meaning; money and payment behaviour; stock behaviour; roles and authorization; legal and tax behaviour; destructive data behaviour; major V1 scope; recurring production cost; major user workflow; accepted risk; release and cutover risk. | [DECISIONS](DECISIONS.md) |
| Owner revalidation | The Owner, on a precise question from the planner. | An older decision that conflicts with AICWDF-4.3 or newer Owner intent, looks stale, causes poor usability or needless complexity, or blocks a cleaner design. Marked `OWNER_REVALIDATION_REQUIRED`. | [OPEN_ITEMS](OPEN_ITEMS.md), then DECISIONS |
| Planner or executor decision | The agent, without asking. | Ordinary technical and planning choices for which the framework and project constraints give enough information: module structure, indexes, validation, test design, document structure, sequencing. | DECISIONS when the choice has lasting effect; an ADR when it is architectural |

A supporting capability may be added by the planner only when it is concretely needed for correctness, security, integrity or real operation, and the simpler existing scope cannot provide it. The record states the problem prevented, the simpler alternative and why it fails, and the cost. A change of business policy is never presented as a technical detail.

## 4. Phases and steps

Planning follows the framework phases P0 to P11:

| Phase | Subject |
| --- | --- |
| P0 | Governance, foundation and agent continuity |
| P1 | Product definition, scope and acceptance |
| P2 | Domain model and business rules |
| P3 | Critical workflows, routes and interactions |
| P4 | Database and application architecture |
| P5 | Security, authentication and authorization |
| P6 | Concurrency, idempotency, API and performance |
| P7 | UX, information architecture, design system, navigation and localization |
| P8 | Testing, quality and Definition of Done |
| P9 | Infrastructure, observability, backup and recovery |
| P10 | Release, migration, cutover, rollback and UAT |
| P11 | Task planning, dependencies and execution specifications |

The re-foundation is carried out in steps RF-0 to RF-7, described in [REFOUNDATION_PLAN](REFOUNDATION_PLAN.md).

Rules:

1. Every step and every phase needs the Owner's explicit authorization before it starts. Finishing one never authorizes the next.
2. Before substantial work, establish: the Git baseline; the planning state; the latest Owner decision; open Owner decisions; current gaps and accepted risks; the design status; the Task status; the exact next authorized action; what is not authorized.
3. A phase is not complete because documents were drafted or a screen renders. Completion needs self-review, validation, adversarial review where the risk warrants it, Owner approval where required, a checkpoint commit, and an updated handoff.

## 5. Four things that are not the same

| Statement | What it means | What it does not mean |
| --- | --- | --- |
| A specification is reviewed or frozen | The contract was reviewed and its open points are listed. | That anything runs. |
| The Owner approved something | There is a recorded Owner decision on that exact subject. | That an agent's review can stand in for it. |
| A check passed | A real command or test ran on a named revision and target, and the result is recorded. | That later revisions pass. |
| A release is authorized | The Owner authorized that exact target, revision and operation. | That a passing test, a commit or a planning approval implies it. |

Facts about the real business, devices, backup destination and server are `VERIFIED` only with actual evidence. Missing facts are recorded as open items, never filled with assumptions.

## 6. Change control

1. Identify the change and classify who decides (section 3).
2. Analyse the effect on requirements, documents, Tasks, tests and operations.
3. Obtain the decision and record it.
4. Revise the authoritative document. Do not leave the old and new versions both claiming authority.
5. Review and validate.
6. Update Tasks, acceptance, dependencies and the handoff.

Evidence that a change invalidates is marked for re-run. Old evidence is never edited to look current, and history is never rewritten as retroactive approval.

## 7. Task model (from P11 onward)

Execution uses explicit bounded Tasks, defined in P11 after P0 to P10 are coherent. Statuses are `TODO`, `READY`, `IN_PROGRESS`, `BLOCKED`, `VERIFYING`, `DONE`, `SUPERSEDED`. A Task is not `READY` because it exists, and not `DONE` because code compiles or a page renders. One executor works on one Task at a time. Task identifiers are permanent. No Task baseline exists yet.

## 8. Working loop between planner and executor

1. The planner reconstructs the state from the repository.
2. The planner prepares one bounded instruction.
3. The Owner sends it to the executor.
4. The executor does only that, validates, and reports exact evidence.
5. The Owner returns the report to the planner.
6. The planner checks the report against the repository.
7. The planner requests remediation or prepares the next instruction for the Owner to authorize.

Decision D-021 additionally allows the planner to write planning and re-foundation documents directly into the working tree, with no commits and no other Git mutations; the executor on Windows validates independently and creates the checkpoint commit.

## 9. Git safety

- Never rewrite published history. Never use `git push --force` or `git push --force-with-lease`.
- Never repair unexpected divergence automatically. Stop and report.
- Before any checkpoint or publication, inspect the branch, HEAD, remote baseline, divergence, working tree, index and staged diff.
- Stage exact, intended paths. Never `git add -A`. Do not stage ignored tooling folders (`.local/`, `.agents/`).
- Do not use reset, clean or restore to tidy up, and do not overwrite unrelated work.
- Use only the Owner's configured Git author and committer identity. Never add AI, model or tool attribution to a commit. Do not rewrite old commits to remove past attribution.
- No push, merge, pull request or deployment without explicit authorization for that exact action.
- The branch model for implementation (feature branches, staging, protected `main`) is defined when P9 and P10 are rebuilt. Today `main` holds an obsolete baseline and must not be modified (D-022).

## 10. Windows and line endings

The Owner works on Windows. Some tracked files have CRLF in the working tree and LF in the index. Before treating a tracked file as changed, determine whether the difference is only line endings. Do not rewrite files solely to normalize them. See open item T-01.

## 11. Language

Agent-facing repository documents are in English (D-020). Technical identifiers are in English. The user interface is Indonesian first; whether English ships in V1 is open. Documents under `legacy/` remain in their original Indonesian until they are rebuilt.

## 12. Quality check before closing a piece of authorized work

Review the complete diff. Verify identifiers, links and anchors, status metadata, approval references and traceability. Verify that no business capability disappeared silently, no Owner authority was weakened, no secret was introduced, no unintended placeholder remains, no unrelated file changed, and the next phase was not silently started. Re-derive counts instead of copying them. Run the checks that apply and record what was actually run.

## 13. Continuity

Before ending substantial authorized work, the repository must let a new agent with no chat history determine: what BantuKasir is; the current scope, architecture, product rules, security model and data-integrity model; the design baseline; what has been decided, superseded and left open; accepted risks; the Git state; the status of P0 to P11; the Task baseline; the exact next authorized action; and what is not authorized. Essential knowledge must not live only in chat or model memory, and there must be one authoritative document per subject.

## 14. Context efficiency

The planner may read broadly. Executors read the minimum: `AGENTS.md`, the execution context, the handoff, the Task plan, the active Task, and the exact sections the Task cites. Task contracts must therefore quote or cite precisely what they need.
