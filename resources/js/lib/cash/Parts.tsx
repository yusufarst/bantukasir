import { Button, ButtonLink, type ButtonVariant } from '@/components/ui/Button';
import { StatusWord } from '@/components/ui/Status';
import { CLOSE_SESSION_COMMAND, CLOSE_SESSION_PAGE, UNRESOLVED_PAGE, varianceOf, varianceTone } from '@/lib/cash/sessions';
import { blockingRequest } from '@/lib/commands/families';
import { useUnresolved } from '@/lib/commands/unresolved';
import { formatVariance } from '@/lib/format';
import { t } from '@/lib/i18n';

// The parts that the close and the session pages share (COPY_DECK sections 1.4 and 15; TASK-030).

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
