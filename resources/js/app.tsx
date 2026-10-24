import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { installCspNonce } from '@/lib/csp';
import { installDictionary } from '@/lib/i18n';

// Client-side Inertia, set up without the optional Inertia Vite plugin so that
// no server-side rendering is generated (STACK_AND_MODULES section 1). Pages are
// added under ./pages by the Tasks that own them.
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

// The styles that libraries inject at run time receive the nonce of the
// content security policy (SEC-HDR-03, TASK-005): before the first render.
const nonce = installCspNonce(document);

void createInertiaApp({
    nonce,
    resolve: async (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (page === undefined) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        // The interface dictionary arrives once, with the first page of a full
        // page load (ADR-005 item 4); t() reads it from then on.
        installDictionary(props.initialPage.props.dictionary);
        createRoot(el).render(<App {...props} />);
    },
});
