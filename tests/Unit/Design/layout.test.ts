import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { EXPANDED_FROM, MEDIUM_FROM, widthClassOf } from '@/layouts/widthClass';

// The width classes of UXA-02 (DESIGN_SYSTEM 7.1, DV-01): compact below 600
// CSS px, medium from 600 to 1023, expanded from 1024 — the same values in
// the script, the tokens' media queries and the Tailwind theme.
describe('the width classes', () => {
    it('are compact, medium and expanded at 600 and 1024 CSS px', () => {
        expect([MEDIUM_FROM, EXPANDED_FROM]).toEqual([600, 1024]);
        expect([320, 599, 600, 1023, 1024, 1440].map(widthClassOf)).toEqual(['compact', 'compact', 'medium', 'medium', 'expanded', 'expanded']);
    });

    it('agree with the stylesheets', () => {
        const tokens = readFileSync('resources/css/tokens.css', 'utf8');
        const app = readFileSync('resources/css/app.css', 'utf8');

        expect(tokens).toContain('@media (max-width: 599.98px)');
        expect(tokens).toContain('@media (max-width: 1023.98px)');
        expect(app).toContain('--breakpoint-medium: 600px;');
        expect(app).toContain('--breakpoint-expanded: 1024px;');
    });
});
