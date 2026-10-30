// The accent set of DESIGN_SYSTEM section 3.3: the shop's BusinessProfile
// stores one accent_token (SCHEMA), and only the brand slots of the tokens
// change, through the one attribute data-accent on the root element. Green is
// the default and the accent of the pre-authentication pages. Anything outside
// the allowlist falls back to green.

export const ACCENT_TOKENS = ['green', 'teal', 'blue', 'purple', 'graphite'] as const;

export type AccentToken = (typeof ACCENT_TOKENS)[number];

export const DEFAULT_ACCENT: AccentToken = 'green';

export function isAccentToken(value: unknown): value is AccentToken {
    return typeof value === 'string' && (ACCENT_TOKENS as readonly string[]).includes(value);
}

/** Applies the shop's accent to the document; returns the accent applied. */
export function applyAccent(doc: Document, token: unknown): AccentToken {
    const accent = isAccentToken(token) ? token : DEFAULT_ACCENT;

    doc.documentElement.setAttribute('data-accent', accent);

    return accent;
}

/**
 * The shop's accent of a page's shared props (SEC-CH-02 as TASK-010 amends
 * it), or null when the page carries no shop — before sign-in, whose pages
 * keep the default green of a fresh document (DESIGN_SYSTEM section 3.3).
 */
export function accentOfPage(props: unknown): unknown {
    if (typeof props !== 'object' || props === null || !('shop' in props)) {
        return null;
    }

    const shop: unknown = props.shop;

    return typeof shop === 'object' && shop !== null && 'accent' in shop ? shop.accent : null;
}

/**
 * Applies the accent of the BusinessProfile in force to every signed-in page,
 * before it renders (AC-010-05): the first page of a full load and each page
 * Inertia visits after it. A page without a shop leaves the document as it is.
 */
export function followPageAccent(doc: Document, props: unknown): void {
    const accent = accentOfPage(props);

    if (accent !== null) {
        applyAccent(doc, accent);
    }
}
