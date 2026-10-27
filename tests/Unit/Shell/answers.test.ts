import { describe, expect, it } from 'vitest';
import { STATE_CATALOGUE } from '@/components/ui/stateCatalogue';
import { ERROR_PAGE_STATES, NO_CONNECTION, retrySeconds, stateOfStatus } from '@/lib/answers';

// The states of the shared answers (AC-007-04; UI_STATES sections 2 and 3;
// TECHNICAL_RULES section 4; TASK-007 scope item 9): 403 UXS-07, 404 UXS-08,
// 401 UXS-09, 419 UXS-10, 423 UXS-11, 429 UXS-12, no connection UXS-20, any
// other failure of a read UXS-22; the error page's states equal the server's
// names (App\Http\Errors\ErrorAnswer).

describe('the state of an answer', () => {
    it('is the one of UI_STATES section 3 for each status', () => {
        expect([401, 403, 404, 405, 419, 423, 429, 500, 503, 400].map(stateOfStatus)).toEqual(['UXS-09', 'UXS-07', 'UXS-08', 'UXS-08', 'UXS-10', 'UXS-11', 'UXS-12', 'UXS-22', 'UXS-22', 'UXS-22']);
        expect(NO_CONNECTION).toBe('UXS-20');
        expect(STATE_CATALOGUE[NO_CONNECTION].title).toBe('ux.state.offline.title');
    });

    it('names the states of the error page as the server does', () => {
        expect(ERROR_PAGE_STATES).toEqual({ forbidden: 'UXS-07', not_found: 'UXS-08', reload: 'UXS-10', throttled: 'UXS-12', load_failed: 'UXS-22' });
    });

    it('reads the retry time as whole seconds, 60 when it is missing or malformed', () => {
        expect(retrySeconds('30')).toBe(30n);
        expect(retrySeconds(42)).toBe(42n);
        expect(retrySeconds(undefined)).toBe(60n);
        expect(retrySeconds('1.5')).toBe(60n);
        expect(retrySeconds(-1)).toBe(60n);
        expect(retrySeconds('9999999')).toBe(60n);
    });
});
