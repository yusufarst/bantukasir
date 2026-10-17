# Device verification

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta) |
| Phase | P8 — the protocol of gate DEVICES: the shop's real PC, tablet, scanners, printers and browsers |
| Replaces | The device clauses of suite DEVICE01 and of the method section of `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-039, D-040, D-075, D-086, D-087, D-088; FD-05, FD-07 |
| Related | [ACCEPTANCE_SUITES](ACCEPTANCE_SUITES.md) DEVICE01 and UX02, [ADAPTIVE_AND_ACCESSIBILITY](../07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md), [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md), [KEY_SCREENS](../07-ux-design/KEY_SCREENS.md), [DOCUMENT_CONTRACTS](../03-workflows/DOCUMENT_CONTRACTS.md), [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md) section 1.7 |

What must be proven on the devices the shop will actually use, and how it is recorded. Checks are numbered `DVT-nn`; identifiers are permanent. The first store's cashiers use a PC and a tablet (OF-02); the devices themselves are not yet known or verified (gate DEVICES).

## 1. Rules

| ID | Rule | Source |
| --- | --- | --- |
| DVT-R1 | **Physical proof only.** A check passes only on the real device, with the real scanner, printer, paper or label media and browser. An emulator, a browser's device mode, a print preview or a print dialog proves nothing about hardware or ink. | REQ-NFR-03, D-039 |
| DVT-R2 | **On staging, with synthetic data.** Device checks run against staging; every Sale, receipt and label they need is synthetic and nothing is created in production for a check (TEST_STRATEGY TS-06). | PDS §4 |
| DVT-R3 | **Recorded.** Each check records the device model, operating system and its version, browser and its version, scanner model and its terminator setting, printer model, driver, paper or media and width, the application revision, the date, the result and who ran it, with a photo or scan of every printout that the check names. The record names no person in the public repository (TEST_STRATEGY TS-31). | L-08 §Metode |
| DVT-R4 | **The provisional values are decided here.** The shortcuts of UXA-09 and the breakpoints of 600 and 1024 CSS px (UXA-02), accepted as provisional (D-075), are confirmed or changed on the real devices; a change goes through change control of the approved P7 artifacts. | D-075, UXA-02, UXA-09 |
| DVT-R5 | **Again after a change.** A new device, operating system, browser, scanner setting, printer, driver, paper width or template version repeats the checks it touches. | TEST_STRATEGY TS-32 |

## 2. What to record first

| Item | Record |
| --- | --- |
| PC kasir | Model; operating system; browser; screen resolution and scaling; keyboard layout |
| Tablet kasir | Model; operating system; browser; screen size; orientation used at the counter |
| USB scanner | Model; interface mode (keyboard wedge); terminator (CR, LF or CR LF); prefix and suffix settings (none) |
| Bluetooth scanner | Model; pairing mode (HID); terminator; sleep and wake behaviour |
| Receipt printer | Model; connection; driver; paper width (58 or 80 mm) |
| A4 printer | Model; driver |
| Label printer | Model; driver; label media and size |
| Phone (Owner, Admin Operasional) | Model; operating system; browser |

## 3. Checks

### PC kasir with a USB scanner

| ID | Check | Passes when | Source |
| --- | --- | --- | --- |
| DVT-01 | Scan an EAN-13 with a leading zero, a UPC-A, an internal `BK-P-…` Code 128 label and a manufacturer label | Each adds one unit of the right product; the token keeps every character | REQ-SCAN-01, REQ-CAT-04 |
| DVT-02 | Scan the same code five times quickly, then three different codes quickly | Five units, then the three in scan order | REQ-SCAN-02 |
| DVT-03 | Scan a code that no product has | Nothing is added; the code stays in the field for search | REQ-SCAN-02 |
| DVT-04 | Scan while the payment screen, a review dialog or the cash count is open | No payment, posting or close is confirmed; capture is paused while the dialog or count is open | REQ-SCAN-03, UXA-10, UXA-11 |
| DVT-05 | Unplug the scanner halfway through a scan; plug it back | No wrong or partial product is added | INTERACTION_CONTRACTS section 1.7 |
| DVT-06 | The keyboard path of the till with the provisional shortcuts (F2, F8, Alt+1, Alt+2, Alt+U, Ctrl+Enter) in the shop's browser | Each works and none collides with the browser or the operating system; or a replacement is recorded for change control (DVT-R4) | UXA-09, D-075 |
| DVT-07 | The till and the back office at the PC's real window sizes | The layouts follow the breakpoints of UXA-02; or a change is recorded (DVT-R4) | UXA-02, D-075 |

### Tablet kasir with a Bluetooth scanner

| ID | Check | Passes when | Source |
| --- | --- | --- | --- |
| DVT-10 | DVT-01 to DVT-05 with the Bluetooth scanner | As for the PC | REQ-SCAN-01, REQ-NFR-02 |
| DVT-11 | Enter a quantity and the tendered amount while the scanner is connected | The on-screen keyboard can still be used where the till needs it, or the shop's way of entering numbers is recorded | UXA-14 |
| DVT-12 | Run 20 synthetic Sales by touch | No mis-tap reaches "Selesaikan"; every target used is at least 44 px and the final action sits away from the cart controls | UXA-07, REQ-SALE-04 |
| DVT-13 | Turn the tablet; use the orientation of the counter | The layout follows UXA-02 and UXA-03 in both orientations | UXA-02 |
| DVT-14 | Let the scanner sleep, then scan | The first scan after waking is read whole or not at all | INTERACTION_CONTRACTS section 1.7 |

### Printers and documents

| ID | Check | Passes when | Source |
| --- | --- | --- | --- |
| DVT-20 | Print a receipt with long product names on the 58 mm printer, then print it again | The layout of DOCUMENT_CONTRACTS DC-01 and DESIGN_SYSTEM section 12; the reprint carries "Salinan" first at the top; no cost or profit appears | REQ-DOC-02, REQ-DOC-03 |
| DVT-21 | The same on 80 mm, if the shop has it | As DVT-20 | REQ-DOC-02 |
| DVT-22 | Print the A4 "Nota Penjualan" | The same content as the receipt, with no tax-invoice wording | REQ-DOC-02, REQ-NFR-10 |
| DVT-23 | Print a session report and open its CSV in the shop's spreadsheet program | The report of DC-02; the CSV of DC-03 opens with its columns, rupiah and negative variance intact | REQ-CASH-12 |
| DVT-24 | Print 1 and 30 labels on the label printer and scan each with both scanners at the counter | Every label scans to its product; the label shows code, name and SKU and never cost | REQ-CAT-11 |
| DVT-25 | Run out of paper, or switch the printer off, during a receipt | The Sale stays saved; the reprint afterwards works; nothing is recorded twice | REQ-SALE-11, REQ-DOC-03 |
| DVT-26 | Record the print-dialog settings (scale, margins, paper) that give the right result for each template in each browser | The settings are recorded in the shop's guidance | UXA-23 |

### Browsers and access

| ID | Check | Passes when | Source |
| --- | --- | --- | --- |
| DVT-30 | Open the ten key screens in the browser of each device | They render as KEY_SCREENS shows; the console reports no content-security-policy violation | REQ-UX-01, SEC-HDR-03 |
| DVT-31 | Sign out on the shared device, let another user sign in, press Back and Forward | Nothing of the first user appears | THR-18, REQ-ACC-04 |
| DVT-32 | A screen-reader pass of the till, the payment screen, the close and one report with the screen reader of the device's platform | Names, roles, headings and announcements follow UXA-13 and UXA-17 | REQ-UX-03 |
| DVT-40 | Owner and Admin Operasional pages on a phone | They follow the compact layout; the till is not an accepted task on the phone | UXA-25, FD-05 |

## 4. Outcome

DEVICE01 reaches `FINAL_PASS` when every check that applies to the shop's devices passes on the release candidate; a check that does not apply (for example DVT-21 without an 80 mm printer) is recorded as such with its reason. The Owner then decides gate DEVICES (TEST_STRATEGY section 9). The confirmed shortcuts and breakpoints, and any change to them, are recorded for change control of the approved P7 artifacts (DVT-R4).

## 5. Open items

| Item | Subject | Step |
| --- | --- | --- |
| Gate DEVICES | The shop's devices, printers and media; the results of section 3 | Before the pilot |
| G3 | Whether one device is shared by several staff (DVT-31 assumes it may be) | Before the pilot |
