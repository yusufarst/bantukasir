# 08 — Testing and acceptance

Canonical evidence and completion gates. Coverage percentage does not replace risk testing. No application, package scripts, database or application tests exist yet.

## Test layers

| Layer | Focus | Planned tools |
| --- | --- | --- |
| Unit | Decimals, normalization, scan reducer, health states, permissions and Indonesian mappings | Vitest and controlled clock |
| Integration | Constraints, rollback, auth, public projections, outbox/audit | Disposable real PostgreSQL, not SQLite/mocks as integrity proof |
| Concurrency | Controlled independent-connection interleavings | Barriers, not random sleeps |
| E2E | Login → scan → review → commit → stock → inbox/cockpit | Playwright with test app/worker/DB; persisted assertions |
| Hardware/manual | USB/Bluetooth, printer, phone, push, accessibility | Actual intended devices |
| Owner visual | Prototype and implementation | Both gates in [05](05-DESIGN-SYSTEM.md), exact-revision evidence |
| Operations | Backup/restore, restart, missing config and incident recovery | Isolated deployment-like environment |

Test runner rejects production/unknown database identities. Destructive tests use runner-created ephemeral databases, never an arbitrary production URL. Mock providers support deterministic retry tests; actual device testing remains required for delivery claims.

## Inventory integrity

| ID / invariant | Scenario | Expected result |
| --- | --- | --- |
| INV01 / I01–I02 | All movement types; runtime history mutation attempts | Full ledger aggregate equals balances; forbidden update/delete fails |
| INV02 / I03 | Two connections each issue the final unit | One success, one conflict, final zero and one issue |
| INV03 / I04 | Duplicate serial issue or concurrent identical serial/barcode receipt | At most one valid posting; no duplicate identity/position/+2 stock |
| INV04 / I05 | Ten concurrent identical requests | One receipt/movement; replay returns the same result |
| INV05 / I05 | Same key changed payload; new key same session | Conflict/equivalent replay under 06; no second movement |
| INV06 / I06 | Inject failure after legs, balances, audit, health and outbox | No partial commit or successful receipt |
| INV07 / I07 | Multi-product transfer failure/opposing transfers | Complete rollback on failure; quantity preserved; consistent lock order |
| INV08 / I03 | Reverse consumed receipt; excessive negative adjustment | Reject, no negative or partial inverse |
| INV09 / I04 | Serial moved away and returned before reversing an older movement | Reject by last movement even when location matches |
| INV10 / I08 | Forged actor, inactive location, float/overflow/excess precision | Backend rejects; no client rounding/identity trust |
| INV11 | Concurrent first balance creation | One unique pair, correct aggregate |
| INV12 | Disconnect during commit then status/retry | Honest uncertainty; one recovered commit with original key |
| INV13 | Role revocation/product-location deactivation versus posting | Guard-defined ordering; no new post after committed revocation |
| INV14 | Projection rebuild and generated movement sequences | Identical results; property checks preserve I01–I08 and precision |
| INV15 (later) | Reserve versus issue/expiry/fulfillment | No oversell or double availability reduction; events equal reserved |
| INV16 (later) | Opname freeze versus writers; stale/double-consumed approval | All ordinary writers blocked; matching approved posting alone consumes freeze |

## Notifications and scanner

| ID | Scenario | Expected result |
| --- | --- | --- |
| NOT01 | Minimum 5: 6→5→4→3→20→5 | Two LOW episodes, no decrement spam |
| NOT02 | 6→0→2→0→20 | One OUT; partial recovery does not reset |
| NOT03 | 6→5→0→2→0 | One LOW and one OUT in one episode |
| NOT04 | Minimum zero, monitor toggle, concurrent threshold/issue | Correct serialized policy/state/event and audit |
| NOT05 | Concurrent locations and direct transfer | One final state, no transient leg alert |
| NOT06 | Worker crash after send before ACK; expired lease | Delivery may retry; no duplicate event/inbox; stable tag/fencing |
| NOT07 | 410/429/5xx and recovery before retry | Revoke/backoff/suppress correctly; stock remains committed |
| NOT08 | Read state, other owner/device, disabled account | Per-user reads, persistent episode and no unauthorized delivery |
| SCN01 | USB/Bluetooth Enter/CRLF, empty buffer, manual/paste/IME | One lookup/token; scanner Enter never finalizes |
| SCN02 | Twenty fast complete identical quantity scans; key repeat/CRLF | Twenty additions without modal; empty terminators/repeat ignored; undo/review correct |
| SCN03 | Repeated item, serialized model, unknown/retired alias | Hard serial dedupe, explicit identity selection, Indonesian errors, no direct mutation |
| SCN04 | Out-of-order lookup, >128 chars, controls and modal focus | Correct product matching; no password capture; queue blocks review |
| SCN05 | Offline before lookup/send/during commit | Correct states/frozen envelope; no offline mutation queue |
| SCN06 | Reload/logout/account switch/draft expiry | Actor isolation and uncertain-outcome recovery |
| SCN07 | Actual label size, long token and reprint | Same identity on rescan; printing creates no stock/item |
| SCN08 (later) | Camera denial/decoder failure/repeated frames | Manual/HID fallback, frame latch, user gesture, tracks stopped on exit |

## Security, language and audit

- SEC01: positive/negative matrix for every 04 permission on endpoint/action/query, foreign actor objects and combined roles. Staff cannot assign roles, correct stock, publish site/product content or obtain private fields via exports/extra parameters.
- SEC02: disabled public signup, rate limiting, expired/revoked sessions, 2FA/recovery, origin/CSRF and cookie flags through direct HTTP tests.
- SEC03: sentinel private values absent from public JSON/HTML/metadata/cache, including costs/suppliers/locations/exact stock/serials/notes/PII/audit.
- SEC04: staged/release secret scan; missing config fails safely; errors/logs expose no credentials/cookies/tokens. Synthetic fixtures are clearly test-only.
- AUD01: successful stock/master/role/publishing changes have actor/action/time/entity/reference and relevant safe before/after. Rollback cannot leave success audit. Denials log separately without sensitive payload.
- UI01: all user-visible states/library errors/print/export/loading/offline text use Bahasa Indonesia, with only approved technical identifiers unchanged.
- UI02: full keyboard path, basic screen-reader form/status, 200% zoom, 375/768/1024/1440/1920 px plus 360 px regression, contrast, modal focus return and hardware input.

## Bulk import and onboarding

| ID | Scenario | Expected result |
| --- | --- | --- |
| IMP01 | Equivalent XLSX/CSV, leading zeros, delimiters/decimal comma, text versus numeric identity | Same normalization; no guessed/mangled identity |
| IMP02 | Missing/invalid fields, normalized duplicates, unknown references/aliases | Every affected original row/field marked; no silent upsert/skip |
| IMP03 | Stock/cost/publication/internal ID in product template | Reject before apply; no stock/value/master side effect or sensitive error echo |
| IMP04 | One bad row among 5,000; apply uniqueness race; stale references | Whole file blocked/rolled back; NEEDS_REVIEW when stale |
| IMP05 | Crash before/during/after commit, double confirm, stale lease | One ImportCommit/result; recovery without duplicate products/opening |
| IMP06 | Unauthorized apply, revoked executor, expired intent | Backend rejects/requires review; prepare does not inherit owner authority |
| IMP07 | Mixed tracking modes, duplicate/already-stocked serial, fabricated LT- identity | Reject safely, no duplicate unit or silent status rewrite |
| IMP08 | 5,000-row opening, late injected failure, ordinary writer during freeze | Entire posting atomic; all ordinary writers reject; explicit owner freeze release |
| IMP09 | Oversize/ZIP bomb/macros/formulas/external links/hidden rows/bad UTF-8 | Bounded resources, no evaluation/network fetch, safe error exports |
| IMP10 | Successful file reupload, APPLYING cancel, cleanup/restore | Honest recovery/cancel behavior; durable manifests/receipts survive TTL |
| IMP11 | Thousands of SKUs, cross-page selection/filter/export | Server pagination/stable order, selector ≤20; filter clears selection; no private fields |

Initial benchmark on reference staging 2 vCPU/4 GiB, not a purchase recommendation: 5,000-row parse/validation p95 ≤15 seconds, product apply ≤15 seconds, opening apply ≤30 seconds; hard apply deadline 60 seconds. Initial parser memory budget 256 MiB. Measure peak memory, query plans/counts and scanner/push latency during import. If resources fail, explicitly reduce admission limits or redesign before acceptance; do not remove integrity checks or silently partial-commit. Preview loads 50-row pages.

## Finance

| ID | Scenario | Expected result |
| --- | --- | --- |
| FIN01 | 10 at Rp100,000 + 10 at Rp140,000; accept five, net revenue Rp900,000 | Average Rp120,000, COGS Rp600,000, gross Rp300,000, margin 33.33%; issue alone is not revenue |
| FIN02 | Same-SKU serial units acquired at Rp10m/Rp12m; sell first | MWA COGS Rp11m, not automatically actual Rp10m; trace remains |
| FIN03 | Return/credit two from FIN01 | Revenue −Rp360,000, COGS −Rp240,000; net revenue Rp540,000, COGS Rp360,000, gross Rp180,000 |
| FIN04 | Issue before acceptance; new receipt; partial acceptance/pre-sale return | Separate clearing; correct warehouse average; no double quantity/value subtraction |
| FIN05 | Unknown receipt/direct cost/opening value | Downstream PENDING; no false zero/complete profit; warehouse posting works |
| FIN06 | Late cost revision, duplicate/reordered workers | Deterministic source sequence, atomic report publication, preserved prior versions/watermarks |
| FIN07 | Supplier return/loss/found stock/receipt reversal after average change | Separate variance/non-sale effects; preserve physical sequence; warehouse Q0 implies V0 |
| FIN08 | Discount/residual/final unit/precision/negative revenue | Exact totals and 15 rounding; margin not applicable for revenue ≤0; no floats/overflow |
| FIN09 | Staff attempts owner DTO/export/error/audit/cached report access | No cost/COGS/margin/profit leak; authorized selling prices only |
| FIN10 | Period changes with active stock attention; company has excluded services | Current attention unchanged; explicit eligible goods scope, no net/whole-company profit label |
| FIN11 | Late acceptance date and return after recognition | RecordedAt preserved, evidence-validated recognition, linked correction/version; no physical backdating or deleted acceptance |

Finance calculation tests run when that phase exists. Core tests evidence permissions, null versus zero, revisions and separation from warehouse DTOs first.

## Public CMS, WhatsApp and portability

| ID | Scenario | Expected result |
| --- | --- | --- |
| PUB01 | Owner edits all normal company/hero/contact/footer/product/SEO fields | Internal structured editor supports changes without source edit, Git commit or content-only redeploy |
| PUB02 | Draft edit, unauthorized preview, concurrent publish, rollback | Live revision unchanged until valid owner publish; no draft leak; stale version rejected; rollback audited as new publication |
| PUB03 | Stored XSS/unsafe URL/media, inactive featured product, orphan asset | Validation rejects; active published allowlist only; media references restored safely |
| PUB04 | Published wa.me number/template changes | Link uses current published settings, fixed wa.me origin, correct international digits/encoding; no hardcoded destination |
| PUB05 | Tracking success/failure, forged redirect, private template placeholders | Safe outbound click only; link still works if tracking fails; no PII/private values or conversation/sale claim |
| OPS01 | Restore database/media to a different configured host/root/origin | No application source edits; correct permissions/links/auth/push; no hardcoded VPS details |
| OPS02 | Failed/off-host-missing backup, lost runtime, restored imports/valuation | Honest failure state; no pilot without restore evidence; no blind replay after snapshot loss |

## Visual acceptance

Every CP/significant variant records purpose/action/fact inventory and anti-AI-slop/redundancy audit. Test logo click/Enter/Space without accidental navigation; 240/64 desktop sidebar and mobile drawer; focus/tooltips/reduced motion; no duplicated account/logout/CTA; purposeful desktop space and phone priority.

Cockpit: critical/stock priority, state counts once, finance absent until supported, correct period labels and distinct empty/error/stale/incomplete states. Import: phone summary versus complete desktop review. Scanner: fast quantity repeats, hard serial dedupe, navigation pauses input, keyboard does not cover review/error. Official logo geometry preserved. Check font/icon licenses at packaging; initial combined Latin font budget ≤160 KiB, without dropping required glyphs.

Screenshots supplement interaction testing, not replace it or owner review.

## Deterministic demo

Future opt-in seed only for development/test, rejected in production. Clearly labeled demo dataset: quantity minimum 5/stock20; minimum5/stock5; minimum0/stock0; one serialized product with two distinct units; two STORAGE locations and three roles. No committed passwords; setup uses secure local input.

Create demo balances through commands/ledger. Dedicated test helpers may prepare fixtures only with verified invariants. Reset only identified demo/test databases. Fixed clock/IDs support predictable attention states. No real business names/contacts/serials in fixtures.

## Performance targets

Reference dataset from 02, two operators, stable network: local input feedback ≤100 ms; barcode resolve p95 ≤500 ms; 100-line finalize p95 ≤2 seconds; cockpit p95 ≤2 seconds. Record host/network/sample and separate server from end-to-end latency. Never remove locks/validation to improve timing.

Inbox is committed with stock. Active tab reflects changes within 20 seconds on a healthy network; first push attempt within 60 seconds. This is an attempt target, not device-delivery guarantee. [09](09-DEPLOYMENT-OPS.md) owns RPO/RTO. None are measured results yet.

## Completion gates

During iteration run targeted risk tests. Before implementation task completion: diff/secret review, lint, typecheck, available unit/integration suites, build and affected critical E2E. Inventory/auth/health changes require their complete risk suite, not one happy path. Core release requires all relevant application/concurrency/rollback/security/E2E/restore/hardware checks.

P01 will create consistent scripts such as lint, typecheck, test:unit, test:integration, test:e2e and build. These names are planned, not commands currently available. Report early-phase available checks honestly without bypassing the active task's acceptance.

- BE: technical evidence, permissions/audit and relevant migration/recovery plan permit [x].
- Prototype FE: browser checks and owner Gate A permit [x] for the prototype only.
- Implementation FE/FS: technical/browser evidence and owner Gate B permit [x]; otherwise [V].
- Store concise task evidence/commit and REVIEW reference. No huge logs or production screenshots in public Git.

## Documentation gate — current task

Verify canonical 00–15, README/AGENTS/prototype index/config example; English documentation with Indonesian UI labels; valid local links; clear ownership; invariant/transition/cost examples; import versus interactive limits; valid dependency graph; **65 tasks / 34 Core / 13 phases**, all unstarted.

Review inventory concurrency/serials, alerts, scanner, scale/import/opening, owner finance/privacy, responsive/sidebar/icons/anti-slop/redundancy, both visual gates, structured CMS/wa.me, near-zero cost, portable VPS, storage/backup and secret safety. Check no HTML/generated assets/application/migrations/dependencies. Validate empty environment values, whitespace/conflict markers, secret patterns, git diff --check and status. Arithmetic verification is documentation checking, not application testing or owner approval.
