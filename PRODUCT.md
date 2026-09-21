# BantuKasir — product context for design

Planning context for Impeccable and every UI executor. This file summarizes [01 PRD](docs/01-PRD.md); it does not override scope.

A configurable GOODS-only POS and stock-control platform for one business. Three users: Pemilik needs truthful sales/HPP/gross profit and exceptions; Admin Operasional needs search-first receiving/stock/restock; Kasir needs scan → cart → pay → receipt plus own daily reconciliation.

Desktop prioritizes HID/keyboard speed and readable operational tables; mobile prioritizes touch, search and one primary action. Bahasa Indonesia labels. Premium, elegant, modern, restrained and professional; avoid generic AI dashboards, decorative gradients/charts and duplicate KPI cards.

Business identity is runtime BusinessProfile. A normal Sale requires no customer, is fully paid by cash or recorded bank transfer, and issues goods atomically. Unknown result must lead to original-result recovery. Missing cost is explicit, never optimistic profit. Staff never sees private costs.

No services, booking/DP, reservations, warehouse/shift engine or public site in V1. [DESIGN](DESIGN.md) owns all tokens/patterns. Four compact UI bundles require complete owner Gate A before production frontend. Product context was authored during planning; no Impeccable run or owner visual approval is claimed.
