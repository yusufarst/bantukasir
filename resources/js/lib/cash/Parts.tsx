import { Button, ButtonLink, type ButtonVariant } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { StatusWord } from '@/components/ui/Status';
import { CLOSE_SESSION_COMMAND, CLOSE_SESSION_PAGE, UNRESOLVED_PAGE, varianceOf, varianceTone, type PreviewRefund } from '@/lib/cash/sessions';
import { blockingRequest } from '@/lib/commands/families';
import { useUnresolved } from '@/lib/commands/unresolved';
import { formatDateTime, formatMoney, formatVariance } from '@/lib/format';
import { t } from '@/lib/i18n';

// The parts that the close and the session pages share (COPY_DECK sections 1.4 and 15; TASK-030).

/**
 * The drawer-funded refunds of the session in a close preview (UXI-101 on UXP-105, UXI-149 on UXP-207;
 * COPY_DECK section 7.5; KS-07; WF-08 step 1; D-068; TASK-034): each with its amount, its Sale's number and
 * its time, under the reminder that its cash should no longer be in the drawer; nothing when the session
 * paid none. The reminder is no fact (D-068).
 */
export function PreviewRefunds({ refunds }: { refunds: readonly PreviewRefund[] }) {
    if (refunds.length === 0) {
        return null;
    }

    return (
        <Section title={t('cash.close.drawer_refunds')} level={3} card={false}>
            <div className={'bk-stack'}>
                <p className={'bk-hint'}>{t('cash.close.drawer_refunds_reminder')}</p>
                <ul className={'bk-stack'} role={'list'}>
                    {refunds.map((refund) => (
                        <li key={refund.saleNumber + refund.recordedAt}>{t('cash.close.drawer_refund_row', { uang: formatMoney(refund.amount), nomor: refund.saleNumber, waktu: formatDateTime(refund.recordedAt) })}</li>
                    ))}
                </ul>
            </div>
        </Section>
    );
}

/**
 * "Tutup sesi kas", the entry of the close (UXI-077 in the till's header, UXI-139 on UXP-206): a link to
 * RP-105, disabled with its reason and the way to UXP-006 while this user holds an unresolved close
 * request (NC-13 (a); COPY_DECK section 15, `cash.close.disabled_unresolved`). With the label and page of
 * the Owner-assisted close, "Tutup sesi oleh Pemilik" (UXI-140 on UXP-206, UXI-340 on UXP-604; TASK-032),
 * whose RC-04 is of the same family.
 */
export function CloseSessionEntry({ variant, href = CLOSE_SESSION_PAGE, label = t('cash.close.title') }: { variant: ButtonVariant; href?: string; label?: string }) {
    const unresolved = useUnresolved();

    if (blockingRequest(unresolved.union, CLOSE_SESSION_COMMAND.operation, CLOSE_SESSION_COMMAND.family) === null) {
        return <ButtonLink href={href} label={label} variant={variant} />;
    }

    const reason = (
        <>
            {t('cash.close.disabled_unresolved')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );

    return <Button label={label} variant={variant} enterActivates={false} disabledReason={reason} />;
}

/**
 * A variance with its sign and its word, "−Rp6.500 (kurang)" (COPY_DECK section 1.4; KS-07): from
 * a count, physical cash minus expected cash, or as a report recorded it. Its meaning never rests on
 * colour or sign alone (UXA-16, UXA-18).
 */
export function VarianceWord({ physical, expected, recorded }: { physical?: string; expected?: string; recorded?: string }) {
    const variance = recorded ?? (physical === undefined || expected === undefined ? null : varianceOf(physical, expected));

    if (variance === null) {
        return null;
    }

    return <StatusWord tone={varianceTone(variance)} label={formatVariance(variance)} />;
}
