import type { ReactNode } from 'react';
import { t } from '@/lib/i18n';
import { REQUESTS_PAGE } from '@/lib/inventory/stock';

// The parts that the stock and receiving pages share (TASK-022).

/**
 * Why a new receiving cannot be started or confirmed now (UXS-13; COPY_DECK section 15): the
 * onboarding freeze (UXS-27, BR-INV-24), or an unresolved receiving, manual issue or adjustment
 * request of the user, with the way to UXP-006 (NC-13); none otherwise.
 */
export function postingBlockedReason(postingFrozen: boolean, blocked: boolean): ReactNode | undefined {
    if (postingFrozen) {
        return t('ux.disabled.onboarding');
    }

    return blocked ? (
        <>
            {t('inventory.disabled_unresolved_posting')} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;
}
