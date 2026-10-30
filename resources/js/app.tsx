import { createInertiaApp, router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { followPageAccent } from '@/lib/accent';
import { installCspNonce } from '@/lib/csp';
import { installDictionary } from '@/lib/i18n';
import { captureLinkFragment } from '@/lib/linkFragment';

// Client-side Inertia, set up without the optional Inertia Vite plugin so that
// no server-side rendering is generated (STACK_AND_MODULES section 1). Pages are
// added under ./pages by the Tasks that own them.
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

// The styles that libraries inject at run time receive the nonce of the
// content security policy (SEC-HDR-03, TASK-005): before the first render.
const nonce = installCspNonce(document);

// The one-time link's fragment is read and removed before Inertia starts,
// which would otherwise keep it in the page it stores in the browser history
// (SEC-ACC-05; TASK-006).
captureLinkFragment(window);

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
        // The shop's accent on every signed-in page, before it renders
        // (DESIGN_SYSTEM section 3.3; TASK-010).
        followPageAccent(document, props.initialPage.props);
        router.on('navigate', (event) => followPageAccent(document, event.detail.page.props));
        createRoot(el).render(<App {...props} />);
    },
});
