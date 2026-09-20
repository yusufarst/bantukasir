# 12 — Barcode, labels and scan sessions

Owns machine-readable identity and input interaction. [06](06-INVENTORY-SPEC.md) owns posting/retry; [05](05-DESIGN-SYSTEM.md) visual review.

## Identity registry

A barcode is an opaque lookup key, not a command, executable URL, price, stock count or private payload. One globally normalized token targets exactly one product OR serialized item. Multiple aliases may share a target; one alias cannot identify multiple targets.

- Core internal format is Code 128, uppercase ASCII with human-readable code. QR may follow actual 2D requirements without replacing the registry.
- Product: RP-P-<16 Crockford Base32 characters>; item: RP-I-<16 Crockford Base32 characters>. Server-generated random identity with a unique constraint; regenerate collisions before printing. This is not an authentication token.
- Reserve RP- for new internal issuance and LT- for historical platform codes, if actually issued. Legacy identities remain valid/retired under their original record; rebranding never reissues them. No live issued codes were found in this planning repository. Manufacturer aliases cannot use it. Do not encode SKU, manufacturer serial or location inside internal identity. Label text may show necessary name/SKU/unit/item identity, never cost/supplier.
- Remove HID terminators; trim outer ASCII spaces. Preserve manufacturer barcode case, punctuation and leading zeros. Internal codes must already match the uppercase format. Never convert barcodes to numbers.
- Token length 1–128 printable ASCII characters. Reject embedded controls/newlines. Non-ASCII support requires a separate decision.
- Validate EAN/UPC format/check digit when using those declared formats. Do not automatically interpret them as SKU/serial. Reject ambiguous aliases; use internal codes instead.
- Manufacturer serial is unique within product under 06. Bare serial is not automatically a global barcode. **Cari nomor seri** requires product context before selecting/verifying the item.
- Never reuse identity after deactivation. Reprint retains the code. Replacement may issue a new alias while retiring the old token; retired scans receive a specific message and remain traceable.

## Printing

Generate SVG barcodes with a verified library and browser print CSS, physical millimeter dimensions, correct quiet zones, black on white. Initial 50×30 mm is a printer-test assumption, not a vendor requirement. Size changes never change identity. Test 100% scaling, long codes, real surfaces and USB/Bluetooth reads.

Do not rely on screenshots, unverified barcode fonts, cropped output or proprietary SDKs. Support label/A4 printers through OS/browser drivers. Print-dialog completion is not proof ink was printed. Audit **Permintaan Cetak Label**, requested count, actor/target and replacement reason where relevant.

## HID assumptions

USB/Bluetooth HID behaves as an OS keyboard; no middleware, WebUSB or WebBluetooth. Configure matching character layout and Enter suffix. Ignore a second CR/LF terminator on an empty buffer. Manual input with Enter follows the same path.

Use field value and keyboard/composition events, not typing speed to guess device type. Timing may support diagnostics, never decide barcode validity or suppress legitimate complete quantity scans. Finish IME composition before treating Enter as terminator. No global listener that captures passwords or other forms.

Without suffix, use **Tambahkan** or configure the scanner. Tab suffix is not supported by default because it moves focus; add only after explicit device tests. Never submit a buffer on timeout.

## Session states

```text
CONTEXT → SCANNING ↔ REVIEW → SUBMITTING → COMMITTED
              ↓                  ↓
           CANCELLED          UNCERTAIN → original status/retry → COMMITTED
                                 ↓ definitive server rejection
                               REVIEW
```

| State | Contract |
| --- | --- |
| CONTEXT | Choose receipt/non-sale issue/transfer or POS with an open shift; inventory location/source/reason or sale context as appropriate; no mutation |
| SCANNING | Dedicated field, buffer, ordered lookup queue and visibly unsaved lines |
| REVIEW | Pause scan capture; require all lookups resolved; inspect/edit identities, quantity and context |
| SUBMITTING | Frozen envelope/key; no scan/edit/cancel; save disabled |
| UNCERTAIN | **Hasil penyimpanan belum dapat dipastikan. Periksa status.** No replacement transaction |
| COMMITTED | Server document/time; clear old draft; explicit **Transaksi Baru** creates new session/key |
| CANCELLED | Confirm when nonempty, discard draft without movement; cannot cancel a possibly committed request |

One sourceSessionId per logical session; increment draft revision on edits. Create the confirmation key once when freezing the envelope and preserve it for recovery, not once per HTTP attempt.

## Buffer, focus and ordering

1. One field: **Pindai atau ketik barcode**. Enter queues a nonempty token and clears the field without submitting its parent form. Reject overlength/control input before network access.
2. Preserve lookup feedback order even when responses arrive out of order. Process serially or with bounded concurrency and sequence IDs. Review/finalize stays disabled until the queue is empty and errors resolved.
3. A disconnected scanner's partial code remains unsent. Offer clear/retry; an optional two-second idle warning must not discard/submit it.
4. Restore focus after a handled scan result, but never steal focus from quantity/reason edits, dialogs, product search or inactive browser. Show **Pemindai Dijeda** and **Lanjutkan Pemindaian**.
5. Escape pauses scanning or closes the current dialog according to hierarchy, never silently deletes the session. Explicit final confirmation is required; scanner Enter never means final confirm.
6. Paste is one token; reject multiline paste. Manual product search/item selection is a complete fallback.

## Repeated scans and correction

Quantity product scan adds one base unit. A fractional product such as cable adds 1 m; explicit decimal edit is available. No hidden pack multiplier. Show last product and quantity delta, with keyboard-accessible **Urungkan Terakhir**. Edit/remove affects the draft only.

Do not interrupt identical quantity tokens within 300 ms with a modal. Every complete nonempty token adds one, including rapid identical scans. Ignore keydown.repeat and extra empty terminators. Optional inline **Dipindai kembali · +1** is sufficient; no error beep, silent drop or modal. Visible totals, undo and review protect against accidental scans. Investigate actual scanner bounce via device configuration; timing alone cannot distinguish intent.

An item barcode can appear once per session. Reject a second serial scan and highlight the existing row. A SERIALIZED product-model barcode starts item selection/registration, never increments anonymous quantity. New receipts identify the correct product/unit and manufacturer serial when available. Issue/transfer selects an existing eligible unit at the source. Server revalidates all conditions.

Unknown token: **Barcode tidak ditemukan.** Keep it available for inspection/search. Owner or operations admin may register an alias through the authorized catalog workflow. Cashier cannot attach aliases; nobody creates stock automatically from an unknown code. New receipt-unit registration follows explicit product selection.

## Network, local drafts and recovery

- Failed lookup is not an accepted scan. Retain the token with **Periksa Lagi**. Offline mode stops new lookup/finalization; draft review remains possible without an offline mutation queue.
- Use memory plus per-tab/per-user sessionStorage for minimal draft/frozen envelope, never credentials. No inventory localStorage or service-worker response caching.
- Ordinary draft maximum age is eight hours. Store timestamp/context revision; clear after commit/cancel/logout.
- Reload offers **Pulihkan Sesi** after the same actor logs in, then re-resolves identity/version. An uncertain frozen envelope must not be edited or silently expire before status recovery.
- If tab storage is lost, inspect recent history/source-session evidence with the owner before recreating work.
- Logout during uncertainty warns the user to retain the nonsensitive session reference, then clears local sensitive data. Server receipts remain durable. Another actor cannot see/replay the draft.
- Concurrent balance/serial changes reject the whole document. Highlight affected lines; never silently lower quantities to make posting pass.
- Only verified COMMITTED response/receipt may display saved status. Beeps/navigation are not proof.

## Mobile and camera extension

Core supports mobile browsers, manual entry and Bluetooth HID. Camera follows Core in R08.4. Do not depend only on native Barcode Detection API support; use feature detection, HTTPS, user-initiated permission, a tested Code 128 decoder and manual fallback. See [MDN Barcode Detection API](https://developer.mozilla.org/en-US/docs/Web/API/Barcode_Detection_API).

RV04 and RV02 show **Pemindai/Manual**, and later **Kamera** only once implemented. Never open camera automatically. Repeated video frames are not new scans: after acceptance, latch until the code leaves the frame or user selects **Pindai Lagi**. Feed accepted codes through the same session reducer. Stop media tracks when leaving camera mode or the tab becomes inactive. Permission/decoder failures retain manual/HID access. Optional audio/vibration supplements text.

## Work layout and feedback

Phone: visible type/location, scan input/last result, dense two-line session list and one review action clear of the virtual keyboard. Long context/serial forms use full-screen presentation. Desktop/tablet: dominant session table, useful side summary, optional sidebar rail without automatic per-scan changes.

F2 may focus scan input only after browser/device conflict testing; provide the visible resume button too. Tab retains normal focus order. Review pauses capture and initially focuses heading/summary/back, never the final submit control. Scan/queue Enter cannot submit confirmation.

| Result | Single visible feedback | Continue |
| --- | --- | --- |
| Quantity accepted | Product/SKU, **Ditambahkan · +1**, row total | Ready for next scan; undo available |
| Serial accepted | Product and selected unit identity | Unique unit row, not hidden aggregate |
| Duplicate serial | **Unit ini sudah ada dalam sesi.** | Highlight prior row; other work continues |
| Unknown | **Barcode tidak ditemukan.** | Retain token, inspect/search |
| Ineligible | **Stok tidak mencukupi.** / **Barang sedang dikarantina.** | Explicit correction, no automatic location switch |
| Commit confirmed | **Barang keluar berhasil dicatat.** plus server document | New session only after definite result |

No toast per scan or server-success sound before commit. One concise live region announces the last result; avoid hundreds of queued screen-reader announcements. [08](08-TESTING-ACCEPTANCE.md) covers actual USB/Bluetooth, labels, manual/IME, CR/LF, rapid scans, focus loss, mobile keyboard, duplicates, out-of-order lookup and lost commit responses.

## POS scan context

Core GOODS share the same alias registry across lookup, receiving, non-sale issue, POS and later stock count. SERVICE is added through SKU/name search, without physical barcode labels. A manufacturer's product code resolves its exact variant/base unit; do not alias a pack barcode to a single unit or strip leading zeros. Different packaging needs a distinct supported SKU or a later explicit conversion policy.

RV04 adds basket/shift/payment review to the same reducer; RV03 covers deferred order/reservation/fulfillment context. No scanner Enter completes payment. Cashier sees authoritative selling price and permitted availability, never private cost. The basket is a draft of the unified Order in 17, not separate Sale truth; local inventory drafts follow the rules above. No new draft may replace an uncertain Order/payment/fulfillment command. Label generation uses a pinned, license-verified JsBarcode build; actual quiet zones, 50×30 mm assumption and printer/scanner acceptance remain required. Retail receipt printing is separate from labels.
