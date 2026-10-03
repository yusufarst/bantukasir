# AGENTS.md — BantuKasir

Stable rules for every agent that works in this repository. This file does not describe the current state of the work; that is in [CURRENT_HANDOFF](docs/handoff/CURRENT_HANDOFF.md). Nothing in this file grants permission to do a specific piece of work.

## 1. What this repository is

BantuKasir is the internal codename of a goods-only point-of-sale system. Each installation serves one business with one stock pool; separate shops use separate installations (D-038). There is no application code yet. The repository holds planning documents and is being re-founded on the AICWDF-4.3 framework (decision D-019).

## 2. Authority

1. **Owner.** Final authority on business policy, product scope, money and payment meaning, inventory meaning, roles and authorization, legal and tax behaviour, accepted risk, recurring cost, destructive operations and major workflow.
2. **AICWDF-4.3.** The operating framework for how this project is planned, executed, validated, released and maintained. The binding subset is written into [docs/00-governance](docs/00-governance/PROJECT_RULES.md). Approved departures are in [FRAMEWORK_DEVIATIONS](docs/00-governance/FRAMEWORK_DEVIATIONS.md).
3. **Repository.** The durable source of project state. [SOURCE_OF_TRUTH](docs/00-governance/SOURCE_OF_TRUTH.md) says which document is authoritative for each subject.
4. **Chat, model memory, summaries, executor reports.** Supporting evidence only. A decision made in chat does not exist for this project until it is written into [DECISIONS](docs/00-governance/DECISIONS.md).

When two sources conflict, do not choose silently. Follow the hierarchy in SOURCE_OF_TRUTH; if it does not settle the matter, stop and report.

## 3. What to read

Every agent, in this order:

1. this file;
2. [docs/handoff/CURRENT_HANDOFF.md](docs/handoff/CURRENT_HANDOFF.md) — current state, next authorized action, what is not authorized;
3. [docs/00-governance/SOURCE_OF_TRUTH.md](docs/00-governance/SOURCE_OF_TRUTH.md) — the document map;
4. only the documents the map names for the work in hand.

Implementation executors will additionally read `docs/11-tasks/EXECUTION_CONTEXT.md`, `docs/11-tasks/TASK_PLAN.md` and the active Task. Those files do not exist yet. They are created when the Task baseline is planned (step RF-7 of the [re-foundation plan](docs/00-governance/REFOUNDATION_PLAN.md)). Until they exist and the Owner authorizes implementation, there is no Task to execute.

## 4. Before any work

Inspect the branch, HEAD, staged, unstaged and untracked files, and the stash. Compare with CURRENT_HANDOFF. If the repository differs materially from what the handoff records, stop and report; do not repair it.

The Owner works on Windows. Before treating a tracked file as modified, check whether the difference is only CRLF versus LF. Do not rewrite files to normalize line endings.

## 5. Rules

1. Do only what the current authorization in CURRENT_HANDOFF allows. Completing one step never authorizes the next.
2. No application code, schema, dependency installation or infrastructure work until the Owner explicitly authorizes the implementation phase.
3. Stop and ask the Owner before deciding anything that changes business meaning, money or payment behaviour, stock behaviour, roles or authorization, legal or tax behaviour, V1 scope, a major workflow, recurring cost, accepted risk, destructive data behaviour or release risk. Ordinary technical and planning choices are yours to make and record.
4. Do not keep an old decision merely because it was once approved, and do not change genuine business meaning silently. Classify doubtful older decisions as `OWNER_REVALIDATION_REQUIRED` and ask a precise question. Open items are in [OPEN_ITEMS](docs/00-governance/OPEN_ITEMS.md).
5. MULTIPLECORP is a separate project. Never inspect it, copy its domain model, interface or assets, mutate it, or use it as evidence or inspiration.
6. Design work uses only the BantuKasir design project recorded in [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md). Verify the project identity before every access; on a mismatch, stop. Do not claim a design tool was used when it was not.
7. Money is never floating point. Quantities are exact. Historical money and stock facts are append-only and are corrected by linked compensating records, never by rewriting. One source of truth per fact. Critical money and stock actions are transactional, with explicit concurrency and idempotency behaviour.
8. Authorization is deny by default and enforced on the server. Hiding a control in the interface is not authorization.
9. Never commit or expose `.env` files, credentials, API keys, tokens, private keys, production secrets or database passwords.
10. Production data is protected. Follow [PRODUCTION_DATA_SAFETY](docs/00-governance/PRODUCTION_DATA_SAFETY.md). Nothing destructive is done to production data without the Owner's approval of that exact operation. No production operation of any kind is authorized today.
11. Git: never rewrite published history; never force push; never repair unexpected divergence on your own; stage exact paths and never use `git add -A`; do not use reset, clean or restore to tidy the tree; do not push, merge or deploy without explicit authorization for that action.
12. Commits use only the Owner's configured Git identity. Never add an attribution trailer or any mention of an AI model, agent or tool to a commit (`Co-Authored-By`, `Generated-By`, `Assisted-By` and similar). Do not rewrite old commits to remove past attribution.
13. The target for additional recurring production cost is zero. A paid service is a stop condition that needs the Owner's approval. See [COST_POLICY](docs/00-governance/COST_POLICY.md).
14. Agent-facing repository documents are written in English. The interface is Indonesian first. V1 localization is not yet decided (D-020).
15. Files under any `legacy/` folder are pre-re-foundation documents. Do not edit them. They are replaced, not patched, when their subject is rebuilt.
16. Evidence, not assertion. Do not report a check as passed unless it was run. Do not invent approvals, test results, timestamps or tool output.
17. Record real decisions in DECISIONS, framework departures in FRAMEWORK_DEVIATIONS, and open questions in OPEN_ITEMS, in the same piece of work that produced them.
18. Before stopping, update CURRENT_HANDOFF last, so that a new agent with no chat history can continue.

## 6. Roles

| Role | Who | Notes |
| --- | --- | --- |
| Owner | Yusuf Arif Setiawan | Final authority (section 2). |
| Planning agent (planner and reviewer) | Claude Project Chat; model and effort per D-031 | Plans, prepares one bounded instruction at a time, reviews executor reports against the repository. |
| Implementation executor | Claude Code or Claude Desktop on Windows; model and effort per D-031 | Executes only the one authorized instruction, validates, reports evidence, stops. |

Models and effort follow D-031: Opus 5.5 is the default for planning and execution, with effort High, XHigh or Max chosen for the task; Fable 5.1 is used only for escalation. Every handoff between the planning agent and the executor states Agent, Model, Effort, Session, Reason and Escalation, and the handoff never authorizes the next task. Instructions to agents are in English; communication with the Owner is in Bahasa Indonesia. A model name is the Owner's request, not a claim that the model is available: if the named model or effort is not available, say so and wait; do not substitute silently. Executor reports are evidence to be checked, not truth. There is no automatic advancement from one step or Task to the next.
