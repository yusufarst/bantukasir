# BK02 final review candidate

**COMPLETE (UI02 owner-approved on 25 Sep 2026).** Gate A OPEN; BK03 NOT STARTED. Approved UI01 R6 source baseline: `1f62e980840c6c10e336450792e660630bd4c931`. No UI01 source or logo edits. No push, merge, deployment or database operation performed in this task.

## Review outcome

Targeted independent-review findings are addressed. The candidate preserves the established BantuKasir family, with coherent ATK categories/prices, a search-first table, role-safe operational facts and distinct posting workflows. Owner explicitly approved the UI02 candidate on 25 Sep 2026.

| Independent finding | Severity | Remediation and final evidence |
| --- | --- | --- |
| Reversal exposed irrelevant count field because a layout rule defeated hidden | High | UI02 hidden rule, required/disabled state synchronized; reversal/adjustment round-trip tested. |
| Phone lookup pushed the draft/action too far down | High | Bounded lookup results, 48px jump above bottom nav, focus/scroll to draft and return to search; 375px and emulated 440px touch pass. |
| Catalog/filter density and repetitive fixture mapping | Medium | Phone filter disclosure, compact rows, coherent category/unit/price fixtures; no horizontal overflow at five widths. |
| Correction lacked precise unit and before/after effect | Medium | Current stock, signed delta and post-confirmation quantity shown together with unit; original retained and cancellation leaves stock unchanged. |
| Unknown-cost checkbox rerender lost focus | Medium | Focus returns to the same checkbox without scroll jump; Tab reaches the enabled cost field; touch verified. |

Pre-polish heuristic scores (review A, not a post-polish rating): visibility 3; real-world match 2; user control 3; consistency 3; error prevention 3; recognition 2; efficiency 2; minimalism 2; recovery 3; help 3. **26/40**. Specificity verdict: recognizably the approved BantuKasir operational family, not a reason to redesign the foundation. Operations needed less repeated scrolling; Owner needed explicit correction impact; Cashier must stay outside product management. These persona findings were the remediation priorities. No critical finding remained in the final scripted audit. No final independent rescore was performed.

## Actual checks

- [Responsive results](responsive.json): **55 cases**, 11 routes at **375, 440, 768, 1024, 1440**, no horizontal overflow, zero captured page errors.
- [Core interactions](interactions.json): **8 groups pass** covering search/list/detail, exact/similar duplicate handling, product creation stock=0, barcode/alias/label intent, receiving confirmation and Operations write-only cost, reasoned issue and stock bound, owner opening/zero checklist, reversal/original preservation and role switching.
- [Final audit](final-audit.json): **9 groups pass**, plus inventory of **21 resolved sidebar destinations**. Includes hidden/required correction fields, before/after impact, focus restoration, mobile draft reachability, LOW/OUT/restock, master edit, positive opening, mandatory non-sale reason, create-and-return draft preservation, loading/error/empty recovery, desktop/account/mobile-shell behavior, denied cashier and emulated touch.
- The first extra audit assumed a product was in the initial five alphabetical lookup results. Those three test selectors timed out; the harness was corrected to search the exact SKU. No application change was needed for those failures. The staged results are the final passing run.
- Final screenshots were generated after the targeted source fixes, not copied from the initial review. Representative phone/desktop catalog, receiving, labels, opening and reversal plus populated draft, confirmation and restock are in [screens](screens/).
- Current verification scripts are in [checks](checks/). They use the locally available Playwright runtime and local HTTP preview at port 4175; they are review evidence, not production tests. No test changes application source. State introspection verifies deterministic fixture deltas and privacy presentation boundaries.
- Syntax, working/staged whitespace, baseline preservation, staged UTF-8 and exact scope counts are recorded in [closeout.json](closeout.json).

## Owner walkthrough

1. Open `#ops/produk`. Search ATK-001, review detail/edit and filters; create a duplicate SKU, then a similar name with a new SKU. New product has stock 0.
2. Open `#ops/masuk`, scan/search ATK-001. Enter quantity and a distinctive incoming cost. Review omits that cost value; only explicit confirmation changes synthetic stock. History stays operational and the submitted input is gone.
3. Open `#ops/keluar`. Missing non-sale reason is blocked. Excess quantity is blocked. Menipis/Habis/restock remain distinct.
4. Open `#owner/awal`, begin opening mode, review an unopened product or zero checklist. Operations cannot open this route.
5. Open `#owner/koreksi`. Reversal shows no count/cost input. Inspect the current/change/after summary; cancel leaves state intact. Confirm appends a new fact. Sale issue and repeat reversal are blocked.
6. At 375px, add a receiving product and use the draft jump. Toggle cost evidence with keyboard/touch. Open/close drawer/account; desktop navigation stays stable.

## Limits and gates

All data and quantities are synthetic and reset on reload. UI01 destinations remain their already-approved representative entries; cross-bundle navigation does not persist UI02 state. History shows up to 25 matching facts, with search/type narrowing, not a production history pager. Catalog has a working 25-record pager. No product image upload, CSV/XLSX import, warehouse/location or full POS/report workflow was added.

Operations presentation never echoes private acquisition cost in review/results/history. This static source necessarily includes synthetic owner evidence; it is not a security boundary. Production must use backend authorization, role DTO allowlists, immutable atomic posting, durable command recovery and valuation dependency checks. No backend security, database, concurrency, real ledger reconciliation or printer/scanner proof is claimed.

Physical phone/tablet, Safari/Firefox, assistive technology, real HID scanner, label quiet zones and printer readability remain device/integration gates. Gate A needs owner approval of all four bundles and BK05. G1–G4 remain open where applicable. No BK03 execution is authorized by this closeout.

Actual tools and license decisions: [TOOLS.md](TOOLS.md). Questions skipped because scope and remediation were already owner-directed. **Next: BK03 UI03 POS.**
