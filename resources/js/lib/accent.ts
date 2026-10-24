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
