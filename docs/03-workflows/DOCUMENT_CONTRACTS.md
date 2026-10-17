# Document contracts

| | |
| --- | --- |
| Status | CURRENT — written in RF-5 under D-087, 18 October 2026 (Asia/Jakarta) |
| Phase | P3 — the contents, order and format of every document and file BantuKasir prints, exports or imports |
| Decisions | D-037, D-038, D-039, D-054, D-062, D-069 (h), D-077, D-086, D-087 |
| Related | [INTERACTION_CONTRACTS](INTERACTION_CONTRACTS.md), [ROUTE_CONTRACTS](ROUTE_CONTRACTS.md), [COPY_DECK](../07-ux-design/COPY_DECK.md) section 17, [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) sections 8.16 and 12, [SALES_CASH](../02-domain/SALES_CASH.md), [CATALOG_BARCODE](../02-domain/CATALOG_BARCODE.md) |

What each document holds and in which order, where the copy marker prints, and the exact format of the session export and the product import file. The words are those of COPY_DECK section 17 and its other keys; the look is DESIGN_SYSTEM. No document carries cost, profit, a tax figure or a claim of equivalence with a tax invoice (BR-DOC-03, BR-COST-08, REQ-NFR-10), and none carries the codename (CW-14). Dates and times print in Asia/Jakarta with the full Indonesian month name and a dotted time, for example `18 Oktober 2026 14.05` (REQ-DOC-05), unless a file format below says otherwise.

## DC-01 Receipt and "Nota Penjualan"

Rendered by RP-103P from the ReceiptSnapshot only (BR-DOC-01, BR-DOC-02), never from current records, in three templates: thermal 58 mm, thermal 80 mm and A4 "Nota Penjualan". Each prints only what the snapshot holds.

| Order | Element | Source and rule |
| --- | --- | --- |
| 1 | Copy marker "Salinan" (`documents.receipt.copy_marker`) | Printed on every print request of the Sale after its first (RC-44 records whether a request is the first). It is the first element of the paper: at the top, centred on thermal paper, at the top right of the A4 header; sentence case, bold, in a box (DESIGN_SYSTEM section 8.16). Never on the first print |
| 2 | Logo | The revision's logo when it has one |
| 3 | Shop name; document identity | From the BusinessProfile revision frozen in the snapshot; the document identity (up to 100 characters) on its own line under the name, when set |
| 4 | Address; contact; email | Each when set |
| 5 | Document header text | When set |
| 6 | Title "Nota Penjualan" | A4 only (`documents.invoice.title`) |
| 7 | No. :nomor; Waktu: :waktu; Kasir: :nama; Sesi kas: :nomor | The Sale number; the issue time; the cashier's frozen name; the session number |
| 8 | Pelanggan: :nama | Only when a name was entered |
| 9 | Lines, in the order of the cart | Each line: the product name in full, wrapping and never cut; then `SKU :sku`; then `:jumlah :satuan × :harga` and the line total (`Subtotal`). A4 shows the same values as a table with the columns Produk, Jumlah, Harga and Subtotal |
| 10 | Total | The Sale total |
| 11 | Pembayaran: :metode | Tunai or Transfer bank (D-077) |
| 12 | Uang diterima; Kembalian | CASH only |
| 13 | Document footer text | When set |

- Amounts print as `Rp15.000` (COPY_DECK section 1.3); quantities with the product's precision and a decimal comma.
- Paper is black on white whatever the accent (DESIGN_SYSTEM section 3.3).
- A reprint shows the same number and content with the copy marker and creates no Sale, Payment or movement (BR-DOC-04). A failed or abandoned print is requested again; a print request is not proof of ink.
- Templates are versioned in the code base and never changed in place; the snapshot names its template version (SCHEMA section 5.9).

## DC-02 Session report

Rendered by RP-206P from the CashSessionReportSnapshot only (BR-CASH-20), on A4, for a CLOSED session; a later refund, return or review note never changes it (BR-CASH-19).

| Order | Element | Source |
| --- | --- | --- |
| 1 | Shop name and document identity | The frozen revision |
| 2 | Title "Laporan sesi kas"; No. sesi: :nomor | The session's number is the report's number |
| 3 | Laci kas; Penanggung jawab | The register's frozen code and label; the responsible user's frozen name |
| 4 | Dibuka; Dihitung sampai; Ditutup | Opened time; the cutoff, which is the moment of the close under the session lock; closed time |
| 5 | Modal awal | Opening cash |
| 6 | Jumlah penjualan; Nilai penjualan | Completed Sales of the session |
| 7 | Pembayaran tunai; Pembayaran transfer bank | Applied CASH and BANK_TRANSFER, separately (BR-PAY-06) |
| 8 | Pengembalian dari laci kas | One row per drawer-funded refund: amount, Sale number, time and the Owner's name (`documents.session_report.drawer_refund_row`) |
| 9 | Uang seharusnya di laci; Uang fisik di laci; Selisih | Expected cash, physical cash, and the variance with its word (COPY_DECK section 1.4) |
| 10 | Ditutup oleh; Alasan penutupan | The close actor; the reason of an Owner-assisted close |
| 11 | Catatan | The snapshot's safe notes, when present |
| 12 | Zona waktu: WIB (Asia/Jakarta) | — |

Owner review notes are linked facts, not part of the report: RP-206 shows them to the Owner beside the report (D-068, D-069 (f)). A print or an export never changes the CLOSED status; a retry renders the same snapshot (BR-CASH-22). No copy marker is printed on a session report.

## DC-03 Session report export (CSV)

RC-46 returns the file as its answer (`text/csv; charset=utf-8`, attachment). It holds the fields of DC-02 from the same snapshot.

| Item | Rule |
| --- | --- |
| Encoding | UTF-8 with a byte-order mark, so that spreadsheet programs read it as UTF-8 |
| Separator; line end | Semicolon `;` (the list separator of Indonesian regional settings); CRLF |
| File name | `laporan-sesi-<session number>.csv`, with every character other than letters, digits and hyphens replaced by a hyphen |
| Layout | First the line `Laporan sesi kas;<session number>`; then one line per field of DC-02 rows 3 to 11 as `<label>;<value>`; then the time-zone line; then an empty line; then the drawer-funded refunds as a table with a header line (Penjualan; Referensi; Jumlah (Rp); Waktu; Oleh) and one line per refund |
| Labels | The COPY_DECK keys of section 17.2; money labels carry " (Rp)" (`documents.session_report.money_header`) |
| Money | Whole rupiah as plain integers, unquoted, without separators; a negative variance stays a negative number (`-1500`), with its word in the next field (BR-CASH-22) |
| Times | `YYYY-MM-DD HH:MM` in Asia/Jakarta, with the time-zone line |
| Text | Every text field quoted with `"`, an inner `"` doubled; a text field that begins with `=`, `+`, `-`, `@`, a tab or a carriage return gets a leading apostrophe so that no spreadsheet reads it as a formula (BR-CASH-22; SECURITY_MODEL SEC-CH-07) |

## DC-04 Product import file (CSV)

The format that RR-01 previews and RC-31 confirms (BR-CAT-37 to BR-CAT-43, SEC-UP-05). The parser accepts the CSV that common spreadsheet programs export with Indonesian regional settings.

### File

| Item | Rule |
| --- | --- |
| Encoding | UTF-8; a leading byte-order mark is removed; any other encoding, invalid UTF-8 or a control character other than a line break is refused (`ux.validation.upload.csv_type`) |
| Size | At most 2 MiB and 5,000 data rows plus one header row (`ux.validation.upload.too_large`, `ux.validation.upload.csv_rows`) |
| Separator | Semicolon or comma, decided by the header line: the one of the two that splits the header into known column names; if neither or both do, the file is refused (`catalog.csv_import.error.missing_column`) |
| Quoting | A field may be enclosed in `"`; an inner `"` is doubled; a line break inside a field is a row error (`catalog.csv_import.error.invalid_value`) |
| Line ends | CRLF or LF; empty lines at the end are ignored; a row with a different number of fields than the header is a row error |
| Formulas | A cell that begins with `=` is a row error (`catalog.csv_import.error.formula`) |

### Columns

Header names are compared after trimming outer spaces and without regard to letter case; each column appears once, in any order. A missing required column or an unknown column refuses the file (`catalog.csv_import.error.missing_column`, `catalog.csv_import.error.unknown_column`). The header texts below are proposals for COPY_DECK change control (INTERACTION_CONTRACTS section 11); until they are approved, a Task that ships the import uses the approved texts.

| Column (proposed header) | Field | Required | Value |
| --- | --- | --- | --- |
| SKU | `sku` | Yes | Text, 1 to 64 characters after trimming; kept as typed, including leading zeros and letter case; unique without regard to letter case, in the file and among existing products (`sku_duplicate`, `sku_taken`) |
| Nama | `name` | Yes | 1 to 200 characters |
| Kategori | `category` | Yes | Matched after trimming outer spaces and without regard to letter case against the names of ACTIVE categories only: exactly one match is required. No match — including a name that only an inactive category has — is `category_missing`; more than one is `category_ambiguous` (D-069 (h)). The import never creates a category |
| Merek | `brand` | No | Up to 100 characters |
| Satuan | `baseUnit` | Yes | 1 to 32 characters |
| Desimal | `quantityPrecision` | Yes | An integer 0, 1, 2 or 3 |
| Harga jual | `sellingPrice` | Yes | Whole rupiah from 0 to 999.999.999.999: digits, optionally with full stops between groups of three and an optional leading `Rp` or `Rp ` (for example `15000`, `15.000`, `Rp15.000`); a trailing `,0` or `,00` is accepted; anything else is `invalid_value` |
| Stok minimum | `minimumStock` | No (0 when empty) | Zero or more, with at most the row's `Desimal` decimals, written with a decimal comma and optional full stops between groups of three (for example `2`, `1,5`, `1.500`) |
| Barcode | `barcode` | No | A manufacturer token: 1 to 128 printable ASCII characters after removing outer spaces, kept as text with its leading zeros (BR-CAT-17); a value in scientific notation such as `8,99E+12` is `invalid_value`; not in the `BK-` namespace (`barcode_reserved`); never mapped to any product before (`barcode_taken`), and unique in the file (`barcode_duplicate`). Recorded without a declared symbology |

The message keys are those of COPY_DECK section 16.3 (`catalog.csv_import.error.*`), each prefixed with its row number (`catalog.csv_import.error.row`). Every row is validated; the preview shows every error; nothing is skipped. At confirmation the whole file is validated again against its checksum, and a category deactivated after the preview refuses the import (`CATEGORY_INACTIVE`; INTERACTION_CONTRACTS RC-31). Imported products are active, start with stock 0 and have no photo, movement or cost (BR-CAT-37).

## DC-05 Barcode label

Rendered by RP-306P for the token and count that RC-47 recorded (BR-CAT-35, BR-CAT-36).

| Item | Rule |
| --- | --- |
| Symbol | Code 128 as SVG, black on white, with quiet zones of at least ten modules on each side, from an open-source library whose licence is verified |
| Size | 50 × 30 mm candidate, at real size in millimetres; the real printer and size are confirmed at gate DEVICES |
| Content, top to bottom | The product name, at most two lines, shortened with an ellipsis beyond them (the code and SKU identify the product); the bars; the code in readable characters; `SKU :sku` (`documents.label.sku`) |
| Never | A cost, a price or anything else not listed |
| Count | 1 to 100 labels per print request; a reprint uses the same code and is audited (`PRINT`) |
