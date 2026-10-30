import { useEffect, useRef, useState, type ReactNode } from 'react';
import { t } from '@/lib/i18n';
import { Button } from './Button';
import { Icon } from './Icon';
import { Notice } from './Notice';
import { STATE_CATALOGUE, type StateId } from './stateCatalogue';

// The presentations of the states of UI_STATES section 2 (DESIGN_SYSTEM
// section 9). UNKNOWN is never zero, and a failed or stale read is never shown
// as zero, empty or "Normal" (UI_STATES section 1).

/**
 * A notice of a state of the catalogue, with its COPY_DECK title and body.
 * A body with placeholders, or the page's own text, is given as `body`.
 */
export function StateNotice({ state, body, actions, children }: { state: StateId; body?: ReactNode; actions?: ReactNode; children?: ReactNode }) {
    const entry = STATE_CATALOGUE[state];
    const text = body ?? (entry.body === undefined ? undefined : t(entry.body));

    return (
        <Notice tone={entry.tone} urgency={entry.urgency} title={t(entry.title)} actions={actions}>
            {text === undefined && children === undefined ? undefined : (
                <>
                    {text !== undefined && <p>{text}</p>}
                    {children}
                </>
            )}
        </Notice>
    );
}

/**
 * UXS-01: skeleton blocks in the shape of the content and, for assistive
 * technology, "Memuat data"; after about one second "Memuat" is shown too.
 * Never a number, zero or an empty list.
 */
export function Loading({ lines = 3, children }: { lines?: number; children?: ReactNode }) {
    const [late, setLate] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => setLate(true), 1000);

        return () => clearTimeout(timer);
    }, []);

    const widths = ['60', '40', '30'] as const;

    return (
        <div className={'bk-skel-group'} role={'status'} aria-busy={true} data-state={'loading'}>
            <span className={'bk-sr-only'}>{t('ux.state.loading.body')}</span>
            {children ??
                Array.from({ length: lines }, (_, index) => <span key={index} className={'bk-skel'} data-width={widths[index % widths.length]} aria-hidden={true} />)}
            {late && (
                <span className={'bk-meta'} aria-hidden={true}>
                    {t('ux.state.loading.title')}
                </span>
            )}
        </div>
    );
}

/** UXS-02: what will appear here, and the page's primary action when the role holds it. */
export function EmptyState({ body, action }: { body: ReactNode; action?: ReactNode }) {
    return (
        <div className={'bk-empty'} data-state={'empty'}>
            <p className={'bk-empty__title'}>{t('ux.state.empty.title')}</p>
            <p>{body}</p>
            {action}
        </div>
    );
}

/** UXS-03: the search or filters returned nothing; "Hapus filter" clears them. */
export function NoResults({ onClear }: { onClear: () => void }) {
    return (
        <div className={'bk-empty'} role={'status'} data-state={'no_results'}>
            <p className={'bk-empty__title'}>{t('ux.state.no_results.title')}</p>
            <p>{t('ux.state.no_results.body')}</p>
            <Button label={t('ux.action.clear_filters')} variant={'secondary'} onClick={onClear} />
        </div>
    );
}

/** UXS-04: the success notice on the destination, naming what was saved. */
export function Committed({ message }: { message: string }) {
    return (
        <Notice tone={'success'} urgency={'status'} title={t('ux.state.committed.title')}>
            <p>{message}</p>
        </Notice>
    );
}

export interface FieldError {
    /** The id of the control, which the summary links to. */
    readonly fieldId: string;
    readonly label: string;
    readonly message: string;
}

/** A message of the summary, which moves the focus to its field. */
function ErrorLink({ error }: { error: FieldError }) {
    const href = `#${error.fieldId}`;

    return (
        <li>
            <a
                href={href}
                onClick={(event) => {
                    event.preventDefault();
                    document.getElementById(error.fieldId)?.focus();
                }}
            >
                {error.label}
            </a>
            <span>{error.message}</span>
        </li>
    );
}

/**
 * UXS-05: the summary at the top of the form, which takes focus; each message
 * also stands beside its field. Nothing was saved.
 */
export function ErrorSummary({ errors }: { errors: readonly FieldError[] }) {
    const ref = useRef<HTMLDivElement | null>(null);

    // The focus moves to the summary when its messages change, not on every render.
    const messages = errors.map((error) => `${error.fieldId}:${error.message}`).join('|');

    useEffect(() => {
        ref.current?.focus();
    }, [messages]);

    return (
        <div className={'bk-error-summary'} ref={ref} tabIndex={-1}>
            <Notice tone={'danger'} urgency={'alert'} title={t('ux.state.validation.title')}>
                <p>{t('ux.state.validation.body')}</p>
                {errors.length > 0 && (
                    <ul>
                        {errors.map((error) => (
                            <ErrorLink key={error.fieldId} error={error} />
                        ))}
                    </ul>
                )}
            </Notice>
        </div>
    );
}

/** UXS-06: after a few seconds of sending, "Masih diproses. Tunggu sebentar." */
export function StillSubmitting({ active, afterMs = 4000 }: { active: boolean; afterMs?: number }) {
    const [late, setLate] = useState(false);

    useEffect(() => {
        if (!active) {
            setLate(false);

            return undefined;
        }

        const timer = setTimeout(() => setLate(true), afterMs);

        return () => clearTimeout(timer);
    }, [active, afterMs]);

    return (
        <p className={'bk-meta'} role={'status'}>
            {active && late ? t('ux.state.submitting.still') : null}
        </p>
    );
}

/**
 * UXS-13 for a control that is not a Button: the reason next to it, tied to
 * it through `id`. When the reason is an unresolved request, the reason
 * carries "Buka Permintaan belum pasti" (NC-13).
 */
export function DisabledReason({ id, reason, unresolvedHref }: { id: string; reason: ReactNode; unresolvedHref?: string }) {
    return (
        <p className={'bk-reason'} id={id}>
            <Icon name={'info'} size={'sm'} />
            <span>
                {reason} {unresolvedHref !== undefined && <a href={unresolvedHref}>{t('ux.action.open_unresolved')}</a>}
            </span>
        </p>
    );
}

export type RecoveryKind = 'uncertain' | 'idempotency_conflict' | 'epoch_stale';

const RECOVERY_STATE: Readonly<Record<RecoveryKind, StateId>> = {
    uncertain: 'UXS-14',
    idempotency_conflict: 'UXS-15',
    epoch_stale: 'UXS-15-epoch',
};

/**
 * UXS-14 and UXS-15: the request is frozen and shown as not yet known, never
 * as failed; the panel names what must not happen meanwhile (the family's
 * text of COPY_DECK section 14.1). An uncertain request offers "Kirim ulang"
 * — the same frozen request — and "Cek status"; a held one only "Cek status".
 */
export function RecoveryPanel({
    kind,
    mustNot,
    onResend,
    onCheckStatus,
    busy = false,
}: {
    kind: RecoveryKind;
    mustNot: ReactNode;
    onResend?: () => void;
    onCheckStatus: () => void;
    busy?: boolean;
}) {
    const entry = STATE_CATALOGUE[RECOVERY_STATE[kind]];

    return (
        <Notice
            tone={entry.tone}
            urgency={'alert'}
            title={t(entry.title)}
            actions={
                <>
                    {/* Enter never activates either (UXA-10; UXI-036, UXI-037; TASK-009). */}
                    {kind === 'uncertain' && onResend !== undefined && <Button label={t('ux.action.resend')} onClick={onResend} busy={busy} enterActivates={false} />}
                    <Button
                        label={t('ux.action.check_status')}
                        variant={kind === 'uncertain' && onResend !== undefined ? 'secondary' : 'primary'}
                        onClick={onCheckStatus}
                        busy={busy && (kind !== 'uncertain' || onResend === undefined)}
                        unavailable={busy}
                        enterActivates={false}
                    />
                </>
            }
        >
            {entry.body !== undefined && <p>{t(entry.body)}</p>}
            <p>
                <strong>{mustNot}</strong>
            </p>
        </Notice>
    );
}

/** UXS-21: the last data stay, with the time of their last refresh and "Muat ulang". */
export function StaleRead({ at, onReload }: { at: string; onReload: () => void }) {
    return (
        <Notice tone={'warning'} title={t('ux.state.stale_read.title')} actions={<Button label={t('ux.action.reload')} variant={'secondary'} onClick={onReload} />}>
            <p>{t('ux.state.stale_read.body', { waktu: at })}</p>
        </Notice>
    );
}

/** UXS-22: the area says it could not be loaded, never zero, with "Coba lagi". */
export function LoadFailed({ onRetry }: { onRetry: () => void }) {
    return (
        <Notice tone={'danger'} urgency={'status'} title={t('ux.state.load_failed.title')} actions={<Button label={t('ux.action.retry')} variant={'secondary'} onClick={onRetry} />}>
            <p>{t('ux.state.load_failed.body')}</p>
        </Notice>
    );
}

/** UXS-25: cost of goods sold, profit and margin are incomplete; the page adds the counts and its link. */
export function CostIncomplete({ children, action }: { children?: ReactNode; action?: ReactNode }) {
    return (
        <Notice tone={STATE_CATALOGUE['UXS-25'].tone} title={t('ux.state.cost_incomplete.title')} actions={action}>
            <p>{t('ux.state.cost_incomplete.body')}</p>
            {children}
        </Notice>
    );
}

/** UXS-33: the code stays in the field; no product is created. */
export function UnknownCode({ code, action }: { code: string; action?: ReactNode }) {
    return (
        <Notice tone={'warning'} urgency={'status'} title={t('ux.state.unknown_code.title')} actions={action}>
            <p>{t('ux.state.unknown_code.body', { kode: code })}</p>
        </Notice>
    );
}

/** UXS-34: the final action stays disabled and the missing amount is shown. */
export function TenderLow({ missing }: { missing: string }) {
    return (
        <Notice tone={'warning'} title={t('ux.state.tender_low.title')}>
            <p>{t('ux.state.tender_low.body', { uang: missing })}</p>
        </Notice>
    );
}

export type MarkKind = 'cost_unknown' | 'owner_corrected' | 'inactive' | 'unverified';

/**
 * A mark in place of a value or beside a record: UXS-24 "Biaya perlu
 * dilengkapi pemilik" in place of a cost, never Rp0 or blank; UXS-26
 * "Dikoreksi Pemilik"; UXS-32 "Nonaktif"; UXS-43 "Belum Terverifikasi", never
 * a green or passing look.
 */
export function StateMark({ kind }: { kind: MarkKind }) {
    switch (kind) {
        case 'cost_unknown':
            return (
                <span className={'bk-mark'} data-tone={'pending'} data-state={'cost_unknown'}>
                    <Icon name={'clock'} size={'sm'} />
                    <span>{t('ux.state.cost_unknown.title')}</span>
                </span>
            );
        case 'owner_corrected':
            return (
                <span className={'bk-pill'} data-tone={'info'} data-state={'owner_corrected'}>
                    <span>{t('ux.state.owner_corrected.title')}</span>
                </span>
            );
        case 'inactive':
            return (
                <span className={'bk-status'} data-tone={'inactive'} data-state={'inactive'}>
                    <span className={'bk-status__dot'} aria-hidden={true} />
                    <span>{t('ux.state.inactive.label')}</span>
                </span>
            );
        case 'unverified':
            return (
                <span className={'bk-mark'} data-tone={'neutral'} data-state={'unverified'}>
                    <Icon name={'info'} size={'sm'} />
                    <span>{t('ux.state.unverified.label')}</span>
                </span>
            );
    }
}
