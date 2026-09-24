# R6 tool evidence — 24 September 2026

Impeccable skill 4.3.1 and bundled CLI **4.0.0** are distinct versions. Actual commands: `--help`, `--version`, `context --target prototypes/ui01/index.html` (once), `detect --help`, `detect --json prototypes/ui01/index.html` (once). Context loaded PRODUCT/DESIGN/surface. Audit, polish and craft-floor playbooks informed the source/browser pass. No invented `audit` or `polish` CLI invocation; those names are skill workflows. No independent R6 sub-agent approval is claimed.

The detector produced **0 primary findings, 4 advisories**: three 1px-border/32px-overlay-shadow notices and one new divider-color notice. Keep intentional overlay elevation; ordinary panels retain the smaller shadow. DESIGN now includes line #cbd6dc and divider #dce4e8. No detector rerun or blanket accessibility claim. [Raw result](detector.json).

## 21st.dev catalog

No callable 21st MCP was exposed. Public catalog/search/preview/code was inspected without registry installation, paid assets or credits. Patterns were adapted into existing vanilla UI01 code; no external component dependency or copied third-party code was introduced.

| Query | Candidate/source | Decision and actual usage | License |
| --- | --- | --- | --- |
| site:21st.dev date range picker calendar | [Shadcn calendar range](https://21st.dev/community/components/shadcn/calendar-with-range-selection), [React Aria range](https://21st.dev/@jolbol1/components/date-range-picker) | Chosen behavior references: linked months, explicit endpoints and typed entry. Do not copy booking min/max duration or React dependency. Own calendar retains date-only arithmetic, draft/apply, month/year controls and roving keyboard. | shadcn underlying [MIT source](https://github.com/shadcn-ui/ui/blob/main/LICENSE.md); community listing license not independently asserted. |
| site:21st.dev line chart | [Evil Charts line](https://21st.dev/@LegionWebDev/components/line-chart) | Use restrained solid/dashed series and point tooltip pattern. Reject glow/rainbow variants and decorative trend badge. Existing SVG now supports pointer, tap and keyboard. | Listing did not establish license; inspiration only, no code copied. |
| site:21st.dev shopping cart | [Shopping cart drawer preview](https://cdn.21st.dev/sshahaider/drawer/shopping-cart/bundle.1753379971064.html) | Preview only exposed a cart count; rejected as insufficient POS reference. Owner screenshot and incumbent catalog/cart split remain authoritative. Added explicit HID entry and reachable summary. | Not established; no reuse. |
| site:21st.dev bottom navigation | [Bottom navigation collection](https://21st.dev/community/components/explore/shadcn-bottom-navigation), [Bottom Nav Bar](https://21st.dev/@arunachalam/components/bottom-nav-bar) | Retain four labeled role-specific destinations; strengthen selection and viewport safe-area setup. Reject hover-dependent magnifying/spotlight dock. | Listing did not establish license; no code copied. |
| site:21st.dev popover shadcn | [shadcn Popover](https://21st.dev/@shadcn/components/popover) | Compact anchored trigger/content model; keep native popovers, role-safe reduced account choices and second-click close. | Underlying shadcn MIT; no package installed. |

Searches/candidates are research records, not a claim that catalog components were installed or that their defaults meet BantuKasir acceptance.
