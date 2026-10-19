# Interaction inventory

| | |
| --- | --- |
| Status | CURRENT — written in RF-4A under D-070 and, after the stop on finding F-03, D-071; corrected after the independent final review under D-072 to D-075 and after the targeted re-verification of that remediation (D-075); 11 and 12 October 2026 (Asia/Jakarta); amended for the Owner's payment-method decision Q38 under D-077 and D-079 (STATIC_QRIS removed), 17 October 2026 (Asia/Jakarta); revised by TASK-002 under change control — the gate row (finding F-01), the working labels of COPY_DECK section 19 (finding F-03) and the rows of INTERACTION_CONTRACTS section 10, 19 October 2026 (Asia/Jakarta) |
| Phase | P7 — interaction inventory and route-to-UI mapping at capability and command level (AICWDF sections 14.3, 18 and 25) |
| Gate | P7-G1, navigation and page structure (D-025): **APPROVED** by D-082, as committed in `d4c0594`. Revised by TASK-002 under change control (OPEN_ITEMS T-19, T-20; [COPY_DECK](COPY_DECK.md) section 22), whose package the Owner approves by the hash of each artifact at gate GATE-CC1 ([TASK_PLAN](../11-tasks/TASK_PLAN.md) section 3) |
| Decisions | D-017, D-018, D-024, D-025, D-032, D-033, D-036, D-039, D-040, D-047, D-049, D-067 to D-075, D-077, D-082, D-084, D-086, D-087 |
| Related | [USER_TASKS](USER_TASKS.md), [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [UI_STATES](UI_STATES.md), [ADAPTIVE_AND_ACCESSIBILITY](ADAPTIVE_AND_ACCESSIBILITY.md) |

Every visible control of every page: who sees it, when, what it does, how it is protected from a scanner Enter, how it works on the keyboard and on touch, which state follows and where back or cancel lead. Interactions are numbered `UXI-nnn`; identifiers are permanent. This document adds no business rule.

Indonesian labels in quotation marks are **working labels**, taken from the [GLOSSARY](../02-domain/GLOSSARY.md) where it has a term; they stay provisional until gate P7-G2 (terminology and copy, RF-4B). Nothing here is visual design (RF-4C, gate P7-G3) or an HTTP route (RF-5).

**Roles.** Owner = SUPER_ADMIN; Ops = OPERATIONS_ADMIN; Cashier = CASHIER; Combined = an account that holds CASHIER and OPERATIONS_ADMIN together and has exactly the union of their capabilities and never an Owner-only one (D-032, SEC-AZ-02). Capabilities are those of [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) section 6.

## 1. How to read the inventory

- **One row per visible control, or per group of controls that act together.** A form's input fields are one "Form" row; every button, link, menu item, tab, filter, search field, paging control and toggle with its own effect has its own row, except that one row may group controls of the same page that act together and share the row's visibility and capabilities: the buttons of one dialog or uncertain panel, a pair of paging buttons, a set of filters, a line's fields with its remove button, and an inline form with its own buttons. Every grouped control is named in the Control column, and where one of them differs in condition, action, hierarchy, result or back and cancel, the cell names that control. Controls that belong to a state ("Coba lagi", "Muat ulang" of UXS-22) are defined in [UI_STATES](UI_STATES.md); controls shared by all pages (navigation, account menu, shared dialogs and panels) are rows of UXP-000.
- **Visible to** names the roles and the capabilities (SECURITY_MODEL section 6). On the pre-authentication pages it names the visitor and the security requirements (INFORMATION_ARCHITECTURE section 3).
- **Action** is one of: *Navigate* to a page; *Read* data; *Local only* (changes the page or draft, sends no request); *Command* named by its command family of [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 7.2; *Request, not a command* — a request that keeps only temporary working data and creates no business fact (TR-CMD-03; the CSV upload with its preview, and its cancel), whose route and contract are set in RF-5; *Authentication endpoint* (TR-CMD-07); *Resolution* of an uncertain intent (ADR-007 section 4).
- **Hierarchy** is Primary, Secondary, Destructive, Overflow, Field, Filter or Navigation (AICWDF section 18.3).
- **Review** says whether a review step precedes the command, and the rule that requires it (BR-SALE-15, BR-CMD-04).
- **Scanner Enter** says why a scanner terminator cannot confirm a payment, a posting or a session close (BR-SALE-13, BR-CAT-32; UXA-10).
- **Route** is "RF-5" for every row: HTTP route, method and handler are set in RF-5 (plan decision P-11). The route-to-UI mapping of AICWDF section 18 starts here at capability and command level.
- **Status** is *Working*, *Disabled with a reason* (a condition in the "Condition" column, state UXS-13), *Hidden by design* (with the open item or rule), or *Not provided* (an absence made explicit by a decision or a rule).

## 2. Inventory by page

### A0 — Common: sign-in, account, shell and Owner home

#### UXP-000 "Kerangka aplikasi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-001 | Navigation item "Beranda" | Owner · CAP-22 | Always | Navigate → UXP-007 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-002 | Navigation item "Kasir" | Owner, Cashier, Combined · CAP-21 | Always | Navigate → UXP-101 when the user has an OPEN session of their own, otherwise UXP-104 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-003 | Navigation item "Penjualan" | Owner, Cashier, Combined · CAP-21, CAP-22 | Always | Navigate → UXP-201 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-004 | Navigation item "Sesi kas" | Cashier, Combined · CAP-21 | Always (the Owner reaches Sesi kas through the Laporan sub-navigation) | Navigate → UXP-205 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-005 | Navigation item "Produk" | Owner, Ops, Combined · CAP-02, CAP-04 | Always | Navigate → UXP-301 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-006 | Navigation item "Stok", with the stock-attention badge | Owner, Ops, Combined · CAP-14 | Always; the badge shows the number of Habis and Menipis products, refreshed after a change, on focus and at least every 30 seconds (BR-INV-41) | Navigate → UXP-401 | Navigation | No | Not applicable | — / — | UXS-01, UXS-21 | — | RF-5 | Working |
| UXI-007 | Navigation item "Laporan" | Owner · CAP-22, CAP-20 | Always | Navigate → UXP-501 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-008 | Navigation item "Pengaturan" | Owner · CAP-25, CAP-32 | Always | Navigate → UXP-601 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-009 | Sub-navigation "Produk": item "Produk" | Owner, Ops, Combined · CAP-02, CAP-04 | On every page of the Produk destination | Navigate → UXP-301 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-010 | Sub-navigation "Produk": item "Kategori" | Owner, Ops, Combined · CAP-04 | On every page of the Produk destination | Navigate → UXP-307 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-011 | Sub-navigation "Produk": item "Impor produk (CSV)" | Owner, Ops, Combined · CAP-10 | On every page of the Produk destination | Navigate → UXP-308 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-012 | Sub-navigation "Stok": item "Stok" | Owner, Ops, Combined · CAP-14 | On every page of the Stok destination | Navigate → UXP-401 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-013 | Sub-navigation "Stok": item "Riwayat stok" | Owner, Ops, Combined · CAP-14 | On every page of the Stok destination | Navigate → UXP-402 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-014 | Sub-navigation "Stok": item "Barang masuk" | Owner, Ops, Combined · CAP-11 | On every page of the Stok destination | Navigate → UXP-404 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-015 | Sub-navigation "Stok": item "Barang keluar" (the Owner's explicit entry, D-018) | Owner, Ops, Combined · CAP-13 | On every page of the Stok destination | Navigate → UXP-407 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-016 | Sub-navigation "Stok": item "Stok awal" | Owner · CAP-15 | On every page of the Stok destination | Navigate → UXP-412 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-017 | Sub-navigation "Stok": item "Insiden rekonsiliasi", with a count when any is open | Owner · CAP-18 | On every page of the Stok destination | Navigate → UXP-414 | Navigation | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-018 | Sub-navigation "Laporan": item "Rekap harian" | Owner · CAP-22 | On every page of the Laporan destination | Navigate → UXP-501 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-019 | Sub-navigation "Laporan": item "Sesi kas" | Owner · CAP-22 | On every page of the Laporan destination | Navigate → UXP-205 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-020 | Sub-navigation "Laporan": item "Laporan keuangan" | Owner · CAP-20 | On every page of the Laporan destination | Navigate → UXP-502 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-021 | Sub-navigation "Laporan": item "Biaya perlu dilengkapi" | Owner · CAP-20 | On every page of the Laporan destination | Navigate → UXP-503 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-022 | Menu button (opens the navigation drawer) | Owner, Ops, Cashier, Combined · CAP-01 | Compact width only (UXA-03) | Local only: changes the page or draft; sends no request | Navigation | No | Not applicable | — / ≥ 44 × 44 CSS px | — | — | RF-5 | Working |
| UXI-023 | Account menu button (user's display name) | Owner, Ops, Cashier, Combined · CAP-01 | Always | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-024 | Account menu item "Akun saya" | Owner, Ops, Cashier, Combined · CAP-01 | Always | Navigate → UXP-003 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-025 | Account menu item "Permintaan belum pasti" with their number | Owner, Ops, Cashier, Combined · CAP-34 | Only while this user has at least one unresolved request: a command reference in this browser (SEC-CH-05) or a request receipt on the server whose outcome is PENDING or HELD (INTERACTION_CONTRACTS section 1.4; D-087) | Navigate → UXP-006 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-026 | Account menu item "Keluar" | Owner, Ops, Cashier, Combined · CAP-01 | Always | Authentication endpoint: sign-out (TR-CMD-07); with unresolved references the sign-out warning opens first (SEC-CH-05) | Secondary | No | Not applicable | — / — | Full page load to UXP-001 (SEC-AUTH-10); never closes a cash session (BR-CASH-11) | — | RF-5 | Working |
| UXI-027 | Sign-out warning dialog: "Buka Permintaan belum pasti" | Owner, Ops, Cashier, Combined · CAP-34 | Sign-out chosen while unresolved references exist | Navigate → UXP-006 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-028 | Sign-out warning dialog: "Tetap keluar" | Owner, Ops, Cashier, Combined · CAP-01 | Same dialog | Authentication endpoint: sign-out (TR-CMD-07); the references stay stored for this user (SEC-CH-05) | Secondary | No | Not applicable | — / — | Full page load to UXP-001 | — | RF-5 | Working |
| UXI-029 | Banner "Ada permintaan yang statusnya belum pasti": "Buka Permintaan belum pasti" | Owner, Ops, Cashier, Combined · CAP-34 | While this user has unresolved requests, from the references in this browser or the request receipts on the server (INTERACTION_CONTRACTS section 1.4; D-087) | Navigate → UXP-006 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-030 | Banner "Masa input stok awal aktif": "Buka Stok awal" | Owner · CAP-15 | While the onboarding freeze is on; the banner text alone is shown to Ops, Cashier and Combined (UXS-27) | Navigate → UXP-412 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-031 | Recent-authentication dialog: form (password) | Owner · CAP-01 | Opens when a capability marked "recent auth" (SECURITY_MODEL section 6) needs a confirmation within 15 minutes (SEC-SESS-05) | Local only: changes the page or draft; sends no request | Field | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | — | — | RF-5 | Working |
| UXI-032 | Recent-authentication dialog: "Konfirmasi" | Owner · CAP-01 | Same dialog | Authentication endpoint: password confirmation (TR-CMD-07); on success the same frozen request is sent again (TECHNICAL_RULES section 4) | Primary | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | UXS-06, UXS-12, UXS-42 (uniform failure, TR-ERR-04), then the command's own result | — | RF-5 | Working |
| UXI-033 | Recent-authentication dialog: "Batal" | Owner · CAP-01 | Same dialog | Local only: changes the page or draft; sends no request; returns to the review with nothing sent | Secondary | No | Not applicable | — / — | — | Back to the review step | RF-5 | Working |
| UXI-034 | Sign-in-ending dialog: "Tetap masuk" | Owner, Ops, Cashier, Combined · CAP-01 | Shown before the 30-minute idle limit is reached (UXS-23); not offered against the 12-hour limit | Read: a user-activity request (SEC-SESS-02) | Primary | No | Not applicable | — / — | UXS-23 closes | — | RF-5 | Working |
| UXI-035 | Sign-in-ending dialog: "Keluar" | Owner, Ops, Cashier, Combined · CAP-01 | Same dialog | Authentication endpoint: sign-out (TR-CMD-07) | Secondary | No | Not applicable | — / — | Full page load to UXP-001 | — | RF-5 | Working |
| UXI-036 | Shared uncertain panel: "Kirim ulang" | Owner, Ops, Cashier, Combined · CAP-34 | In UXS-14 on a page whose command has no page-specific uncertain panel (master data, configuration, user management, review notes; ADR-007 section 1 leaves their further recovery interface to RF-5) | Command: the same frozen request of the page's command family (TECHNICAL_RULES section 7.2) | Primary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16, UXS-11 (for a command that needs recent authentication: a repeat sent after the recent authentication has lapsed asks for it first) | — | RF-5 | Working |
| UXI-037 | Shared uncertain panel: "Cek status" | Owner, Ops, Cashier, Combined · CAP-34 | Same panel; also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE (D-073) | Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16 | — | RF-5 | Working |
| UXI-038 | Unsaved-changes dialog (UXS-45): "Tetap di halaman ini" and "Tinggalkan" | Owner, Ops, Cashier, Combined · CAP-01 | Leaving a create, edit or command page with data entered and not sent | Local only: changes the page or draft; sends no request; "Tetap di halaman ini" closes the dialog, "Tinggalkan" leaves and discards the entered data | "Tetap di halaman ini": Primary; "Tinggalkan": Secondary | No | Not applicable | — / — | — | "Tetap di halaman ini" is the default | RF-5 | Working |

#### UXP-001 "Masuk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-039 | Form: Email, Kata sandi | Visitor without a sign-in session (pre-authentication; SEC-AUTH-01, SEC-AUTH-07, SEC-AUTH-08, SEC-AUTH-09, SEC-AUTH-10) | Always | Local only: changes the page or draft; sends no request | Field | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | — | — | RF-5 | Working |
| UXI-040 | Toggle "Tampilkan kata sandi" | Visitor without a sign-in session (pre-authentication; SEC-AUTH-01, SEC-AUTH-07, SEC-AUTH-08, SEC-AUTH-09, SEC-AUTH-10) | Always | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-041 | Button "Masuk" | Visitor without a sign-in session (pre-authentication; SEC-AUTH-01, SEC-AUTH-07, SEC-AUTH-08, SEC-AUTH-09, SEC-AUTH-10) | Always; no "remember me" (SEC-AUTH-07) | Authentication endpoint: sign-in (TR-CMD-07) | Primary | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | UXS-06; success: full page load to the role's home; UXS-42 (uniform failure, SEC-AUTH-08); UXS-12; UXS-10 | — | RF-5 | Working |

#### UXP-002 "Atur kata sandi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-042 | Form: Kata sandi baru, Ulangi kata sandi baru, with the AICWDF-COMPAT-8 rule shown | Visitor without a sign-in session (pre-authentication; SEC-AUTH-03, SEC-AUTH-05, SEC-ACC-04, SEC-ACC-05, SEC-ACC-06, SEC-CH-03) | Always; the token is read from the link fragment and removed from the address bar (SEC-ACC-05) | Local only: changes the page or draft; sends no request | Field | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | UXS-05 for the password rule | — | RF-5 | Working |
| UXI-043 | Button "Simpan kata sandi" | Visitor without a sign-in session (pre-authentication; SEC-AUTH-03, SEC-AUTH-05, SEC-ACC-04, SEC-ACC-05, SEC-ACC-06, SEC-CH-03) | Always | Authentication endpoint: one-time link consumption (TR-CMD-07) | Primary | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | UXS-06; success message with "Masuk"; UXS-41 (uniform, SEC-ACC-06); UXS-12; a lost answer: UI_STATES section 5 | — | RF-5 | Working |
| UXI-044 | Link "Masuk" / "Ke halaman masuk" | Visitor without a sign-in session (pre-authentication; SEC-AUTH-03, SEC-AUTH-05, SEC-ACC-04, SEC-ACC-05, SEC-ACC-06, SEC-CH-03) | Always; the success message makes it primary | Navigate → UXP-001 | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |

#### UXP-003 "Akun saya"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-045 | Button "Ubah kata sandi" | Owner, Ops, Cashier, Combined · CAP-01 | Always | Navigate → UXP-004 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-046 | Button "Ubah nama dan email" | Owner · CAP-27 | Owner only; staff names and emails are changed by the Owner (SEC-ACC-10) | Navigate → UXP-005 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |

#### UXP-004 "Ubah kata sandi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-047 | Form: Kata sandi sekarang, Kata sandi baru, Ulangi kata sandi baru | Owner, Ops, Cashier, Combined · CAP-01 | Always | Local only: changes the page or draft; sends no request | Field | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | UXS-05 | — | RF-5 | Working |
| UXI-048 | Button "Simpan kata sandi" | Owner, Ops, Cashier, Combined · CAP-01 | Always | Authentication endpoint: own password change (TR-CMD-07) | Primary | No | Authentication form: Enter submits it (UXA-10 exception); not a scanner field | — / — | UXS-06; UXS-04 → UXP-003; UXS-05; UXS-42 (wrong current password, uniform); UXS-12; a lost answer: UI_STATES section 5 | — | RF-5 | Working |
| UXI-049 | Button "Batal" | Owner, Ops, Cashier, Combined · CAP-01 | Always | Navigate → UXP-003 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard → UXP-003 | RF-5 | Working |
| UXI-050 | Link "Kembali ke Akun saya" (contextual back) | Owner, Ops, Cashier, Combined · CAP-01 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-003 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-005 "Ubah nama dan email"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-051 | Form: Nama tampilan, Email | Owner · CAP-27 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-052 | Button "Simpan perubahan" | Owner · CAP-27 | Enabled when a field changed; otherwise disabled with the reason shown (UXS-13) | Command: User management (TECHNICAL_RULES section 7.2) | Primary | No (recent authentication, SEC-SESS-05) | Safe: Enter never activates it (UXA-10) | — / — | UXS-13, UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-17; UXS-04 → UXP-003 | — | RF-5 | Working |
| UXI-053 | Button "Batal" | Owner · CAP-27 | Always | Navigate → UXP-003 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-003 with the unsaved-changes guard | RF-5 | Working |
| UXI-054 | Link "Kembali ke Akun saya" (contextual back) | Owner · CAP-27 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-003 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-006 "Permintaan belum pasti"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-055 | Row button "Cek status" | Owner, Ops, Cashier, Combined · CAP-34 | Each own unresolved request — a reference in this browser or a request receipt known from the server (INTERACTION_CONTRACTS section 2.2; D-087) — whose state allows resolution, including a request refused as EPOCH_STALE after a restore: the server answers it from the restore reconciliation that the Owner carries out under the restore runbook, and never concludes it on its own (UXS-15; D-073) | Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | Primary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06; COMMITTED → "Lihat hasil"; IN_PROGRESS → UXS-06 and a later re-check; NOT_COMMITTED → the row disappears (UXS-04); a check that gets no answer leaves the request in the state it was in: UXS-14 stays UXS-14, and a held request stays UXS-15 with "Cek status" only (D-084, COPY_DECK finding F-05); EPOCH_STALE again → UXS-15 stays until the reconciliation has recorded an outcome, and the reference is kept | — | RF-5 | Working |
| UXI-056 | Row link "Lihat hasil" | Owner, Ops, Cashier, Combined · CAP-34, CAP-21, CAP-11, CAP-13 | After COMMITTED; the result page is authorized again (SEC-AZ-06) | Navigate → the command's result page (for example UXP-103, UXP-204, UXP-206, UXP-406, UXP-409, UXP-403) | Secondary | No | Not applicable | — / — | UXS-01, UXS-08 | — | RF-5 | Working |
| UXI-057 | Button "Kembali ke …", naming the page that opened it, or "Kembali ke halaman awal" | Owner, Ops, Cashier, Combined · CAP-34 | Always; the label names the destination (NC-01); after a Sale checkout resolved as COMMITTED the cart behind it has been discarded, so going back to UXP-101 shows an empty cart (NC-13) | Navigate → the page that opened it, or the role's home | Secondary | No | Not applicable | — / — | UXS-01 on the destination; there, a control of a family that still holds an unresolved request stays disabled with its reason (UXS-13, NC-13) | Back | RF-5 | Working |
| UXI-058 | Row button "Cek status" for another user's unresolved request, listed with that user's name (CAP-34 "any") | Owner · CAP-34 | Owner only; each unresolved request receipt of another user (INTERACTION_CONTRACTS section 2.2; D-087, ADR-009) | Resolution of an uncertain intent, as the Owner (ADR-007 section 4; CAP-34) | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06; COMMITTED or NOT_COMMITTED settles that user's receipts of the intent and the row disappears (UXS-04); IN_PROGRESS → UXS-06 and a later re-check; a check that gets no answer leaves the row in the state it was in (D-084) | — | RF-5 | Working |

#### UXP-007 "Beranda"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-059 | Panel "Hari ini": link "Rekap harian" | Owner · CAP-22 | Always | Navigate → UXP-501 for today | Secondary | No | Not applicable | — / — | UXS-01, UXS-22, UXS-25 | — | RF-5 | Working |
| UXI-060 | List "Sesi kas yang sedang dibuka": row | Owner · CAP-22 | One row per OPEN session; empty state when none (UXS-02) | Navigate → UXP-206 | Secondary | No | Not applicable | — / — | UXS-01, UXS-02, UXS-22 | — | RF-5 | Working |
| UXI-061 | Attention item "Perlu restok" with count | Owner · CAP-14 | Always; count refreshed as the badge (BR-INV-41) | Navigate → UXP-401 filtered to Habis and Menipis | Secondary | No | Not applicable | — / — | UXS-01, UXS-21 | — | RF-5 | Working |
| UXI-062 | Attention item "Biaya perlu dilengkapi" with count | Owner · CAP-20 | When at least one cost is UNKNOWN or a product's reporting is marked incomplete | Navigate → UXP-503 | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-063 | Attention item "Insiden rekonsiliasi" with count | Owner · CAP-18 | When at least one incident is open | Navigate → UXP-414 | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-064 | Attention item "Status sistem perlu dicek" | Owner · CAP-32 | When any status item is failed or "Belum Terverifikasi" (UXS-43) | Navigate → UXP-611 | Secondary | No | Not applicable | — / — | UXS-01, UXS-43 | — | RF-5 | Working |
| UXI-065 | Attention item "Masa input stok awal aktif" | Owner · CAP-15 | While the onboarding freeze is on | Navigate → UXP-412 | Secondary | No | Not applicable | — / — | UXS-27 | — | RF-5 | Working |

### A1 — Till

#### UXP-101 "Kasir"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-066 | Field "Cari atau scan produk" (shared Product Search, BR-CAT-23) | Owner, Cashier, Combined · CAP-02 | Always while the user's own session is OPEN; focused on arrival and after every cart change; capture paused while a dialog is open (BR-CAT-32) | Read: exact barcode, then SKU, then name (BR-CAT-24); an exact barcode or SKU match adds one base unit (BR-CAT-29) | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | F2 focuses it (provisional, UXA-09) / Full width; the on-screen keyboard may open (UXA-14) | UXS-01, UXS-03, UXS-33, UXS-32, UXS-20, UXS-22 | — | RF-5 | Working |
| UXI-067 | Search result row "Tambah ke keranjang" (thumbnail, name, SKU, price, available stock) | Owner, Cashier, Combined · CAP-02, CAP-03, CAP-14 | Active product; an inactive product is shown read-only and cannot be added (BR-CAT-27, UXS-32) | Local only: changes the page or draft; sends no request; adds one base unit, or one more to the product's existing line, which is highlighted and focused (D-067) | Primary | No | Not applicable | Arrow keys move, Enter adds the focused result (UXA-09) / Whole row is the target | Cart updated; live announcement (UXA-17) | — | RF-5 | Working |
| UXI-068 | Search results: "Muat lebih banyak" | Owner, Cashier, Combined · CAP-02 | When more results exist (server paging, 25 per page, BR-CAT-25) | Read: the next page of results | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-069 | Search filters: Kategori, Merek | Owner, Cashier, Combined · CAP-02 | Collapsed behind "Filter"; filters only narrow the search | Read: filtered results | Filter | No | Not applicable | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-070 | Cart line field "Jumlah" | Owner, Cashier, Combined · CAP-21 | Every cart line; decimals only up to the product's precision (BR-NUM-02) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter only ends the edit and returns focus to the search field (UXA-09) | Enter ends the edit / Opens the decimal keyboard | UXS-05 at the line | — | RF-5 | Working |
| UXI-071 | Cart line buttons "+" and "−" | Owner, Cashier, Combined · CAP-21 | Every cart line; "−" disabled at the smallest valid quantity (UXS-13) | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / ≥ 44 × 44 CSS px, apart from "Bayar" | Cart updated | — | RF-5 | Working |
| UXI-072 | Cart line "Hapus dari keranjang" | Owner, Cashier, Combined · CAP-21 | Every cart line | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / ≥ 44 × 44 CSS px | Cart updated | — | RF-5 | Working |
| UXI-073 | Button "Kosongkan keranjang" (overflow), with a confirmation dialog | Owner, Cashier, Combined · CAP-21 | When the cart is not empty | Local only: changes the page or draft; sends no request | Overflow | No | Not applicable | — / — | Empty cart | — | RF-5 | Working |
| UXI-074 | Button "Setuju harga baru" | Owner, Cashier, Combined · CAP-21 | Only after a price change was found (UXS-18); the changed lines show the old and new price and the new total | Local only: changes the page or draft; sends no request; the explicit review of BR-SALE-05 | Primary | No | Not applicable | — / — | "Bayar" enabled again | — | RF-5 | Working |
| UXI-075 | Button "Bayar" | Owner, Cashier, Combined · CAP-21 | Enabled when the cart has at least one valid line, no price change is waiting for review, no onboarding freeze is known (UXS-27), and the user holds no unresolved Sale checkout request; otherwise disabled with the reason (UXS-13). An unresolved checkout keeps it disabled across navigation, Back, reload and a new sign-in session (UXS-14, UXS-15; NC-13; BR-SALE-20, TR-ERR-06) | Navigate → UXP-102 | Primary | No | Safe: not activated by Enter (UXA-10) | F8 (provisional, UXA-09) / Large; apart from cart controls | UXS-13, UXS-14, UXS-15, UXS-27 | — | RF-5 | Working |
| UXI-076 | Link "Cek pembayaran yang belum pasti" | Owner, Cashier, Combined · CAP-34 | Only while the user holds an unresolved Sale checkout request (NC-13); it is then the page's primary action, in place of the disabled "Bayar". When the checkout resolves as COMMITTED its cart is discarded (NC-13). A kept duplicate of the link in the reason of "Bayar" (UXA-04): this one is the page's primary action while the block lasts. Shown only while a checkout is unresolved and never disabled itself (COPY_DECK finding F-02) | Navigate → UXP-006 | Primary | No | Not applicable | — / — | UXS-01, UXS-14, UXS-15, UXS-13 | — | RF-5 | Working |
| UXI-077 | Link "Tutup sesi kas" in the session header | Owner, Cashier, Combined · CAP-21 | Always; placed apart from "Bayar"; the close entry is disabled while this user holds an unresolved session close request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-105 | Secondary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |

#### UXP-102 "Pembayaran"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-078 | Method choice "Tunai" / "Transfer bank" | Owner, Cashier, Combined · CAP-21 | Always; "Tunai" chosen when the screen opens (accepted by the Owner, D-075; G1-10); no other method exists (BR-PAY-01, BR-PAY-07) | Local only: changes the page or draft; sends no request | Field | No | Not applicable | Alt+1, Alt+2 (provisional, UXA-09); arrow keys inside the group / Two large segments | — | — | RF-5 | Working |
| UXI-079 | Field "Uang diterima" | Owner, Cashier, Combined · CAP-21 | Method Tunai; focused when the screen opens. Scanner input that lands here is ordinary typing (no timing heuristic, BR-CAT-28): Enter submits nothing and the change is shown live and large, so a mistaken scan is visible before the final action (UXA-11) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Numeric keyboard | Change shown live; UXS-34 | — | RF-5 | Working |
| UXI-080 | Button "Uang Pas" | Owner, Cashier, Combined · CAP-21 | Method Tunai | Local only: changes the page or draft; sends no request; sets the tendered amount to the total (BR-SALE-11) | Secondary | No | Not applicable | Alt+U (provisional, UXA-09) / — | Change Rp0 | — | RF-5 | Working |
| UXI-081 | Field "Referensi pembayaran (opsional)" | Owner, Cashier, Combined · CAP-21 | Method Transfer bank | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-082 | Field "Nama pelanggan (opsional)" | Owner, Cashier, Combined · CAP-21 | Always; optional (BR-SALE-08) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-083 | Final button "Selesaikan" (Tunai) / "Dana sudah diterima — Selesaikan" (Transfer bank) | Owner, Cashier, Combined · CAP-21 | Tunai: enabled only when the tendered amount is valid and at least the total, with the change shown (BR-SALE-11); Transfer bank: always enabled, the label is the confirmation (BR-SALE-12); disabled while submitting (UXS-06); otherwise disabled with the missing amount shown (UXS-34, UXS-13). Not offered while the checkout is uncertain (UXS-14; NC-13) | Command: Sale checkout (TECHNICAL_RULES section 7.2) | Primary | No: the one payment screen is the confirmation (D-040, BR-SALE-15) | Safe: Enter never activates it; not the initial focus (BR-SALE-13, UXA-10) | Ctrl+Enter (provisional, UXA-09); Space when focused / Largest target on the screen, apart from other controls (BR-SALE-14) | UXS-06; UXS-04 → UXP-103; UXS-18 → UXP-101; UXS-31, UXS-32, UXS-29, UXS-30, UXS-27, UXS-28, UXS-34, UXS-46, UXS-05 stay here; UXS-14; UXS-15; UXS-16, UXS-13 | — | RF-5 | Working |
| UXI-084 | Button "Kembali ke keranjang" | Owner, Cashier, Combined · CAP-21 | Disabled while submitting, uncertain or held (UXS-13) | Navigate → UXP-101 with the cart kept | Secondary | No | Not applicable | Esc / — | — | Back and cancel → UXP-101 | RF-5 | Working |
| UXI-085 | Uncertain panel: "Kirim ulang" | Owner, Cashier, Combined · CAP-21, CAP-34 | Only in UXS-14; never creates a new Sale (BR-SALE-20) | Command: Sale checkout (TECHNICAL_RULES section 7.2), the same frozen request | Primary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16 | — | RF-5 | Working |
| UXI-086 | Uncertain panel: "Cek status" | Owner, Cashier, Combined · CAP-34 | In UXS-14, and in UXS-15 after IDEMPOTENCY_CONFLICT or EPOCH_STALE (D-073) | Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | COMMITTED → UXP-103; IN_PROGRESS → UXS-06; NOT_COMMITTED → "Mulai lagi dari keranjang"; a check that gets no answer leaves the request in the state it was in: UXS-14, or UXS-15 with "Cek status" only (D-084, COPY_DECK finding F-05); EPOCH_STALE again → UXS-15 stays until the restore reconciliation has recorded an outcome (D-073) | — | RF-5 | Working |
| UXI-087 | Uncertain panel: "Mulai lagi dari keranjang" | Owner, Cashier, Combined · CAP-21 | Only after NOT_COMMITTED (the original can never commit, ADR-007 section 4) | Navigate → UXP-101 with the same lines as a new draft | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |

#### UXP-103 "Struk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-088 | Button "Transaksi baru" | Owner, Cashier, Combined · CAP-21 | Always; a scan or typed search on this page does the same (UXA-09) | Navigate → UXP-101 with an empty cart | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-089 | Field "Cari atau scan produk" (starts the next sale) | Owner, Cashier, Combined · CAP-02, CAP-21 | Focused when the receipt appears; a scan or a search here opens UXP-101 with an empty cart and adds the product (UXA-11) | Navigate → UXP-101, then the lookup of BR-CAT-24 | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | F2 (provisional, UXA-09) / — | UXS-01, UXS-03, UXS-33 | — | RF-5 | Working |
| UXI-090 | Button "Cetak struk" | Owner, Cashier, Combined · CAP-21 | Always; whether a later print is marked as a copy follows BR-DOC-04 (set with the document contract in RF-5) | Command: Print, reprint or export request (TECHNICAL_RULES section 7.2); then the browser print dialog | Secondary | No | Safe: Enter never prints (UXA-10) | Tab to it, Space / — | UXS-06, UXS-44 | — | RF-5 | Working |
| UXI-091 | Choice "Ukuran struk" (58 mm / 80 mm / A4) | Owner, Cashier, Combined · CAP-21 | Always; the last choice is remembered as a harmless interface preference for the browser session (SEC-CH-05); the real paper width is a gate DEVICES fact | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-092 | Link "Detail penjualan" | Owner, Cashier, Combined · CAP-21 | Always | Navigate → UXP-202 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-093 | Link "Kembali ke Kasir" | Owner, Cashier, Combined · CAP-21 | Always | Navigate → UXP-101 with an empty cart | Secondary | No | Not applicable | — / — | — | Back → UXP-101 | RF-5 | Working |

#### UXP-104 "Buka sesi kas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-094 | Field "Laci kas" (choice of available cash registers) | Owner, Cashier, Combined · CAP-21 | Only active registers without an OPEN session; preselected when only one is available; when none is available, UXS-48 | Local only: changes the page or draft; sends no request | Field | No | Not applicable | — / — | UXS-48 | — | RF-5 | Working |
| UXI-095 | Field "Modal awal" (Rp; Rp0 valid, blank refused) | Owner, Cashier, Combined · CAP-21 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Numeric keyboard | UXS-05 | — | RF-5 | Working |
| UXI-096 | Button "Tinjau" | Owner, Cashier, Combined · CAP-21 | Enabled when a register is chosen and the amount is valid (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog and pauses scan capture | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | Review dialog | — | RF-5 | Working |
| UXI-097 | Review dialog: "Buka sesi" | Owner, Cashier, Combined · CAP-21 | Inside the review dialog | Command: Open cash session (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-CASH-05 | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16; UXS-04 → UXP-101 | — | RF-5 | Working |
| UXI-098 | Review dialog: "Kembali ubah" | Owner, Cashier, Combined · CAP-21 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the form | RF-5 | Working |
| UXI-099 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner, Cashier, Combined · CAP-21, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Open cash session (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; COMMITTED → UXP-101 | — | RF-5 | Working |
| UXI-100 | Button "Batal" | Owner, Cashier, Combined · CAP-21 | Always; the guard asks only when an amount was entered | Navigate → the role's non-till home (UXP-007, UXP-401 or UXP-201) | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard | RF-5 | Working |

#### UXP-105 "Tutup sesi kas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-101 | Step 1 (preview): list of drawer-funded refunds of this session with the reminder that their cash should no longer be in the drawer (D-068) | Owner, Cashier, Combined · CAP-21 | When the session has drawer-funded refunds (data of the session report, BR-CASH-20) | Read: the close preview (BR-CASH-13) | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-102 | Step 1: link "Cek permintaan yang belum pasti" | Owner, Cashier, Combined · CAP-34 | When this user holds an unresolved checkout of this session (BR-CASH-16) | Navigate → UXP-006 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-103 | Step 1: button "Lanjut hitung" | Owner, Cashier, Combined · CAP-21 | Disabled while this user has an unresolved checkout — a reference in this browser or a request receipt on the server whose outcome is PENDING or HELD — (UXS-13, BR-CASH-16; INTERACTION_CONTRACTS section 2.5; D-087) | Local only: changes the page or draft; sends no request; goes to step 2 and pauses scan capture (BR-CAT-32) | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | Step 2 | — | RF-5 | Working |
| UXI-104 | Step 2: field "Uang fisik di laci" (Rp; Rp0 valid, blank refused) | Owner, Cashier, Combined · CAP-21 | Step 2 | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Numeric keyboard | UXS-05 | — | RF-5 | Working |
| UXI-105 | Step 2: button "Tinjau" | Owner, Cashier, Combined · CAP-21 | Enabled when the amount is valid; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-106 | Step 2: button "Kembali ke pratinjau" | Owner, Cashier, Combined · CAP-21 | Step 2 | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | Step 1 | Back to step 1 | RF-5 | Working |
| UXI-107 | Review dialog: "Tutup sesi" (shows physical cash, expected cash and the variance) | Owner, Cashier, Combined · CAP-21 | Inside the review dialog | Command: Close, own or Owner-assisted (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-CASH-13, BR-SALE-15 | Safe: Enter never closes a session (BR-CAT-32, UXA-10) | Ctrl+Enter / — | UXS-06; UXS-04 → UXP-206; UXS-19 → step 1 with a new preview; UXS-30; UXS-05; UXS-14; UXS-15; UXS-16 | — | RF-5 | Working |
| UXI-108 | Review dialog: "Kembali ubah" | Owner, Cashier, Combined · CAP-21 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to step 2 | RF-5 | Working |
| UXI-109 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner, Cashier, Combined · CAP-21, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Close, own or Owner-assisted (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; COMMITTED → UXP-206 | — | RF-5 | Working |
| UXI-110 | Button "Batal" | Owner, Cashier, Combined · CAP-21 | Every step; the guard asks once a count is entered | Navigate → UXP-101 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard → UXP-101 | RF-5 | Working |
| UXI-111 | Link "Kembali ke Kasir" (contextual back) | Owner, Cashier, Combined · CAP-21 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-101 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) once a count is entered | RF-5 | Working |

### A2 — Sales and cash sessions

#### UXP-201 "Penjualan"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-112 | Filter "Tanggal" (period) | Owner, Cashier, Combined · CAP-21, CAP-22 | Always; Cashier and Combined see only their own Sales (SEC-AZ-03) | Read: Sales in the period | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-03, UXS-05, UXS-22 | — | RF-5 | Working |
| UXI-113 | Search "Nomor penjualan" | Owner, Cashier, Combined · CAP-21, CAP-22 | Always | Read: the matching Sale within scope | Filter | No | Search field: Enter runs the search only | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-114 | Filters "Kasir" and "Laci kas" | Owner · CAP-22 | Owner only | Read: filtered Sales | Filter | No | Not applicable | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-115 | List row (number, time, total, method, cashier) | Owner, Cashier, Combined · CAP-21, CAP-22 | Each Sale within scope | Navigate → UXP-202 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-116 | Paging "Berikutnya" / "Sebelumnya" | Owner, Cashier, Combined · CAP-21, CAP-22 | When more rows exist (keyset paging, TR-PAGE-02) | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-202 "Detail penjualan"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-117 | Button "Cetak ulang struk" with the size choice | Owner, Cashier, Combined · CAP-21, CAP-22 | Sale within scope; the reprint shows the copy marker (BR-DOC-04) | Command: Print, reprint or export request (TECHNICAL_RULES section 7.2); then the browser print dialog | Primary | No | Safe: Enter never prints (UXA-10) | — / — | UXS-06, UXS-44 | — | RF-5 | Working |
| UXI-118 | Button "Pengembalian uang dan retur" | Owner · CAP-24 | Owner only; disabled with the reason "Sudah dikembalikan seluruhnya" when both the money and the quantity bounds are used up (UXS-13, BR-RFD-03); also disabled while this user holds an unresolved refund or return request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) (BR-RFD-17) | Navigate → UXP-203 | Secondary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-119 | Link "Sesi kas [nomor]" | Owner, Cashier, Combined · CAP-21, CAP-22 | Always | Navigate → UXP-206 | Secondary | No | Not applicable | — / — | UXS-01, UXS-08 | — | RF-5 | Working |
| UXI-120 | Linked refund and return entries (amount, method, source, time, reason) | Owner · CAP-22 | Owner only (refunds and returns are Owner records outside the cashier's scope, SEC-AZ-03); the original Sale stays unchanged (BR-RFD-12) | Read: linked correction records | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-121 | Button "Kembali ke …", naming the page it was opened from | Owner, Cashier, Combined · CAP-21, CAP-22 | Always; the label names the destination (NC-01) | Navigate → the list or page it was opened from (UXP-201 by default) | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-203 "Pengembalian uang dan retur"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-122 | Per line (one per product): "Jumlah barang diretur", "Kondisi" (Layak jual / Tidak layak jual), "Uang dikembalikan", with the remaining bounds shown | Owner · CAP-24 | Each line of the original Sale with a remaining bound (BR-RFD-03) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05, UXS-35 | — | RF-5 | Working |
| UXI-123 | Field "Cara pengembalian uang": Tunai / Transfer bank | Owner · CAP-24 | When money is refunded (BR-RFD-05) | Local only: changes the page or draft; sends no request | Field | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-124 | Field "Sumber uang": a listed OPEN cash session (register, responsible user, expected cash) or "Dana Pemilik" | Owner · CAP-24 | Method Tunai; exactly one explicit source, no default, no split (BR-RFD-06, D-045); only OPEN sessions are listed (BR-RFD-07) | Local only: changes the page or draft; sends no request | Field | No | Not applicable | — / — | UXS-36 | — | RF-5 | Working |
| UXI-125 | Field "Referensi pengembalian uang" | Owner · CAP-24 | When money is refunded; every payout records a reference (BR-RFD-05): the Owner types it for a transfer or an Owner-funded payout; for a drawer-funded refund, whether the Owner types it or the server generates it is set in RF-5 (SCHEMA section 16) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-126 | Field "Alasan" (mandatory) | Owner · CAP-24 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-127 | Button "Tinjau" | Owner · CAP-24 | Enabled when at least one quantity or amount is entered and the form is valid; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-128 | Review dialog: "Konfirmasi pengembalian" (shows lines, total, method, source, goods condition, reason) | Owner · CAP-24 | Inside the review dialog | Command: Drawer-funded refund or return (TECHNICAL_RULES section 7.2) or Command: Owner-funded or BANK_TRANSFER refund or return (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-RFD-02 (with recent authentication) | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06; UXS-04 → UXP-204; UXS-35, UXS-36, UXS-30, UXS-27, UXS-28, UXS-05 → back to the form; UXS-11; UXS-14 with "Jangan ambil uang dari laci sampai pengembalian ini tercatat. Jangan buat pengembalian baru." (BR-RFD-17); UXS-15; UXS-16 | — | RF-5 | Working |
| UXI-129 | Review dialog: "Kembali ubah" | Owner · CAP-24 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the form | RF-5 | Working |
| UXI-130 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-24, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073); no cash is taken until COMMITTED (D-046) | Command: refund family of the frozen request (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first); COMMITTED → UXP-204 | — | RF-5 | Working |
| UXI-131 | Button "Batal" | Owner · CAP-24 | Always | Navigate → UXP-202 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-202 with the unsaved-changes guard | RF-5 | Working |
| UXI-132 | Link "Kembali ke Detail penjualan" (contextual back) | Owner · CAP-24 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-202 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-204 "Pengembalian tercatat"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-133 | Instruction "Ambil :uang dari :laci sekarang dan serahkan kepada pelanggan.", with the guidance to hand it over before the next opening count of that drawer | Owner · CAP-24 | Drawer-funded refunds only (D-046; D-068); the most prominent element of the page | Read: the committed refund | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-134 | Button "Kembali ke Detail penjualan" | Owner · CAP-24 | Always | Navigate → UXP-202 | Primary | No | Not applicable | — / — | UXS-01 | Back → UXP-202 | RF-5 | Working |

#### UXP-205 "Sesi kas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-135 | Filters "Status" (Dibuka / Ditutup) and "Tanggal" | Owner, Cashier, Combined · CAP-21, CAP-22 | Always; Cashier and Combined see only their own sessions | Read: sessions | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-136 | Filters "Laci kas" and "Penanggung jawab" | Owner · CAP-22 | Owner only | Read: filtered sessions | Filter | No | Not applicable | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-137 | List row (number, register, responsible user, opened, closed, variance) | Owner, Cashier, Combined · CAP-21, CAP-22 | Each session within scope | Navigate → UXP-206 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-138 | Paging "Berikutnya" / "Sebelumnya" | Owner, Cashier, Combined · CAP-21, CAP-22 | When more rows exist | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-206 "Detail sesi kas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-139 | Button "Tutup sesi kas" | Owner, Cashier, Combined · CAP-21 | Session OPEN and the viewer is its responsible user; the close entry is disabled while this user holds an unresolved session close request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-105 | Primary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-140 | Button "Tutup sesi oleh Pemilik" | Owner · CAP-23 | Session OPEN and responsible user is not the Owner; the close entry is disabled while this user holds an unresolved session close request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-207 | Primary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-141 | Button "Cetak laporan" | Owner, Cashier, Combined · CAP-21, CAP-22 | Session CLOSED; within scope | Command: Print, reprint or export request (TECHNICAL_RULES section 7.2); then the browser print dialog | Primary | No | Safe: Enter never prints (UXA-10) | — / — | UXS-06, UXS-44 | — | RF-5 | Working |
| UXI-142 | Button "Unduh CSV" | Owner, Cashier, Combined · CAP-21, CAP-22 | Session CLOSED; within scope (BR-CASH-22) | Command: Print, reprint or export request (TECHNICAL_RULES section 7.2); then the file download | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-44 | — | RF-5 | Working |
| UXI-143 | Section "Pengembalian dari laci kas" beside the variance | Owner, Cashier, Combined · CAP-21, CAP-22 | When the session has drawer-funded refunds (BR-CASH-20); for the Owner, with the guidance of D-068 when the variance is positive | Read: the report's refund entries | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-144 | Section "Catatan tinjauan Pemilik" (every note in order with time and author) | Owner · CAP-22 | Session CLOSED; notes are append-only and have no edit or delete control (D-069) | Read: review notes | Secondary | No | Not applicable | — / — | UXS-02 | — | RF-5 | Working |
| UXI-145 | Button "Tambah catatan tinjauan" | Owner · CAP-22 | Session CLOSED; may be used again for every new note (D-069) | Local only: changes the page or draft; sends no request; opens the inline note form | Secondary | No | Not applicable | — / — | Inline form | — | RF-5 | Working |
| UXI-146 | Inline form: field "Catatan" and button "Simpan catatan" | Owner · CAP-22 | Inline form open; 1 to 2,000 characters | Command: Owner review note on a CLOSED session (TECHNICAL_RULES section 7.2), sent by "Simpan catatan"; the field "Catatan" is local only | "Simpan catatan": Primary; "Catatan": Field | No (append-only note; it changes no count, variance or snapshot, BR-CASH-19) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16; the note appears in the list | — | RF-5 | Working |
| UXI-147 | Inline form: button "Batal" | Owner · CAP-22 | Inline form open | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | Cancel closes the form | RF-5 | Working |
| UXI-148 | Button "Kembali ke …", naming the page it was opened from | Owner, Cashier, Combined · CAP-21, CAP-22 | Always; the label names the destination (NC-01) | Navigate → the page it was opened from (UXP-205 by default); after the success of UXP-105 or UXP-207, UXP-205 (NC-01) | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-207 "Tutup sesi oleh Pemilik"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-149 | Step 1 (preview): responsible user, register, totals, drawer-funded refunds with the reminder of D-068 | Owner · CAP-23 | Always. The preview lists the responsible user's unresolved checkout request receipts, each resolved by the Owner with "Cek status" (CAP-34); BR-CASH-16 is met as INTERACTION_CONTRACTS section 2.5 says (D-087, ADR-009 section 4) | Read: the close preview | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-150 | Step 1: button "Lanjut hitung" | Owner · CAP-23 | Disabled while a listed checkout of the responsible user is unresolved (UXS-13, BR-CASH-16; INTERACTION_CONTRACTS section 2.5; D-087) | Local only: changes the page or draft; sends no request | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | Step 2 | — | RF-5 | Working |
| UXI-151 | Step 2: fields "Uang fisik di laci" and "Alasan" (mandatory) | Owner · CAP-23 | Step 2 | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Numeric keyboard for the amount | UXS-05 | — | RF-5 | Working |
| UXI-152 | Step 2: button "Tinjau" | Owner · CAP-23 | Enabled when both are valid; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-153 | Step 2: button "Kembali ke pratinjau" | Owner · CAP-23 | Step 2 | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | Step 1 | Back to step 1 | RF-5 | Working |
| UXI-154 | Review dialog: "Tutup sesi" (states that the responsible user does not change) | Owner · CAP-23 | Inside the review dialog | Command: Close, own or Owner-assisted (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-CASH-18 (with recent authentication) | Safe: Enter never closes a session (BR-CAT-32, UXA-10) | Ctrl+Enter / — | UXS-06; UXS-04 → UXP-206; UXS-19 → step 1; UXS-30; UXS-05; UXS-11; UXS-14; UXS-15; UXS-16 | — | RF-5 | Working |
| UXI-155 | Review dialog: "Kembali ubah" | Owner · CAP-23 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to step 2 | RF-5 | Working |
| UXI-156 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-23, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Close, own or Owner-assisted (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first); COMMITTED → UXP-206 | — | RF-5 | Working |
| UXI-157 | Button "Batal" | Owner · CAP-23 | Every step; the guard asks once a count or reason is entered | Navigate → UXP-206 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard → UXP-206 | RF-5 | Working |
| UXI-158 | Link "Kembali ke Detail sesi kas" (contextual back) | Owner · CAP-23 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-206 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) once a count or reason is entered | RF-5 | Working |

### A3 — Products

#### UXP-301 "Produk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-159 | Field "Cari produk" (shared Product Search) | Owner, Ops, Combined · CAP-02 | Always | Read: Product Search results (BR-CAT-24, BR-CAT-25) | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03, UXS-33, UXS-22 | — | RF-5 | Working |
| UXI-160 | Filters "Kategori", "Merek", "Status" (Aktif / Nonaktif) | Owner, Ops, Combined · CAP-02 | Always | Read: filtered results | Filter | No | Not applicable | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-161 | Button "Tambah produk" | Owner, Ops, Combined · CAP-04 | Always | Navigate → UXP-303 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-162 | List row (thumbnail, name, SKU, price, stock state) | Owner, Ops, Combined · CAP-02, CAP-03 | Each result; inactive products marked read-only (BR-CAT-27) | Navigate → UXP-302 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-163 | Row overflow "Cetak label barcode" | Owner, Ops, Combined · CAP-08 | Product with an active barcode | Navigate → UXP-306 | Overflow | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-164 | Paging "Muat lebih banyak" | Owner, Ops, Combined · CAP-02 | When more results exist (25 per page, at most 100) | Read: the next page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-302 "Detail produk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-165 | Button "Ubah produk" | Owner, Ops, Combined · CAP-04, CAP-05 | Always | Navigate → UXP-304 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-166 | Overflow "Nonaktifkan" / "Aktifkan kembali", with a confirmation dialog | Owner, Ops, Combined · CAP-04 | "Nonaktifkan" on an active product, "Aktifkan kembali" on an inactive one; never a delete (D-042) | Command: Price change; product edit, including minimum stock and category; product deactivation or reactivation (TECHNICAL_RULES section 7.2) | Overflow | Confirmation dialog | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-17 | — | RF-5 | Working |
| UXI-167 | Section "Stok": current stock, state and "Riwayat stok" | Owner, Ops, Combined · CAP-14 | Always | Navigate → UXP-402 filtered to the product | Secondary | No | Not applicable | — / — | UXS-01, UXS-21 | — | RF-5 | Working |
| UXI-168 | Section "Riwayat harga jual" | Owner, Ops, Combined · CAP-04 | Always | Read: price revisions (BR-CAT-13) | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-169 | Photo: "Unggah foto" / "Ganti foto" | Owner, Ops, Combined · CAP-09 | "Unggah foto" without a photo, "Ganti foto" with one | Command: Product photo upload, replace or remove (TECHNICAL_RULES section 7.2) | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05 (file refused with its reason, SECURITY_MODEL section 13), UXS-12, UXS-14 | — | RF-5 | Working |
| UXI-170 | Photo: "Lepas foto", with a confirmation dialog | Owner, Ops, Combined · CAP-09 | With a photo; the file is kept, no longer shown (D-042) | Command: Product photo upload, replace or remove (TECHNICAL_RULES section 7.2) | Overflow | Confirmation dialog | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16 | — | RF-5 | Working |
| UXI-171 | Barcodes: "Tambah barcode pabrik" (inline form: token field, "Simpan barcode", "Batal") | Owner, Ops, Combined · CAP-06 | Always | Command: Barcode add, generate, retire (TECHNICAL_RULES section 7.2), sent by "Simpan barcode"; "Tambah barcode pabrik" opens the inline form and "Batal" closes it, both local only | "Tambah barcode pabrik": Secondary; "Simpan barcode": Primary; the token field: Field; "Batal": Secondary | No | The token field accepts a scan; Enter ends the code and saves nothing (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-37, UXS-38 | "Batal": cancel, sends nothing | RF-5 | Working |
| UXI-172 | Barcodes: "Buat barcode internal" | Owner, Ops, Combined · CAP-06 | Always | Command: Barcode add, generate, retire (TECHNICAL_RULES section 7.2) | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16 | — | RF-5 | Working |
| UXI-173 | Barcode row: "Pensiunkan barcode", with a dialog that records the reason class (Salah pasang / Alasan lain) | Owner, Ops, Combined · CAP-06 | Active token (BR-CAT-52) | Command: Barcode add, generate, retire (TECHNICAL_RULES section 7.2) | Overflow | Confirmation dialog with the reason class | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16 | — | RF-5 | Working |
| UXI-174 | Barcodes: "Koreksi salah pasang barcode" | Owner · CAP-07 | Owner only; disabled while this user holds an unresolved barcode correction request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-305 | Overflow | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-175 | Button "Cetak label barcode" | Owner, Ops, Combined · CAP-08 | Product with an active barcode | Navigate → UXP-306 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-176 | Link "Riwayat biaya" | Owner · CAP-20 | Owner only | Navigate → UXP-505 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-177 | Button "Kembali ke Produk", or "Kembali ke …" naming the page it was opened from | Owner, Ops, Combined · CAP-02 | Always; the label names the destination (NC-01) | Navigate → the page it was opened from when that is the search-first step of UXP-303, UXP-612 or UXP-006 (a list keeps its filters and page, NC-10), otherwise UXP-301; after the success of UXP-303, UXP-301 (NC-01) | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-303 "Tambah produk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-178 | Step 1 (search first): fields "Barcode", "SKU", "Nama" and button "Cek" | Owner, Ops, Combined · CAP-02, CAP-04 | Always first (BR-CAT-04) | Read: exact barcode and SKU matches and similar names, run by "Cek" or by the Enter that ends a scanned code in "Barcode"; the fields themselves are local only | "Cek": Primary; "Barcode", "SKU" and "Nama": Field | No | The barcode field accepts a scan; Enter ends the code and runs the check only | — / — | UXS-01, UXS-37 with a link to the existing product, UXS-47, UXS-03 | — | RF-5 | Working |
| UXI-179 | Step 1: link to an exactly matching product | Owner, Ops, Combined · CAP-02 | When the barcode or SKU already exists (refused, BR-CAT-04) | Navigate → UXP-302 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-180 | Step 1: button "Tetap buat produk baru" | Owner, Ops, Combined · CAP-04 | Only when similar names were found and no exact match (UXS-47) | Local only: changes the page or draft; sends no request; goes to the form | Primary | No | Not applicable | — / — | Form | — | RF-5 | Working |
| UXI-181 | Step 1: button "Lanjut isi data" | Owner, Ops, Combined · CAP-04 | When nothing similar or exact was found | Local only: changes the page or draft; sends no request; goes to the form | Primary | No | Not applicable | — / — | Form | — | RF-5 | Working |
| UXI-182 | Form: SKU, Nama, Kategori (active only), Merek, Satuan, Jumlah desimal, Harga jual, Stok minimum, Barcode pabrik (optional) | Owner, Ops, Combined · CAP-04, CAP-05, CAP-06 | Step 2 | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-183 | Button "Simpan produk" | Owner, Ops, Combined · CAP-04 | Step 2; the new product starts with stock 0 (BR-CAT-03) | Command: Product creation (TECHNICAL_RULES section 7.2) | Primary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-37, UXS-38, UXS-32; UXS-04 → UXP-302, or back to UXP-405 or UXP-413 with the product added | — | RF-5 | Working |
| UXI-184 | Button "Batal" | Owner, Ops, Combined · CAP-04 | Always | Navigate → the page that opened it | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard | RF-5 | Working |
| UXI-185 | Link "Kembali ke …" (contextual back), naming the page that opened it | Owner, Ops, Combined · CAP-02, CAP-04 | Always, at the top of the page; the label names the destination (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → the page that opened it | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-304 "Ubah produk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-186 | Form: Nama, Kategori (active only), Merek, Stok minimum, Harga jual (old → new shown), Satuan and Jumlah desimal | Owner, Ops, Combined · CAP-04, CAP-05 | Satuan and Jumlah desimal disabled after the product's first movement, with the reason (UXS-13, BR-CAT-07) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05, UXS-13 | — | RF-5 | Working |
| UXI-187 | Button "Simpan perubahan" | Owner, Ops, Combined · CAP-04, CAP-05 | Enabled when a field changed; otherwise disabled with the reason shown (UXS-13) | Command: Price change; product edit, including minimum stock and category; product deactivation or reactivation (TECHNICAL_RULES section 7.2) | Primary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-13, UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-17, UXS-32; UXS-04 → UXP-302 | — | RF-5 | Working |
| UXI-188 | Button "Batal" | Owner, Ops, Combined · CAP-04 | Always | Navigate → UXP-302 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-302 with the unsaved-changes guard | RF-5 | Working |
| UXI-189 | Link "Kembali ke Detail produk" (contextual back) | Owner, Ops, Combined · CAP-04, CAP-05 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-302 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-305 "Koreksi salah pasang barcode"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-190 | Field "Barcode" (scan or type) with its current mapping shown | Owner · CAP-07 | Always; only manufacturer barcodes, never `BK-P-…` codes (D-051, D-052) | Read: the token's mapping and retirement record | Field | No | Scan accepted; Enter ends the code and runs the lookup only | — / — | UXS-01, UXS-38, UXS-33 | — | RF-5 | Working |
| UXI-191 | Field "Produk yang benar" (shared Product Search) | Owner · CAP-02, CAP-07 | After a correctable token is found | Read: Product Search | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-192 | Field "Alasan" (mandatory) | Owner · CAP-07 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-193 | Button "Tinjau" | Owner · CAP-07 | Enabled when token, product and reason are valid; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-194 | Review dialog: "Konfirmasi koreksi" | Owner · CAP-07 | Inside the review dialog | Command: Barcode misassignment correction (move or re-attach) (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-CAT-50, BR-CAT-51 (with recent authentication) | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-38, UXS-17; UXS-04 → UXP-302 of the correct product | — | RF-5 | Working |
| UXI-195 | Review dialog: "Kembali ubah" | Owner · CAP-07 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the form | RF-5 | Working |
| UXI-196 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-07, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Barcode misassignment correction (move or re-attach) (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first); COMMITTED → UXP-302 | — | RF-5 | Working |
| UXI-197 | Button "Batal" | Owner · CAP-07 | Always | Navigate → UXP-302 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-302 | RF-5 | Working |
| UXI-198 | Link "Kembali ke Detail produk" (contextual back) | Owner · CAP-07 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-302 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-306 "Cetak label barcode"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-199 | Field "Barcode" (when the product has more than one active token) and "Jumlah label" | Owner, Ops, Combined · CAP-08 | Always; label size candidate 50 × 30 mm, to be confirmed on the real printer (BR-CAT-36, gate DEVICES) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-200 | Button "Cetak" | Owner, Ops, Combined · CAP-08 | Always; the label shows code, name and SKU, never cost (BR-CAT-35) | Command: Print, reprint or export request (TECHNICAL_RULES section 7.2); then the browser print dialog | Primary | No | Safe: Enter never prints (UXA-10) | — / — | UXS-06, UXS-44 | — | RF-5 | Working |
| UXI-201 | Button "Kembali ke Detail produk" | Owner, Ops, Combined · CAP-08 | Always | Navigate → UXP-302 | Secondary | No | Not applicable | — / — | — | Back and cancel → UXP-302 | RF-5 | Working |

#### UXP-307 "Kategori"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-202 | Filter "Status" (Aktif / Nonaktif) | Owner, Ops, Combined · CAP-04 | Always | Read: categories | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-22 | — | RF-5 | Working |
| UXI-203 | Button "Tambah kategori" (inline form: "Nama kategori", "Simpan", "Batal") | Owner, Ops, Combined · CAP-04 | Always | Command: Category create, rename, deactivate or reactivate (TECHNICAL_RULES section 7.2), sent by "Simpan"; "Tambah kategori" opens the inline form and "Batal" closes it, both local only | "Tambah kategori" and "Simpan": Primary; "Nama kategori": Field; "Batal": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16 | "Batal": cancel, sends nothing | RF-5 | Working |
| UXI-204 | Row "Ubah nama" (inline form: "Simpan", "Batal") | Owner, Ops, Combined · CAP-04 | Every category | Command: Category create, rename, deactivate or reactivate (TECHNICAL_RULES section 7.2), sent by "Simpan"; "Ubah nama" opens the inline form and "Batal" closes it, both local only | "Ubah nama": Secondary; "Simpan": Primary; "Batal": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-17 | "Batal": cancel, sends nothing | RF-5 | Working |
| UXI-205 | Row "Nonaktifkan" / "Aktifkan kembali", with a confirmation dialog | Owner, Ops, Combined · CAP-04 | Every category; deactivating changes no product (BR-CAT-09) | Command: Category create, rename, deactivate or reactivate (TECHNICAL_RULES section 7.2) | Overflow | Confirmation dialog | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-17 | — | RF-5 | Working |

#### UXP-308 "Impor produk (CSV)"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-206 | Section "Format kolom" (the required header and columns) | Owner, Ops, Combined · CAP-10 | Always; the column list is set in RF-5 (SEC-UP-05) | Read: the column list | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-207 | Button "Pilih berkas CSV" | Owner, Ops, Combined · CAP-10 | No preview open; disabled while this user holds an unresolved CSV import request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Request, not a command (TR-CMD-03): upload and whole-file preview, temporary working data only (D-054); its route and contract are set in RF-5 | Primary | No | Not applicable | — / — | UXS-06, UXS-39, UXS-05 (file refused, SECURITY_MODEL section 13), UXS-12, UXS-13 | — | RF-5 | Working |
| UXI-208 | Preview table with one error per row, including "Tidak ada kategori aktif dengan nama ini." and "Nama kategori cocok dengan lebih dari satu kategori aktif" (D-069) | Owner, Ops, Combined · CAP-10 | After a preview | Read: preview results | Secondary | No | Not applicable | — / — | UXS-39 | — | RF-5 | Working |
| UXI-209 | Link "Buat kategori di halaman Kategori" | Owner, Ops, Combined · CAP-04 | When a row names a category that does not exist (D-069); the import itself never creates a category | Navigate → UXP-307 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-210 | Button "Impor [jumlah] produk" | Owner, Ops, Combined · CAP-10 | Disabled while any row has an error (UXS-13, UXS-39; BR-CAT-38, D-069) | Command: CSV import confirmation (TECHNICAL_RULES section 7.2) | Primary | Yes: the preview is the review (BR-CAT-38) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-39, UXS-37, UXS-17; UXS-04 → UXP-301 | — | RF-5 | Working |
| UXI-211 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner, Ops, Combined · CAP-10, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: CSV import confirmation (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; COMMITTED → UXP-301 | — | RF-5 | Working |
| UXI-212 | Button "Batalkan impor" | Owner, Ops, Combined · CAP-10 | A preview is open; the unsaved-changes dialog asks first (UXS-45) | Request, not a command (TR-CMD-03): cancel the previewed import and remove its temporary file (D-054); no business fact; its route and contract are set in RF-5 | Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-45; UXS-04; empty state | — | RF-5 | Working |

### A4 — Stock

#### UXP-401 "Stok"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-213 | Field "Cari produk" (shared Product Search) | Owner, Ops, Combined · CAP-02, CAP-14 | Always | Read: stock of matching products | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03, UXS-33 | — | RF-5 | Working |
| UXI-214 | Filters "Keadaan stok" (Habis / Menipis / Normal) and "Kategori" | Owner, Ops, Combined · CAP-14 | Always; a new product with stock 0 is Habis, never Normal (BR-INV-40) | Read: filtered stock | Filter | No | Not applicable | — / — | UXS-01, UXS-03 | — | RF-5 | Working |
| UXI-215 | Time of the last refresh and "Muat ulang" | Owner, Ops, Combined · CAP-14 | Always; automatic refresh at least every 30 seconds while visible (BR-INV-41) | Read: stock and attention | Secondary | No | Not applicable | — / — | UXS-01, UXS-21 | — | RF-5 | Working |
| UXI-216 | List row (product, SKU, stock, minimum stock, state) | Owner, Ops, Combined · CAP-14 | Each product | Navigate → UXP-402 filtered to the product | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-217 | Row overflow "Sesuaikan stok" | Owner · CAP-16 | Owner only; disabled with the reason (UXS-13) while the onboarding freeze is on (UXS-27; BR-INV-24) or while this user holds an unresolved receiving, manual issue or adjustment request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-410 with the product | Overflow | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-218 | Paging "Muat lebih banyak" | Owner, Ops, Combined · CAP-14 | When more rows exist | Read: the next page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-402 "Riwayat stok"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-219 | Filters "Produk" (shared Product Search), "Jenis mutasi", "Tanggal" | Owner, Ops, Combined · CAP-14, CAP-02 | Always | Read: movements | Filter | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-02, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-220 | List row (time, product, type, quantity, actor, source) | Owner, Ops, Combined · CAP-14 | Each movement within scope | Navigate → UXP-403 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-221 | Button "Sesuaikan stok" | Owner · CAP-16 | Owner only; with a product filter set; disabled with the reason (UXS-13) while the onboarding freeze is on (UXS-27; BR-INV-24) or while this user holds an unresolved receiving, manual issue or adjustment request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-410 with the product | Secondary | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-222 | Paging "Berikutnya" / "Sebelumnya" | Owner, Ops, Combined · CAP-14 | When more rows exist (keyset paging) | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-223 | Button "Kembali ke …", naming the page it was opened from | Owner, Ops, Combined · CAP-14 | Only when opened from UXP-302, a row of UXP-401, UXP-415 or UXP-006, the pages that the BACK of UXP-402 lists (NAVIGATION_CONTRACTS); not shown when the page is reached from the Stok sub-navigation or after the success of UXP-410, whose completed adjustment form does not count as the page it was opened from (NC-01); the label names the destination (NC-01) | Navigate → the page it was opened from: UXP-302, UXP-401, UXP-415 or UXP-006 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-403 "Detail mutasi stok"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-224 | Link to the source (receiving document, manual issue, Sale, adjustment, reversal) | Owner, Ops, Combined · CAP-14, CAP-11, CAP-13, CAP-22 | Source within the viewer's scope; for Ops a Sale source is text without a link (SEC-AZ-03) | Navigate → UXP-406, UXP-409, UXP-202 (Owner) or the paired movement | Secondary | No | Not applicable | — / — | UXS-01, UXS-08 | — | RF-5 | Working |
| UXI-225 | Section "Bukti biaya" (status, amount, corrections) | Owner · CAP-20 | Owner only; for OPENING, RECEIPT and positive ADJUSTMENT | Read: cost evidence | Secondary | No | Not applicable | — / — | UXS-24, UXS-26 | — | RF-5 | Working |
| UXI-226 | Button "Koreksi biaya" / "Lengkapi biaya" | Owner · CAP-19 | Owner only; movements that carry acquisition cost evidence; "Lengkapi" when UNKNOWN; disabled while this user holds an unresolved cost correction or completion request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-504 | Secondary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-227 | Button "Batalkan mutasi" | Owner · CAP-17 | Owner only; an OPENING, RECEIPT or MANUAL_ISSUE not yet reversed (BR-INV-34); disabled with the reason when already reversed, or while this user holds an unresolved reversal request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06). While the onboarding freeze is on: for an OPENING the control is also shown, as the correction of an onboarding entry before release (UXI-228; D-087, T-17 b); for a RECEIPT or MANUAL_ISSUE it is disabled with the reason (UXS-13, UXS-27; BR-INV-24). Not shown for other types (BR-INV-36) | Navigate → UXP-411 | Destructive | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-228 | Button "Batalkan mutasi" for an OPENING while onboarding is active (correction of an onboarding entry before release) | Owner · CAP-15, CAP-17 | Owner only; while the onboarding freeze is on and not held, an OPENING not yet reversed (D-087, T-17 b; D-072); disabled with the reason while this user holds an unresolved reversal request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-411; once the OPENING is reversed, its replacement entry is recorded on UXP-413 (RC-48) | Destructive | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-229 | Button "Kembali ke …", naming the page it was opened from | Owner, Ops, Combined · CAP-14 | Always; the label names the destination (NC-01) | Navigate → the page it was opened from (UXP-402 by default) | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-404 "Barang masuk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-230 | Filter "Tanggal" and search "Cari referensi atau produk" | Owner, Ops, Combined · CAP-11 | Always | Read: receiving documents | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-231 | Button "Catat barang masuk" | Owner, Ops, Combined · CAP-11 | Disabled with the reason (UXS-13) while the onboarding freeze is on (UXS-27; BR-INV-24) or while this user holds an unresolved receiving, manual issue or adjustment request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06); no list of unsaved drafts exists (D-069) | Navigate → UXP-405 | Primary | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-232 | List row (time, reference, lines, actor) | Owner, Ops, Combined · CAP-11 | Each confirmed document | Navigate → UXP-406 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-233 | Paging "Berikutnya" / "Sebelumnya" | Owner, Ops, Combined · CAP-11 | When more rows exist | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-405 "Catat barang masuk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-234 | Field "Cari atau scan produk" (shared Product Search) | Owner, Ops, Combined · CAP-02, CAP-11 | Always. A scan of a known code adds one base unit to the latest line of that product, or a new line when there is none; choosing a product from the results always adds a new line, so a product may have several lines with different costs (one line per product applies to Sales only, D-067) | Read: Product Search | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03, UXS-33, UXS-32 | — | RF-5 | Working |
| UXI-235 | Button "Tambah produk" for an unknown code | Owner, Ops, Combined · CAP-04 | After UXS-33; opens UXP-303 over this page with the code filled in, keeping the draft (BR-CAT-31) | Navigate → UXP-303 (over this page) | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-236 | Per line: "Jumlah", "Biaya" (total for the line) or toggle "Biaya belum diketahui"; for the Owner, with Biaya 0, the field "Bukti atau alasan biaya nol" | Owner, Ops, Combined · CAP-11, CAP-12 | Each line. A zero cost needs evidence or a reason confirmed by the Owner (BR-COST-04, BR-INV-13): the Owner may enter Biaya 0 with its evidence or reason; for Ops and Combined a zero is disabled with the reason (UXS-13) and "Biaya belum diketahui" is used, which the Owner completes on UXP-504 | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Numeric keyboard | UXS-05, UXS-13, UXS-24 | — | RF-5 | Working |
| UXI-237 | Per line: "Hapus baris" | Owner, Ops, Combined · CAP-11 | Each line | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | Draft updated | — | RF-5 | Working |
| UXI-238 | Fields "Referensi (opsional)" and "Tanggal dokumen (opsional)" | Owner, Ops, Combined · CAP-11 | Always; the document date never backdates the movement (BR-INV-05) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-239 | Button "Tinjau" | Owner, Ops, Combined · CAP-11 | Enabled when: at least one valid line; at most 500 lines (TR section 11.2); otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog, UXS-05 | — | RF-5 | Working |
| UXI-240 | Review dialog: "Konfirmasi barang masuk" (lines, quantities, costs, lines with unknown cost highlighted) | Owner, Ops, Combined · CAP-11 | Inside the review dialog | Command: Receiving confirmation, manual issue, adjustment (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-INV-10, BR-SALE-15 | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-27, UXS-28, UXS-32; UXS-17; UXS-04 → UXP-406 | — | RF-5 | Working |
| UXI-241 | Review dialog: "Kembali ubah" | Owner, Ops, Combined · CAP-11 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the draft | RF-5 | Working |
| UXI-242 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner, Ops, Combined · CAP-11, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Receiving confirmation, manual issue, adjustment (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; COMMITTED → UXP-406 | — | RF-5 | Working |
| UXI-243 | Button "Batal" | Owner, Ops, Combined · CAP-11 | Always | Navigate → UXP-404 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-404 with the unsaved-changes guard; the draft is lost (D-069) | RF-5 | Working |
| UXI-244 | Control "Simpan draf" / list "Draf barang masuk" | Owner, Ops, Combined · CAP-11 | — | — | — | No | Not applicable | — / — | — | — | RF-5 | Not provided by decision: receiving drafts stay in page memory for V1 (D-069, T-17 a); listed so that its absence is explicit |
| UXI-245 | Link "Kembali ke Barang masuk" (contextual back) | Owner, Ops, Combined · CAP-02, CAP-11 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-404 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-406 "Detail barang masuk"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-246 | Per line: purchase cost; after an Owner correction, "Dikoreksi Pemilik", the current cost and the cost at confirmation | Owner, Ops, Combined · CAP-12 | Owner, Ops and Combined; Ops never sees the reason or the correction history (D-047) | Read: lines and cost as the role may see them | Secondary | No | Not applicable | — / — | UXS-24, UXS-26 | — | RF-5 | Working |
| UXI-247 | Per line (Owner): correction history and reasons | Owner · CAP-12, CAP-20 | Owner only | Read: correction history | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-248 | Per line: link to the movement | Owner, Ops, Combined · CAP-14 | Always | Navigate → UXP-403 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-249 | Per line (Owner): "Koreksi biaya" / "Lengkapi biaya" | Owner · CAP-19 | Owner only; disabled while this user holds an unresolved cost correction or completion request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-504 | Secondary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-250 | Per line (Owner): "Batalkan mutasi" | Owner · CAP-17 | Owner only; line's RECEIPT not yet reversed, otherwise disabled with the reason (UXS-13); disabled while the onboarding freeze is on (UXS-27; BR-INV-24) or while this user holds an unresolved reversal request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-411 | Destructive | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-251 | Button "Kembali ke Barang masuk", or "Kembali ke …" naming the page it was opened from | Owner, Ops, Combined · CAP-11 | Always; the label names the destination (NC-01) | Navigate → the page it was opened from when that is UXP-403, UXP-612 or UXP-006 (a list keeps its filters and page, NC-10), otherwise UXP-404; after the success of UXP-405, UXP-404 (NC-01) | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-407 "Barang keluar"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-252 | Filters "Tanggal" and "Alasan" | Owner, Ops, Combined · CAP-13 | Always | Read: manual issues | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-253 | Button "Catat barang keluar" | Owner, Ops, Combined · CAP-13 | Disabled with the reason (UXS-13) while the onboarding freeze is on (UXS-27; BR-INV-24) or while this user holds an unresolved receiving, manual issue or adjustment request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-408 | Primary | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-254 | List row (time, reason, lines, actor) | Owner, Ops, Combined · CAP-13 | Each issue | Navigate → UXP-409 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-255 | Paging "Berikutnya" / "Sebelumnya" | Owner, Ops, Combined · CAP-13 | When more rows exist | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-408 "Catat barang keluar"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-256 | Field "Cari atau scan produk" (shared Product Search) | Owner, Ops, Combined · CAP-02, CAP-13 | Always | Read: Product Search | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03, UXS-33, UXS-32 | — | RF-5 | Working |
| UXI-257 | Per line: "Jumlah" and "Hapus baris" | Owner, Ops, Combined · CAP-13 | Each line | Local only: changes the page or draft; sends no request; "Hapus baris" removes the line from the unsaved draft | "Jumlah": Field; "Hapus baris": Secondary | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Decimal keyboard | UXS-05 | — | RF-5 | Working |
| UXI-258 | Fields "Alasan" (Rusak, Pemakaian internal, Hilang, Sampel, Lainnya) and "Catatan (opsional)" | Owner, Ops, Combined · CAP-13 | Always; the reason is mandatory (BR-INV-19) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-259 | Button "Tinjau" | Owner, Ops, Combined · CAP-13 | Enabled when: at least one valid line and a reason; at most 200 lines; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-260 | Review dialog: "Konfirmasi barang keluar" (stock before, change and after per line) | Owner, Ops, Combined · CAP-13 | Inside the review dialog | Command: Receiving confirmation, manual issue, adjustment (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-INV-10, BR-INV-19 | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-31, UXS-27, UXS-28, UXS-32; UXS-17; UXS-04 → UXP-409 | — | RF-5 | Working |
| UXI-261 | Review dialog: "Kembali ubah" | Owner, Ops, Combined · CAP-13 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the draft | RF-5 | Working |
| UXI-262 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner, Ops, Combined · CAP-13, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Receiving confirmation, manual issue, adjustment (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; COMMITTED → UXP-409 | — | RF-5 | Working |
| UXI-263 | Button "Batal" | Owner, Ops, Combined · CAP-13 | Always | Navigate → UXP-407 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-407 with the unsaved-changes guard | RF-5 | Working |
| UXI-264 | Link "Kembali ke Barang keluar" (contextual back) | Owner, Ops, Combined · CAP-02, CAP-13 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-407 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-409 "Detail barang keluar"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-265 | Per line: link to the movement | Owner, Ops, Combined · CAP-14 | Always | Navigate → UXP-403 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-266 | Per line (Owner): "Batalkan mutasi" | Owner · CAP-17 | Owner only; line's MANUAL_ISSUE not yet reversed, otherwise disabled with the reason (UXS-13); disabled while the onboarding freeze is on (UXS-27; BR-INV-24) or while this user holds an unresolved reversal request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-411 | Destructive | No | Not applicable | — / — | UXS-01, UXS-13, UXS-27 | — | RF-5 | Working |
| UXI-267 | Button "Kembali ke Barang keluar", or "Kembali ke …" naming the page it was opened from | Owner, Ops, Combined · CAP-13 | Always; the label names the destination (NC-01) | Navigate → the page it was opened from when that is UXP-403, UXP-612 or UXP-006 (a list keeps its filters and page, NC-10), otherwise UXP-407; after the success of UXP-408, UXP-407 (NC-01) | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-410 "Sesuaikan stok"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-268 | Field "Cari atau scan produk" (shared Product Search) | Owner · CAP-02, CAP-16 | Always | Read: Product Search | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03, UXS-33, UXS-32 | — | RF-5 | Working |
| UXI-269 | Per line: "Tambah" / "Kurangi", "Jumlah"; for a positive line "Biaya" or "Biaya belum diketahui" (a zero cost with its evidence or reason, BR-COST-04); "Hapus baris" | Owner · CAP-16 | Each line (BR-INV-32) | Local only: changes the page or draft; sends no request; "Hapus baris" removes the line from the unsaved draft | "Hapus baris": Secondary; the other controls: Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Decimal keyboard | UXS-05, UXS-24 | — | RF-5 | Working |
| UXI-270 | Fields "Alasan", "Referensi hitung", "Referensi" (all mandatory, BR-INV-31) | Owner · CAP-16 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-271 | Button "Tinjau" | Owner · CAP-16 | Enabled when: valid lines and fields; at most 200 lines; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-272 | Review dialog: "Konfirmasi penyesuaian" (stock before, change and after per line) | Owner · CAP-16 | Inside the review dialog | Command: Receiving confirmation, manual issue, adjustment (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-INV-10, BR-INV-31 (with recent authentication) | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-31, UXS-27, UXS-28, UXS-32; UXS-17; UXS-04 → UXP-402 | — | RF-5 | Working |
| UXI-273 | Review dialog: "Kembali ubah" | Owner · CAP-16 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the draft | RF-5 | Working |
| UXI-274 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-16, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Receiving confirmation, manual issue, adjustment (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first); COMMITTED → UXP-402 | — | RF-5 | Working |
| UXI-275 | Button "Batal" | Owner · CAP-16 | Always | Navigate → the page that opened it | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard | RF-5 | Working |
| UXI-276 | Link "Kembali ke …" (contextual back), naming the page that opened it | Owner · CAP-02, CAP-16 | Always, at the top of the page; the label names the destination (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → the page that opened it | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-411 "Batalkan mutasi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-277 | Summary: the movement to reverse, its exact inverse, stock now and after (no quantity field, BR-INV-34) | Owner · CAP-17 | Always | Read: the source movement | Secondary | No | Not applicable | — / — | UXS-01, UXS-08 | — | RF-5 | Working |
| UXI-278 | Field "Alasan" (mandatory) | Owner · CAP-17 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-279 | Button "Tinjau" | Owner · CAP-17 | Enabled when a reason is entered; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-280 | Review dialog: "Konfirmasi pembatalan" | Owner · CAP-17 | Inside the review dialog | Command: Reversal (TECHNICAL_RULES section 7.2) | Destructive | Yes: BR-INV-34 (with recent authentication) | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-40, UXS-31, UXS-28, UXS-27, UXS-17; UXS-04 → UXP-403 of the reversal | — | RF-5 | Working |
| UXI-281 | Review dialog: "Kembali ubah" | Owner · CAP-17 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the form | RF-5 | Working |
| UXI-282 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-17, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Reversal (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first); COMMITTED → UXP-403 | — | RF-5 | Working |
| UXI-283 | Button "Batal" | Owner · CAP-17 | Always | Navigate → the page that opened it | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard | RF-5 | Working |
| UXI-284 | Link "Kembali ke …" (contextual back), naming the page that opened it | Owner · CAP-17 | Always, at the top of the page; the label names the destination (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → the page that opened it | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-412 "Stok awal"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-285 | Button "Mulai masa input stok awal", with a confirmation dialog stating that Sales and ordinary postings stop until the release | Owner · CAP-15 | Only while the freeze is off and onboarding has never been released for these business data: the onboarding period is allowed only once per business-data lifecycle (D-071). After the release the button is not shown, also after a reinstallation, a server migration or a restore of an older backup (D-072), and the page shows the completed onboarding read only; later corrections use adjustment, reversal and cost correction (BR-INV-29, D-071). Not shown while the onboarding state of restored business data waits for the Owner's restore reconciliation (UXS-50, D-072). Disabled while this user holds an unresolved onboarding set or release request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Command: Onboarding set or release (TECHNICAL_RULES section 7.2) | Secondary | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-13 | — | RF-5 | Working |
| UXI-286 | Button "Input stok awal" | Owner · CAP-15 | Onboarding freeze on and onboarding never released for these business data. When onboarding was released before a restore of an older backup, not offered even if the restored data still show the freeze on: that period does not continue, and the page shows the completed onboarding read only (D-072). Disabled while this user holds an unresolved opening stock entry request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06); disabled with the reason (UXS-13) while the onboarding state of restored business data waits for the Owner's restore reconciliation (UXS-50, D-072) | Navigate → UXP-413 | Primary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-287 | Checklist filter (Sudah dihitung / Belum dihitung) and rows (product, counted quantity or zero count, cost status) | Owner · CAP-15, CAP-14 | Always | Read: the onboarding checklist | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-22, UXS-24 | — | RF-5 | Working |
| UXI-288 | Checklist row link | Owner · CAP-14 | Rows with an OPENING movement | Navigate → UXP-403 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-289 | Button "Akhiri masa input stok awal", with a review dialog of the checks of BR-INV-28 (counts, barcodes, minimum stock, costs still unknown) that states the period cannot be started again, also not after a restore of an older backup (D-071, D-072) | Owner · CAP-15 | Onboarding freeze on and onboarding never released for these business data. When onboarding was released before a restore of an older backup, not offered even if the restored data still show the freeze on: that period does not continue, and how it is closed is specified in RF-5 and RF-6 (D-072). Disabled while this user holds an unresolved onboarding set or release request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06); disabled with the reason (UXS-13) while the onboarding state of restored business data waits for the Owner's restore reconciliation (UXS-50, D-072) | Command: Onboarding set or release (TECHNICAL_RULES section 7.2) | Secondary | Yes: BR-INV-28 (with recent authentication) | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-13 | — | RF-5 | Working |
| UXI-290 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-15, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Onboarding set or release (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first); COMMITTED → UXP-412 | — | RF-5 | Working |
| UXI-291 | Correction of an onboarding entry before release, per checklist row: "Tarik hitungan nol" for a zero-count entry, with a review dialog that asks for a reason; "Catat ulang stok awal" for a product whose OPENING was reversed; for an effective OPENING, the row link to UXP-403, where it is reversed (UXI-228) | Owner · CAP-15 | Owner only; while onboarding is active and not held (D-087, T-17 b; D-072): a zero count not yet withdrawn, or an OPENING reversed and not yet replaced | Command: Opening stock (TECHNICAL_RULES section 7.2): the withdrawal of a zero count (RC-14), or the replacement entry of a reversed OPENING (RC-48) | Secondary | Yes: D-087 (with a reason and recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-17, UXS-14, UXS-15, UXS-16, UXS-11 | Review dialog: "Batal" sends nothing | RF-5 | Working |

#### UXP-413 "Input stok awal"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-292 | Field "Cari atau scan produk" (shared Product Search) | Owner · CAP-02, CAP-15 | Always; products with an earlier movement are shown as not eligible (BR-INV-26); a product with an effective zero count or OPENING is shown as counted, and entering it again is not offered; a product whose zero count was withdrawn is eligible; a product whose OPENING was reversed shows its replacement entry (D-087; INTERACTION_CONTRACTS section 3.1) | Read: Product Search | Field | No | Scanner input: Enter ends the code and runs the lookup, never a command (BR-CAT-32) | — / — | UXS-01, UXS-03, UXS-33 | — | RF-5 | Working |
| UXI-293 | Button "Tambah produk" for an unknown code | Owner · CAP-04 | After UXS-33; opens UXP-303 over this page, keeping the rows (BR-INV-24 allows product creation) | Navigate → UXP-303 (over this page) | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-294 | Per row: "Jumlah" (0 records a zero count, BR-INV-27), "Biaya" or "Biaya belum diketahui" (positive rows; a zero cost with its evidence or reason, BR-COST-04), "Referensi hitung" (positive rows), "Hapus baris" | Owner · CAP-15 | Each row; at most 100 rows (BR-INV-25) | Local only: changes the page or draft; sends no request; "Hapus baris" removes the line from the unsaved draft | "Hapus baris": Secondary; the other controls: Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / Decimal keyboard | UXS-05, UXS-24 | — | RF-5 | Working |
| UXI-295 | Button "Tinjau" | Owner · CAP-15 | Enabled when: 1 to 100 valid rows; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog, UXS-05 | — | RF-5 | Working |
| UXI-296 | Review dialog: "Konfirmasi stok awal" | Owner · CAP-15 | Inside the review dialog | Command: Opening stock (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-SALE-15, BR-INV-25 | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-17; UXS-04 → UXP-412 | — | RF-5 | Working |
| UXI-297 | Review dialog: "Kembali ubah" | Owner · CAP-15 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the rows | RF-5 | Working |
| UXI-298 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-15, CAP-34 | Only in UXS-14 ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Opening stock (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; COMMITTED → UXP-412 | — | RF-5 | Working |
| UXI-299 | Button "Batal" | Owner · CAP-15 | Always | Navigate → UXP-412 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-412 with the unsaved-changes guard | RF-5 | Working |
| UXI-300 | Link "Kembali ke Stok awal" (contextual back) | Owner · CAP-02, CAP-15 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-412 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-414 "Insiden rekonsiliasi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-301 | Filter "Status" (Mutasi diblokir / Mutasi dibuka kembali) | Owner · CAP-18 | Always | Read: incidents | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-22 | — | RF-5 | Working |
| UXI-302 | List row (product, detected, status) | Owner · CAP-18 | Each incident | Navigate → UXP-415 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |

#### UXP-415 "Detail insiden"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-303 | Link "Riwayat stok" | Owner · CAP-14, CAP-18 | Always | Navigate → UXP-402 filtered to the product | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-304 | Button "Buka kembali mutasi", with a confirmation dialog stating that reopening follows the reconciliation of the facts (BR-INV-46) | Owner · CAP-18 | Incident open | Command: Reconciliation block (scheduled command) or reopening (Owner) (TECHNICAL_RULES section 7.2) | Primary | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11 | — | RF-5 | Working |
| UXI-305 | Button "Kembali ke Insiden rekonsiliasi" | Owner · CAP-18 | Always | Navigate → UXP-414 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

### A5 — Reports

#### UXP-501 "Rekap harian"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-306 | Date control ("Hari sebelumnya", "Hari berikutnya", date picker) | Owner · CAP-22 | Always; Asia/Jakarta half-open days (BR-RPT-01) | Read: the daily summary | Filter | No | Not applicable | — / — | UXS-01, UXS-22, UXS-25 | — | RF-5 | Working |
| UXI-307 | Session row (cashier, register, opened, closed, status, variance; a session crossing midnight shown once) | Owner · CAP-22 | Each relevant session (BR-RPT-02) | Navigate → UXP-206 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |

#### UXP-502 "Laporan keuangan"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-308 | Period choices "Hari Ini", "7 Hari", "Bulan Ini", "Bulan Lalu" | Owner · CAP-20 | Always (BR-COST-37) | Read: the report for the period | Filter | No | Not applicable | — / — | UXS-01, UXS-22, UXS-25 | — | RF-5 | Working |
| UXI-309 | Period choice "Rentang Kustom": dates, "Terapkan", "Batal" | Owner · CAP-20 | Always; applies only on "Terapkan"; "Batal" keeps the previous period; start after end or more than 366 days refused (TR section 11.4) | Read: the report for the custom range, on "Terapkan"; the dates and "Batal" are local only | Filter | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-01, UXS-05, UXS-22, UXS-25 | "Batal": keeps the previous period (BR-COST-37) | RF-5 | Working |
| UXI-310 | Trend chart: points that show date and value on pointer, focus or touch (D-018) | Owner · CAP-20 | Always; same snapshot and completeness as the figures (REQ-RPT-06) | Read: the trend of the report | Secondary | No | Not applicable | Tab into the chart, arrow keys move between points (UXA-19) / Tap a point | UXS-25 | — | RF-5 | Working |
| UXI-311 | Toggle "Tampilkan sebagai tabel" | Owner · CAP-20 | Always (accessible alternative of the chart) | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-312 | Link "Biaya perlu dilengkapi" | Owner · CAP-20 | When figures are incomplete (UXS-25) | Navigate → UXP-503 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |

#### UXP-503 "Biaya perlu dilengkapi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-313 | Filter "Produk" | Owner · CAP-20 | Always | Read: worklist | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-22, UXS-24, UXS-25 | — | RF-5 | Working |
| UXI-314 | Row button "Lengkapi biaya" / "Koreksi biaya" | Owner · CAP-19 | Each UNKNOWN cost source or marked product; disabled while this user holds an unresolved cost correction or completion request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-504 | Primary | No | Not applicable | — / — | UXS-01, UXS-28, UXS-24, UXS-13 | — | RF-5 | Working |
| UXI-315 | Row link "Riwayat biaya" | Owner · CAP-20 | Each row | Navigate → UXP-505 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-316 | Paging "Berikutnya" / "Sebelumnya" | Owner · CAP-20 | When more rows exist | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |

#### UXP-504 "Lengkapi biaya" or "Koreksi biaya"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-317 | Fields "Biaya baru" (total) or "Biaya nol (berbukti)" with its evidence or reason (BR-COST-04), and "Alasan" (mandatory) | Owner · CAP-19 | Always; the current value and history are shown above | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05, UXS-24 | — | RF-5 | Working |
| UXI-318 | Button "Tinjau" | Owner · CAP-19 | Enabled when: valid fields; otherwise disabled with the reason shown (UXS-13) | Local only: changes the page or draft; sends no request; opens the review dialog (old value → new value; reports will be recalculated) | Primary | No | Safe: Enter never activates it (UXA-10) | Ctrl+Enter (provisional, UXA-09) / — | UXS-13, Review dialog | — | RF-5 | Working |
| UXI-319 | Review dialog: "Konfirmasi koreksi" | Owner · CAP-19 | Inside the review dialog | Command: Cost correction or completion (TECHNICAL_RULES section 7.2) | Primary | Yes: BR-COST-20 (with recent authentication) | Safe: Enter never activates it (UXA-10) | Ctrl+Enter / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-40, UXS-28, UXS-17; UXS-04 → the page that opened it | — | RF-5 | Working |
| UXI-320 | Review dialog: "Kembali ubah" | Owner · CAP-19 | Inside the review dialog | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | Esc / — | — | Back to the form | RF-5 | Working |
| UXI-321 | Uncertain panel: "Kirim ulang" and "Cek status" | Owner · CAP-19, CAP-34 | Only in UXS-14, including SERVICE_BUSY (D-063) ("Cek status" also in UXS-15, after IDEMPOTENCY_CONFLICT or EPOCH_STALE, D-073) | Command: Cost correction or completion (TECHNICAL_RULES section 7.2), the same request, sent by "Kirim ulang"; "Cek status": Resolution of an uncertain intent (ADR-007 section 4; CAP-34) | "Kirim ulang": Primary; "Cek status": Secondary | No | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-14, UXS-15, UXS-16; "Kirim ulang" also UXS-11 (a repeat sent after the recent authentication has lapsed asks for it first) | — | RF-5 | Working |
| UXI-322 | Button "Batal" | Owner · CAP-19 | Always | Navigate → the page that opened it | Secondary | No | Not applicable | — / — | UXS-45 | Cancel with the unsaved-changes guard | RF-5 | Working |
| UXI-323 | Link "Kembali ke …" (contextual back), naming the page that opened it | Owner · CAP-19 | Always, at the top of the page; the label names the destination (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → the page that opened it | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-505 "Riwayat biaya"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-324 | Row button "Koreksi biaya" / "Lengkapi biaya" for a cost source | Owner · CAP-19 | Each cost source; disabled while this user holds an unresolved cost correction or completion request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-504 | Secondary | No | Not applicable | — / — | UXS-01, UXS-24, UXS-25, UXS-13 | — | RF-5 | Working |
| UXI-325 | Button "Kembali ke …", naming the page it was opened from | Owner · CAP-20 | Always; the label names the destination (NC-01) | Navigate → the page it was opened from | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

### A6 — Settings

#### UXP-601 "Pengaturan"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-326 | Link "Pengguna" | Owner · CAP-25 | Always | Navigate → UXP-602 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-327 | Link "Profil usaha" | Owner · CAP-30 | Always | Navigate → UXP-607 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-328 | Link "Laci kas" | Owner · CAP-31 | Always | Navigate → UXP-609 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-329 | Link "Status sistem" | Owner · CAP-32 | Always | Navigate → UXP-611 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-330 | Link "Riwayat aktivitas" | Owner · CAP-32 | Always | Navigate → UXP-612 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-331 | Link "Riwayat keamanan" | Owner · CAP-32 | Always | Navigate → UXP-613 | Navigation | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |

#### UXP-602 "Pengguna"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-332 | Filters "Status" (Aktif / Nonaktif / Dianonimkan) and "Peran" | Owner · CAP-25, CAP-28 | Always | Read: accounts | Filter | No | Not applicable | — / — | UXS-01, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-333 | Button "Tambah pengguna" | Owner · CAP-25 | Always | Navigate → UXP-603 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-334 | List row (name, email, roles, status, "Memegang sesi kas" mark) | Owner · CAP-27, CAP-28 | Each account | Navigate → UXP-604 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-335 | Button "Kembali ke Pengaturan" | Owner · CAP-25 | Always | Navigate → UXP-601 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-603 "Tambah pengguna"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-336 | Form: Nama tampilan, Email, Peran ("Kasir", "Admin Operasional", or both; at least one; never Pemilik) | Owner · CAP-25 | Always (SEC-ACC-02, CAP-33) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-337 | Button "Simpan dan buat tautan" | Owner · CAP-25 | Enabled when: valid form; otherwise disabled with the reason shown (UXS-13) | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Primary | No (recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-13, UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-12; UXS-04 → UXP-606 | — | RF-5 | Working |
| UXI-338 | Button "Batal" | Owner · CAP-25 | Always | Navigate → UXP-602 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-602 with the unsaved-changes guard | RF-5 | Working |
| UXI-339 | Link "Kembali ke Pengguna" (contextual back) | Owner · CAP-25 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-602 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-604 "Detail pengguna"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-340 | Section "Sesi kas yang masih dibuka" with links "Detail sesi kas" and "Tutup sesi oleh Pemilik" | Owner · CAP-22, CAP-23 | When the account holds an OPEN cash session, before and after deactivation (D-069); the close entry is disabled while this user holds an unresolved session close request (UXS-14, UXS-15), with the link to UXP-006 in the reason (UXS-13; NC-13, TR-ERR-06) | Navigate → UXP-206 ("Detail sesi kas") or UXP-207 ("Tutup sesi oleh Pemilik") | Secondary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-341 | Button "Ubah pengguna" | Owner · CAP-27 | Staff account not anonymized; never for a SUPER_ADMIN account (the Owner uses UXP-005) | Navigate → UXP-605 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-342 | Button "Buat tautan atur ulang kata sandi", with a confirmation dialog | Owner · CAP-26 | ACTIVE staff account that has set a password; at most one new link per account per 60 seconds | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Secondary | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-12; UXS-04 → UXP-606 | — | RF-5 | Working |
| UXI-343 | Button "Buat ulang tautan kata sandi pertama", with a confirmation dialog | Owner · CAP-25 | ACTIVE staff account that has not yet set a password (its first-password link expired or was revoked; SEC-ACC-02, SEC-ACC-04) | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Secondary | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-12; UXS-04 → UXP-606 | — | RF-5 | Working |
| UXI-344 | Button "Cabut tautan", with a confirmation dialog | Owner · CAP-26 | A link is active | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Overflow | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11 | — | RF-5 | Working |
| UXI-345 | Button "Nonaktifkan", with a confirmation dialog that names any OPEN cash session and says it stays open until the Owner closes it (D-069) | Owner · CAP-28 | ACTIVE staff account, also while it holds an OPEN cash session (D-069); never a SUPER_ADMIN account | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Destructive | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11 | — | RF-5 | Working |
| UXI-346 | Button "Aktifkan kembali", with a confirmation dialog | Owner · CAP-28 | DEACTIVATED staff account not anonymized; never a SUPER_ADMIN account (BR-USR-10) | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Secondary | Confirmation dialog (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11 | — | RF-5 | Working |
| UXI-347 | Button "Anonimkan akun", with a dialog that asks for a reason and states that it cannot be undone | Owner · CAP-29 | DEACTIVATED staff account, or a former Owner account deactivated by the replacement procedure (D-043, D-048) | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Destructive | Confirmation dialog with reason (with recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11 | — | RF-5 | Working |
| UXI-348 | Controls to change the active Owner's role, deactivate or anonymize it, or create or assign SUPER_ADMIN | Owner · CAP-27 | — | — | — | No | Not applicable | — / — | — | — | RF-5 | Not provided by rule: CAP-33 is operator-only (D-048); listed so that its absence is explicit |
| UXI-349 | Button "Kembali ke Pengguna", or "Kembali ke Riwayat aktivitas" when the page was opened from UXP-612 | Owner · CAP-27 | Always; the label names the destination (NC-01) | Navigate → UXP-612 when the page was opened from there (filters and page kept, NC-10), otherwise UXP-602 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-605 "Ubah pengguna"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-350 | Form: Nama tampilan, Email (a change revokes an active link), Peran (Kasir / Admin Operasional / both) | Owner · CAP-27 | Always; never SUPER_ADMIN; nobody changes their own role (BR-USR-04) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-351 | Button "Simpan perubahan" | Owner · CAP-27 | Enabled when: a field changed; otherwise disabled with the reason shown (UXS-13) | Command: User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) (TECHNICAL_RULES section 7.2) | Primary | No (recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-13, UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-17; UXS-04 → UXP-604 | — | RF-5 | Working |
| UXI-352 | Button "Batal" | Owner · CAP-27 | Always | Navigate → UXP-604 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-604 with the unsaved-changes guard | RF-5 | Working |
| UXI-353 | Link "Kembali ke Detail pengguna" (contextual back) | Owner · CAP-27 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-604 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-606 "Tautan kata sandi"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-354 | Link shown once as a scannable code and text, with its purpose, the staff member's name and its expiry (60 minutes) | Owner · CAP-25, CAP-26 | Only right after issuing; held in page memory, never in props, history, storage or the URL (SEC-ACC-05) | Read: the issuing response, once | Secondary | No | Not applicable | — / — | UXS-49 after a reload | — | RF-5 | Working |
| UXI-355 | Button "Salin tautan" | Owner · CAP-25, CAP-26 | While the link is shown | Local only: changes the page or draft; sends no request; copies to the clipboard | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-356 | Handover guidance (tell the staff member to report a password they did not set, THR-21) | Owner · CAP-25, CAP-26 | Always | Read: guidance text | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-357 | Button "Selesai" | Owner · CAP-25, CAP-26 | Always | Navigate → UXP-604 | Primary | No | Not applicable | — / — | — | Back → UXP-604; the link is discarded from memory | RF-5 | Working |

#### UXP-607 "Profil usaha"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-358 | Button "Ubah profil usaha" | Owner · CAP-30 | Always | Navigate → UXP-608 | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-359 | Version list row (time, actor), expandable | Owner · CAP-30 | Each earlier version; versions are never deleted (BR-CFG-02) | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-360 | Button "Kembali ke Pengaturan" | Owner · CAP-30 | Always | Navigate → UXP-601 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-608 "Ubah profil usaha"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-361 | Form: Nama toko, Alamat, Kontak, Email (opsional), Teks atas dokumen, Teks bawah dokumen, Label dokumen | Owner · CAP-30 | Always (lengths TECHNICAL_RULES section 11.3) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-362 | Field "Logo": "Pilih logo" / "Ganti logo" | Owner · CAP-30 | Always; PNG, JPEG or WebP up to 5 MiB and 16 megapixels (SECURITY_MODEL section 13) | Local only: changes the page or draft; sends no request; the file is sent with the save | Secondary | No | Not applicable | — / — | UXS-05 | — | RF-5 | Working |
| UXI-363 | Field "Warna aksen" | Owner · CAP-30 | Always; one of the five accents of DESIGN_SYSTEM section 3.3, checked on the server (RC-32; TECHNICAL_RULES section 11.3; SCHEMA `accent_token`; D-086, D-087) | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-364 | Button "Simpan sebagai versi baru" | Owner · CAP-30 | Enabled when: a field changed; otherwise disabled with the reason shown (UXS-13) | Command: BusinessProfile change (TECHNICAL_RULES section 7.2) | Primary | No (recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-13, UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-17, UXS-12; UXS-04 → UXP-607 | — | RF-5 | Working |
| UXI-365 | Button "Batal" | Owner · CAP-30 | Always | Navigate → UXP-607 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-607 with the unsaved-changes guard | RF-5 | Working |
| UXI-366 | Link "Kembali ke Profil usaha" (contextual back) | Owner · CAP-30 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-607 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-609 "Laci kas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-367 | Button "Tambah laci kas" | Owner · CAP-31 | Always | Navigate → UXP-610 (new) | Primary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-368 | Row "Ubah laci kas" | Owner · CAP-31 | Disabled while a session is OPEN on the register, with the reason (UXS-13, BR-CASH-02) | Navigate → UXP-610 (edit) | Secondary | No | Not applicable | — / — | UXS-01, UXS-13 | — | RF-5 | Working |
| UXI-369 | Button "Kembali ke Pengaturan" | Owner · CAP-31 | Always | Navigate → UXP-601 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-610 "Tambah laci kas" or "Ubah laci kas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-370 | Form: Kode, Label, Aktif | Owner · CAP-31 | Always | Local only: changes the page or draft; sends no request | Field | No | Safe: Enter in these fields submits nothing (UXA-10) | — / — | UXS-05 | — | RF-5 | Working |
| UXI-371 | Button "Simpan" | Owner · CAP-31 | Enabled when: valid form; otherwise disabled with the reason shown (UXS-13) | Command: Cash register configuration (TECHNICAL_RULES section 7.2) | Primary | No (recent authentication) | Safe: Enter never activates it (UXA-10) | — / — | UXS-13, UXS-06, UXS-04, UXS-05, UXS-14, UXS-15, UXS-16, UXS-11, UXS-17; UXS-04 → UXP-609 | — | RF-5 | Working |
| UXI-372 | Button "Batal" | Owner · CAP-31 | Always | Navigate → UXP-609 | Secondary | No | Not applicable | — / — | UXS-45 | Cancel → UXP-609 with the unsaved-changes guard | RF-5 | Working |
| UXI-373 | Link "Kembali ke Laci kas" (contextual back) | Owner · CAP-31 | Always, at the top of the page (NC-01). A kept duplicate of "Batal" (UXA-04): the top link is the page's standard way back (AICWDF section 18.7), "Batal" ends the form; both lead to the same place with the same guard | Navigate → UXP-609 | Navigation | No | Not applicable | — / — | UXS-45 | Back; with the unsaved-changes guard (UXS-45) | RF-5 | Working |

#### UXP-611 "Status sistem"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-374 | Button "Muat ulang" | Owner · CAP-32 | Always | Read: system status | Secondary | No | Not applicable | — / — | UXS-01, UXS-22, UXS-43 | — | RF-5 | Working |
| UXI-375 | Link "Insiden rekonsiliasi" | Owner · CAP-18 | When an incident is open | Navigate → UXP-414 | Secondary | No | Not applicable | — / — | UXS-01 | — | RF-5 | Working |
| UXI-376 | Button "Kembali ke Pengaturan" | Owner · CAP-32 | Always | Navigate → UXP-601 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-612 "Riwayat aktivitas"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-377 | Filters "Tanggal", "Pengguna", "Jenis", "Produk", "Sesi kas", "Referensi" | Owner · CAP-32 | Always (REQ-AUD-02) | Read: audit events | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-378 | Row (time, user, action, reference), expandable | Owner · CAP-32 | Each event | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-379 | Row reference link | Owner · CAP-32, CAP-22 | When the event names a Sale, a cash session, a product, a stock movement, a receiving document, a manual issue or a user; the reference of an event about any other object is shown as text | Navigate → the object's page: UXP-202, UXP-206, UXP-302, UXP-403, UXP-406, UXP-409 or UXP-604; its back action returns here with the filters and page kept (NC-10) | Secondary | No | Not applicable | — / — | UXS-01, UXS-08 | — | RF-5 | Working |
| UXI-380 | Paging "Berikutnya" / "Sebelumnya" | Owner · CAP-32 | When more rows exist | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-381 | Button "Kembali ke Pengaturan" | Owner · CAP-32 | Always | Navigate → UXP-601 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

#### UXP-613 "Riwayat keamanan"

| ID | Control | Visible to | Condition | Action | Hierarchy | Review | Scanner Enter | PC keyboard / tablet touch | Result states | Back / cancel | Route | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UXI-382 | Filters "Tanggal", "Jenis", "Pengguna" | Owner · CAP-32 | Always | Read: security log entries | Filter | No | Not applicable | — / — | UXS-01, UXS-02, UXS-03, UXS-22 | — | RF-5 | Working |
| UXI-383 | Row, expandable | Owner · CAP-32 | Each entry; never a secret, token or password (SEC-LOG-03) | Local only: changes the page or draft; sends no request | Secondary | No | Not applicable | — / — | — | — | RF-5 | Working |
| UXI-384 | Paging "Berikutnya" / "Sebelumnya" | Owner · CAP-32 | When more rows exist | Read: the next or previous page | Secondary | No | Not applicable | — / — | UXS-01, UXS-22 | — | RF-5 | Working |
| UXI-385 | Button "Kembali ke Pengaturan" | Owner · CAP-32 | Always | Navigate → UXP-601 | Secondary | No | Not applicable | — / — | — | Back | RF-5 | Working |

## 3. Zero dead interactions (G1-09)

| Status | Count | Rows |
| --- | --- | --- |
| Working | 383 | Every row not listed as not provided |
| … of which disabled with a reason while a stated condition holds (UXS-13) | 49 | UXI-052, UXI-071, UXI-075, UXI-077, UXI-083, UXI-084, UXI-096, UXI-103, UXI-105, UXI-118, UXI-127, UXI-139, UXI-140, UXI-150, UXI-152, UXI-174, UXI-186, UXI-187, UXI-193, UXI-207, UXI-210, UXI-217, UXI-221, UXI-226, UXI-227, UXI-228, UXI-231, UXI-236, UXI-239, UXI-249, UXI-250, UXI-253, UXI-259, UXI-266, UXI-271, UXI-279, UXI-285, UXI-286, UXI-289, UXI-295, UXI-314, UXI-318, UXI-324, UXI-337, UXI-340, UXI-351, UXI-364, UXI-368, UXI-371 |
| Hidden by design | 0 | — (UXI-058, UXI-228, UXI-291 and UXI-363 are provided, INTERACTION_CONTRACTS section 10; D-086, D-087) |
| Not provided (explicit absence) | 2 | UXI-244 (receiving drafts stay in page memory for V1 (D-069, T-17 a); listed so that its absence is explicit); UXI-348 (CAP-33 is operator-only (D-048); listed so that its absence is explicit) |
| Total rows | 385 | — |

Re-derived from the rows of section 2 by TASK-002 (COPY_DECK finding F-02), never copied: a row counts as disabled with a reason when it is Working and its Condition states a disabled condition or UXS-13. UXI-076 is not counted: it is shown only while a checkout is unresolved and is never disabled; the disabled control its Condition names is "Bayar" (UXI-075).

- Every working row has an action and a result state; no row is visible, clickable and without behaviour (AICWDF section 14.3).
- **Nothing outside V1 appears** ([PRODUCT_BLUEPRINT](../01-product/PRODUCT_BLUEPRINT.md) section 8; REQ-SCOPE-02): no discount, price override, split or partial payment, payment gateway, order or reservation, shift, paid-in or paid-out, stock-taking, XLSX import, camera scan, language switch, registration, "forgot password", two-factor or Google sign-in.
- **No delete control** for a business record or master data (D-042, D-044, REQ-OPS-05). "Hapus baris" and "Hapus dari keranjang" remove a line from an unsaved draft; "Lepas foto" hides a photo whose file is kept (D-042).
- **NC-13 applies to every control that starts a new command.** Besides the conditions counted above, such a control is disabled with its reason (UXS-13) while the user holds an unresolved request of the same command family; the rows of the critical families' entries state it explicitly. Repeating the same request and resolving it stay available, print and export requests are exempt, and deactivating an account or revoking a link stays available unless the unresolved request is itself a deactivation or a revocation ([NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md) NC-13). A request refused as EPOCH_STALE after a restore keeps its status check, and its family stays blocked until the restore reconciliation has recorded its outcome (D-073). The block acts only in the browser that holds the request (NC-13 (c), D-074).

## 4. Access checks (G1-01, G1-02, G1-05)

| Check | Result |
| --- | --- |
| Rows on protected pages without a capability | 0 |
| Rows on pre-authentication pages | 6, all for the visitor, each tied to its page's security requirements |
| Rows where a listed role holds none of the listed capabilities | 0 (checked against SECURITY_MODEL section 6) |
| Rows visible to Combined that need only Owner-only capabilities | 0 |
| Capabilities without an interaction (CAP-33 excluded) | 0 |
| Rows that enable CAP-33 | 0 |

## 5. Decisions applied (G1-14)

| Decision | Rows |
| --- | --- |
| D-067 — one line per product | UXI-067, UXI-234 |
| D-068 — refund cash handover and review notes | UXI-101, UXI-133, UXI-143, UXI-149 |
| D-069 — T-17 a, no receiving draft | UXI-231, UXI-243, UXI-244 |
| D-069 — T-17 d, deactivation with an OPEN session | UXI-340, UXI-345 |
| D-069 — T-17 f, append-only notes | UXI-144, UXI-145 |
| D-069 — T-17 h, category names in CSV | UXI-208, UXI-209, UXI-210 |
| D-071 — at most one onboarding period | UXI-285, UXI-289 |
| D-072 — onboarding after a restore of an older backup; the hold for reconciliation (UXS-50) | UXI-285, UXI-286, UXI-289 |
| D-073 — the status check stays available for EPOCH_STALE | UXI-037, UXI-055, UXI-086 and the "Cek status" of every uncertain panel |
| D-075 — accepted G1 proposals | UXI-078 ("Tunai" chosen when the payment screen opens); the provisional shortcuts (ADAPTIVE_AND_ACCESSIBILITY UXA-09) |
| D-087 — T-17 b, corrections of onboarding entries before release (INTERACTION_CONTRACTS section 10) | UXI-227, UXI-228, UXI-291, UXI-292 |
| D-087 — N-07, unresolved requests known from the server's receipts; BR-CASH-16 before a close (INTERACTION_CONTRACTS sections 1.4, 2.2 and 2.5) | UXI-025, UXI-029, UXI-055, UXI-058, UXI-103, UXI-149, UXI-150 |
| D-084 — a status check without an answer leaves the request in its state (COPY_DECK finding F-05) | UXI-055, UXI-058, UXI-086; NAVIGATION_CONTRACTS UXP-006 |
| D-086, D-087 — the accent set | UXI-363 |

## 6. Commands and the scanner Enter (G1-08)

Every row whose action is a command, with its protection. A command is never sent by Enter (UXA-10).

| ID | Page | Control | Scanner Enter |
| --- | --- | --- | --- |
| UXI-036 | UXP-000 | Shared uncertain panel: "Kirim ulang" | Safe: Enter never activates it (UXA-10) |
| UXI-052 | UXP-005 | Button "Simpan perubahan" | Safe: Enter never activates it (UXA-10) |
| UXI-083 | UXP-102 | Final button "Selesaikan" (Tunai) / "Dana sudah diterima — Selesaikan" (Transfer bank) | Safe: Enter never activates it; not the initial focus (BR-SALE-13, UXA-10) |
| UXI-085 | UXP-102 | Uncertain panel: "Kirim ulang" | Safe: Enter never activates it (UXA-10) |
| UXI-090 | UXP-103 | Button "Cetak struk" | Safe: Enter never prints (UXA-10) |
| UXI-097 | UXP-104 | Review dialog: "Buka sesi" | Safe: Enter never activates it (UXA-10) |
| UXI-099 | UXP-104 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-107 | UXP-105 | Review dialog: "Tutup sesi" (shows physical cash, expected cash and the variance) | Safe: Enter never closes a session (BR-CAT-32, UXA-10) |
| UXI-109 | UXP-105 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-117 | UXP-202 | Button "Cetak ulang struk" with the size choice | Safe: Enter never prints (UXA-10) |
| UXI-128 | UXP-203 | Review dialog: "Konfirmasi pengembalian" (shows lines, total, method, source, goods condition, reason) | Safe: Enter never activates it (UXA-10) |
| UXI-130 | UXP-203 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-141 | UXP-206 | Button "Cetak laporan" | Safe: Enter never prints (UXA-10) |
| UXI-142 | UXP-206 | Button "Unduh CSV" | Safe: Enter never activates it (UXA-10) |
| UXI-146 | UXP-206 | Inline form: field "Catatan" and button "Simpan catatan" | Safe: Enter never activates it (UXA-10) |
| UXI-154 | UXP-207 | Review dialog: "Tutup sesi" (states that the responsible user does not change) | Safe: Enter never closes a session (BR-CAT-32, UXA-10) |
| UXI-156 | UXP-207 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-166 | UXP-302 | Overflow "Nonaktifkan" / "Aktifkan kembali", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-169 | UXP-302 | Photo: "Unggah foto" / "Ganti foto" | Safe: Enter never activates it (UXA-10) |
| UXI-170 | UXP-302 | Photo: "Lepas foto", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-171 | UXP-302 | Barcodes: "Tambah barcode pabrik" (inline form: token field, "Simpan barcode", "Batal") | The token field accepts a scan; Enter ends the code and saves nothing (UXA-10) |
| UXI-172 | UXP-302 | Barcodes: "Buat barcode internal" | Safe: Enter never activates it (UXA-10) |
| UXI-173 | UXP-302 | Barcode row: "Pensiunkan barcode", with a dialog that records the reason class (Salah pasang / Alasan lain) | Safe: Enter never activates it (UXA-10) |
| UXI-183 | UXP-303 | Button "Simpan produk" | Safe: Enter never activates it (UXA-10) |
| UXI-187 | UXP-304 | Button "Simpan perubahan" | Safe: Enter never activates it (UXA-10) |
| UXI-194 | UXP-305 | Review dialog: "Konfirmasi koreksi" | Safe: Enter never activates it (UXA-10) |
| UXI-196 | UXP-305 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-200 | UXP-306 | Button "Cetak" | Safe: Enter never prints (UXA-10) |
| UXI-203 | UXP-307 | Button "Tambah kategori" (inline form: "Nama kategori", "Simpan", "Batal") | Safe: Enter never activates it (UXA-10) |
| UXI-204 | UXP-307 | Row "Ubah nama" (inline form: "Simpan", "Batal") | Safe: Enter never activates it (UXA-10) |
| UXI-205 | UXP-307 | Row "Nonaktifkan" / "Aktifkan kembali", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-207 | UXP-308 | Button "Pilih berkas CSV" | Not applicable |
| UXI-210 | UXP-308 | Button "Impor [jumlah] produk" | Safe: Enter never activates it (UXA-10) |
| UXI-211 | UXP-308 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-212 | UXP-308 | Button "Batalkan impor" | Safe: Enter never activates it (UXA-10) |
| UXI-240 | UXP-405 | Review dialog: "Konfirmasi barang masuk" (lines, quantities, costs, lines with unknown cost highlighted) | Safe: Enter never activates it (UXA-10) |
| UXI-242 | UXP-405 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-260 | UXP-408 | Review dialog: "Konfirmasi barang keluar" (stock before, change and after per line) | Safe: Enter never activates it (UXA-10) |
| UXI-262 | UXP-408 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-272 | UXP-410 | Review dialog: "Konfirmasi penyesuaian" (stock before, change and after per line) | Safe: Enter never activates it (UXA-10) |
| UXI-274 | UXP-410 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-280 | UXP-411 | Review dialog: "Konfirmasi pembatalan" | Safe: Enter never activates it (UXA-10) |
| UXI-282 | UXP-411 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-285 | UXP-412 | Button "Mulai masa input stok awal", with a confirmation dialog stating that Sales and ordinary postings stop until the release | Safe: Enter never activates it (UXA-10) |
| UXI-289 | UXP-412 | Button "Akhiri masa input stok awal", with a review dialog of the checks of BR-INV-28 (counts, barcodes, minimum stock, costs still unknown) that states the period cannot be started again, also not after a restore of an older backup (D-071, D-072) | Safe: Enter never activates it (UXA-10) |
| UXI-291 | UXP-412 | Correction of an onboarding entry before release: "Tarik hitungan nol" with its review dialog; "Catat ulang stok awal" | Safe: Enter never activates it (UXA-10) |
| UXI-290 | UXP-412 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-296 | UXP-413 | Review dialog: "Konfirmasi stok awal" | Safe: Enter never activates it (UXA-10) |
| UXI-298 | UXP-413 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-304 | UXP-415 | Button "Buka kembali mutasi", with a confirmation dialog stating that reopening follows the reconciliation of the facts (BR-INV-46) | Safe: Enter never activates it (UXA-10) |
| UXI-319 | UXP-504 | Review dialog: "Konfirmasi koreksi" | Safe: Enter never activates it (UXA-10) |
| UXI-321 | UXP-504 | Uncertain panel: "Kirim ulang" and "Cek status" | Safe: Enter never activates it (UXA-10) |
| UXI-337 | UXP-603 | Button "Simpan dan buat tautan" | Safe: Enter never activates it (UXA-10) |
| UXI-342 | UXP-604 | Button "Buat tautan atur ulang kata sandi", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-343 | UXP-604 | Button "Buat ulang tautan kata sandi pertama", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-344 | UXP-604 | Button "Cabut tautan", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-345 | UXP-604 | Button "Nonaktifkan", with a confirmation dialog that names any OPEN cash session and says it stays open until the Owner closes it (D-069) | Safe: Enter never activates it (UXA-10) |
| UXI-346 | UXP-604 | Button "Aktifkan kembali", with a confirmation dialog | Safe: Enter never activates it (UXA-10) |
| UXI-347 | UXP-604 | Button "Anonimkan akun", with a dialog that asks for a reason and states that it cannot be undone | Safe: Enter never activates it (UXA-10) |
| UXI-351 | UXP-605 | Button "Simpan perubahan" | Safe: Enter never activates it (UXA-10) |
| UXI-364 | UXP-608 | Button "Simpan sebagai versi baru" | Safe: Enter never activates it (UXA-10) |
| UXI-371 | UXP-610 | Button "Simpan" | Safe: Enter never activates it (UXA-10) |
