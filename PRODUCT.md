# BantuKasir — product context for design

Planning context for Impeccable and every UI executor. This file summarizes [01 PRD](docs/01-PRD.md); it does not override scope.

A configurable GOODS-only POS and stock-control platform for one business. Three users: Pemilik needs truthful sales/HPP/gross profit and exceptions; Admin Operasional needs search-first receiving/stock/restock; Kasir needs scan → cart → pay → receipt plus accountable cash sessions.

Desktop prioritizes HID/keyboard speed and readable operational tables; mobile prioritizes touch, search and one primary action. Bahasa Indonesia labels. Premium, elegant, modern, restrained and professional; avoid generic AI dashboards, decorative gradients/charts and duplicate KPI cards. Proposed brand direction is dark navy/charcoal-blue with teal/aqua and quiet pastel neutrals; semantic colors remain independent. The exact BantuKasir logo asset is not yet in this repository and must not be fabricated.

Business identity is runtime BusinessProfile. A normal Sale requires no customer, is fully paid by cash or recorded bank transfer, and issues goods atomically. Unknown result must lead to original-result recovery. Missing cost is explicit, never optimistic profit. Staff never sees private costs.

Cash control is intentionally narrow: one logical drawer has one active session and one responsible cashier. A cashier closes before handing over; returning later means a new session. Opening cash, cash sales, physical count and variance are recorded; transfers appear in activity but never physical expected cash. This is not an HR/attendance shift engine, and simultaneous cashiers sharing one drawer are outside V1.

No services, booking/DP, reservations, warehouse/complex shift engine or public site in V1. [DESIGN](DESIGN.md) owns all tokens/patterns. Four compact UI bundles require complete owner Gate A before production frontend. Product context was authored during planning; no Impeccable run or owner visual approval is claimed.
