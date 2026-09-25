# UI02 reuse map

Candidate extension of **approved BK-DS-UI01-r6**; BK02 WAITING OWNER REVIEW. DESIGN.md remains the sole token/pattern authority. Do not treat this candidate as owner approval or authorization for BK03.

| Composition | Implementation | Reuse contract |
| --- | --- | --- |
| Shell, role navigation, utilities, account | shell.js; shared ../ui01/styles.css | Reference copy with route adaptation only. Approved UI01 files stay unchanged. Expanded/rail desktop; stable outside click; mobile drawer outside-dismiss; account within shell. |
| Product table / mobile records | catalog, catalogResults; .product-table | Shared panel/table type hierarchy. Search first, explicit filters, compact mobile disclosure, labeled record facts, 25 per page. |
| Product form / duplicate review | productForm, duplicateReview | Existing .field/.btn/native dialog. Exact SKU/barcode conflict blocks; similar name requires deliberate confirmation. No stock field on create. |
| Lookup + posting draft | workflow, lookup, renderDraft | Two balanced desktop panels; stacked phone panels; bounded result list and draft jump above bottom nav; return-to-search action. Scan affects draft only. |
| Cost evidence | renderDraft, reviewMovement, movementDetail | Operations write-only input boundary; checkbox rerender restores its focus; known and UNKNOWN distinct. Owner-only read projection. |
| Correction review | originalFact; .correction-impact | Reversal hides irrelevant fields. Three columns: current / delta / after explicit confirmation. Original immutable fact retained. |
| Barcode label | labels; vendor/JsBarcode.code128.min.js | Real Code128 renderer, persistent identity, provisional paper size, honest print intent. No product image support. |
| UI states | queryState, denied, missing, dialogs | Loading/error never imply zero stock; empty can clear filters; native modal focus; explicit denied state. |

Use existing Source Sans 3, navy/teal, semantic amber/red/green, border/radius/shadow and spacing tokens. UI02 CSS only composes workflow/table layouts and protects hidden fields; it does not redefine the foundation. The .mobile-draft-jump follows UI01's existing action-above-bottom-navigation pattern. All new phone action targets use at least 44px; desktop product titles have an adjacent 44px Detail action.

Review footer and synthetic state controls are prototype-only. Do not copy them into production. Production command recovery, durable idempotency, full audit, valuation, API pagination and backend role enforcement require the later authorized vertical slices.
