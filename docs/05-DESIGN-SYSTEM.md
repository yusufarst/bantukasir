# 05 — Design workflow and visual gates

[DESIGN.md](../DESIGN.md) is the sole visual token/component/pattern authority. [PRODUCT.md](../PRODUCT.md) supplies product context; [01](01-PRD.md) controls scope. No design approval or tool execution is claimed by this plan.

## Four compact prototype bundles

| Bundle / task | Representative coverage |
| --- | --- |
| UI01 / BK01 | Foundation/type/brand and semantic tokens; real-logo status, login, shell/sidebar, all three workspaces, profile settings, active-session identity/entry and desktop/mobile conventions |
| UI02 / BK02 | Shared search, product list/detail/form/duplicate warning, receiving/opening, manual issue, stock/history/LOW/OUT, labels and correction pattern |
| UI03 / BK03 | Open session/opening cash, responsible cashier/drawer visibility, search/scan/cart/cash/transfer, handover via close/new session, success/error/unknown result, thermal/A4, reprint and minimal owner refund/return |
| UI04 / BK04 | Session physical count/version race/expected/variance/report/PDF/CSV/history, owner daily aggregation across sessions, periods/HPP/incomplete profit, audit and system/backup states |

Use minimal deterministic prototypes and representative states, not every screen or a disconnected production frontend. UI01 defines common components; later bundles extend only necessary patterns. [Prototype registry](../prototypes/README.md) holds artifacts/review references.

## Required tool workflow for every meaningful frontend/design task

1. Verify actual installed **21st.dev MCP/catalog** and **Impeccable skill/CLI/integration**, available commands, access and free usage limits in the executor environment. Read actual integration instructions. Do not treat Impeccable as MCP when it is a skill/CLI.
2. Search the available free 21st catalog for relevant reusable patterns; inspect candidates and existing project components before inventing new ones. Record queried catalog, choices/rejections, source/license and actual usage. Do not buy/use paid AI credits/templates/component access or any paid UI service without explicit owner approval.
3. Use actual Impeccable integration to establish/review PRODUCT and DESIGN context, critique/audit/polish meaningful screens and record resulting changes. Do not invent command names or claim a run from prose alone.
4. Prefer existing project code, shadcn/ui and open-source ingredients. Normalize imports to canonical tokens/components; reuse before variants. Record dependencies/licenses.
5. Verify keyboard/focus/touch/responsive/contrast/states and run consistency/anti-generic-UI review with real evidence.

If either required integration is unavailable or only paid access is possible without approval, **STOP that UI task and report missing setup**. This does not block documentation-only planning. No callable matching tools were exposed in the planning session; actual executor installation is unverified. No claim of 21st/Impeccable use or setup completion. If the real BantuKasir logo is still absent, UI01 may document a neutral text placeholder but cannot claim asset review, sample colors from it or freeze logo-specific brand approval; resolve the asset or obtain explicit owner approval of a provisional identity before Gate A completes.

## Gates

**Gate A / BK05:** owner approves complete UI01–UI04 visual language and representative workflows at an exact revision. Record evidence and freeze DESIGN baseline. Individual feedback is welcome earlier; all four are required before any production frontend. Tests/screenshots cannot self-approve. Do not start production UI with a partial gate.

After Gate A, vertical slices integrate real backend/data/RBAC/audit/tests/UI. **Gate B:** owner reviews real role workspaces (BK10), product/stock workflow (BK20), POS/documents (BK25), reports/audit (BK31), then final pilot. Technical and owner evidence are separate; waiting review earns no completion credit. New patterns or deviations require an approved design-system revision.

Navigation uses Bahasa Indonesia: Dasbor; Kasir; Sesi Kas Saya; Produk; Stok/Barang Masuk/Barang Keluar/Restock/Riwayat; Laporan; Log/Riwayat; Pengguna/Profil Bisnis/Sistem as role permits. No unimplemented or Later menus. Cashier shell always shows the active responsible session or a clear **Buka Sesi Kas** state; closing/handover is secondary to checkout but never hidden.
