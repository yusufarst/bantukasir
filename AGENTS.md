# Agent rules — LATANSA Platform

These rules apply throughout the repository, including Gemini Antigravity and future implementation agents. **The repository is the source of truth.** Explicit owner instructions take precedence. Never silently replace D01–D34 or later accepted decisions. If implementation conflicts with a canonical rule or safety invariant, STOP the affected task and report the conflict; do not invent a workaround or change the decision yourself.

## Recover context efficiently

1. Inspect `git status --short`. Preserve user work.
2. Read this file, `docs/00-CURRENT-STATE.md`, and the active/next row in `docs/11-BUILD-PLAN.md`. Select exactly one task and verify its dependencies before editing.
3. Search with `rg` before broad reading. Read relevant decisions and canonical specifications only. `README.md` maps ownership.
4. Planning is complete. At the planning checkpoint, the first executor task is **P00.1**, the CP01 standalone HTML shell/auth visual contract. D01–D34 have owner approval and the official colored mark is available under `assets/brand/source/`. Use the pastel system in 05. The final planning session creates no prototype; the next Gemini Antigravity execution session starts P00.1 without repeating architecture approval. On subsequent sessions, follow the updated CURRENT-STATE/build row rather than restarting P00.1. See `docs/16-EXECUTOR-HANDOFF.md` for the operational checklist.
5. P00.2 MUST NOT start until P00.1 passes owner Gate A and is [x]. P01 production work MUST NOT start until CP01–CP04 are approved and the owner explicitly authorizes production implementation. This is a phase boundary, not a permanent ban on execution.

## Mandatory product and safety rules

- Inventory is the priority: staff → scan/input → receipt/issue → ledger → balance → stock attention → owner action.
- All canonical documentation MUST be English. All user-facing UI, errors, reports, human-readable audit messages and notifications MUST be Bahasa Indonesia. Map internal enums to approved labels.
- The immutable movement ledger is the quantity source of truth. MUST NOT allow arbitrary balance edits, negative stock, deleted history, or silent SQL repairs. Follow `06-INVENTORY-SPEC.md`.
- Every stock mutation uses the same command service, PostgreSQL transaction, backend authorization, idempotency, locks and audit. Hiding a control is not authorization.
- A scan changes a draft only. Show success only after confirmed server commit. No offline stock mutation.
- Low/out-of-stock alerts use transitions and attention episodes in `13-NOTIFICATIONS.md`.
- Ordinary staff MUST NOT receive `SUPER_ADMIN`. Enforce permissions on every API/action/job and object access.
- Public data uses an allowlist projection of the same Product Master. Never send internal entities to public clients.
- Public company/catalog content is structured and database-managed. `SUPER_ADMIN` controls draft, preview and publication. Routine content changes need no code edit, commit or redeploy.
- MVP WhatsApp uses configured `wa.me` links only. Never hardcode the destination or claim a click proves a conversation or sale.
- Thousands of SKUs are normal. Follow `14-BULK-IMPORT.md`: validate/preview/confirm, create-only product import, atomic file apply, separate ledger-based quantity/serial opening. Business exports are not backups.
- Follow `15-FINANCE-PROFITABILITY.md`: private cost evidence in Core; valuation and gross profit after Sales and complete data. Unknown is not zero. Purchase cost, COGS, margin and profit are owner-only by default. Never label gross profit as net profit.
- Use existing infrastructure and mature open-source tools. Additional paid services require explicit approval. Cost savings MUST NOT weaken integrity, security or off-host recovery.
- No secrets, real customer data, database dumps or production configuration in Git. `.env.example` contains variable names with empty values. No fallback secrets, fixed credentials, VPS IPs or environment-specific paths in source.

## Visual contracts

Classify tasks `[BE]`, `[FE]` or `[FS]` before implementation.

Major new visual systems/interactions: requirements → standalone HTML/CSS with safe demo data → browser preview → owner Gate A review → revisions until approved → production implementation → real data → technical/browser verification → owner Gate B review.

- Approved HTML is the visual contract. Prototypes MUST NOT access production APIs or databases.
- Reuse approved canonical patterns directly in Next.js. Do not build every screen twice. Significant deviations need review and, when a new pattern is introduced, a revised prototype.
- FE/FS prototype work waits at `[V] WAITING FOR OWNER VISUAL REVIEW` for Gate A. Production FE/FS work waits at `[V]` for Gate B. Only explicit owner approval of the specific revision permits `[x]`.
- Tests, builds and agent screenshots never substitute for owner approval. BE may complete after technical verification; do not count an entire FS task complete because its backend passes.
- Use the five canonical bundles in `05-DESIGN-SYSTEM.md`. CP01–CP04 establish Core; CP05 is later. Audit every major variant for anti-AI-slop, redundancy, mobile and desktop usability, consistent Lucide icons and official branding.
- Never redesign the logo, generate a replacement, or use the default shadcn appearance as LATANSA's identity.

## Execution and token discipline

- Keep one canonical document per topic. Link specifications rather than copying them.
- Keep **one active build task**. No unrelated refactoring, speculative features or silent MVP expansion.
- Do not repeatedly read unchanged files. Search sections first and limit command output.
- Preserve accepted decisions. Changes need a concise replacement decision with reason and impact.
- Run targeted tests during implementation and the relevant completion gate in `08-TESTING-ACCEPTANCE.md`. Efficiency never reduces safety or verification.
- Never claim an unrun test passed. State that it was not run and why.
- Do not install paid services, message third parties, publish, push or merge without authorization for that action.
- Use a reviewable branch after baseline. Never rewrite Git history or discard user work.

## Handoff and completion

Lifecycle: [ ] → [~] → [T] for technical verification, then [V] if owner visual review is required, then [x] only with the applicable evidence. Only one [~] task. Stop at a required visual gate; do not treat silence as approval or advance to a dependent task. BE with no visual output needs technical verification, not visual approval.

STOP and report contradictory canonical instructions, a required invariant change, unavailable required credentials, destructive-history migration risk, an unapproved paid dependency, material scope expansion or a required owner visual decision. Never substitute fake credentials, weaken safety, buy a service, or mark work complete to bypass a blocker. Record [!] for an actual blocker, [V] for visual review, and the exact unblock condition.

Update actual task status and discoverable evidence in `11-BUILD-PLAN.md`. Record real decisions in `10-DECISIONS.md`. Update `00-CURRENT-STATE.md` **last**, with verified state, current/next task, blockers, checks and visual/production status. Documentation completion is not implementation completion.

Keep final reports concise. Do not count `[V]` as complete. Never store credentials, cookies, sensitive payloads or long transcripts as evidence.
