# Key screens

| | |
| --- | --- |
| Status | CURRENT — written in RF-4C under D-086, 17 and 18 October 2026 (Asia/Jakarta) |
| Phase | P7 — visual design: the key screens that show the design system |
| Gate | P7-G3, visual design (D-025): **artifacts ready for the Owner's review — NOT APPROVED**. Approval is a separate Owner decision that names the checkpoint commit and the hash of each artifact (plan decision P-10) |
| Decisions | D-018, D-025, D-036, D-039, D-040, D-075, D-077, D-085, D-086 |
| Related | [DESIGN_SYSTEM](DESIGN_SYSTEM.md), [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [UI_STATES](UI_STATES.md), [COPY_DECK](COPY_DECK.md), [previews](previews/index.html) |

Ten renderable, nonproduction previews of the Owner's, the Operations admin's and the Cashier's key screens: the till, stock and the cash session. They render [DESIGN_SYSTEM](DESIGN_SYSTEM.md) with invented data. Every text is a COPY_DECK key, rendered from `previews/assets/bk-copy.js`, or one of the proposals of DESIGN_SYSTEM section 13; invented data is marked `data-fixture` in the markup. The previews are not application code and add no business rule.

## 1. The set

| KS | Page | Role shown | Preview | States (`?state=`) |
| --- | --- | --- | --- | --- |
| KS-01 | UXP-001 Masuk | Before sign-in | [ks-01-masuk.html](previews/ks-01-masuk.html) | `default`, `failed`, `signed-out`, `throttled` |
| KS-02 | UXP-007 Beranda | Owner | [ks-02-beranda.html](previews/ks-02-beranda.html) | `default`, `quiet`, `failed`, `loading` |
| KS-03 | UXP-101 Kasir | Cashier; Owner and combined with `?peran=` | [ks-03-kasir.html](previews/ks-03-kasir.html) | `default`, `empty`, `price-changed`, `unresolved`, `opened` |
| KS-04 | UXP-102 Pembayaran | Cashier | [ks-04-pembayaran.html](previews/ks-04-pembayaran.html) | `default`, `short`, `transfer`, `submitting`, `uncertain`, `held` |
| KS-05 | UXP-103 Struk | Cashier | [ks-05-struk.html](previews/ks-05-struk.html) | `default`, `copy`; `?ukuran=58`, `80`, `a4` |
| KS-06 | UXP-104 Buka sesi kas | Cashier | [ks-06-buka-sesi.html](previews/ks-06-buka-sesi.html) | `default`, `review`, `no-register` |
| KS-07 | UXP-105 Tutup sesi kas | Cashier | [ks-07-tutup-sesi.html](previews/ks-07-tutup-sesi.html) | `default`, `count`, `review`, `recount`, `unresolved-checkout` |
| KS-08 | UXP-401 Stok | Ops; Owner and combined with `?peran=` | [ks-08-stok.html](previews/ks-08-stok.html) | `default`, `attention`, `no-results`, `stale`, `empty` |
| KS-09 | UXP-405 Catat barang masuk | Ops; Owner and combined with `?peran=` | [ks-09-catat-barang-masuk.html](previews/ks-09-catat-barang-masuk.html) | `default`, `review`, `unknown-code`, `uncertain` |
| KS-10 | UXP-502 Laporan keuangan | Owner | [ks-10-laporan-keuangan.html](previews/ks-10-laporan-keuangan.html) | `default`, `incomplete`, `table` |

Review aids, in English and not product screens: [index.html](previews/index.html), the gallery with every screen and state, whose section "Outside the set" receives the navigation links to pages that are not previewed; [ds-specimen.html](previews/ds-specimen.html), the tokens and components; [frame.html](previews/frame.html), one screen at an exact viewport (`?src=`, `&w=`, `&h=`).

## 2. Opening the previews

- Serve the folder `docs/07-ux-design/previews/` with any local static file server and open `index.html`, or open the files directly in a desktop browser. The desktop app's built-in browser pane needs the server, because it opens a local file without its stylesheets and scripts.
- Parameters: `?state=` as listed above; `?peran=owner`, `ops`, `cashier` or `combined` where the page allows that role; `?aksen=green`, `teal`, `blue`, `purple` or `graphite`.
- Inter loads from a pinned public address for review only; offline, the previews fall back to a size-adjusted Segoe UI (DESIGN_SYSTEM section 14).

## 3. Design choices shown

The choices that COPY_DECK section 21.2 and ADAPTIVE_AND_ACCESSIBILITY section 5 leave to RF-4C, and the other choices of the executor that the screens show. Gate P7-G3 decides them.

| Screen | Choice |
| --- | --- |
| KS-02 | The "Hari ini" panel shows Penjualan neto, Jumlah penjualan, Pembayaran tunai and Pembayaran transfer bank with the as-of line, then the open cash sessions, then "Perlu perhatian" (products to restock, costs to complete, open incidents, system status). A panel that fails says so, never zero (UXS-22) |
| KS-03 | Search and results beside the cart when the till area is at least 760 px wide (the PC, a tablet in landscape), with the total and "Bayar" fixed at the foot of the cart; below that one column, with the total and "Bayar" fixed at the bottom (a tablet in portrait, a phone; UXA-25). The icon rail with tooltips (UXA-03) |
| KS-04 | One screen, one final action (D-040): method as a segmented control, amount with "Uang Pas" and the change shown large; the final action reads "Selesaikan" for Tunai and "Dana sudah diterima — Selesaikan" for Transfer bank. The uncertain and held states freeze the request and name what must not happen (UXS-14, UXS-15) |
| KS-05 | Next sale first (the button and the search field with F2; a scan starts the next sale with its product), the receipt beside it. On the 58 mm receipt a long name wraps in full on its own lines, never cut, with quantity × price and the line total on the next line. The copy marker "Salinan" prints in sentence case, bold, in a box (DESIGN_SYSTEM section 12) |
| KS-06, KS-07 | Command pages with a review dialog; the close flow in two steps (Pratinjau, Hitung uang) before the review; the variance in words with its sign (`−Rp6.500 (kurang)`) |
| KS-08 | Filter chips Habis, Menipis, Normal; the table turns into labelled cards on a narrow container; the Owner's row action "Sesuaikan stok" names its product |
| KS-09 | Receiving lines with quantity, line cost and "Biaya belum diketahui"; the review dialog lists every line and marks those whose cost is unknown |
| KS-10 | Period presets and a custom range; completeness before the figures; the trend chart with one series, Penjualan neto per Asia/Jakarta business day of the period, and the same data as a table. Margin is hidden while cost data is incomplete |
| All | The accent set of five colours with proposed names; no toasts, list position numbers, command palette, bottom navigation or breadcrumbs (DESIGN_SYSTEM section 1.3) |

## 4. Verification record

Checked by the executor on 18 October 2026 in the desktop app's browser (Chromium) through a local static server.

- **Widths.** Every screen at 1366 × 768, 1024 × 768, 768 × 1024, 640 × 400 (200 % zoom of 1280 × 800), 390 × 844, 360 × 740 and 320 × 640, KS-08 also for each role. An in-page audit found, on every screen: no sideways page scrolling and a layout as wide as the window; one level-1 heading and one main landmark; no missing copy key and no visible text without a copy key or fixture mark; no duplicate id or broken ARIA reference; no target below 44 × 44 px; no control without an accessible name; no text below 12 px; no text below 4.5:1, or 3:1 for large text, against its rendered background. The only finding is in the gallery, a review page: one link inside a sentence, which the target rule exempts.
- **Keyboard,** with real key events: arrival focus (Kasir search, Pembayaran amount, Struk search, first register on Buka sesi kas); F2; scan or code with Enter adds the product; F8 opens Pembayaran; Alt+2, Alt+1 and Alt+U; Enter on the focused final action, a review dialog's confirm button and the print button does nothing, Space prints; Ctrl+Enter opens a review and a second, separate press confirms, while a repeated press is ignored; while a payment is sending, the form is frozen, the way back is closed with its reason and Esc does nothing; the review dialog takes focus on "Kembali ubah", keeps Tab inside, closes with Esc and returns focus to "Tinjau"; Esc on Pembayaran returns to the cart; the drawer and the account menu open, move with the arrow keys and close with Esc, returning focus; the chart moves with the arrow keys, Home and End and announces each point.
- **Copy.** All 306 values of `bk-copy.js` stand on their key's line in COPY_DECK; the 8 proposals are not in COPY_DECK. No word of GLOSSARY section 13 appears in a screen's text; the two near matches are COPY_DECK's own wording ("Harga pokok penjualan" defines HPP; "Gagal masuk" and "Gagal memuat" are a sign-in failure and a failed read, not an uncertain outcome). "BantuKasir" appears on no screen.
- **Navigation.** Each role's destinations, their order, the sub-navigation, the badges and the account menu equal INFORMATION_ARCHITECTURE section 4.
- **Contrast.** DESIGN_SYSTEM section 3.5 from the token values, and the rendered text as above.
- **Not checked here:** screen-reader output (names and roles were checked in the accessibility tree), real devices, scanners, printers and the tablet's on-screen keyboard (gate DEVICES).

## 5. Fixtures

The shop "Toko Contoh"; the people Hendra (Owner), Agus (Admin Operasional), Rina (Kasir) and Dewi (Kasir and Admin Operasional); stationery products, prices, numbers and dates around 17 October 2026. All are invented for the previews. No real person, shop, account or MULTIPLECORP data is used.
