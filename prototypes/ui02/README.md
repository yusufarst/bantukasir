# UI02 — Product Operations

BK02 **WAITING OWNER REVIEW**, 25 September 2026. Gate A remains OPEN. BK03 NOT STARTED.

Deterministic browser prototype using the approved UI01 R6 foundation at `1f62e980840c6c10e336450792e660630bd4c931`. No UI01 source or brand asset changes. Reload resets all synthetic data. This is a presentation/interaction contract, not backend authorization or a production inventory ledger.

## Preview and representative routes

Serve the repository root over local HTTP, then open `prototypes/ui02/index.html#ops/produk`. Current review server: http://127.0.0.1:4175/prototypes/ui02/index.html#ops/produk . The ignored local server helper is not a deliverable; any static server preserving relative paths can serve the prototype.

| Route after # | Review purpose |
| --- | --- |
| ops/produk | Search name/SKU/barcode; category, brand, status; 25-record pages; empty/loading/error |
| ops/detail/p1, ops/edit/p1, ops/baru | Master detail/edit/create; exact identity block and similar-name warning |
| ops/label/p1 | Persistent internal Code128, manufacturer aliases, label/print-request intent |
| ops/masuk | Receiving draft; scan aggregates lines; create missing product and return |
| ops/keluar | Non-sale issue with mandatory category and explanation |
| ops/stok, ops/restock | Current quantity, Menipis (>0 and <=minimum), Habis (=0), active restock |
| ops/riwayat | Operational facts with no acquisition cost value |
| owner/awal | Explicit onboarding mode, no-ledger products, zero checklist without movement |
| owner/koreksi | Append-only correction/reversal, before/change/after review |
| owner/riwayat | Owner-only cost evidence or honest unknown-cost state |
| cashier/produk | Denied management access; return to approved cashier entry |

The footer role switch is a review harness, not sign-in or permission granting. External role destinations reuse UI01 representative entries. Cross-bundle navigation does not share UI02 in-memory state; use UI02 navigation while reviewing a draft. Full POS and reports are outside BK02.

## Important contracts

- Product creation always starts at stock 0, with no movement. Editing master data never changes stock. Units/precision lock once movements exist.
- Stock changes only on explicit final confirmation. Native review cancellation preserves the draft. Version, active-product and quantity checks reject stale/negative outcomes in the prototype.
- Operations can enter incoming unit cost or mark it unknown. The value is absent from review/result/history; submitted draft inputs are cleared. No browser storage is used. Synthetic owner evidence remains in the source/in-memory harness; production must enforce server-side DTO allowlists and authorization, which this static prototype cannot prove.
- Opening is owner-only, restricted to products without prior movements, at most 100 lines per confirmation. Zero counts are checklist facts with no stock movement. Ordinary receiving/issue is held during opening mode. Finish is blocked until all pending products are checked.
- Manual issue requires a non-sale reason and cannot create negative stock. Customer sales belong to the cashier path.
- Reversal retains the original and appends one opposite fact. Sale issues cannot be independently reversed; repeated reversal and negative outcomes are blocked. Physical adjustment explicitly records verified count. Production valuation dependencies and authorization remain real integration gates.
- Barcode aliases preserve leading zeros/case, cannot collide or replace previous aliases. BK- is reserved. Local JsBarcode renders the internal Code128; label size 50 × 30 mm is provisional. Print request is not proof of printing.
- Thirty synthetic ATK products exercise normal, low, out, inactive and unopened states. Dates follow approved R6 Indonesian long-date formatting; times use hh:mm:ss.

No product-image upload/storage, CSV/XLSX import, warehouse/location, backend, database, migration or production frontend was added.

## Review evidence

[Final packet](../../.impeccable/review/bk02/REVIEW.md), [actual tools/provenance](../../.impeccable/review/bk02/TOOLS.md), and [reuse map](PATTERNS.md). Browser checks are Chromium and emulated touch, not physical scanner/printer, screen-reader, Safari or device certification.
