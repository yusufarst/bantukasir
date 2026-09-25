# BK02 actual tools and provenance

25 September 2026. Evidence records the interrupted execution and its continuation as one task. No paid templates, credits, service, generated-image workflow or production integration was used.

## 21st.dev

No callable 21st MCP was available. The free public catalog fallback permitted by docs/05 was genuinely searched and inspected through the browser/search tools before implementation.

Queries used:

1. `site:21st.dev shadcn data table filter pagination`
2. `site:21st.dev inventory product search form drawer dialog`
3. `site:21st.dev shadcn form input inventory sheet`
4. `site:21st.dev/@shadcn/components input form`
5. `site:21st.dev inventory table receiving sheet dialog form`

| Inspected candidate | Decision |
| --- | --- |
| [Data Table Filter](https://21st.dev/@uniquesonu/components/data-table-filter) | Explicit filter state/clear pattern; implemented in existing vanilla UI01 primitives. |
| [Data Grid column controls](https://21st.dev/@sean0205/components/data-grid-table/column-controls) | Bounded rows/pagination and header hierarchy; rejected grid framework and column-customization complexity. |
| [Credenza](https://21st.dev/@redpangilinan/components/credenza) | Responsive overlay reference; retained existing native UI01 dialog/drawer instead of importing a library. |
| [Input with helper text](https://21st.dev/@originui/components/input/input-with-helper-text) | Visible label/helper pattern examined in search results; reused UI01 fields. |
| [Custom input group](https://21st.dev/community/components/shadcn/input-group/custom-input) | Examined search-result code; rejected unnecessary grouping/dependency. |
| [Tag input form](https://21st.dev/community/components/shadcn/form/tag-input-form) | Thin page, not usable implementation evidence. No code adopted. |
| [Hero UI pagination](https://21st.dev/community/components/hero_ui/the-pagination/default) | Rejected new dependency; native controls suffice. |

No component source was copied from these pages. Individual community-component licenses were not established; no blanket MIT claim is made. The [upstream shadcn license](https://github.com/shadcn-ui/ui/blob/main/LICENSE.md) was inspected, but does not establish every catalog contributor's license.

The separate search `site:github.com/lindell/JsBarcode LICENSE MIT` and official README/license inspection supported the sole new vendor dependency: pinned Code128-only JsBarcode 3.11.0, MIT. Exact download URLs and local license are in [vendor provenance](../../../prototypes/ui02/vendor/README.md). No runtime external font/script request is required.

## Impeccable

- Actual local skill 4.3.1 and bundled CLI 4.0.0 were verified. `--version`, `--help`, `context --target prototypes/ui02/index.html`, `critique-storage slug prototypes/ui02/index.html`, and `detect --help` ran. Context ran once for this task; slug resolved to `prototypes-ui02-index-html`.
- Critique, craft-floor and polish playbooks guided the work. These are skill workflows, not invented CLI `critique` or `polish` commands.
- Independent review A (`bk02_design_review`) inspected the running desktop/mobile prototype in a fresh browser tab. Independent review B (`bk02_evidence_review`) inspected a separate tab and ran the detector once. A was completed before B's detector findings were released to the primary reviewer. Both were read-only.
- Actual detector result: **0 primary findings; 3 advisories**, all `gpt-thin-border-wide-shadow`, reporting 1px border plus 32px shadow. See [raw result](detector.json). These concern the inherited approved overlay treatment; intentional exception, not silently removed or treated as primary failures. The detector was not rerun after targeted corrections.
- No live-server injection or overlay is claimed: the browser inspection API available to the reviewers did not support mutable injection. Runtime browser inspection and detector output were separate evidence.
- Baseline heuristic score from A was 26/40 before the targeted fixes. The final review consolidates findings and verification; there was no second independent numerical rescore. No retroactive approval or new score is claimed.
- Only the final consolidated BK02 packet is staged. Superseded intermediate captures and helper outputs remain in ignored `.local/ui02`; no historical critique snapshot is presented as a current assessment.

Questions skipped: the owner explicitly authorized these bounded remediations and supplied the role/scope constraints. Remaining visual approval belongs to the owner.
