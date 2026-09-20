# Prototype registry

No prototypes exist. No visual pattern has owner approval. Architecture decisions D01–D34 are approved, and the [official colored mark](../assets/brand/README.md) is preserved unchanged. No separate monochrome/wordmark/banner was supplied. CP01 will verify small-size use and the refined pastel system; no replacement assets have been created.

[05 — Design system](../docs/05-DESIGN-SYSTEM.md) owns structure, states and review evidence. Register each future artifact here and keep a revision-specific `REVIEW.md`. This registry is not the build progress tracker.

| Pattern ID | Revision | Artifact status | HTML / review link |
| --- | --- | --- | --- |
| CP01 | INITIAL_DRAFT | UNDER_REVIEW | [REVIEW.md](cp01-shell-auth/REVIEW.md) |

Canonical order: CP01 shell/auth → CP02 products/import → CP03 inventory/scan → CP04 owner cockpit → CP05 public website/catalog. The first four are P00 tasks.

**Next executor task:** P00.1 (CP01 shell/auth) has been prepared by Gemini Antigravity and is currently **[V] WAITING FOR OWNER VISUAL REVIEW**. P00.2 cannot start before Gate A approval of CP01. Reuse approved patterns rather than creating a second frontend codebase. No production API/database connections or Next.js implementation belong in P00. See [executor handoff](../docs/16-EXECUTOR-HANDOFF.md).
