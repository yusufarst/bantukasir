import { describe, expect, it } from 'vitest';
import { problemOf } from '@/lib/authAnswer';
import { captureLinkFragment, forgetLinkFragment, linkFragment } from '@/lib/linkFragment';

// The one-time link's fragment (SECURITY_MODEL SEC-ACC-05; AC-006-06; TASK-006):
// read and removed from the address and the current history entry before
// Inertia starts, kept in memory only, forgotten once the password is set; and
// the answers of the authentication endpoints that the pages read.

interface Recorded {
    replaced: { state: unknown; url: string }[];
}

function browserAt(pathname: string, hash: string, search = ''): { target: Window; recorded: Recorded } {
    const recorded: Recorded = { replaced: [] };
    const target = {
        location: { pathname, hash, search },
        history: {
            state: { kept: true },
            replaceState(state: unknown, _unused: string, url: string) {
                recorded.replaced.push({ state, url });
            },
        },
    } as unknown as Window;

    return { target, recorded };
}

describe('captureLinkFragment()', () => {
    it('reads the account and the token and removes the fragment from the address and the history entry', () => {
        forgetLinkFragment();
        const { target, recorded } = browserAt('/set-password', '#u=12&t=abc%2Bdef');

        captureLinkFragment(target);

        expect(linkFragment()).toEqual({ user: '12', token: 'abc+def' });
        expect(recorded.replaced).toEqual([{ state: { kept: true }, url: '/set-password' }]);

        forgetLinkFragment();
        expect(linkFragment()).toBeNull();
    });

    it('removes a malformed fragment and keeps nothing', () => {
        forgetLinkFragment();
        const { target, recorded } = browserAt('/set-password', '#t=only-a-token', '?x=1');

        captureLinkFragment(target);

        expect(linkFragment()).toBeNull();
        expect(recorded.replaced).toEqual([{ state: { kept: true }, url: '/set-password?x=1' }]);
    });

    it('leaves every other page and an address without a fragment alone', () => {
        forgetLinkFragment();

        for (const [pathname, hash] of [
            ['/login', '#u=1&t=x'],
            ['/set-password', ''],
        ] as const) {
            const { target, recorded } = browserAt(pathname, hash);

            captureLinkFragment(target);

            expect(recorded.replaced).toEqual([]);
            expect(linkFragment()).toBeNull();
        }
    });
});

describe('problemOf()', () => {
    it('reads THROTTLED with its retry time, CSRF_FAILED and every other failure', () => {
        expect(problemOf({ status: 429, headers: { 'retry-after': '42' } })).toEqual({ kind: 'throttled', seconds: 42n });
        expect(problemOf({ status: 429, headers: {} })).toEqual({ kind: 'throttled', seconds: 60n });
        expect(problemOf({ status: 429, headers: { 'retry-after': 'soon' } })).toEqual({ kind: 'throttled', seconds: 60n });
        expect(problemOf({ status: 419, headers: {} })).toEqual({ kind: 'reload' });
        expect(problemOf({ status: 500, headers: {} })).toEqual({ kind: 'lost' });
        expect(problemOf({ status: 503, headers: {} })).toEqual({ kind: 'lost' });
    });
});
