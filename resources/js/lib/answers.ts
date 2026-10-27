import type { StateId } from '@/components/ui/stateCatalogue';

// The states of the answers that every page shares (UI_STATES sections 2 and 3;
// TECHNICAL_RULES section 4; TASK-007 scope item 9): what the shell shows for
// an answer that the page itself does not handle. 401 is answered by the
// server with the sign-in page (UXS-09), 423 opens the recent-authentication
// dialog of the shell (UXS-11, TASK-009), and a request that never reached
// the server is the no-connection state (UXS-20); a page's own commands show
// their uncertain panel instead (UXS-14, TASK-009).

/** The state of an HTTP answer's status. */
export function stateOfStatus(status: number): StateId {
    switch (status) {
        case 401:
            return 'UXS-09';
        case 403:
            return 'UXS-07';
        case 404:
        case 405:
            return 'UXS-08';
        case 419:
            return 'UXS-10';
        case 423:
            return 'UXS-11';
        case 429:
            return 'UXS-12';
        default:
            return 'UXS-22';
    }
}

/** The state of a request that got no answer: the browser had no connection. */
export const NO_CONNECTION: StateId = 'UXS-20';

/** The error page's states, as the server names them (App\Http\Errors\ErrorAnswer). */
export const ERROR_PAGE_STATES = {
    forbidden: 'UXS-07',
    not_found: 'UXS-08',
    reload: 'UXS-10',
    throttled: 'UXS-12',
    load_failed: 'UXS-22',
} as const satisfies Readonly<Record<string, StateId>>;

export type ErrorPageState = keyof typeof ERROR_PAGE_STATES;

/** The retry time of a throttled answer, in whole seconds; 60 when it is missing or not a whole number. */
export function retrySeconds(value: string | number | null | undefined): bigint {
    if (typeof value === 'number' && Number.isSafeInteger(value) && value >= 0) {
        return BigInt(value);
    }

    return typeof value === 'string' && /^[0-9]{1,6}$/.test(value) ? BigInt(value) : 60n;
}
