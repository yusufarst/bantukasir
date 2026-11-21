import { readFileSync } from 'node:fs';
import { beforeAll, describe, expect, it } from 'vitest';
import { ACTION_CODES, ACTION_LABELS, actionWord, activityAddress, activityBack, actorWord, outcomeWord, referenceLink, SECURITY_TYPES, securityAddress, securityTypeOf, securityTypeWord, type ActivityFilters } from '@/lib/audit/activity';
import { installDictionary } from '@/lib/i18n';

// The words and addresses of UXP-612 and UXP-613 (AC-042-01, AC-042-03; COPY_DECK sections 11.12 to 11.14;
// INTERACTION_CONTRACTS section 8; NAVIGATION_CONTRACTS UXP-612, UXP-613; TASK-042): the interface labels
// exactly the codes that the server holds, in their order, and the nine event types; who acted; the page of
// each kind of reference; the addresses with their filters and page; and the way back to UXP-612 of the
// pages it opens, with its filters, and only from it.

const ORIGIN = 'https://bantukasir.test';

const NONE: ActivityFilters = { from: null, to: null, user: null, action: null, product: null, session: null, reference: null, perPage: 25 };

beforeAll(() => {
    installDictionary({
        'audit.action.sale': 'Penjualan',
        'audit.activity.title': 'Riwayat aktivitas',
        'audit.security.type.sign_in_failed': 'Gagal masuk',
        'audit.security.outcome.succeeded': 'Berhasil',
        'audit.security.outcome.refused': 'Ditolak',
        'audit.security.actor.anonymous': 'Belum masuk',
        'audit.security.actor.scheduled': 'Tugas terjadwal',
        'ux.actor.operator': 'Prosedur operator',
        'ux.actor.system': 'Sistem',
        'inventory.issue_detail.title': 'Detail barang keluar',
    });
});

describe('the labels of the action codes', () => {
    it('are those of App\\Domain\\Audit\\AuditActions, code by code and in its order', () => {
        const source = readFileSync('app/Domain/Audit/AuditActions.php', 'utf8');
        const server = [...source.matchAll(/'([A-Z][A-Z0-9_]*)' => '(audit\.action\.[a-z_]+)'/g)].map((match) => [match[1], match[2]]);

        expect(server).toHaveLength(55);
        expect(Object.entries(ACTION_LABELS)).toEqual(server);
        expect(ACTION_CODES).toEqual(server.map((entry) => entry[0]));
    });

    it('name a code in shop words, and keep a code that section 8 does not list', () => {
        expect(actionWord('SALE_CHECKOUT')).toBe('Penjualan');
        expect(actionWord('NOT_A_CODE')).toBe('NOT_A_CODE');
    });
});

describe('the security log', () => {
    it('labels the nine event types of App\\Domain\\Audit\\SecurityEvent, in its order', () => {
        const source = readFileSync('app/Domain/Audit/SecurityEvent.php', 'utf8');
        const server = [...source.matchAll(/case \w+ = '([A-Z_]+)';/g)].map((match) => match[1]);

        expect(SECURITY_TYPES.map((entry) => entry.type)).toEqual(server);
        expect(SECURITY_TYPES.map((entry) => entry.label)).toEqual(server.map((type) => `audit.security.type.${(type ?? '').toLowerCase()}`));
        expect(securityTypeWord('SIGN_IN_FAILED')).toBe('Gagal masuk');
        expect(securityTypeOf('SIGN_IN_FAILED')).toBe('SIGN_IN_FAILED');
        expect(securityTypeOf('sign_in_failed')).toBeNull();
    });

    it('says SUCCEEDED and REFUSED in words, and keeps any other code as it is', () => {
        expect([outcomeWord('SUCCEEDED'), outcomeWord('REFUSED'), outcomeWord('HELD')]).toEqual(['Berhasil', 'Ditolak', 'HELD']);
    });
});

describe('who acted', () => {
    it('is the name the record holds, or the kind of actor without a user', () => {
        expect(actorWord({ kind: 'USER', name: 'Rina' }, 'activity')).toBe('Rina');
        expect(actorWord({ kind: 'OPERATOR_PROCEDURE', name: null }, 'activity')).toBe('Prosedur operator');
        expect(actorWord({ kind: 'SCHEDULED_COMMAND', name: null }, 'activity')).toBe('Sistem');
        expect(actorWord({ kind: 'SCHEDULED_COMMAND', name: null }, 'security')).toBe('Tugas terjadwal');
        expect(actorWord({ kind: 'ANONYMOUS', name: null }, 'security')).toBe('Belum masuk');
    });
});

describe('the reference of an event', () => {
    it('leads to the page of each kind of object, with its number or name, or the page title without one', () => {
        expect(referenceLink({ text: '20261115-000001', kind: 'sale', id: 4 })).toEqual({ href: '/sales/4', text: '20261115-000001' });
        expect(referenceLink({ text: '20261115-000002', kind: 'session', id: 5 })?.href).toBe('/cash-sessions/5');
        expect(referenceLink({ text: 'Pulpen Hitam', kind: 'product', id: 6 })?.href).toBe('/products/6');
        expect(referenceLink({ text: 'Pulpen Hitam', kind: 'movement', id: 7 })?.href).toBe('/stock/movements/7');
        expect(referenceLink({ text: 'NOTA-7', kind: 'receiving', id: 8 })?.href).toBe('/stock/receivings/8');
        expect(referenceLink({ text: null, kind: 'issue', id: 9 })).toEqual({ href: '/stock/issues/9', text: 'Detail barang keluar' });
        expect(referenceLink({ text: 'Rina', kind: 'user', id: 10 })?.href).toBe('/settings/users/10');
    });

    it('is text for an object without a page', () => {
        expect(referenceLink({ text: 'REF-01', kind: null, id: null })).toBeNull();
    });
});

describe('the addresses of the lists', () => {
    it('hold the filters that are set and the page, compared as parameters', () => {
        expect(activityAddress(NONE)).toBe('/settings/activity');

        const address = new URL(activityAddress({ ...NONE, from: '2026-11-15', to: '2026-11-15', user: 3, action: 'SALE_CHECKOUT', product: 6, session: '20261115-000002', reference: 'NOTA 7', perPage: 50 }, { after: 'abc' }), ORIGIN);

        expect(address.pathname).toBe('/settings/activity');
        expect(Object.fromEntries(address.searchParams)).toEqual({ from: '2026-11-15', to: '2026-11-15', user: '3', action: 'SALE_CHECKOUT', product: '6', session: '20261115-000002', reference: 'NOTA 7', perPage: '50', after: 'abc' });

        const security = new URL(securityAddress({ from: null, to: null, type: 'SIGN_IN_FAILED', user: 2, perPage: 25 }, { before: 'xyz' }), ORIGIN);

        expect(security.pathname).toBe('/settings/security-log');
        expect(Object.fromEntries(security.searchParams)).toEqual({ type: 'SIGN_IN_FAILED', user: '2', before: 'xyz' });
        expect(securityAddress({ from: null, to: null, type: null, user: null, perPage: 25 })).toBe('/settings/security-log');
    });
});

describe('the way back to UXP-612', () => {
    it('returns to it with its filters and page when it opened the page, and only then', () => {
        expect(activityBack(`${ORIGIN}/settings/activity?action=SALE_CHECKOUT&after=abc`, ORIGIN)).toEqual({ href: '/settings/activity?action=SALE_CHECKOUT&after=abc', page: 'Riwayat aktivitas' });
        expect(activityBack(`${ORIGIN}/settings/activity`, ORIGIN)).toEqual({ href: '/settings/activity', page: 'Riwayat aktivitas' });
        expect(activityBack(`${ORIGIN}/stock/receivings`, ORIGIN)).toBeNull();
        expect(activityBack('https://elsewhere.test/settings/activity', ORIGIN)).toBeNull();
        expect(activityBack('', ORIGIN)).toBeNull();
    });
});
