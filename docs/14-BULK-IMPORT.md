# 14 — Bulk import and stock onboarding

Canonical templates, validation, atomicity, recovery, results and bulk actions. [06](06-INVENTORY-SPEC.md) owns ledger posting; [04](04-AUTH-RBAC-SECURITY.md) permissions; RV02 in [05](05-DESIGN-SYSTEM.md) presentation. These are specifications; no template files or implemented features exist.

## Core scope

Support macro-free **XLSX** and **UTF-8 CSV**, at most 5,000 data rows/job. XLSX is the primary download because it preserves text identity and carries instructions/reference values. CSV supports interoperability. Reject XLS, XLSM, ODS, encrypted workbooks, formulas, external links and cloud-spreadsheet connections.

Three distinct jobs: **Impor Produk & Jasa**, **Impor Saldo Awal Kuantitas**, **Impor Saldo Awal Berserial**. Product import MUST NOT create physical items, stock quantities, balances, movements, costs or publication. Opening is enabled only after verified ledger commands exist.

Core product mode is **Tambah Baru Saja**. Existing active/inactive SKU rejects the file; no silent skip/upsert. Future update mode requires a field allowlist, before/after preview, version checks and audit. Individual authorized product editing remains available.

## Product template v2

XLSX sheets: **Petunjuk**, **Produk**, and current active **Kategori**, **Merek**, **Satuan** references. CSV contains only the Products table with separate UI instructions. Headers use exact Indonesian names. CSV upload metadata carries templateVersion; XLSX instructions carry it. Examples belong in instructions, never importable data rows.

| Column | Validation | Effect |
| --- | --- | --- |
| SKU | Required text, ASCII letters/digits/dot/slash/hyphen/underscore, length 1–64; outer trim/uppercase | New identity; preserve leading zeros |
| Jenis Item | Required **Barang** or **Jasa** | commercialType GOODS/SERVICE; no implicit default |
| Harga Jual | Required nonnegative IDR amount, two decimal places | Audited initial selling price; no cost fields |
| Nama Produk | Required plain text 1–200 after trim | Internal name |
| Kode Kategori | Required existing active code | Reference, no typo-driven category creation |
| Kode Merek | Optional existing active code | Blank means unspecified, no automatic Other brand |
| Kode Satuan | Required existing active base unit | Unit/precision from reference |
| Jenis Pelacakan | GOODS: required **Kuantitas** or **Berserial**; SERVICE: blank | No fake service tracking |
| Stok Minimum | GOODS: optional exact decimal ≥0; blank proposes zero; SERVICE: blank | Owner confirms batch; goods monitoring stays off |
| Barcode Produsen | GOODS: optional text alias under 12; SERVICE: blank | No reserved RP-/legacy LT- code or item serial |
| Deskripsi | Optional plain text, max 2,000 characters | Unpublished content |

Generate product ID/version/creator/time and active state. GOODS gets internal code/GoodsProfile and monitoring off. SERVICE gets neither. Reject service barcode/minimum/tracking/location/serial data, even explicit zero in a prohibited field. New product content remains draft once publication exists.

Reject unknown columns, including actual stock, location, item serials, purchase cost, COGS/margin, supplier terms, user/role, internal IDs, timestamps/audit, publication flags or arbitrary metadata. Never silently ignore them.

CSV defaults to comma delimiter; semicolon is an explicit parse option, never guessed from numerical content. Decimal values use comma with no grouping separator; quote them under comma delimiter. Reject ambiguous 1.000. XLSX accepts numeric cells for quantity/minimum/selling price, but SKU/barcode/serial cells MUST be text. Lost leading zeros require source correction, never reconstruction by guess. Distinguish blank and zero. Reject duplicate headers, hidden/filtered data rows, merged data cells and formulas.

## Workflow and validation

Upload → parse → validate → preview → summary → confirm → apply → result. All pre-apply work writes private staging only, never master/ledger/reference identities.

| Check | Required behavior |
| --- | --- |
| Structure | Verify version/sheets/headers, signature, encoding/delimiter, cell types, size/rows and formula prohibition |
| Values | Required fields, length, Indonesian enum, precision/range and identity normalization |
| In-file duplicates | Mark every conflicting normalized SKU/alias row; no last-row-wins |
| Master conflict | Existing active/inactive SKU/alias; missing/inactive category/brand/unit |
| Consistency | Serialized unit has integer precision; no wrong target/stock/finance fields |
| Authorization | Prepare permission plus separate apply permission; no worker privilege escalation |
| Freshness | Capture reference IDs/versions and normalized preview hash; revalidate during apply |

Preview shows total/valid/error row counts and causes. Valid + error rows equals data rows; multiple errors on a row do not inflate the row count. Default to problem rows with cause/SKU filter and 50-row pagination. Never render all 5,000 rows.

Each error includes original sheet/row, column, safe truncated supplied value, reason and deterministic correction advice when possible. Example: **Baris 28 · SKU · SKU sudah terdaftar.** Suggest a matching category only when unambiguous; never auto-apply fuzzy guesses. Sanitize formula/control prefixes in downloadable XLSX/CSV error reports. Financial/unexpected sensitive values must be redacted rather than echoed. Correct the file and upload a new revision; no full browser spreadsheet editor.

**One invalid row blocks the entire file.** No import-valid-rows-only mode. Summary shows creation count, default minimum choices, actor and validation time. Owner-only final action names the effect, e.g. **Tambahkan 1.250 Produk**. Record preparer and executor. This is owner batch execution, not a general approval engine.

## Jobs, transactions and recovery

| Entity | Contract |
| --- | --- |
| ImportJob | Type, uploader/optional executor, file hash, template/parser version, revision, state/counts, createdAt/expiry, reference, opaque ID |
| ImportRow | Original sheet/row, normalized values, error codes and reference versions; never queried as live master |
| ImportCommit | Unique job/revision, frozen payload/preview hash, executor, applied count and result reference; durable with business data |

States: UPLOADED → VALIDATING → INVALID or READY → QUEUED → APPLYING → SUCCEEDED. READY expires after 24 hours and needs a new validated revision. FAILED means known rollback only. NEEDS_REVIEW means changed reference/version/permission or expired authorization intent. CANCELLED only before APPLYING. Browser disconnect is not job failure.

- Parse/validate in 250-row staging batches, using PostgreSQL-backed jobs. This is not partial business commit.
- Input max 10 MiB; expanded XLSX max 50 MiB and 100 archive entries, checked before/during extraction. One apply job/company at a time; no external queue required.
- Confirmation freezes manifest/hash and actor authorization intent. Worker rechecks current active permission. Confirmation intent lasts 15 minutes in queue; expiry requires review/reconfirmation, not indefinite delegated privilege. Worker lease is not an owner credential.
- **One business transaction per file/job**: all products/internal codes/aliases/entity audit/batch audit/ImportCommit, or all OPENING legs/balances/items/health/inbox/outbox/audit/CommandReceipt/ImportCommit. Any constraint failure rolls back all.
- Do heavy parsing before locks. Lock the import job to claim application, then follow the global [02](02-ARCHITECTURE.md) order: command receipt, actor/policy/reference guards, locations/products, balances/reservations if applicable, then serials. Import skips unused commercial/shift guards; no other command locks an import job after these guards. No human waits inside locks.
- Initial apply transaction deadline 60 seconds; lock wait three seconds. Timeout rolls back the whole apply. UI says **Sedang menerapkan…**, never increasing committed-row counts before commit. Measure the bounded 5,000-row workload in R02.5 and verify its browser flow in R02.6; revise admission limits explicitly if necessary, never silently switch to partial import.
- Claim lease 120 seconds, fencing token on ACK. Reclaimed workers first check ImportCommit. Duplicate requests/reloads use the same job/revision. Successful receipt/normalized manifest outlive raw-upload TTL.
- Unknown outcome: check result/retry the same job, never create a replacement. A matching file hash may link to a prior job the actor may read; hash alone does not authorize replay or prove identical intent. Constraints prevent a successful reupload creating duplicate SKU/opening.
- Cancellation locks and removes queued work before apply. Do not promise cancellation during APPLYING. Success has no delete/undo-job button; product edits follow permission rules and stock correction uses reversal/adjustment.

## Separate quantity and serialized opening

Products/references must exist and be active. Owner establishes cutover and persistent location onboarding freeze before counting. All ordinary writers check freeze. It is not a long database transaction. Owner releases only after all results reconcile and monitoring setup is complete.

| Template | Required columns | Optional fields/rules |
| --- | --- | --- |
| Saldo Awal Kuantitas | SKU, Kode Gudang, Kode Lokasi, Jumlah | Required count reference in job metadata; one row/product-location, positive quantity at unit precision |
| Saldo Awal Berserial | ID Baris, SKU, Kode Gudang, Kode Lokasi | Optional Nomor Seri Produsen; optional Barcode Internal only for platform-issued REGISTERED items; implicit quantity one |

ID Baris is a unique text staging-row ID, not an official serial. Manufacturer serial follows product-scoped uniqueness; duplicate item/internal barcode is rejected globally, including IN_STOCK/ISSUED items. Blank manufacturer serial stays blank. Create stable item/barcode inside commit and print after success. Never accept an arbitrary RP- or legacy LT- spreadsheet code as platform-issued identity.

Opening accepts GOODS only. Do not mix tracking modes in one opening job. Zero counts create no movement; record the zero count in a separate cutover report and enable monitoring normally. Reject negative quantity, unknown product, wrong/inactive location, cross-product serial and any product/location with prior ledger. Owner acquisition evidence is separate under [15](15-FINANCE-PROFITABILITY.md), absent from warehouse templates.

OPENING carries owner actor, batch/count reference and job/revision, with normal command semantics. Interactive limit remains 200; the authorized worker supports up to 5,000 logical rows in **one movement/receipt/transaction**, never several silent commits.

Over 5,000 rows: reject and split only into independent product/location scopes. Do not split one serial product/location group across two openings; the first creates history. A single group above the cap needs a tested capacity change before onboarding, not disguised ordinary receipt or direct balance edit.

Keep monitoring off during multi-job count/import. After full cutover verification, activate monitoring under 13; all stocked goods must be monitored before pilot.

## Useful bulk operations

Core includes new-product import, separate opening, filtered product export and selected product/item label printing. Cross-page selection uses an explicit ID/version snapshot; at most 200 labels/batch. Filter change clears selection visibly. Printing is not physical posting.

Initial minimum is already owner-reviewed in product import. Later mass threshold changes need a real use case and alert-impact preview. Mass category/brand changes await update-import policy. Mass deactivation awaits per-SKU stock/reservation/open-operation checks. Bulk publication belongs to later owner-controlled catalog validation, not Core. Do not display unimplemented actions.

Exports are authorized DTOs with template/version/filter/time context, not database dumps or backups. They need not round-trip into import because generated/read-only columns differ. Exclude private cost/serial/location fields from ordinary product export. [09](09-DEPLOYMENT-OPS.md) owns recovery.

## Responsive UX and file security

At ≥768 px, provide upload/template/delimiter selection, paged conflicts/preview and confirmation. Phone shows summary/status/errors/history/report download and **Tinjau dan terapkan impor melalui tablet atau komputer.** No phone bulk-apply UI. This is a usability boundary, never user-agent authorization; backend permissions apply to every client.

Store files privately outside webroot with random keys and validated hash/size. Bound parser memory/CPU/archive expansion. Never evaluate formulas/macros, fetch external references, trust extension/MIME alone or run server-side Office automation. No sensitive raw values in logs. Apply retention under 09. Reference: [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html); numerical limits and atomicity are product decisions.
