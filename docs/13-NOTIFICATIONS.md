# 13 — Stock states and owner notifications

Canonical classification, episodes, deduplication, recipients and delivery. Inventory evaluates inside [06](06-INVENTORY-SPEC.md) transactions; the dashboard reads the same state.

## Stock policy

Core scope is each product's aggregate available quantity in active, issue-eligible STORAGE locations, using eligible(p) from 06. Do not use total physical quantity. Future reservation/quarantine/transit therefore changes availability consistently. Location detail remains visible; per-location thresholds require separate explicit policy keys later.

Product owns monitoringEnabled, minimumQty ≥0 and optional reorder target. Precision matches product; reorder target, when present, must exceed minimum. It is planning information, not another trigger or automatic purchase order. StockHealth stores result/episode/version only.

```text
available = 0                 → OUT    → Habis
0 < available ≤ minimumQty    → LOW    → Menipis
available > minimumQty        → NORMAL → Normal
```

Minimum zero has no LOW interval but still detects OUT. Negative values are forbidden upstream. Monitoring off means null state and **Tidak Dipantau**, not falsely Normal. Inactive products are unmonitored. Reenable evaluates current stock afresh.

New products start monitoring off to prevent incomplete onboarding flooding inboxes. After opening/policy review, owner chooses **Mulai Pantau Stok**; current zero may legitimately open OUT. All genuinely stocked SKUs must be monitored before pilot. Owner documents custom-order exceptions. Show incomplete monitoring setup in settings.

## Episodes and transitions

An episode is unresolved attention until stock returns to NORMAL. At most one OPEN episode per product. Store state/version, episode sequence/openedAt/resolvedAt/reason and highest severity. Product guards serialize movement, threshold changes and authorized evaluation.

| Before | After | Episode action | New event |
| --- | --- | --- | --- |
| NORMAL | LOW | Open | LOW once |
| NORMAL | OUT | Open | OUT once, no extra LOW |
| LOW | LOW | Keep | None |
| LOW | OUT | Keep, raise maximum severity | OUT once if not already emitted |
| OUT | OUT | Keep | None |
| OUT | LOW | Keep, partial recovery | None |
| LOW/OUT | NORMAL | Resolve | No recovery push in Core |
| NORMAL | NORMAL | None | None |

Unique episode/severity permits at most one LOW and one OUT. OUT→LOW→OUT never emits another OUT in that episode. Return to NORMAL then decline creates a new episode. No periodic same-state reminders. Read/unread never resets deduplication.

Minimum 5 example: 6→5 LOW#1; 5→4→3 none; 3→0 OUT#1; 0→2 same episode; 2→0 none; 0→20 resolve; 20→5 LOW#2. Monitoring activation at LOW/OUT opens an initial episode; activation above minimum starts NORMAL silently.

Threshold/monitor changes use the same guarded evaluator and actor/reason audit. Policy change may open/resolve attention without physical movement; record that cause. Monitor-off/product deactivation resolves administratively, not as physical recovery. Reenable can open a fresh episode. Only owner changes policy.

## Atomicity and deduplication

Finish all movement legs before evaluating each affected product's final balance. State/episode/event, recipient inbox and outbox share the stock transaction. STORAGE→STORAGE transfer cannot trigger a transient low state between legs. Required audit/event/outbox failure rolls back posting.

Constraints: one health row/product, partial unique open episode/product, unique episode/severity, event/user inbox and event/channel/recipient/device delivery. No registered device means inbox only, not missing attention.

Recipients are active owners with ownerDashboard permission at event creation. Staff receive their transaction feedback, not all owner alerts. A later owner can still see current dashboard attention; historical events are not silently broadcast again.

Read-only reconciliation compares state with stock. Projection mismatch raises an incident under 06 rather than repeatedly generating events to conceal the bug. Controlled maintenance/rebuild uses product guards and the same deduplication keys.

## Channels and delivery promises

1. **In-app inbox is mandatory and durable.** Poll every 15 seconds while active; refresh after commands and focus return. Show last successful refresh. No WebSocket/SSE required.
2. **Web Push is opt-in proactive delivery outside the tab.** Same-codebase worker uses VAPID and user-approved device subscriptions. Browser/provider/network dependencies remain; no guaranteed arrival time or paid provider prerequisite.
3. Existing SMTP may be evaluated later if available; it is not a Core dependency. Separate event from delivery channel so future adapters do not change stock facts.

Push payload is minimal: **Stok memerlukan perhatian**, same-origin inbox link and stable event tag. Show SKU/quantity/location only after login, not on lock screens. Subscription endpoints/keys are sensitive. Register on user gesture with active authorized association. Logout, permission revocation and account disablement revoke device association.

Owner onboarding tests permission, delivery, opening the notification and closed-tab behavior on the actual device. Unsupported/denied UI: **Pemberitahuan perangkat belum aktif; notifikasi tetap tersedia di aplikasi.** Provider acceptance does not prove owner delivery. Reference: [MDN Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API).

Pilot proactive acceptance requires verified owner-device push, or explicit owner acceptance of the actual channel limitation and an inbox-checking procedure. Otherwise the gate remains open. This is capability acceptance, not permission to purchase a service. Email/Telegram/WhatsApp APIs are not Core requirements; paid providers need explicit approval. Public wa.me links are separate from notification delivery.

## Worker, retry and spam control

- Claim due rows with FOR UPDATE SKIP LOCKED, 60-second lease and fencing claim token. Claim transaction is short; send outside it. Only current token can ACK. Expired lease may be reclaimed.
- External delivery is **at least once**. Crash after provider acceptance but before ACK may resend. Stable eventId push tag can replace duplicates where supported; never claim network-wide exactly-once.
- Retry transient failure after 1, 5, 15, 60 and 240 minutes; after the final retry fails, mark DEAD. Record attempts/nextAttemptAt and safe errors. Respect bounded Retry-After for 429; no tight loops.
- 404/410 revokes the subscription. VAPID/auth errors expose a configuration incident; no endless retries. Inbox remains.
- Before send, recheck recipient active/authorized and episode OPEN. Resolved episode → SUPPRESSED. LOW superseded by OUT → SUPPRESSED. Keep historical inbox. If state changes between check and send, generic push remains safe and the opened UI shows current state.
- Initial limit five pushes/minute/recipient; delay excess, never drop business events. No unchanged heartbeat pushes.
- Oldest pending >5 minutes raises operational warning. Authorized manual retry reuses the delivery identity and creates no business event.

## Content and action

LOW example: **Stok Menipis — Produk Demo tersisa 5 unit. Minimum 5 unit.**
OUT example: **Stok Habis — Produk Demo tidak memiliki stok tersedia.**

Include event time/snapshot, current state and **Lihat Stok/Lihat Riwayat** after authorization. No automatic purchase order or threshold change. Dashboard attention remains even if read or delivery fails. Queue details belong in restricted system views. Future approval/QC/RFQ/backup alerts have their own event types and keys, not inventory episodes.
