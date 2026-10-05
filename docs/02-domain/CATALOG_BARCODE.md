# Catalogue, barcodes and product search

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P2 — domain rules |
| Replaces | `docs/02-domain/legacy/12-BARCODE-SCANNER.md` as to domain rules; the catalogue parts of `docs/01-product/legacy/01-PRD.md` and `docs/02-domain/legacy/06-INVENTORY-SPEC.md` |
| Map and terms | [DOMAIN_MODEL](DOMAIN_MODEL.md), [GLOSSARY](GLOSSARY.md) |

Products, categories, selling prices, barcode tokens, the shared Product Search, scanning, labels, CSV product import and product photos. Stock and cost are in [INVENTORY](INVENTORY.md) and [COSTING](COSTING.md); selling at the till is in [SALES_CASH](SALES_CASH.md).

The conventions of DOMAIN_MODEL section 1 apply: rules are `BR-CAT-nn`, every rule names its source, and `OPEN` items are in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Source keys (`L-12`, `L-06`, …) are listed in DOMAIN_MODEL section 2. The screen behaviour of the scanner in L-12 (field placement, focus handling, feedback and undo display) is not carried here; it is input for RF-4 and RF-5.

## 1. Product master data

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-01 | A product has: SKU, name, category, optional brand, base unit, quantity precision (0 to 3 decimals), selling price, minimum stock, active flag, and at most one primary photo. | L-P3 §Katalog, L-02, L-06, D-037 |
| BR-CAT-02 | The Owner and OPERATIONS_ADMIN create and edit products, categories, selling prices and barcodes, and print labels. CASHIER manages no master data. On a combined account the right comes from its OPERATIONS_ADMIN functions. | L-04, D-007, D-032, D-036 |
| BR-CAT-03 | Creating a product never creates stock: a new product starts with on-hand quantity 0 and no movement. Scanning, printing a label or hearing a scanner beep never posts stock. | L-06, L-12 |
| BR-CAT-04 | Before a product is created, the shared Product Search is run for the exact barcode and SKU and shows similar names. A similar name is a warning; going ahead needs the explicit confirmation "Tetap buat produk baru". An exact SKU or barcode collision is always refused, also when two creations race. | L-12, L-P3 |
| BR-CAT-05 | The SKU is text (leading zeros are kept) and unique across all products. Before it is stored, outer whitespace is removed, as from all text; the rest is kept exactly as typed, including letter case. Control characters are refused, and the length is 1 to 64 characters. Two SKUs that differ only in letter case are the same SKU and cannot both exist (T-17 g; [SCHEMA](../04-architecture/SCHEMA.md) section 5.6). | L-02, D-037, CORE-004; TECHNICAL_RULES section 11.3 |
| BR-CAT-06 | The base unit is the unit in which the product is stocked, received, issued and sold. There is no pack conversion: a barcode alias never stands for a pack of several base units. The real units and precisions of the first store are part of gate G2. | L-12, L-PRD §Batas Later, L-06 |
| BR-CAT-07 | The base unit and quantity precision cannot change after the product's first stock movement, including when the edit races with that first posting. | L-02, L-06, CORE-005 |
| BR-CAT-08 | Only an active product can be received, issued, adjusted or sold. An inactive product stays visible, read-only, in search results, history and documents. Products are deactivated and reactivated, never deleted. | L-12, L-06, D-042 |
| BR-CAT-09 | Every product belongs to one category. The Owner and OPERATIONS_ADMIN create, rename, deactivate and reactivate categories. An inactive category cannot be chosen for a product; deactivating it changes no product. Categories are never deleted. | L-P3 §Katalog, L-PRD, L-04, D-042 |
| BR-CAT-10 | Brand is an optional attribute of a product, usable as a search filter. It is a free-text attribute, not a managed list (SCHEMA section 5.6); this choice carries no business rule. | L-02, L-12 |
| BR-CAT-11 | Minimum stock is a non-negative quantity in the product's precision. Changing it is validated and audited, creates no movement and immediately changes the product's stock attention state (INVENTORY). | L-06 |

## 2. Selling price

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-12 | The selling price is a non-negative whole rupiah amount. | L-04, BR-NUM-01 |
| BR-CAT-13 | A price changes only through the product master data, by the Owner or OPERATIONS_ADMIN, durably and audited. Each change is kept as a SellingPriceRevision with the amount, the actor and the time. A master price change is never a hidden per-transaction override. | D-036, L-02 |
| BR-CAT-14 | A Sale uses the authoritative current selling price. If it changes while a cart is open, checkout becomes stale and needs an explicit review (SALES_CASH BR-SALE-05). | D-036, L-17 |
| BR-CAT-15 | There is no discount engine, price book, promotion, cashier price override or negotiated price. | D-036, D-002, L-PRD §Batas Later |

## 3. Barcode tokens

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-16 | A barcode token identifies exactly one product. Manufacturer aliases and internal codes share one token space, and a token is unique across all products, also when two creations race. | L-12, L-02, CORE-004 |
| BR-CAT-17 | A token is handled as text: leading zeros, case and punctuation are kept; only the scanner terminator and outer ASCII spaces are removed; internal control characters and line breaks are refused; length is 1 to 128 printable ASCII characters. When a token is declared as EAN or UPC, its check digit is validated. | L-12 |
| BR-CAT-18 | The server generates internal codes for Code 128 labels as `BK-P-` followed by 16 uppercase Crockford Base32 characters, retrying on a uniqueness clash. The `BK-` namespace is reserved: a manufacturer alias may not use it. It is a technical namespace, not the shop's identity, and codes are not changed when the shop's branding changes. | L-12 |
| BR-CAT-19 | A retired token is not reusable in general, by the same or another product. The only exception is the Owner's correction of a proven misassignment of a manufacturer barcode (BR-CAT-50, BR-CAT-51); it opens no general mechanism for reusing retired barcodes. A manufacturer barcode retired for any other reason stays unusable. Internal `BK-P-…` codes are never reused. | L-02, L-12, CORE-004, D-051, D-052 |
| BR-CAT-20 | Barcodes that the shop already uses keep their identity when they are proven through gate G2. The repository holds no live barcode registry. | L-12 |
| BR-CAT-21 | A token never contains a cost or an executable command. | L-12 |
| BR-CAT-22 | The Owner and OPERATIONS_ADMIN add aliases, generate internal codes and retire tokens; retiring a token records its reason (BR-CAT-52). Only the Owner corrects a misassignment (BR-CAT-50, BR-CAT-51). CASHIER never creates a product or an alias, also not from an unknown scanned token. | L-12, L-06, L-04, D-051, D-052 |
| BR-CAT-50 | The Owner, and only the Owner, may move a manufacturer barcode that is active on the wrong product to the correct product. In one audited action, with a mandatory reason and recent authentication, the mapping on the wrong product is retired as a misassignment and the same token is attached to the correct product. The mapping history — from which product to which, when, by whom and why — is kept. Earlier Sales stay on the product of that time, because Sale lines reference the product, not the token. | D-051 |
| BR-CAT-51 | The Owner, and only the Owner, may attach a manufacturer barcode that is already retired to the correct product, only as a verified misassignment correction: the token is a manufacturer barcode, never a `BK-P-…` code; its retirement is recorded as a misassignment; its earlier product mapping is traceable; a correction reason is mandatory; recent authentication is required; the earlier mapping history stays and is audited; after the correction the token is active on exactly one product. | D-052 |
| BR-CAT-52 | Retiring a token records whether it was retired as a misassignment or for another reason. The retirement record is history and is never rewritten (BR-AUD-03); a correction adds a new, audited mapping fact. A token is active on at most one product at a time (BR-CAT-16). The exact list of retirement reasons and their Indonesian labels are interface copy, approved at gate P7-G2; only the distinction between a misassignment and another reason is a rule. Mappings, their history and the retirement reason are stored as SCHEMA section 5.6 describes. | D-052, D-051, BR-AUD-03 |

## 4. Product Search

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-23 | One shared Product Search serves the till, receiving, manual issue, product management, stock lookup and labels. | L-12 |
| BR-CAT-24 | Matching order: exact barcode first; then SKU exact and prefix; then name, case-insensitive; with category and optional brand filters. | L-12 |
| BR-CAT-25 | Results are paged by the server: 25 by default, at most 100, in a stable order by name and then identifier. A newer search supersedes a stale one. The whole product list is never sent to the browser. | L-12, L-P6 |
| BR-CAT-26 | What a result shows depends on the role: identity, name, unit, selling price, the stock the role may see, and the photo thumbnail. It never shows acquisition cost, valuation, cost of goods sold or profit. | L-12, D-033, D-037 |
| BR-CAT-27 | Active products can be selected for posting and sale; inactive or historical results are marked read-only. | L-12 |

## 5. Scanning

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-28 | Scanners are keyboard (HID) input: USB HID on the PC, Bluetooth HID on the tablet, with manual entry as the fallback. No proprietary driver or WebUSB integration. No timing heuristic decides whether input is a barcode. Camera scanning is outside V1. | L-12, D-039 |
| BR-CAT-29 | A scan only changes a draft. Each complete scan of a code adds one base unit of its product; an empty terminator or key repeat adds nothing. Fractional quantities need an explicit quantity edit. Scan feedback never claims that stock was saved. | L-12 |
| BR-CAT-30 | Scans are applied in the order they were made, even if their lookups finish in a different order. A late or stale lookup never changes a newer selection or the draft. | L-12, CORE-006 |
| BR-CAT-31 | An unknown token stays available for search. Only an authorized actor may then create a product or an alias from it, and never with automatic stock. A failed lookup keeps the token. | L-12 |
| BR-CAT-32 | A scanner terminator (Enter) never confirms a payment, a posting or a cash session close. Scan capture pauses while a dialog, a review or a cash count is open. | L-12, D-040 |
| BR-CAT-33 | Scanning at the till needs an authenticated actor with their own eligible OPEN cash session. Scanning never opens, hands over or closes a cash session. | L-12 |
| BR-CAT-34 | Without a connection, posting stops. There is no offline queue of changes. | L-12, L-PRD §Batas Later |

## 6. Labels

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-35 | Labels use Code 128 from an open-source library whose licence is verified: black on white SVG with quiet zones, real size in millimetres, and readable code, name and SKU. A label never shows cost. | L-12 |
| BR-CAT-36 | Labels print through the browser and operating system. The 50 × 30 mm candidate size needs confirmation on the real printer (gate DEVICES). A print request is not proof of ink. A reprint uses the same code, and the request is audited. | L-12, D-039 |

## 7. CSV product import

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-37 | A CSV import creates new products only. It never creates opening stock, a stock movement, acquisition cost, cost of goods sold or any other financial fact; imported products start with stock 0. Opening stock and its cost go through the opening stock workflow (INVENTORY). | D-037, L-06 |
| BR-CAT-38 | An import never updates or overwrites an existing product, never succeeds partly and never skips a row silently. It shows a preview with validation before confirmation, validates again at confirmation, and processes a confirmed file all-or-nothing. | D-037, L-06 §Batas Later |
| BR-CAT-39 | The header and columns are explicit and strict. Barcode and SKU are read as text, keeping leading zeros. Duplicates inside the file and conflicts with existing products are found before anything is committed. Unknown or unexpected columns are refused. The file is UTF-8; spreadsheet formulas are not accepted. File size and row count are limited: at most 2 MiB and 5,000 data rows (SECURITY_MODEL section 13); the column list and the dialect are set in RF-5. | D-037, L-06 §Batas Later |
| BR-CAT-40 | An import is idempotent and durable: a retry or a lost response never creates duplicate products. The uploaded file is temporary working data of the import command: it is not kept after the command reaches its final lifecycle outcome, is never a business record and cannot be downloaded again; the audit keeps the import result — the products created, a row summary, the file's checksum, the actor and the time. | D-037, D-044, D-054 |
| BR-CAT-41 | Only roles that manage the catalogue (the Owner and OPERATIONS_ADMIN) may import, enforced on the server. | D-037, L-04 |
| BR-CAT-42 | It is not a general spreadsheet or enterprise import engine: XLSX is outside V1 unless decided separately, and the import is never extended to stock or cost. Whether a row may name a category that does not exist yet is T-17 h. | D-037 |
| BR-CAT-43 | The upload rules of SECURITY_MODEL section 13 apply to CSV files. | D-013 (status), D-037, D-054 |

## 8. Product photos

| ID | Rule | Source |
| --- | --- | --- |
| BR-CAT-44 | A product has at most one primary photo, and the photo is optional. A product without a photo is valid and shows a simple, consistent placeholder. | D-037 |
| BR-CAT-45 | At the till the cashier sees a thumbnail in search results and product lists, and may see the photo on a detail view or in the cart. The photo is never the only identity of a product: name, barcode or SKU and the text information stay clear. | D-037 |
| BR-CAT-46 | Only the Owner and OPERATIONS_ADMIN upload, replace or remove a photo; CASHIER only views it. The file of a replaced or removed photo is kept: it is no longer shown, stays in backups and is never deleted. | D-037, D-042 |
| BR-CAT-47 | Photos are JPG, PNG or WebP, with type, file size and dimensions validated, under the upload rules of D-013. Thumbnails may be stored and served for the till. The limits are in SECURITY_MODEL section 13. | D-037, D-013 (status) |
| BR-CAT-48 | There is no gallery, crop editor, camera capture, background removal or bulk image import in V1. A photo changes no stock, costing, pricing or catalogue audit rule. | D-037 |
| BR-CAT-49 | Product photos are part of the data to be backed up. This is input to question 18 (backup destination), not a decision on it. | D-037, OPEN_ITEMS question 18 |

## 9. Lifecycle

The lifecycle of Category, Product, Barcode, SellingPriceRevision and product photo is in [DOMAIN_MODEL](DOMAIN_MODEL.md) section 11.1.

## 10. Outside V1

Pack conversion, serial numbers, batches, expiry dates and consignment; XLSX import and any import of stock or cost; product photo galleries and bulk image import; camera scanning; price books, discounts and promotions; supplier purchasing (L-PRD §Batas Later, D-036, D-037, D-039).

## 11. Open items

| Item | Subject |
| --- | --- |
| T-17 h | A CSV row that names a category that does not exist yet |
| Gate G2 | Real units, precisions, barcodes and product volume of the first store |
| Gate DEVICES | Real scanners and label printer |

Revalidation item R-17 (questions 26 and 31) is resolved by D-051 and D-052; T-17 c (question 30) by D-054; T-17 g in RF-3B (BR-CAT-05).
