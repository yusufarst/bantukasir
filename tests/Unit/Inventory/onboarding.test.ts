import { beforeAll, describe, expect, it } from 'vitest';
import type { ProductRow } from '@/lib/catalog/products';
import { installDictionary } from '@/lib/i18n';
import { countProblem, eligibilityWord, hubView, isZeroCount, onboardingAddress, openingEntryPage, openingProductOf, type OnboardingInfo } from '@/lib/inventory/onboarding';

// The onboarding pages' rules (TASK-035 scope item 5; ROUTE_CONTRACTS RP-412, RP-413, RD-01 `opening`;
// INTERACTION_INVENTORY UXI-285 to UXI-294; COPY_DECK sections 9.13 and 9.14; D-071, D-072, D-087): what
// UXP-412 shows — completed once a release is recorded, also when restored data still show it running,
// held while a restore waits for its reconciliation; the addresses of UXP-412 and UXP-413; a product of
// RD-01 in the context `opening` and the words of its eligibility; and the count of a row, where 0 records
// a zero count and a replacement takes more.

const ONBOARDING: OnboardingInfo = { state: 'NOT_STARTED', version: 1, held: false, startedAt: null, releasedAt: null };

const ROW: ProductRow = { id: 7, name: 'Tinta', sku: 'TINTA-01', baseUnit: 'pcs', quantityPrecision: 0, price: { amount: '7500', revisionId: 3 }, active: true, thumbnail: null, activeBarcode: false };

beforeAll(() => {
    installDictionary({
        'ux.validation.required': 'wajib',
        'ux.validation.quantity_positive': 'positif',
        'ux.validation.quantity_whole': 'bulat',
        'ux.validation.quantity_decimals': 'desimal :desimal',
        'ux.validation.quantity_max': 'maks',
    });
});

describe('the state that UXP-412 shows', () => {
    it('is the completed onboarding once a release is recorded, before the hold and the state', () => {
        expect(hubView(ONBOARDING)).toBe('not_started');
        expect(hubView({ ...ONBOARDING, state: 'ACTIVE', version: 2 })).toBe('active');
        expect(hubView({ ...ONBOARDING, state: 'RELEASED', version: 3, releasedAt: '2026-11-15T03:00:00.000000Z' })).toBe('released');
        // D-072: restored data that still show it running, with the release record, are completed.
        expect(hubView({ ...ONBOARDING, state: 'ACTIVE', releasedAt: '2026-11-15T03:00:00.000000Z', held: true })).toBe('released');
        expect(hubView({ ...ONBOARDING, state: 'ACTIVE', held: true })).toBe('held');
        expect(hubView({ ...ONBOARDING, held: true })).toBe('held');
    });
});

describe('the addresses of the onboarding pages', () => {
    it('hold the filter and one cursor, and the product of a replacement', () => {
        expect(onboardingAddress(null)).toBe('/stock/onboarding');
        expect(onboardingAddress('counted')).toBe('/stock/onboarding?filter=counted');
        expect(onboardingAddress('not_counted', { after: 'abc' })).toBe('/stock/onboarding?filter=not_counted&after=abc');
        expect(onboardingAddress(null, { before: 'xyz' })).toBe('/stock/onboarding?before=xyz');
        expect(openingEntryPage()).toBe('/stock/onboarding/entry');
        expect(openingEntryPage(7)).toBe('/stock/onboarding/entry?product=7');
    });
});

describe('a product of RD-01 in the context opening', () => {
    it('carries its version and eligibility, and the line of a replacement only when replaceable', () => {
        expect(openingProductOf({ ...ROW, version: 2, opening: { eligibility: 'REPLACEABLE', zeroCountId: null, replacesLineId: 11 } } as ProductRow)).toEqual({
            id: 7,
            name: 'Tinta',
            sku: 'TINTA-01',
            baseUnit: 'pcs',
            quantityPrecision: 0,
            version: 2,
            active: true,
            eligibility: 'REPLACEABLE',
            replacesLineId: 11,
        });
        expect(openingProductOf({ ...ROW, version: 2, opening: { eligibility: 'COUNTED', zeroCountId: null, replacesLineId: 11 } } as ProductRow)?.replacesLineId).toBeNull();
        // Without its version or eligibility it is no product of this context.
        expect(openingProductOf(ROW)).toBeNull();
        expect(openingProductOf({ ...ROW, version: 2 } as ProductRow)).toBeNull();
        expect(openingProductOf({ ...ROW, version: 2, opening: { eligibility: 'OTHER' } } as ProductRow)).toBeNull();
    });

    it('names why a new row cannot take a product, and nothing for an eligible one (UXI-292)', () => {
        expect(eligibilityWord('ELIGIBLE')).toBeNull();
        expect(eligibilityWord('ZERO_COUNTED')).toBe('inventory.opening_entry.already_zero');
        expect(eligibilityWord('COUNTED')).toBe('inventory.onboarding.filter_counted');
        expect(eligibilityWord('REPLACEABLE')).toBe('inventory.opening_entry.replace_eligible');
        expect(eligibilityWord('HAS_MOVEMENT')).toBe('inventory.opening_entry.not_eligible');
    });
});

describe('the count of a row', () => {
    it('takes 0 as a zero count, a replacement more, in the product precision within the largest', () => {
        expect([countProblem('0', 0, false), countProblem('0,000', 3, false), countProblem('12', 0, false), countProblem('2,5', 3, false)]).toEqual([null, null, null, null]);
        expect([countProblem('', 0, false), countProblem('  ', 0, false)]).toEqual(['wajib', 'wajib']);
        expect([countProblem('0', 0, true), countProblem('-1', 0, false), countProblem('x', 0, false)]).toEqual(['positif', 'positif', 'positif']);
        expect([countProblem('1,5', 0, false), countProblem('1,2345', 3, false)]).toEqual(['bulat', 'desimal 3']);
        expect(countProblem('100000000', 0, false)).toBe('maks');
        expect([isZeroCount('0'), isZeroCount('0,00'), isZeroCount('00'), isZeroCount('0,5'), isZeroCount(''), isZeroCount('1')]).toEqual([true, true, true, false, false, false]);
    });
});
