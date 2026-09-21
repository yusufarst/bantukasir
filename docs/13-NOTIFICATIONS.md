# 13 — Current stock attention / restock

Core is reliable in-app dashboard/badge/list, not a notification delivery engine.

For each active product in the single stock pool:

| Condition | State / label |
| --- | --- |
| onHand = 0 | OUT / Habis |
| 0 < onHand <= minimumStock | LOW / Menipis |
| onHand > minimumStock | NORMAL / Normal |

minimumStock >=0 at unit precision; minimum zero has no LOW interval. New active product at zero is OUT; onboarding can filter pending setup without calling it Normal. Negative stock is prohibited. No reservation/location aggregate.

Owner and operations list product/SKU/current stock/minimum/state with filter, server pagination, last-refreshed time and stock/history/receiving actions. Cashier sees permitted stock for checkout, not owner alerts. Threshold changes are validated/audited and immediately affect classification; no movement is fabricated.

Read from current committed balance/minimum in one query. Refresh after mutation, tab focus and at most 30-second active-page intervals; show stale/error status if refresh fails, never an optimistic empty restock list. No separate attention source of truth or acknowledgment requirement.

Test minimum 5: 6 NORMAL, 5 LOW, 1 LOW, 0 OUT, receipt to 6 NORMAL; threshold changes and inactive filter. Badge and list must agree. Web Push, inbox episodes, outbox/worker, provider retry, delivery guarantees and camera notifications are Later. No push device gate in Core.
