import { router } from '@inertiajs/react';

// What the browser does to keep a signed-in user's data from the next person
// on a shared device (SECURITY_MODEL SEC-AUTH-10, SEC-SESS-02, SEC-CH-04,
// SEC-CH-05, THR-18; UI_STATES section 5; TASK-007): at sign-out, at either
// limit of the sign-in session and on a change of user, the history
// encryption key and the application's browser storage are cleared, and the
// sign-in page is loaded with a full page load, so that no page data stays in
// memory and no page restored from history can be decrypted. The other tabs
// of the same browser share the sign-in session's cookie: they are told, and
// do the same. TASK-009 keeps the unresolved command references, which
// survive a sign-out (SEC-CH-05).

const SIGN_IN = '/login';

/**
 * Where the browser goes when the sign-in session ended without a sign-out:
 * the application's address (RN-01), which the server answers with the
 * sign-in page and the note that the sign-in session ended (UXS-09), or, when
 * the session is still alive by the server's clock — another tab of the
 * browser kept it — with the home.
 */
const ENDED = '/';

const CHANNEL = 'bk-sign-in-session';

// The deadlines name the user whose sign-in session they are: a tab never
// takes another user's deadlines as its own, so a page of a user whose
// session ended on the server is never kept alive by the next user's
// sign-in in another tab (THR-18; TASK-007 review finding R-27).
type TabMessage = { readonly type: 'ended' } | { readonly type: 'deadlines'; readonly userId: number; readonly idleAt: number; readonly absoluteAt: number };

let channel: BroadcastChannel | null | undefined;

function tabs(): BroadcastChannel | null {
    if (channel === undefined) {
        channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(CHANNEL);
    }

    return channel;
}

/** Clears the history encryption key and every entry of the application's browser storage. */
export function clearBrowserData(): void {
    router.clearHistory();

    for (const storage of [window.sessionStorage, window.localStorage]) {
        try {
            storage.clear();
        } catch {
            // A storage the browser refuses holds nothing of the application.
        }
    }
}

/** Ends the sign-in session in this browser: its data cleared, the other tabs told, the sign-in page loaded. */
export function leaveSignedInSession(): void {
    clearBrowserData();
    tabs()?.postMessage({ type: 'ended' } satisfies TabMessage);
    window.location.assign(ENDED);
}

/** Another tab ended the sign-in session: this tab's data cleared and the sign-in page loaded, without telling the others again. */
export function followOtherTab(): void {
    clearBrowserData();
    window.location.assign(ENDED);
}

/**
 * Signs out (UXI-026, UXI-035; RA-02): the browser's data cleared and the
 * other tabs told first; the server answers with a full page load to the
 * sign-in page, and when no answer arrives the browser loads it anyway
 * (UI_STATES section 5).
 */
export function signOut(): void {
    clearBrowserData();
    tabs()?.postMessage({ type: 'ended' } satisfies TabMessage);

    router.post(
        '/logout',
        {},
        {
            onHttpException: () => {
                window.location.assign(SIGN_IN);

                return false;
            },
            onNetworkError: () => {
                window.location.assign(SIGN_IN);

                return false;
            },
        },
    );
}

/** Tells the other tabs the deadlines this tab learned from the server for this user. */
export function shareDeadlines(userId: number, idleAt: number, absoluteAt: number): void {
    tabs()?.postMessage({ type: 'deadlines', userId, idleAt, absoluteAt } satisfies TabMessage);
}

/** Listens to the other tabs: an ended sign-in session, or deadlines they learned, with the user they belong to. */
export function listenToOtherTabs(onEnded: () => void, onDeadlines: (userId: number, idleAt: number, absoluteAt: number) => void): () => void {
    const source = tabs();

    if (source === null) {
        return () => undefined;
    }

    const listener = (event: MessageEvent<unknown>): void => {
        const message = event.data;

        if (typeof message !== 'object' || message === null || !('type' in message)) {
            return;
        }

        if (message.type === 'ended') {
            onEnded();
        } else if (
            message.type === 'deadlines' &&
            'userId' in message &&
            'idleAt' in message &&
            'absoluteAt' in message &&
            typeof message.userId === 'number' &&
            typeof message.idleAt === 'number' &&
            typeof message.absoluteAt === 'number'
        ) {
            onDeadlines(message.userId, message.idleAt, message.absoluteAt);
        }
    };

    source.addEventListener('message', listener);

    return () => source.removeEventListener('message', listener);
}

/** The CSRF token of the session, from the cookie that the framework sets for scripts. */
function xsrfToken(): string {
    const prefix = 'XSRF-TOKEN=';
    const cookie = document.cookie.split('; ').find((part) => part.startsWith(prefix));

    return cookie === undefined ? '' : decodeURIComponent(cookie.slice(prefix.length));
}

export type ActivityAnswer = 'kept' | 'ended' | 'lost';

/**
 * A user-activity request (RA-07): it counts as user activity, so the idle
 * limit counts from its answer (SEC-SESS-02); 401 means the sign-in session
 * has already ended.
 */
export async function reportActivity(): Promise<ActivityAnswer> {
    try {
        const answer = await fetch('/session/activity', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
        });

        return answer.status === 204 ? 'kept' : answer.status === 401 ? 'ended' : 'lost';
    } catch {
        return 'lost';
    }
}
