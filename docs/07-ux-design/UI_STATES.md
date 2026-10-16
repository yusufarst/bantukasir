# UI states

| | |
| --- | --- |
| Status | CURRENT — written in RF-4A under D-070 and, after the stop on finding F-03, D-071; corrected after the independent final review under D-072 to D-075 and after the targeted re-verification of that remediation (D-075); 11 and 12 October 2026 (Asia/Jakarta) |
| Phase | P7 — screen states, error mapping and recovery (AICWDF sections 18.9 and 27; REQ-UX-02) |
| Gate | P7-G1, navigation and page structure (D-025): **artifacts ready for the Owner's review — NOT APPROVED**. Approval is a separate Owner decision that names the RF-4A checkpoint commit and the hash of each artifact (D-070) |
| Decisions | D-017, D-018, D-024, D-025, D-032, D-033, D-036, D-039, D-040, D-047, D-049, D-067 to D-075 |
| Related | [USER_TASKS](USER_TASKS.md), [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md), [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) |

Every state a page can be in, what the user sees, how the user recovers, which rule it comes from, and which state answers each error code of [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 4. States are numbered `UXS-nn`; identifiers are permanent. The copy key names where the wording will live; the wording is approved at gate P7-G2 (RF-4B). This document adds no business rule.

Indonesian labels in quotation marks are **working labels**, taken from the [GLOSSARY](../02-domain/GLOSSARY.md) where it has a term; they stay provisional until gate P7-G2 (terminology and copy, RF-4B). Nothing here is visual design (RF-4C, gate P7-G3) or an HTTP route (RF-5).

## 1. Rules

- **UNKNOWN is never zero**, and a failed or stale read is never shown as zero, empty or "Normal" (BR-DOM-08, BR-COST-03, BR-INV-41).
- **A definite rejection writes nothing** and returns to an explicit review state; it never changes a payable total silently (TR-ERR-05, BR-SALE-19).
- **An uncertain outcome is never a failure.** The page never turns UNCERTAIN or HELD into a rejection, never creates a new intent for the same business action while either lasts, and never asks the customer to pay again (TR-ERR-01, TR-ERR-06, BR-SALE-20).
- **HELD is never repeated.** An identity conflict is resolved by the intent's actor or the Owner; an intent from before a restore is reconciled by the Owner under the restore runbook (ADR-007 section 5). Its status check stays available: the server answers it from the restore reconciliation, and the reference is released only by a verified outcome or an audited reconciliation recorded with a safe fence, never cleared by the client (D-073).
- **Errors reveal nothing** outside the viewer's scope, no internal detail and no cost the role may not see (TR-ERR-02, TR-ERR-03, TR-ERR-04, SEC-CH-10).
- **Every state is designed per page**, not shown by a global switch (re-foundation analysis section 20); section 6 lists the states of every page.

## 2. Catalogue

| ID | State | Working label | Group | When | What the user sees | Recovery | Source | Copy key |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXS-01 | Loading | "Memuat" | General | A read is in progress and nothing has arrived yet | A placeholder in the area being loaded; never a number, zero or an empty list (BR-DOM-08); after about one second a visible progress indicator; announced politely to screen readers | None needed; a slow read ends in data or UXS-22 | BR-DOM-08, REQ-UX-02 | `ux.state.loading` |
| UXS-02 | Empty | "Belum ada data" | General | A successful read returned no records and no filter is applied | A short statement of what will appear here and, when the viewer may create the first record, the page's primary action | The page's primary action, when the role holds it | REQ-UX-02 | `ux.state.empty` |
| UXS-03 | No results | "Tidak ada hasil" | General | A successful read with a search or filter returned nothing | The search or filters used, and a way to clear them | "Hapus filter" or a new search | REQ-UX-02, BR-CAT-31 (an unknown code stays in the field) | `ux.state.no_results` |
| UXS-04 | Committed | "Berhasil disimpan" | Command outcome | The server confirmed the durable result (COMMITTED) | The success destination of the navigation contract, with a short confirmation that names what was saved; for a Sale, the receipt with no further step (BR-SALE-14) | None | ADR-007 section 5, BR-SALE-14 | `ux.state.committed` |
| UXS-05 | Validation error | "Periksa isian" | Command outcome | VALIDATION_FAILED, or a field rule checked in the page before sending | Each message beside its field and a summary at the top that receives focus; the form stays filled (passwords excepted, SEC-CH-03); nothing was saved | Correct the fields and submit again | TECHNICAL_RULES section 4, TR-ERR-05 | `ux.state.validation` |
| UXS-06 | Submitting | "Sedang diproses" | Command outcome | A command was sent and no answer has arrived (SUBMITTING) | The pressed action shows that it is working; edits and repeated submissions are blocked (BR-SALE-18); after a few seconds "masih diproses"; never reports failure because of time alone | Wait; the page moves to UXS-04, a refusal, or UXS-14 | ADR-007 section 5, TECHNICAL_RULES section 9 (client wait), BR-SALE-18 | `ux.state.submitting` |
| UXS-07 | Permission denied | "Anda tidak memiliki akses" | General | FORBIDDEN: the role lacks the capability (normally unreachable through the interface, SEC-AZ-07) | A plain statement and the way back to the role's home; no detail of what exists | "Kembali ke halaman awal" (the role's home, NC-12) | SEC-AZ-07, TECHNICAL_RULES section 4 | `ux.state.forbidden` |
| UXS-08 | Not found | "Data yang Anda cari tidak ditemukan" | General | NOT_FOUND: the object does not exist or is outside the viewer's scope (one answer for both, TR-ERR-03) | A plain statement and the contextual way back; it never says whether the object exists | Contextual back, or the role's home | TR-ERR-03, SEC-AZ-03 | `ux.state.not_found` |
| UXS-09 | Sign-in session ended | "Sesi masuk berakhir" | General | UNAUTHENTICATED: idle limit (30 minutes), absolute limit (12 hours), or a revocation of SEC-SESS-04: sign-out of this sign-in session (for example in another tab), deactivation, role change or anonymization of the account, the consumption of a one-time link for it, the user's own password change made in another sign-in session, Owner recovery or replacement, or a restore from backup (SEC-SESS-02, SEC-SESS-04) | UXP-001 after a full page load, with a neutral note that the sign-in session ended; no earlier page data stays visible (SEC-CH-04); a cash session is never closed (BR-CASH-11) | Sign in again; unresolved command references are resolved afterwards (ADR-007 section 5) | SEC-SESS-02, SEC-SESS-03, SEC-CH-04, TECHNICAL_RULES section 4 | `ux.state.signed_out` |
| UXS-10 | Page must be reloaded | "Muat ulang halaman" | General | CSRF_FAILED | A statement that the page is out of date and nothing was saved | "Muat ulang"; then repeat the action | SEC-HDR-01, TECHNICAL_RULES section 4 | `ux.state.reload` |
| UXS-11 | Recent authentication | "Verifikasi ulang" | Command outcome | RECENT_AUTH_REQUIRED, or the page knows that the last confirmation is older than 15 minutes, for a capability marked "recent auth" | The recent-authentication dialog of UXP-000 asks for the password; the form and review stay as they were; nothing ran | Confirm the password; the same request is then sent (TECHNICAL_RULES section 4) | SEC-SESS-05, TECHNICAL_RULES section 4 | `ux.state.recent_auth` |
| UXS-12 | Too many attempts | "Terlalu banyak percobaan" | General | THROTTLED (a rate limit of SECURITY_MODEL section 11) | A statement that nothing ran and when to try again (from the retry time) | Wait, then repeat | SECURITY_MODEL section 11, TECHNICAL_RULES section 4 | `ux.state.throttled` |
| UXS-13 | Disabled with a reason | "Tidak dapat dipakai sekarang" | General | A control is visible but cannot be used in the current condition | The control looks disabled and its reason is shown next to it and exposed to assistive technology; it is never a control that does nothing (AICWDF section 14.3). When the reason is an unresolved request (NC-13), the reason carries the link "Lihat permintaan" to UXP-006 | Change the condition named in the reason | AICWDF sections 14.3 and 25, REQ-UX-02 | `ux.state.disabled` |
| UXS-14 | Uncertain | "Status belum pasti" | Command outcome | SERVICE_BUSY, a timeout, a lost connection, any 5xx answer or an unreadable answer to a command (UNCERTAIN) | The request is frozen and shown as not yet known; the page names what must not happen meanwhile (for a Sale: do not ask the customer to pay again; for a drawer-funded refund: take no cash from the drawer) | "Kirim ulang" (the same frozen request) or "Cek status" (resolution); the reference is kept across a reload, a closed tab or a sign-out (UXP-006) | ADR-007 sections 4 and 5, TR-ERR-01, TR-ERR-06, BR-SALE-20, BR-RFD-17, BR-CMD-03 | `ux.state.uncertain` |
| UXS-15 | Held | "Status belum pasti" | Command outcome | IDEMPOTENCY_CONFLICT or EPOCH_STALE (HELD) | Shown as uncertain; the request is never repeated; IDEMPOTENCY_CONFLICT: "Cek status" by the actor (or the Owner); EPOCH_STALE: a statement that the request dates from before a restore and that the Owner reconciles it under the restore runbook, with "Cek status" | "Cek status". For EPOCH_STALE the server answers it from the restore reconciliation: until the reconciliation has recorded an outcome, the answer is EPOCH_STALE again and this state stays; then COMMITTED, or NOT_COMMITTED recorded by an audited reconciliation with a safe fence. The client never clears the reference itself (D-073; mechanisms RF-5, RF-6) | ADR-007 section 5, TECHNICAL_RULES section 4, D-073 | `ux.state.held` |
| UXS-16 | Request no longer valid | "Permintaan lama sudah tidak berlaku" | Command outcome | INTENT_ABANDONED: the intent was fenced by a resolution and can never commit | A statement that nothing was saved by this request and that a new draft may be started | Start again from the draft (a new intent) | ADR-007 section 4, TECHNICAL_RULES section 4 | `ux.state.abandoned` |
| UXS-17 | Data changed | "Data sudah berubah" | Command outcome | STALE_VERSION: what was reviewed no longer matches | The current values beside what the user reviewed; nothing was saved | Review the current values and submit again | TECHNICAL_RULES section 4, BR-SALE-19 | `ux.state.stale_version` |
| UXS-18 | Price changed | "Harga berubah" | Domain | PRICE_CHANGED at checkout, or a revalidated cart after a reload (BR-SALE-22) | UXP-101 with every changed line marked (old and new price) and the new total; "Bayar" waits for "Setuju harga baru"; the total never changes silently | "Setuju harga baru", then "Bayar" | BR-SALE-05, BR-SALE-19, TR-ERR-05 | `ux.state.price_changed` |
| UXS-19 | Recount required | "Ada transaksi baru; hitung ulang kas" | Domain | RECOUNT_REQUIRED at close: a Sale or a drawer-funded refund changed the session after the preview | Step 1 of the close with a new preview and the message; the session stays OPEN | Count again and close | BR-CASH-14, TECHNICAL_RULES section 4 | `ux.state.recount` |
| UXS-20 | No connection | "Tidak ada koneksi" | General | The browser reports no network, or requests fail before reaching the server | A banner that saving is stopped; drafts stay in page memory while the page is open; nothing is queued (BR-CAT-34); a command already sent becomes UXS-14 | Wait for the connection; then repeat | BR-CAT-34, REQ-SCAN-04 | `ux.state.offline` |
| UXS-21 | Not refreshed | "Data belum diperbarui" | General | A live read (stock attention, the badge, stock list) failed to refresh | The last data with the time of its last refresh and a marker that it may be out of date; never an empty list, zero or "Normal" (BR-INV-41) | "Muat ulang"; the automatic refresh continues | BR-INV-41, BR-DOM-08 | `ux.state.stale_read` |
| UXS-22 | Could not load | "Gagal memuat" | General | A read failed (server error, timeout) with no earlier data to show | A plain statement in the area that failed; other areas of the page stay usable; never zero or empty (BR-DOM-08) | "Coba lagi" | BR-DOM-08, TR-PAGE-05, SEC-CH-10 | `ux.state.load_failed` |
| UXS-23 | Sign-in session about to end | "Sesi masuk akan berakhir" | General | The client timer is about to reach the idle limit (SEC-SESS-02) | A dialog with the time left; it is announced to screen readers | "Tetap masuk" (a user-activity request) or "Keluar" | SEC-SESS-02 (client timers), REQ-UX-03 | `ux.state.session_warning` |
| UXS-24 | Unknown cost | "Biaya perlu dilengkapi pemilik" | Domain | An acquisition cost is UNKNOWN | The words of the glossary in place of a number; never Rp0 or a blank (BR-COST-03) | For the Owner: "Lengkapi biaya" (UXP-504); for Ops: none | BR-COST-03, BR-INV-13, D-033 | `ux.state.cost_unknown` |
| UXS-25 | Incomplete cost figures | "Data biaya belum lengkap" | Domain | A figure depends on UNKNOWN cost, or a product carries an active "reporting incomplete" mark | Sales and payment totals still shown; cost of goods sold, gross profit and margin marked incomplete; the number of lines with unknown cost and of marked products (BR-COST-38) | Link to UXP-503 | BR-COST-38, BR-COST-27, REQ-RPT-03 | `ux.state.cost_incomplete` |
| UXS-26 | Owner correction mark | "Dikoreksi Pemilik" | Domain | A confirmed receiving line whose cost the Owner corrected or completed, seen by Ops or Combined | The mark, the current cost and the cost at confirmation; never the reason or history (D-047) | None | D-047, BR-INV-15, CAP-12 | `ux.state.owner_corrected` |
| UXS-27 | Onboarding active | "Masa input stok awal aktif" | Domain | ONBOARDING_FREEZE, or the onboarding freeze is known to be on | A banner on every page; Sales and ordinary postings explain that they are stopped until the Owner ends the onboarding; the Owner gets a link to UXP-412 | Owner: UXP-412; others: wait | BR-INV-24, BR-INV-11, TECHNICAL_RULES section 4 | `ux.state.onboarding` |
| UXS-28 | Postings blocked | "Mutasi diblokir" | Domain | POSTING_BLOCKED: the product's postings are blocked after a reconciliation mismatch | The product named as blocked; nothing was saved; the Owner gets a link to the incident | Owner: UXP-415; others: tell the Owner | BR-INV-45, BR-INV-46, TECHNICAL_RULES section 4 | `ux.state.blocked` |
| UXS-29 | No OPEN cash session | "Belum ada sesi kas" | Domain | NO_OPEN_SESSION, or the till opened without an OPEN session of the user's own | UXP-104 (Buka sesi kas) | Open a session | BR-SALE-02, TECHNICAL_RULES section 4 | `ux.state.no_session` |
| UXS-30 | Cash session not open | "Sesi kas sudah ditutup" | Domain | SESSION_NOT_OPEN: the session was closed (for example by an Owner-assisted close) or a selected refund source closed meanwhile | A statement that the session is closed and nothing was saved; for a cashier, the way to open a new session; for a refund, back to the choice of source | Open a new session (UXP-104), or choose another source (UXP-203) | BR-CASH-19, BR-RFD-09, TECHNICAL_RULES section 4 | `ux.state.session_closed` |
| UXS-31 | Insufficient stock | "Stok tidak cukup" | Domain | INSUFFICIENT_STOCK | The lines concerned with the available stock; nothing was saved; a warning is never an override (BR-INV-20) | Change the quantities | BR-INV-04, BR-INV-20, TECHNICAL_RULES section 4 | `ux.state.insufficient_stock` |
| UXS-32 | Inactive product or category | "Nonaktif" | Domain | PRODUCT_INACTIVE or CATEGORY_INACTIVE; also every display of an inactive record | The record marked inactive and read-only; it cannot be chosen for a posting, a Sale or a product (BR-CAT-08, BR-CAT-09, BR-CAT-27) | Remove the line or choose another; the role that may reactivate it can do so on its page | BR-CAT-08, BR-CAT-09, BR-CAT-27 | `ux.state.inactive` |
| UXS-33 | Unknown code | "Kode tidak dikenal" | Domain | A scanned or typed code matches no product | The code stays in the field for search (BR-CAT-31); no product is created automatically | Search by name; where allowed, "Buat produk baru" (UXP-405, UXP-413) | BR-CAT-31 | `ux.state.unknown_code` |
| UXS-34 | Tendered amount too low | "Uang diterima kurang" | Domain | TENDER_INSUFFICIENT, or the amount entered is below the total | The final action stays disabled with the missing amount shown (BR-SALE-11) | Enter the received amount, or "Uang Pas" | BR-SALE-11, BR-PAY-02 | `ux.state.tender_low` |
| UXS-35 | Refund or return bound exceeded | "Melebihi batas pengembalian" | Domain | REFUND_BOUND_EXCEEDED or RETURN_BOUND_EXCEEDED | The lines concerned with what remains refundable and returnable; nothing was saved | Change the amounts or quantities | BR-RFD-03 | `ux.state.refund_bound` |
| UXS-36 | Drawer cash not enough | "Uang seharusnya di laci tidak cukup" | Domain | DRAWER_CASH_INSUFFICIENT: the refund is above the selected session's expected cash | Nothing was saved and no cash may leave the drawer; the Owner chooses another OPEN session or "Dana Pemilik" explicitly; nothing is split or moved automatically (D-045) | Choose another source | BR-RFD-15, BR-RFD-16, D-045 | `ux.state.drawer_short` |
| UXS-37 | SKU or barcode already used | "Kode sudah dipakai" | Domain | DUPLICATE_SKU or DUPLICATE_BARCODE | The field concerned, and a link to the product that holds the code where the viewer may see it | Change the code, or open the existing product | BR-CAT-04, BR-CAT-05, BR-CAT-16 | `ux.state.duplicate_code` |
| UXS-38 | Barcode not eligible | "Barcode tidak dapat dipakai" | Domain | BARCODE_NOT_ELIGIBLE: a retired token, a `BK-` namespace token, a failed check digit, or a token outside the correction rules | The reason in plain words; nothing was saved | Use another code; the Owner's correction applies only to a manufacturer barcode retired as a misassignment (D-052) | BR-CAT-17 to BR-CAT-19, D-051, D-052 | `ux.state.barcode_ineligible` |
| UXS-39 | CSV file not valid | "Berkas CSV belum benar" | Domain | CSV_INVALID, or preview rows with errors, including a category name that matches no active category or more than one (D-069) | Every row error in the preview; confirmation disabled until the file is corrected and uploaded again; nothing was created (BR-CAT-38) | Correct the file (for categories: create the category first, UXP-307) and choose it again | BR-CAT-38, BR-CAT-39, D-069 | `ux.state.csv_invalid` |
| UXS-40 | Correction could not be recalculated | "Koreksi tidak dapat dihitung ulang" | Domain | REPLAY_REJECTED for a cost correction or a reversal | Nothing changed; for a cost correction, the affected reporting is marked incomplete (BR-COST-27); a refused reversal writes no mark (D-063) | Review the history with the Owner's knowledge; try later; the reviewed recovery procedure is RF-6 | BR-COST-27, TR-TIME-01, D-063 | `ux.state.replay_rejected` |
| UXS-41 | Link not valid | "Tautan tidak berlaku" | Domain | LINK_INVALID: any failure of a one-time link (expired, used, replaced, revoked, account not ACTIVE) | One uniform message that reveals nothing about the account or the link (TR-ERR-04), and the advice to ask the Owner for a new link | Ask the Owner for a new link | SEC-ACC-06, TR-ERR-04 | `ux.state.link_invalid` |
| UXS-42 | Sign-in or confirmation failed | "Gagal masuk" | Domain | A failed sign-in, recent-authentication confirmation or current-password check | One uniform message that never reveals whether the email exists, whether the account is deactivated or which field was wrong (SEC-AUTH-08) | Try again; throttling applies (UXS-12) | SEC-AUTH-08, SEC-AUTH-09, TR-ERR-04 | `ux.state.signin_failed` |
| UXS-43 | Not verified | "Belum Terverifikasi" | Domain | A system-status item that real evidence has not proven (REQ-OPS-04) | The words "Belum Terverifikasi" in place of a value; never a green or passing look | None in the page (operator procedures, RF-6) | REQ-OPS-04 | `ux.state.unverified` |
| UXS-44 | Print or export failed | "Cetak atau unduh gagal" | Domain | A print, reprint or export request was refused or the file could not be produced | A statement that the business record is unchanged; a print dialog closing is not proof of ink (BR-DOC-04) | Repeat the print or export | BR-DOC-04, BR-CASH-22, BR-SALE-21 | `ux.state.print_failed` |
| UXS-45 | Unsaved changes | "Perubahan belum disimpan" | General | The user leaves a create, edit or command page with entered data that was not sent | A dialog that names what will be lost; for receiving, that the draft is not kept anywhere (D-069) | "Tetap di halaman ini" (default) or "Tinggalkan" | BR-INV-17, D-069, AICWDF section 18.4 | `ux.state.unsaved` |
| UXS-46 | Method not allowed | "Metode pembayaran tidak tersedia" | Domain | METHOD_NOT_ALLOWED | A statement that the method is not available; the method choice stays on the screen | Choose another method | BR-PAY-01, BR-PAY-07 | `ux.state.method_not_allowed` |
| UXS-47 | Similar product found | "Produk mirip" | Domain | The search-first step found similar names but no exact SKU or barcode match | The similar products, each openable; going ahead needs "Tetap buat produk baru" (BR-CAT-04) | Open an existing product, or go ahead | BR-CAT-04 | `ux.state.similar_product` |
| UXS-48 | No cash register available | "Tidak ada laci kas yang tersedia" | Domain | No active cash register is free when opening a session | A statement that every register is in use or inactive, and that a handover is A closes, then B opens (BR-CASH-09) | Wait for a close, or ask the Owner (UXP-609) | BR-CASH-06, BR-CASH-09 | `ux.state.no_register` |
| UXS-49 | Link no longer shown | "Tautan tidak ditampilkan lagi" | Domain | UXP-606 after a reload, Back or a new visit | A statement that the link is shown only once; a new link replaces the old one | "Kembali ke detail pengguna"; issue a new link if needed | SEC-ACC-05 | `ux.state.link_gone` |
| UXS-50 | Onboarding waiting for restore reconciliation | "Stok awal menunggu rekonsiliasi pemulihan" | Domain | After a restore, the server reports that it cannot verify whether onboarding was already released for the restored business data (D-072) | On UXP-412, a statement that starting, entering and ending the onboarding period are on hold until the Owner reconciles the restored data under the restore runbook; no start control is shown; where the restored data show the onboarding freeze on, "Input stok awal" and "Akhiri masa input stok awal" are shown disabled with the reason (UXS-13), and otherwise neither is shown | The Owner reconciles the restored data under the restore runbook (RF-6); the page then shows the reconciled onboarding state | D-072, BR-CMD-06 | `ux.state.onboarding_held` |

## 3. Error codes

Each code of TECHNICAL_RULES section 4 maps to exactly one state.

| Code | State |
| --- | --- |
| `UNAUTHENTICATED` | UXS-09 Sign-in session ended |
| `CSRF_FAILED` | UXS-10 Page must be reloaded |
| `RECENT_AUTH_REQUIRED` | UXS-11 Recent authentication |
| `FORBIDDEN` | UXS-07 Permission denied |
| `NOT_FOUND` | UXS-08 Not found |
| `IDEMPOTENCY_CONFLICT` | UXS-15 Held |
| `EPOCH_STALE` | UXS-15 Held |
| `INTENT_ABANDONED` | UXS-16 Request no longer valid |
| `STALE_VERSION` | UXS-17 Data changed |
| `PRICE_CHANGED` | UXS-18 Price changed |
| `RECOUNT_REQUIRED` | UXS-19 Recount required |
| `VALIDATION_FAILED` | UXS-05 Validation error |
| `INSUFFICIENT_STOCK` | UXS-31 Insufficient stock |
| `PRODUCT_INACTIVE` | UXS-32 Inactive product or category |
| `CATEGORY_INACTIVE` | UXS-32 Inactive product or category |
| `NO_OPEN_SESSION` | UXS-29 No OPEN cash session |
| `SESSION_NOT_OPEN` | UXS-30 Cash session not open |
| `ONBOARDING_FREEZE` | UXS-27 Onboarding active |
| `POSTING_BLOCKED` | UXS-28 Postings blocked |
| `METHOD_NOT_ALLOWED` | UXS-46 Method not allowed |
| `TENDER_INSUFFICIENT` | UXS-34 Tendered amount too low |
| `REFUND_BOUND_EXCEEDED` | UXS-35 Refund or return bound exceeded |
| `RETURN_BOUND_EXCEEDED` | UXS-35 Refund or return bound exceeded |
| `DRAWER_CASH_INSUFFICIENT` | UXS-36 Drawer cash not enough |
| `BARCODE_NOT_ELIGIBLE` | UXS-38 Barcode not eligible |
| `DUPLICATE_SKU` | UXS-37 SKU or barcode already used |
| `DUPLICATE_BARCODE` | UXS-37 SKU or barcode already used |
| `CSV_INVALID` | UXS-39 CSV file not valid |
| `REPLAY_REJECTED` | UXS-40 Correction could not be recalculated |
| `LINK_INVALID` | UXS-41 Link not valid |
| `THROTTLED` | UXS-12 Too many attempts |
| `SERVICE_BUSY` | UXS-14 Uncertain |
| Any other 5xx answer, a timeout or a lost connection | UXS-14 Uncertain |
| A committed result (200 or 201, or the original result of a repeated identity) | UXS-04 Committed |

Codes: **32**, each mapped once.

## 4. Uncertain outcome: recovery per command (G1-07)

In UXS-14 the page offers "Kirim ulang" (the same frozen request, always safe) and "Cek status" (resolution: COMMITTED, IN_PROGRESS or NOT_COMMITTED; ADR-007 section 4). In UXS-15 it offers only "Cek status"; for a request refused as EPOCH_STALE after a restore, the answer comes from the restore reconciliation and is EPOCH_STALE again until the Owner has reconciled the request (D-073). After a reload, a closed tab or a sign-out, the reference is kept for its user and resolved from UXP-006 (SEC-CH-05). The critical commands of ADR-007 section 1 have a page-specific panel; the others use the shared panel of UXP-000, and RF-5 may add more (ADR-007 section 1).

| Command family (TECHNICAL_RULES section 7.2) | Page | What must not happen meanwhile | Recovery | After COMMITTED |
| --- | --- | --- | --- | --- |
| Sale checkout | UXP-102 | Customer is never asked to pay or transfer again; no replacement Sale (BR-SALE-20) | "Kirim ulang"; "Cek status"; after a reload, UXP-006 | UXP-103 |
| Open cash session | UXP-104 | No second open is attempted with a new request | "Kirim ulang"; "Cek status" (the uncertain panel of the page); UXP-006 | UXP-101 |
| Close, own or Owner-assisted | UXP-105, UXP-207 | The drawer is not counted again for a new close request; the session may already be CLOSED | "Kirim ulang"; "Cek status"; UXP-006; the session's state is shown on UXP-206 | UXP-206 |
| Drawer-funded refund or return | UXP-203 | No cash is taken from the drawer until COMMITTED; no replacement refund (BR-RFD-17, D-046) | "Kirim ulang"; "Cek status"; UXP-006 | UXP-204 |
| Owner-funded or BANK_TRANSFER refund or return | UXP-203 | The payout evidence and the original intent are checked before anything else is done (BR-RFD-14) | "Kirim ulang"; "Cek status"; UXP-006 | UXP-204 |
| Receiving confirmation, manual issue, adjustment | UXP-405, UXP-408, UXP-410 | No new receiving, manual issue or adjustment is started until the first is known (NC-13) | "Kirim ulang"; "Cek status"; UXP-006 | UXP-406, UXP-409, UXP-402 |
| Opening stock | UXP-413 | The same rows are not entered again in a new command | "Kirim ulang"; "Cek status"; UXP-006 | UXP-412 |
| Reversal | UXP-411 | No second reversal is attempted; a source is reversed at most once (BR-INV-35) | "Kirim ulang"; "Cek status"; UXP-006 | UXP-403 |
| Cost correction or completion | UXP-504 | SERVICE_BUSY is repeated with the same identity (D-063); no new correction for the same source | "Kirim ulang"; "Cek status"; UXP-006 | The page that opened it; from UXP-006, the cost history (UXP-505) of the product whose cost source is named in the result |
| Product creation | UXP-303 | No second product is created; an exact SKU or barcode collision would refuse it anyway | "Kirim ulang"; "Cek status"; UXP-006 | UXP-302 |
| Price change; product edit, including minimum stock and category; product deactivation or reactivation | UXP-302, UXP-304 | No second edit with different values until the first is known | "Kirim ulang"; "Cek status"; UXP-006 | UXP-302 |
| Category create, rename, deactivate or reactivate | UXP-307 | As above | "Kirim ulang"; "Cek status"; UXP-006 | UXP-307 |
| Product photo upload, replace or remove | UXP-302 | As above | "Kirim ulang"; "Cek status"; UXP-006 | UXP-302 |
| Barcode add, generate, retire | UXP-302 | As above | "Kirim ulang"; "Cek status"; UXP-006 | UXP-302 |
| Barcode misassignment correction (move or re-attach) | UXP-305 | No second correction until the first is known | "Kirim ulang"; "Cek status"; UXP-006 | UXP-302 |
| CSV import confirmation | UXP-308 | The file is not imported again under a new request (BR-CAT-40) | "Kirim ulang"; "Cek status"; UXP-006 | UXP-301 |
| Onboarding set or release | UXP-412 | No opposite action until the state is known | "Kirim ulang"; "Cek status"; UXP-006 | UXP-412 |
| BusinessProfile change | UXP-608 | No second version with different values until the first is known | "Kirim ulang"; "Cek status"; UXP-006 | UXP-607 |
| Cash register configuration | UXP-610 | As above | "Kirim ulang"; "Cek status"; UXP-006 | UXP-609 |
| User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) | UXP-005, UXP-603, UXP-604, UXP-605 | No second account and no second link until the first is known; a link that was issued but not shown is replaced by issuing a new one | "Kirim ulang"; "Cek status"; UXP-006 | UXP-604 or UXP-003; a link issued but not shown is never shown later (SEC-ACC-05): the Owner issues a new one |
| Reconciliation block (scheduled command) or reopening (Owner) | UXP-415 | No second reopening until the first is known | "Kirim ulang"; "Cek status"; UXP-006 | UXP-415 |
| Owner review note on a CLOSED session | UXP-206 | The note is not written again under a new request | "Kirim ulang"; "Cek status"; UXP-006 | UXP-206 |
| Print, reprint or export request | UXP-103, UXP-202, UXP-206, UXP-306 | Printing never repeats a business fact (BR-SALE-21); a repeat is a reprint | Repeat the print or export | The same page |
| Resolution of an uncertain intent | UXP-006 and every uncertain panel | A resolution that gets no answer stays UNCERTAIN; it never concludes failure | "Cek status" again | The command's result page |

## 5. Authentication endpoints

These carry no command envelope (TR-CMD-07). A lost answer is handled as follows.

| Endpoint | Page | Recovery |
| --- | --- | --- |
| Sign-in | UXP-001 | Repeat the sign-in; the result is the same |
| Sign-out | UXP-000 | The client clears its data and loads UXP-001 regardless (SEC-AUTH-10) |
| Password confirmation | UXP-000 dialog | Confirm again; nothing else ran |
| Own password change | UXP-004 | Repeat; if the current password is then refused, the change was saved and the new password applies |
| One-time link consumption | UXP-002 | Try to sign in with the new password; if it fails, ask the Owner for a new link, because the link may be used |

## 6. States per page

| Page | Type | States |
| --- | --- | --- |
| UXP-000 "Kerangka aplikasi" | Shell | UXS-01, UXS-04, UXS-06, UXS-09, UXS-10, UXS-12, UXS-14, UXS-15, UXS-16, UXS-20, UXS-21, UXS-22, UXS-23, UXS-27, UXS-42, UXS-45 |
| UXP-001 "Masuk" | Sign-in form | UXS-06, UXS-10, UXS-12, UXS-20, UXS-42 |
| UXP-002 "Atur kata sandi" | One-time link form | UXS-05, UXS-06, UXS-10, UXS-12, UXS-20, UXS-41 |
| UXP-003 "Akun saya" | Account | UXS-01, UXS-09, UXS-20, UXS-22 |
| UXP-004 "Ubah kata sandi" | Edit | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-10, UXS-12, UXS-13, UXS-17, UXS-20, UXS-42, UXS-45 |
| UXP-005 "Ubah nama dan email" | Edit | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-45 |
| UXP-006 "Permintaan belum pasti" | List | UXS-01, UXS-02, UXS-03, UXS-04, UXS-06, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22 |
| UXP-007 "Beranda" | Home | UXS-01, UXS-02, UXS-09, UXS-20, UXS-21, UXS-22, UXS-25, UXS-27, UXS-43 |
| UXP-101 "Kasir" | Till workspace | UXS-01, UXS-03, UXS-05, UXS-09, UXS-13, UXS-14, UXS-15, UXS-18, UXS-20, UXS-22, UXS-27, UXS-29, UXS-30, UXS-32, UXS-33 |
| UXP-102 "Pembayaran" | Payment | UXS-01, UXS-04, UXS-05, UXS-06, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-18, UXS-20, UXS-27, UXS-28, UXS-29, UXS-30, UXS-31, UXS-32, UXS-34, UXS-46 |
| UXP-103 "Struk" | Result | UXS-01, UXS-03, UXS-04, UXS-06, UXS-09, UXS-33, UXS-44 |
| UXP-104 "Buka sesi kas" | Command page | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-45, UXS-48 |
| UXP-105 "Tutup sesi kas" | Close flow | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-19, UXS-20, UXS-22, UXS-30, UXS-45 |
| UXP-201 "Penjualan" | List | UXS-01, UXS-02, UXS-03, UXS-05, UXS-07, UXS-09, UXS-20, UXS-22 |
| UXP-202 "Detail penjualan" | Detail | UXS-01, UXS-06, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-44 |
| UXP-203 "Pengembalian uang dan retur" | Command page | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-27, UXS-28, UXS-30, UXS-35, UXS-36, UXS-45 |
| UXP-204 "Pengembalian tercatat" | Result | UXS-01, UXS-04, UXS-09, UXS-44 |
| UXP-205 "Sesi kas" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-20, UXS-22 |
| UXP-206 "Detail sesi kas" | Detail | UXS-01, UXS-02, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-22, UXS-44 |
| UXP-207 "Tutup sesi oleh Pemilik" | Close flow | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-19, UXS-20, UXS-22, UXS-30, UXS-45 |
| UXP-301 "Produk" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-20, UXS-22, UXS-33 |
| UXP-302 "Detail produk" | Detail | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-12, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-21, UXS-22, UXS-37, UXS-38 |
| UXP-303 "Tambah produk" | Create | UXS-01, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-32, UXS-37, UXS-38, UXS-45, UXS-47 |
| UXP-304 "Ubah produk" | Edit | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-32, UXS-45 |
| UXP-305 "Koreksi salah pasang barcode" | Command page | UXS-01, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-33, UXS-38, UXS-45 |
| UXP-306 "Cetak label barcode" | Print | UXS-05, UXS-06, UXS-09, UXS-44 |
| UXP-307 "Kategori" | List | UXS-01, UXS-02, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-22 |
| UXP-308 "Impor produk (CSV)" | Command page | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-12, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-37, UXS-39, UXS-45 |
| UXP-401 "Stok" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-21, UXS-22, UXS-27, UXS-33 |
| UXP-402 "Riwayat stok" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-27 |
| UXP-403 "Detail mutasi stok" | Detail | UXS-01, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-24, UXS-26, UXS-27 |
| UXP-404 "Barang masuk" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-27 |
| UXP-405 "Catat barang masuk" | Command page | UXS-01, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-24, UXS-27, UXS-28, UXS-32, UXS-33, UXS-45 |
| UXP-406 "Detail barang masuk" | Detail | UXS-01, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-24, UXS-26, UXS-27 |
| UXP-407 "Barang keluar" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-27 |
| UXP-408 "Catat barang keluar" | Command page | UXS-01, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-27, UXS-28, UXS-31, UXS-32, UXS-33, UXS-45 |
| UXP-409 "Detail barang keluar" | Detail | UXS-01, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-27 |
| UXP-410 "Penyesuaian stok" | Command page | UXS-01, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-24, UXS-27, UXS-28, UXS-31, UXS-32, UXS-33, UXS-45 |
| UXP-411 "Pembatalan mutasi" | Command page | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-27, UXS-28, UXS-31, UXS-40, UXS-45 |
| UXP-412 "Stok awal" | Hub | UXS-01, UXS-02, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-22, UXS-24, UXS-27, UXS-50 |
| UXP-413 "Input stok awal" | Command page | UXS-01, UXS-03, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-24, UXS-33, UXS-45 |
| UXP-414 "Insiden rekonsiliasi" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-20, UXS-22 |
| UXP-415 "Detail insiden" | Detail | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-22 |
| UXP-501 "Rekap harian" | Report | UXS-01, UXS-02, UXS-07, UXS-09, UXS-20, UXS-22, UXS-25 |
| UXP-502 "Laporan keuangan" | Report | UXS-01, UXS-02, UXS-05, UXS-07, UXS-09, UXS-20, UXS-22, UXS-25 |
| UXP-503 "Biaya perlu dilengkapi" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-24, UXS-25, UXS-28 |
| UXP-504 "Koreksi biaya" | Command page | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-24, UXS-28, UXS-40, UXS-45 |
| UXP-505 "Riwayat biaya produk" | Detail | UXS-01, UXS-07, UXS-08, UXS-09, UXS-13, UXS-14, UXS-15, UXS-20, UXS-22, UXS-24, UXS-25 |
| UXP-601 "Pengaturan" | Settings index | UXS-01, UXS-07, UXS-09 |
| UXP-602 "Pengguna" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-20, UXS-22 |
| UXP-603 "Tambah pengguna" | Create | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-12, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-45 |
| UXP-604 "Detail pengguna" | Detail | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-11, UXS-12, UXS-13, UXS-14, UXS-15, UXS-16, UXS-20, UXS-22 |
| UXP-605 "Ubah pengguna" | Edit | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-45 |
| UXP-606 "Tautan kata sandi" | Handover | UXS-09, UXS-49 |
| UXP-607 "Profil usaha" | Detail | UXS-01, UXS-07, UXS-08, UXS-09, UXS-20, UXS-22 |
| UXP-608 "Ubah profil usaha" | Edit | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-08, UXS-09, UXS-10, UXS-11, UXS-12, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-45 |
| UXP-609 "Laci kas" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-13, UXS-20, UXS-22 |
| UXP-610 "Form laci kas" | Create / Edit | UXS-01, UXS-04, UXS-05, UXS-06, UXS-07, UXS-09, UXS-10, UXS-11, UXS-13, UXS-14, UXS-15, UXS-16, UXS-17, UXS-20, UXS-45 |
| UXP-611 "Status sistem" | Status | UXS-01, UXS-07, UXS-09, UXS-20, UXS-22, UXS-43 |
| UXP-612 "Riwayat aktivitas" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-08, UXS-09, UXS-20, UXS-22 |
| UXP-613 "Log keamanan" | List | UXS-01, UXS-02, UXS-03, UXS-07, UXS-09, UXS-20, UXS-22 |

Every state appears on at least one page.
