# 12 — Product search, barcodes and scanner

## One reusable Product Search

Canonical service reused by POS, receiving, manual issue, product management, stock lookup and barcode/label. Exact barcode index lookup first; SKU exact/prefix and case-insensitive name search, category and optional brand filters. PostgreSQL B-tree unique indexes for barcode/SKU plus measured native name index (e.g. pg_trgm if justified/available); no external search.

Bounded server pagination, default 25/max 100, stable name/ID order, context filters and canceled stale requests. Never fetch whole master to browser. Search authorization shapes allowlisted operational DTO (identity/name/unit/selling price/permitted stock); no costs. Active products selectable for posting; historical/deactivated results clearly read-only.

Before creating, search exact barcode/SKU and show similar-name candidates using the same service. Require explicit “Tetap buat produk baru” after review; fuzzy match is warning, exact barcode/SKU collision is a hard DB rejection even under concurrency. Receiving resumes with selected existing/new product; product creation alone never receives stock.

## Identity

Manufacturer aliases and internal codes share globally unique tokens. Treat as strings: preserve leading zeros, case and punctuation; strip HID terminators/outer ASCII spaces only, reject internal controls/newlines, length 1–128 printable ASCII. Validate check digit for declared EAN/UPC. No pack conversion hidden in alias.

Server-generated Code 128 internal token: BK-P- plus 16 uppercase Crockford Base32 characters, uniqueness retry. Reserved BK- namespace, manufacturer aliases cannot claim it. Do not recycle retired codes or regenerate on branding change. Legacy issued codes, if any proven by G2, retain identity; inherited repository contains no live code registry. Barcode contains no cost or executable command.

## Scanning

USB/Bluetooth HID is keyboard input; no proprietary driver/WebUSB integration. Configure Enter suffix; dedicated scan field/manual Enter fallback. No timing heuristic for barcode validity. Each complete repeated code adds one base unit to draft; CR/LF empty terminators/key-repeat do not add. Preserve response order. Explicit quantity edit for fractional units. Visible delta/undo; no per-scan success toast claiming saved stock.

Pause capture during dialogs/review/session cash counting, do not steal focus from other inputs. POS capture requires the authenticated actor's own eligible OPEN CashSession; scanning never opens, hands over or closes a session. Unknown token stays available for search; authorized catalog creation only, never automatic stock. Scanner Enter cannot confirm payment/posting/session close. Lookup failure retains token; offline stops posting, no offline mutation queue. Camera scanning Later.

Freeze source intent/key/payload on final confirmation. SUBMITTING → confirmed COMMITTED or UNCERTAIN. Uncertain freezes edits/replacement, original status/retry only. Minimal per-tab actor-scoped recovery reference; no staff cost inputs or secrets stored. Reload reauthorizes; ordinary stale drafts revalidate products/prices. Never expire uncertain identity into a new Sale.

## Labels and evidence

Use license-verified open-source Code 128 library, black on white SVG, quiet zones, real mm dimensions and readable code/name/SKU. No purchase cost. Browser/OS label printing; candidate 50×30 mm requires actual printer confirmation. Print request is not proof of ink. Reprint uses same code and records request audit.

BK20/BK25 must test manual/IME input, fast duplicate scans, CR/LF, disconnection/partial input, stale lookup, focus/virtual keyboard, USB and Bluetooth HID, printed label readability and actual receipt printer. No hardware evidence may be invented.
