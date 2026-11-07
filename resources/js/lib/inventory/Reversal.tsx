import type { ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { blockingRequest } from '@/lib/commands/families';
import { useUnresolved } from '@/lib/commands/unresolved';
import { rowLinkName } from '@/lib/costing/Parts';
import { t } from '@/lib/i18n';
import { REQUESTS_PAGE, REVERSAL_COMMAND, reversalPage, type ReversalOpener } from '@/lib/inventory/stock';

// The parts that UXP-411 and the pages that lead to it share (TASK-026).

/**
 * Why "Batalkan mutasi" cannot be used now (UXS-13; COPY_DECK section 15): an unresolved reversal of
 * the user, with the way to UXP-006 (NC-13).
 */
export function unresolvedReversalReason(): ReactNode {
    return (
        <>
            {t('inventory.reversal.disabled_unresolved')} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
}

/**
 * Why a movement cannot be reversed now, or undefined (INTERACTION_INVENTORY UXI-227, UXI-250,
 * UXI-266; UXS-13, UXS-27): already reversed (BR-INV-35); an unresolved reversal of the user (NC-13);
 * a RECEIPT or a MANUAL_ISSUE while the onboarding freeze is on (BR-INV-24) — an OPENING may be
 * reversed then too (D-087).
 */
export function reversalBlocked(type: string, reversed: boolean, unresolved: boolean, frozen: boolean): ReactNode {
    if (reversed) {
        return t('inventory.reversal.disabled_done');
    }

    if (unresolved) {
        return unresolvedReversalReason();
    }

    return frozen && type !== 'OPENING' ? t('ux.disabled.onboarding') : undefined;
}

/**
 * "Batalkan mutasi", which opens UXP-411 for the movement with the page that opened it (UXI-227,
 * UXI-250, UXI-266): the Owner's alone, and disabled with its reason while it cannot be used
 * (reversalBlocked). `rowName` names the row of a list in the accessible name.
 */
export function ReversalButton({ movementId, type, reversed, frozen, opener, rowName }: { movementId: number; type: string; reversed: boolean; frozen: boolean; opener: ReversalOpener; rowName?: string }) {
    const unresolved = useUnresolved();
    const label = t('inventory.reversal.title');
    const blocked = reversalBlocked(type, reversed, blockingRequest(unresolved.union, REVERSAL_COMMAND.operation, REVERSAL_COMMAND.family) !== null, frozen);

    if (blocked !== undefined) {
        return <Button label={label} {...(rowName === undefined ? {} : { rowName })} variant={'secondary'} enterActivates={false} disabledReason={blocked} />;
    }

    // A row's link names its row after its visible text (WCAG 2.5.3).
    if (rowName !== undefined) {
        return (
            <a className={'bk-table-link'} href={reversalPage(movementId, opener)} aria-label={rowLinkName(label, rowName)}>
                {label}
            </a>
        );
    }

    return <ButtonLink href={reversalPage(movementId, opener)} label={label} variant={'secondary'} />;
}
