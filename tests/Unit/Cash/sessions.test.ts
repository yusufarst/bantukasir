import { describe, expect, it } from 'vitest';
import {
    ASSISTED_CLOSE_COMMAND,
    assistedClosePage,
    choiceKey,
    choiceOf,
    CLOSE_SESSION_COMMAND,
    CLOSED,
    closedMeanwhile,
    OPEN,
    registerText,
    REVIEW_NOTE_COMMAND,
    sessionPage,
    sessionsAddress,
    sessionsListFrom,
    sessionsShell,
    statusOf,
    varianceOf,
    varianceTone,
    type AssistedClosePreview,
    type ClosePreview,
} from '@/lib/cash/sessions';

// The close and the session pages (AC-030-01, AC-030-07; KEY_SCREENS KS-07; COPY_DECK sections 7.5,
// 7.10 and 7.11; INTERACTION_INVENTORY UXI-004, UXI-019, UXI-105, UXI-135 to UXI-138, UXI-148;
// ROUTE_CONTRACTS RP-205, RP-206; NAVIGATION_CONTRACTS UXP-105, UXP-205, UXP-206; BR-CASH-12;
// TASK-030): the variance exactly from canonical strings with its sign and tone, the filters, page
// size and cursor in RP-205's address and UXP-206's way back to it, the keys a filter takes, a session
// closed meanwhile, and the shell of each role.

describe('the variance of a count', () => {
    it('is the physical cash minus expected cash, exactly, with its sign', () => {
        expect(varianceOf('118000', '120000')).toBe('-2000');
        expect(varianceOf('120500', '120000')).toBe('500');
        expect(varianceOf('120000', '120000')).toBe('0');
        expect(varianceOf('999999999999', '0')).toBe('999999999999');
    });

    it('gives none for a blank or malformed amount', () => {
        for (const amount of ['', '1.500', '1e3', ' 5', 'Rp5']) {
            expect(varianceOf(amount, '120000')).toBeNull();
        }
    });

    it('is a warning short or over and a success even, as the approved KS-07 gives it, never colour alone', () => {
        expect([varianceTone('-2000'), varianceTone('500'), varianceTone('0')]).toEqual(['warning', 'warning', 'success']);
    });
});

describe('the address of the sessions list', () => {
    it('keeps the filters, a page size other than the default and the cursor that are set, and nothing else', () => {
        const none = { status: null, from: null, to: null, register: null, responsible: null, perPage: 25 };

        expect(sessionsAddress(none)).toBe('/cash-sessions');
        expect(sessionsAddress({ ...none, status: CLOSED, from: '2026-11-10', to: '2026-11-11' })).toBe('/cash-sessions?status=CLOSED&from=2026-11-10&to=2026-11-11');
        expect(sessionsAddress({ ...none, register: '3', responsible: '7' }, { after: 'abc' })).toBe('/cash-sessions?register=3&responsible=7&after=abc');
        expect(sessionsAddress(none, { before: 'xyz' })).toBe('/cash-sessions?before=xyz');
        expect(sessionsAddress({ ...none, status: CLOSED, perPage: 1 }, { after: 'abc' })).toBe('/cash-sessions?status=CLOSED&perPage=1&after=abc');
    });

    it('reads the filters as the page read them, a key as its text', () => {
        expect(choiceOf({ status: OPEN, from: null, to: '2026-11-11', register: 3, responsible: null, perPage: 10 })).toEqual({
            status: 'OPEN',
            from: null,
            to: '2026-11-11',
            register: '3',
            responsible: null,
            perPage: 10,
        });
    });

    it('is the way back of a session to the list it was opened from, with its filters and page, and else the whole list (UXI-148; review R-03)', () => {
        const origin = 'https://kasir.test';

        expect(sessionsListFrom(`${origin}/cash-sessions?status=CLOSED&perPage=1&after=abc`, origin)).toBe('/cash-sessions?status=CLOSED&perPage=1&after=abc');
        expect(sessionsListFrom(`${origin}/cash-sessions`, origin)).toBe('/cash-sessions');

        for (const opener of [`${origin}/till`, `${origin}/cash-sessions/7`, `${origin}/settings/users/3`, 'https://other.test/cash-sessions?status=OPEN', '', 'not an address']) {
            expect(sessionsListFrom(opener, origin)).toBe('/cash-sessions');
        }
    });

    it('takes a status of the list and a key of a choice, nothing else', () => {
        expect([statusOf('OPEN'), statusOf('CLOSED'), statusOf(''), statusOf('open')]).toEqual(['OPEN', 'CLOSED', null, null]);
        expect([choiceKey('7'), choiceKey(''), choiceKey('0'), choiceKey('-1'), choiceKey('1.5')]).toEqual(['7', null, null, null, null]);
    });
});

describe('the session pages', () => {
    it('name the detail of a session and the shell of each role', () => {
        expect(sessionPage(42)).toBe('/cash-sessions/42');
        expect(sessionsShell(true)).toEqual({ current: 'reports', area: 'reports', subCurrent: 'sessions' });
        expect(sessionsShell(false)).toEqual({ current: 'sessions' });
    });

    it('close a session without recent authentication', () => {
        expect(CLOSE_SESSION_COMMAND).toEqual({ operation: 'cash_session.close', family: 'close_session', recentAuth: false });
    });

    it('say the session closed meanwhile when the close is refused so or the preview read again is none or another session (UXS-30; F-6)', () => {
        const figures = { openingCash: '0', saleCount: 0, saleValue: '0', cashPayments: '0', transferPayments: '0', drawerRefunds: '0', expectedCash: '0' };
        const own: ClosePreview = { sessionId: 7, activityVersion: 0, figures, unresolvedCheckouts: 0 };
        const other: ClosePreview = { ...own, sessionId: 8 };

        expect(closedMeanwhile(7, own, null)).toBe(false);
        expect(closedMeanwhile(7, undefined, null)).toBe(false);
        expect(closedMeanwhile(7, own, 'RECOUNT_REQUIRED')).toBe(false);
        expect(closedMeanwhile(7, own, 'SESSION_NOT_OPEN')).toBe(true);
        expect(closedMeanwhile(7, null, null)).toBe(true);
        expect(closedMeanwhile(7, other, null)).toBe(true);
    });

    it('name a register by its code and its label', () => {
        expect([registerText('LACI-1', 'Laci 1'), registerText('LACI-1', 'LACI-1')]).toEqual(['LACI-1 · Laci 1', 'LACI-1']);
    });
});

// TASK-032: RP-207's path, RC-04 in the close's family with recent authentication, RC-43 in the review
// note's without it (INTERACTION_CONTRACTS sections 1.4 and 1.5), and a session closed meanwhile under the
// assisted close's preview, whose unresolved checkouts are a list.
describe('the Owner-assisted close and the review notes', () => {
    it('name RP-207 of a session and the families of RC-04 and RC-43', () => {
        expect(assistedClosePage(42)).toBe('/cash-sessions/42/assisted-close');
        expect(ASSISTED_CLOSE_COMMAND).toEqual({ operation: 'cash_session.close_assisted', family: 'close_session', recentAuth: true });
        expect(REVIEW_NOTE_COMMAND).toEqual({ operation: 'review_note.add', family: 'review_note', recentAuth: false });
        expect(ASSISTED_CLOSE_COMMAND.family).toBe(CLOSE_SESSION_COMMAND.family);
    });

    it('say the session closed meanwhile under the assisted preview as under the own one (UXS-30)', () => {
        const figures = { openingCash: '0', saleCount: 0, saleValue: '0', cashPayments: '0', transferPayments: '0', drawerRefunds: '0', expectedCash: '0' };
        const preview: AssistedClosePreview = { sessionId: 7, activityVersion: 0, figures, unresolvedCheckouts: [{ sourceIntentId: 'a', receivedAt: '2026-11-13T03:00:00.000000Z' }] };

        expect(closedMeanwhile(7, preview, null)).toBe(false);
        expect(closedMeanwhile(7, preview, 'SESSION_NOT_OPEN')).toBe(true);
        expect(closedMeanwhile(7, null, null)).toBe(true);
        expect(closedMeanwhile(8, preview, null)).toBe(true);
    });
});
