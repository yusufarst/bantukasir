import { describe, expect, it } from 'vitest';
import {
    choiceKey,
    choiceOf,
    CLOSE_SESSION_COMMAND,
    CLOSED,
    OPEN,
    registerText,
    sessionPage,
    sessionsAddress,
    sessionsShell,
    statusOf,
    varianceOf,
    varianceTone,
} from '@/lib/cash/sessions';

// The close and the session pages (AC-030-01, AC-030-07; KEY_SCREENS KS-07; COPY_DECK sections 7.5,
// 7.10 and 7.11; INTERACTION_INVENTORY UXI-004, UXI-019, UXI-105, UXI-135 to UXI-138; ROUTE_CONTRACTS
// RP-205, RP-206; BR-CASH-12; TASK-030): the variance exactly from canonical strings with its sign and
// tone, the filters and cursor in RP-205's address, the keys a filter takes, and the shell of each role.

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

    it('is a danger short, a warning over and a success even, never colour alone', () => {
        expect([varianceTone('-2000'), varianceTone('500'), varianceTone('0')]).toEqual(['danger', 'warning', 'success']);
    });
});

describe('the address of the sessions list', () => {
    it('keeps the filters and the cursor that are set, and nothing else', () => {
        const none = { status: null, from: null, to: null, register: null, responsible: null };

        expect(sessionsAddress(none)).toBe('/cash-sessions');
        expect(sessionsAddress({ ...none, status: CLOSED, from: '2026-11-10', to: '2026-11-11' })).toBe('/cash-sessions?status=CLOSED&from=2026-11-10&to=2026-11-11');
        expect(sessionsAddress({ ...none, register: '3', responsible: '7' }, { after: 'abc' })).toBe('/cash-sessions?register=3&responsible=7&after=abc');
        expect(sessionsAddress(none, { before: 'xyz' })).toBe('/cash-sessions?before=xyz');
    });

    it('reads the filters as the page read them, a key as its text', () => {
        expect(choiceOf({ status: OPEN, from: null, to: '2026-11-11', register: 3, responsible: null })).toEqual({ status: 'OPEN', from: null, to: '2026-11-11', register: '3', responsible: null });
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

    it('name a register by its code and its label', () => {
        expect([registerText('LACI-1', 'Laci 1'), registerText('LACI-1', 'LACI-1')]).toEqual(['LACI-1 · Laci 1', 'LACI-1']);
    });
});
