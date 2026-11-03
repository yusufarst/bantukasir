import type { ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { blockingRequest } from '@/lib/commands/families';
import { useUnresolved } from '@/lib/commands/unresolved';
import { COST_CORRECTION_COMMAND, correctionPage, correctionTitle, type CorrectionEntry, type CorrectionOpener, type CostValue } from '@/lib/costing/costs';
import { formatCost, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { REQUESTS_PAGE } from '@/lib/inventory/stock';

// The parts that the cost pages and the pages that lead to UXP-504 share (TASK-025).

/**
 * Why "Koreksi biaya" or "Lengkapi biaya" cannot be used now (UXS-13; COPY_DECK section 15): an
 * unresolved cost correction or completion of the user, with the way to UXP-006 (NC-13).
 */
export function unresolvedCorrectionReason(): ReactNode {
    return (
        <>
            {t('costing.disabled_unresolved')} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
}

/**
 * "Koreksi biaya", or "Lengkapi biaya" while the cost is UNKNOWN, which opens UXP-504 for the cost
 * source with the page that opened it (UXI-226, UXI-249, UXI-314, UXI-324): the Owner's alone, and
 * disabled with its reason while the user holds an unresolved cost correction or completion request
 * (UXS-13; NC-13). `rowName` names the row of a list in the accessible name.
 */
export function CorrectionButton({ evidenceId, cost, opener, rowName, variant = 'secondary' }: { evidenceId: number; cost: CostValue; opener: CorrectionOpener; rowName?: string; variant?: 'primary' | 'secondary' }) {
    const unresolved = useUnresolved();
    const label = t(correctionTitle(cost));
    const blocked = blockingRequest(unresolved.union, COST_CORRECTION_COMMAND.operation, COST_CORRECTION_COMMAND.family) !== null;

    if (blocked) {
        return <Button label={label} {...(rowName === undefined ? {} : { rowName })} variant={variant} enterActivates={false} disabledReason={unresolvedCorrectionReason()} />;
    }

    // A row's link names its row after its visible text (WCAG 2.5.3).
    if (rowName !== undefined) {
        return (
            <a className={'bk-table-link'} href={correctionPage(evidenceId, opener)} aria-label={`${label} ${rowName}`}>
                {label}
            </a>
        );
    }

    return <ButtonLink href={correctionPage(evidenceId, opener)} label={label} variant={variant} />;
}

/**
 * One correction as COPY_DECK `costing.field.correction_entry` writes it: the old value — "Biaya perlu
 * dilengkapi pemilik" for a completion, never Rp0 (UXS-24) — the new one, the time, who and why.
 */
export function correctionText(entry: CorrectionEntry): string {
    return t('costing.field.correction_entry', {
        lama: entry.oldAmount === null ? t('costing.status.unknown') : formatCost(entry.oldAmount),
        baru: formatCost(entry.newAmount),
        waktu: formatDateTime(entry.recordedAt),
        nama: entry.actorDisplayName,
        alasan: entry.reason,
    });
}

/**
 * The Owner's correction history of a cost source (UXI-247; COPY_DECK `costing.field.correction_entry`),
 * newest first: the old value — "Biaya perlu dilengkapi pemilik" for a completion — the new one, the
 * time, who and why. Nothing when the source has no correction.
 */
export function CorrectionHistory({ title, entries, level = 3 }: { title: string; entries: readonly CorrectionEntry[]; level?: 2 | 3 }) {
    if (entries.length === 0) {
        return null;
    }

    return (
        <Section title={title} level={level} card={false}>
            <ul className={'bk-stack'}>
                {entries.map((entry) => (
                    <li key={entry.correctionNo} className={'bk-meta'}>
                        {correctionText(entry)}
                    </li>
                ))}
            </ul>
        </Section>
    );
}
