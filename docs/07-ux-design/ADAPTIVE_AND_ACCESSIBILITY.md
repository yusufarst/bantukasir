# Adaptive behaviour and accessibility

| | |
| --- | --- |
| Status | CURRENT — written in RF-4A under D-070 and, after the stop on finding F-03, D-071; corrected after the independent final review under D-072 to D-075; 11 October 2026 (Asia/Jakarta) |
| Phase | P7 — responsive and adaptive rules and the exact accessibility targets of REQ-UX-03 |
| Gate | P7-G1, navigation and page structure (D-025): **artifacts ready for the Owner's review — NOT APPROVED**. Approval is a separate Owner decision that names the RF-4A checkpoint commit and the hash of each artifact (D-070) |
| Decisions | D-017, D-018, D-024, D-025, D-032, D-033, D-036, D-039, D-040, D-047, D-049, D-067 to D-075 |
| Related | [USER_TASKS](USER_TASKS.md), [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](INTERACTION_INVENTORY.md), [UI_STATES](UI_STATES.md) |

How BantuKasir adapts to the PC kasir, the tablet kasir and the phone, and the exact accessibility targets that [REQUIREMENTS](../01-product/REQUIREMENTS.md) REQ-UX-03 leaves to RF-4. Rules are numbered `UXA-nn`; identifiers are permanent. A rule marked as a target is verified on the approved design (RF-4C) and on the build; a device behaviour that depends on real hardware is confirmed at gate DEVICES. This document adds no business rule.

Indonesian labels in quotation marks are **working labels**, taken from the [GLOSSARY](../02-domain/GLOSSARY.md) where it has a term; they stay provisional until gate P7-G2 (terminology and copy, RF-4B). Nothing here is visual design (RF-4C, gate P7-G3) or an HTTP route (RF-5).

## 1. Device classes at a glance

| Class | Input | Who | Pages |
| --- | --- | --- | --- |
| PC kasir | Physical keyboard, mouse, USB HID scanner | Cashier, Owner, Combined at the till; any role for back-office work | All; the till is designed for it |
| Tablet kasir | Touch, on-screen keyboard, Bluetooth HID scanner | Cashier, Owner, Combined at the till; any role for back-office work | All; the till is designed for it |
| Phone | Touch, on-screen keyboard | Owner and Ops (phone first, FD-05) | All except that the till is not an accepted phone task (D-039) |

## 2. Rules

### UXA-01 — Device classes

Three classes, decided by input, not by screen width alone. **PC kasir:** physical keyboard, mouse, USB HID scanner, receipt printer through the browser. **Tablet kasir:** touch, on-screen keyboard, Bluetooth HID scanner, receipt printer through the browser. **Phone:** touch and on-screen keyboard; for Owner and Ops work only. PC and tablet are the primary cashier devices with equivalent cashier capabilities; the phone is not a primary cashier device. Owner and Ops screens are designed phone first and adapt upward.

*Kind:* Target. *Source:* D-039, FD-05, REQ-NFR-02, OF-02.

### UXA-02 — Logical breakpoints

Three layout widths: **compact** below 600 CSS px, **medium** 600 to 1023 CSS px, **expanded** 1024 CSS px and above. The values are provisional: the Owner accepted them (D-075), and RF-4C and the real devices confirm them. Layout follows the width; input behaviour (hover, pointer precision) follows the pointer type, never the width alone.

*Kind:* Provisional, accepted by the Owner (D-075); confirmed in RF-4C. *Source:* AICWDF section 18 (mobile-first, responsive, adaptive).

### UXA-03 — Navigation by width

Compact: a top bar with the page title, a menu button that opens the navigation drawer, and the account menu; sub-navigation as a segmented control or a select under the title. Medium and expanded: persistent side navigation with labels; sub-navigation as tabs. On the till (UXP-101 to UXP-105) the side navigation collapses to icons with visible tooltips and accessible names, so that the search field, the cart and "Bayar" have the room. The same destination has the same label in every place (G1-01).

*Kind:* Target. *Source:* AICWDF sections 18.3 and 18.5; INFORMATION_ARCHITECTURE section 4.

### UXA-04 — Page patterns and action hierarchy

List, detail and create or edit pages follow AICWDF section 18.7. Each context has at most one primary action; destructive actions are visually and spatially separate and need a confirmation; lower-priority actions go into an overflow menu (section 18.8). No unnecessary duplicate actions; a duplicate that is kept is justified in INTERACTION_INVENTORY.

*Kind:* Target. *Source:* AICWDF sections 18.3, 18.7 and 18.8.

### UXA-05 — When a dialog is used

A dialog is used only for a review step, a confirmation, recent authentication, the unsaved-changes guard, the sign-out warning and the sign-in-ending warning. Detail, create and edit are pages. One exception: Tambah produk (UXP-303) opens as a full page **over** a working page (UXP-405, UXP-413) so that the working draft, which lives in page memory only, is kept; it has its own back and cancel and returns to the working page.

*Kind:* Target. *Source:* NAVIGATION_CONTRACTS section 2; BR-INV-17; D-069.

### UXA-06 — Minimum text size

Body text at least **14 CSS px** and secondary text at least **12 CSS px**; no text below 12 CSS px anywhere, including tables, badges and the receipt preview. The type scale itself is set in RF-4C. Origin: the audit of the former candidate found most visible text below 12 px.

*Kind:* Target for RF-4C. *Source:* Re-foundation analysis sections 20 and 21; REQ-UX-03.

### UXA-07 — Touch targets

Every interactive target is at least **44 × 44 CSS px**, with at least 8 CSS px between adjacent targets. On the tablet the final payment action is at least 56 CSS px high, spans the payment panel and is at least 24 CSS px away from every other control. The production adequacy of these sizes is confirmed on the real devices (gate DEVICES).

*Kind:* Target. *Source:* BR-SALE-14, REQ-UX-03; former candidate: 16 % of phone controls were under 44 px.

### UXA-08 — Keyboard and focus

Every control is reachable with Tab in visual order and operable from the keyboard. The focus indicator is always visible: at least 2 CSS px, with at least 3:1 contrast against its surroundings. Dialogs keep focus inside while open, close with Esc (the same as their cancel action) and return focus to the control that opened them. No other keyboard trap exists. A page's first focus is its main content or, on the till, the search field.

*Kind:* Target. *Source:* REQ-UX-03, AICWDF section 27.

### UXA-09 — Till focus model and keyboard path

The search field of UXP-101 has focus on arrival and gets it back after every cart change and after a quantity edit ends. In the result list the arrow keys move and Enter adds the focused product. Shortcuts, accepted by the Owner as provisional (D-075); their keyboard safety and their behaviour on the target browsers and devices are verified in RF-4C and at gate DEVICES: **F2** focus the search field; **F8** "Bayar"; **Alt+1**, **Alt+2**, **Alt+3** choose "Tunai", "Transfer bank" or "QRIS statis" on UXP-102 (the arrow keys also move inside the method group); **Alt+U** "Uang Pas"; **Ctrl+Enter** the final action on UXP-102, the button "Tinjau" or "Lanjut hitung" that opens a review step, and the confirm button of every review dialog; **Esc** back or cancel. A Ctrl+Enter that is still held, or repeats, from the step before is ignored, so one key press never opens a review and confirms it. Moving the focus is not counted as an action in the step budgets of USER_TASKS section 5. On UXP-103 a scan or typed search starts the next sale. A complete CASH, BANK_TRANSFER or STATIC_QRIS sale is possible on the PC with the keyboard and scanner only. Shortcuts are shown in tooltips and in accessible names.

*Kind:* Target; the shortcuts are provisional (D-075). *Source:* REQ-UX-03, BR-SALE-14, BR-CAT-30.

### UXA-10 — Enter and the scanner

Enter never activates a command button and never submits a form that sends a command. Enter submits only the authentication forms (sign-in, set password, change password, the recent-authentication confirmation) and search or filter fields, which only read. In a field that accepts a scan, Enter ends the code and runs the lookup. A final action is activated by pointer or touch, by Space while it has focus, or by Ctrl+Enter; it is never the initial focus. Print and export buttons follow the same rule. A scanner Enter therefore never confirms a payment, a posting or a session close.

*Kind:* Target. *Source:* BR-SALE-13, BR-CAT-32, TR-CMD-05, REQ-SCAN-03.

### UXA-11 — Scan capture

The search fields of UXP-101, UXP-103 (where a scan starts the next sale), UXP-405, UXP-408, UXP-410 and UXP-413 are the scan targets. A scan is ordinary keyboard input; no timing heuristic decides whether input is a barcode. A scan that lands in another field is ordinary typing there: Enter submits nothing (UXA-10), and on UXP-102 the change is shown live and large, so a mistaken scan into "Uang diterima" is visible before the final action. Capture pauses while a dialog, a review step or a cash count is open: keystrokes then go only to the focused field. Scans apply in the order they were made, and a late lookup never replaces a newer one. Feedback says what was added to the draft, never that stock was saved. Scanning at the till needs the user's own OPEN session; without a connection nothing is queued.

*Kind:* Target. *Source:* BR-CAT-28 to BR-CAT-34, REQ-SCAN-01 to REQ-SCAN-04.

### UXA-12 — Zoom to 200 %

With the browser zoomed to 200 % in a 1280 × 800 window (640 CSS px of width), every primary task — selling, paying, opening and closing a session, receiving, issuing, adjusting, refunding, correcting cost — completes without horizontal scrolling of the page. A wide data table that is marked as such in the page (stock history, audit log, security log, CSV preview, long documents) may scroll horizontally inside its own container, with its first column kept visible; nothing else may.

*Kind:* Target. *Source:* REQ-UX-03.

### UXA-13 — Screen reader structure

Landmarks for the banner, the navigation and the main content; on the till the cart is a labelled region. One level-1 heading per page, equal to the page label. A document title per page in the form "[page label] — [shop name from the BusinessProfile]" on protected pages; on the pre-authentication pages (UXP-001, UXP-002) the title is the page label alone, because they show no business data (INFORMATION_ARCHITECTURE section 5). "BantuKasir" is never shown as the shop's identity. Every control's accessible name contains its visible label, plus its shortcut where one exists. Field errors are tied to their fields and summarized at the top of the form, which receives focus. Tables have header cells. A control that is disabled exposes its reason (UXS-13).

*Kind:* Target. *Source:* REQ-UX-03, REQ-SCOPE-03, BR-DOM-11.

### UXA-14 — On-screen keyboard

Rupiah fields open the numeric keyboard; quantity fields open the decimal keyboard when the product allows decimals; email fields open the email keyboard. While the on-screen keyboard is open, the active field and the page's primary action stay visible above it; on the till the cart total and "Bayar" stay reachable. When a Bluetooth HID scanner is connected some tablets hide the on-screen keyboard; manual entry must stay possible, and the real behaviour is checked at gate DEVICES.

*Kind:* Target; device behaviour at gate DEVICES. *Source:* REQ-UX-03, D-039, REQ-NFR-03.

### UXA-15 — Long content

Product names up to 200 characters wrap; in lists at most two lines are shown, with the full name on the detail page and in the accessible name. SKUs (64) and barcode tokens (128) use a fixed-width style that may break anywhere. Notes up to 2,000 characters and reasons up to 1,000 wrap and keep their line breaks. Amounts are never truncated or abbreviated. A cart of up to 200 lines, a receiving document of up to 500 lines and an opening entry of up to 100 rows scroll inside their own area while the total and the primary action stay fixed. Display names up to 100 characters wrap in the account menu.

*Kind:* Target. *Source:* TECHNICAL_RULES sections 11.2 and 11.3, REQ-UX-03.

### UXA-16 — Numbers, money and dates

Rupiah in whole rupiah with dot thousand separators, for example Rp1.234.567. A negative variance carries a minus sign and words, not colour alone. Quantities show the product's precision with a decimal comma. Dates show the full Indonesian month name and a dotted time, for example `01 Oktober 2026, 09.15.00`. An UNKNOWN cost is never shown as zero or blank (UXS-24); a failed figure is never shown as zero (UXS-22).

*Kind:* Target. *Source:* D-018, BR-NUM-07, BR-DOM-08, BR-COST-03.

### UXA-17 — Announcements

Polite announcements: a scan result ("added" with the product and new quantity, or "unknown code"), loading done, a committed result. Assertive announcements: a refusal, an uncertain outcome and the sign-in-ending warning. The automatic stock refresh is not announced unless the user asked for it.

*Kind:* Target. *Source:* REQ-UX-03, BR-CAT-29.

### UXA-18 — Colour and contrast

No state is carried by colour alone: Habis, Menipis and Normal, variance signs, errors and the "Dikoreksi Pemilik" mark always have text and, where useful, an icon. Contrast targets: at least 4.5:1 for text, 3:1 for large text, controls and focus indicators. The full rendered contrast is measured in RF-4C on the real design.

*Kind:* Target; measured in RF-4C. *Source:* REQ-UX-03.

### UXA-19 — Trend chart

The trend chart of UXP-502 is reachable with Tab; the arrow keys move between points; each point shows and announces its date and value on pointer, focus or touch; "Tampilkan sebagai tabel" shows the same data as a table. The chart uses the same snapshot and completeness as the figures.

*Kind:* Target. *Source:* D-018, REQ-RPT-06.

### UXA-20 — Motion

No information is carried by animation alone. With the operating system's reduced-motion setting, transitions are removed.

*Kind:* Target. *Source:* AICWDF section 18.1.

### UXA-21 — Time limits

The sign-in-ending dialog (UXS-23) appears at least 60 seconds before the 30-minute idle limit, with "Tetap masuk". The 12-hour absolute limit cannot be extended; the user is told when it ends. Neither ever closes a cash session.

*Kind:* Target. *Source:* SEC-SESS-02, SEC-SESS-03, REQ-UX-03.

### UXA-22 — Language

Indonesian only in V1; the document language is Indonesian; every user-facing text comes from a translation key; there is no language switcher. The labels in these documents are working labels until gate P7-G2.

*Kind:* Target. *Source:* REQ-NFR-01, D-050, ADR-005, FD-02.

### UXA-23 — Printing

Receipts print through the browser in three templates from the same snapshot: thermal 58 mm, thermal 80 mm and A4 ("Nota Penjualan"). Barcode labels use the 50 × 30 mm candidate size. Print styles hide navigation and controls. Real paper widths and printers are confirmed at gate DEVICES; a print dialog is not proof of ink.

*Kind:* Target. *Source:* D-039, BR-DOC-02, BR-CAT-36, BR-DOC-04.

### UXA-24 — Hover and pointer

Nothing is available only on hover; every hover detail has a focus and a touch equivalent.

*Kind:* Target. *Source:* REQ-UX-03.

### UXA-25 — The till on a phone

On compact width the till renders in one column (search, results, cart, then "Bayar" fixed at the bottom). The phone is not an accepted cashier device; no phone-specific till design is required at P7-G3.

*Kind:* Target. *Source:* D-039.

### UXA-26 — Breathing room and density

Comfortable density by default, with clear separation between sections and consistent spacing; a denser presentation only for data tables (stock history, audit and security logs, CSV preview). The exact spacing tokens are set in RF-4C and checked with the gates of AICWDF section 18.2.

*Kind:* Target for RF-4C. *Source:* AICWDF section 18.2.

## 3. REQ-UX-03: exact targets

| What REQ-UX-03 names | Rules |
| --- | --- |
| Keyboard-only use | UXA-08, UXA-09, UXA-10 |
| Visible focus | UXA-08 |
| 200 % browser zoom | UXA-12 |
| Screen reader | UXA-13, UXA-17, UXA-19 |
| On-screen keyboard | UXA-14 |
| Long content | UXA-15 |
| Full rendered contrast | UXA-18 (measured in RF-4C) |
| Touch targets usable in production on the tablet | UXA-07 (confirmed at gate DEVICES) |

## 4. Rules per page type (G1-08)

Every page type of [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md) section 5 has zoom, screen-reader, on-screen-keyboard and long-content rules.

| Page type | Rules |
| --- | --- |
| Shell | UXA-03, UXA-08, UXA-12, UXA-13, UXA-14 (dialog fields), UXA-15 (display names), UXA-17, UXA-21, UXA-22 |
| Sign-in form; one-time link form; account | UXA-07, UXA-08, UXA-10 (Enter submits), UXA-12, UXA-13, UXA-14 (email and password), UXA-15, UXA-18 |
| Till workspace; payment; result | UXA-07, UXA-08, UXA-09, UXA-10, UXA-11, UXA-12, UXA-13, UXA-14, UXA-15, UXA-16, UXA-17, UXA-18, UXA-23, UXA-25 |
| Close flow; command page with review | UXA-05, UXA-07, UXA-08, UXA-10, UXA-11 (pause), UXA-12, UXA-13, UXA-14, UXA-15, UXA-16, UXA-17, UXA-18 |
| List; report; status | UXA-04, UXA-07, UXA-08, UXA-12 (wide tables), UXA-13, UXA-14 (search and filter fields), UXA-15, UXA-16, UXA-17, UXA-18, UXA-19 (chart), UXA-26 |
| Detail; hub; settings index; home | UXA-04, UXA-07, UXA-08, UXA-12, UXA-13, UXA-14 (inline fields), UXA-15, UXA-16, UXA-18 |
| Create; edit; handover | UXA-04, UXA-05, UXA-07, UXA-08, UXA-10, UXA-12, UXA-13, UXA-14, UXA-15, UXA-18 |
| Print | UXA-07, UXA-08, UXA-10, UXA-12, UXA-13, UXA-14 ("Jumlah label"), UXA-15 (names on receipts and labels), UXA-23 |

## 5. Verified later

- Full rendered contrast, the type scale, spacing tokens and the visual focus style: RF-4C (gate P7-G3).
- Keyboard shortcuts on the target browsers, scanner behaviour, the on-screen keyboard with a Bluetooth scanner, touch-target adequacy and print widths: gate DEVICES on the real PC, tablet, scanners and printers (REQ-NFR-03).
- Every rule on the build: the tests of RF-6 and the Task acceptance of RF-7.
