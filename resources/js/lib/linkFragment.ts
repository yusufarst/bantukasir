// The one-time link's account and token (SECURITY_MODEL SEC-ACC-05; ROUTE_CONTRACTS
// RP-002; TASK-006). The link carries them in the fragment, `#u=<id>&t=<token>`,
// which browsers never send to the server. app.tsx calls captureLinkFragment()
// before Inertia starts, because Inertia copies the address's fragment into
// the page it keeps in the browser history: the fragment is read, removed from
// the address bar and from the current history entry, and kept in this
// module's memory only — never in props, history state, browser storage, a
// URL or a log — for the link page to post in the body of its submission.

export interface LinkFragment {
    readonly user: string;
    readonly token: string;
}

/** The path of the link page, RP-002. */
export const LINK_PATH = '/set-password';

let captured: LinkFragment | null = null;

/**
 * Reads and removes the fragment of the link page's address. Any fragment of
 * that page is removed, also a malformed one; a malformed fragment keeps
 * nothing, and the server then answers the uniform LINK_INVALID.
 */
export function captureLinkFragment(target: Window): void {
    if (target.location.pathname !== LINK_PATH || target.location.hash === '') {
        return;
    }

    const parameters = new URLSearchParams(target.location.hash.slice(1));
    const user = parameters.get('u');
    const token = parameters.get('t');

    target.history.replaceState(target.history.state, '', target.location.pathname + target.location.search);
    captured = user !== null && token !== null && user !== '' && token !== '' ? { user, token } : null;
}

/** The link captured when this page was loaded, or null. */
export function linkFragment(): LinkFragment | null {
    return captured;
}

/** Forgets the link once the password is set: it can never be used again. */
export function forgetLinkFragment(): void {
    captured = null;
}
