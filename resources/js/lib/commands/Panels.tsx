import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, PasswordInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import { Committed, RecoveryPanel, StateNotice, StillSubmitting } from '@/components/ui/States';
import type { StateId } from '@/components/ui/stateCatalogue';
import { retrySeconds } from '@/lib/answers';
import { throttledBody } from '@/lib/authAnswer';
import { requestJson, STILL_AFTER_MS } from '@/lib/commands/client';
import { committedText, familyMustNot, isLabelledFamily } from '@/lib/commands/families';
import type { CheckNote, CommandControl } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';

// The shared panels of a command (UXP-000; INTERACTION_INVENTORY UXI-031 to
// UXI-033, UXI-036 and UXI-037; UI_STATES UXS-04, UXS-06, UXS-10 to UXS-16
// and section 4; COPY_DECK sections 12 and 14; INTERACTION_CONTRACTS sections
// 1.3 and 1.5; TASK-009 scope item 4): what a page shows beside its final
// action for each client state — "Masih diproses" while it is sent, the
// uncertain panel with "Kirim ulang" and "Cek status" (UXS-14), the held one
// with "Cek status" only (UXS-15), the results of a status check (COPY_DECK
// section 14.2), and the recent-authentication dialog after a 423, which then
// sends the same frozen request. The critical families' pages give their own
// panel with the same parts (ADR-007 section 1).

/** The states the panels show themselves. */
const PANEL_STATES = { reload: 'UXS-10', throttled: 'UXS-12', refused: 'UXS-42-confirmation', lost: 'UXS-20' } as const satisfies Readonly<Record<string, StateId>>;

/** The states of the refusals that every page shows the same way; the others are the page's own (UI_STATES section 3). */
const SHARED_REFUSALS: Readonly<Record<string, StateId>> = {
    INTENT_ABANDONED: 'UXS-16',
    FORBIDDEN: 'UXS-07',
    NOT_FOUND: 'UXS-08',
    VALIDATION_FAILED: 'UXS-05',
};

function noteText(note: CheckNote | null): string | null {
    if (note === 'no_answer') {
        return t('commands.resolution.no_answer');
    }

    return note === 'in_progress' ? t('commands.resolution.in_progress') : null;
}

/** The family's text of what must not happen meanwhile (COPY_DECK section 14.1). */
function mustNotOf(family: string): ReactNode {
    return isLabelledFamily(family) ? familyMustNot(family) : null;
}

/**
 * The panels of a page's command for its current state. `committed` is the
 * page's own success text when the command committed; a status check that
 * concluded COMMITTED says "Sudah tersimpan." (COPY_DECK section 14.2).
 */
export function CommandPanels({ control, committed }: { control: CommandControl; committed?: string }) {
    const { phase, target } = control;
    const family = target?.family ?? '';

    switch (phase.kind) {
        case 'submitting':
            return <StillSubmitting active={true} afterMs={STILL_AFTER_MS} />;
        case 'uncertain':
        case 'held': {
            const note = noteText(phase.note);
            const kind = phase.kind === 'uncertain' ? 'uncertain' : phase.code === 'EPOCH_STALE' ? 'epoch_stale' : 'idempotency_conflict';

            return (
                <div className={'bk-stack'}>
                    {phase.kind === 'uncertain' && phase.throttled !== undefined && <StateNotice state={PANEL_STATES.throttled} body={throttledBody(phase.throttled)} />}
                    <RecoveryPanel
                        kind={kind}
                        mustNot={mustNotOf(family)}
                        onResend={phase.kind === 'uncertain' && control.canResend ? control.resend : undefined}
                        onCheckStatus={control.check}
                        busy={phase.checking}
                    />
                    {phase.checking && <StillSubmitting active={true} afterMs={STILL_AFTER_MS} />}
                    {note !== null && (
                        <p className={'bk-meta'} role={'status'}>
                            {note}
                        </p>
                    )}
                </div>
            );
        }
        case 'committed':
            return <Committed message={committed ?? committedText(target?.operation ?? '')} />;
        case 'not_committed':
            return (
                <Notice tone={'neutral'} urgency={'status'}>
                    <p>{t('commands.resolution.not_committed')}</p>
                </Notice>
            );
        case 'rejected': {
            const state = SHARED_REFUSALS[phase.refusal.code];

            return state === undefined ? null : <StateNotice state={state} />;
        }
        case 'reload':
            return <StateNotice state={PANEL_STATES.reload} actions={<Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} />} />;
        case 'throttled':
            return <StateNotice state={PANEL_STATES.throttled} body={throttledBody(phase.seconds)} />;
        case 'recent_auth':
            return <RecentAuthDialog onConfirmed={control.confirmed} onCancel={control.cancel} />;
        case 'idle':
            return null;
    }
}

type ConfirmProblem = 'refused' | 'reload' | 'lost' | { readonly seconds: bigint };

/**
 * The recent-authentication dialog of UXP-000 (UXS-11; UXI-031 the password,
 * UXI-032 "Konfirmasi", UXI-033 "Batal"): the password is sent to RA-03; on
 * success the same frozen request is sent; a refused password is one uniform
 * message (UXS-42, TR-ERR-04); "Batal" returns to the review with nothing
 * sent. Enter submits it, as every authentication form (UXA-10 exception).
 * The password is never kept after an answer (SEC-CH-03).
 */
export function RecentAuthDialog({ onConfirmed, onCancel }: { onConfirmed: () => void; onCancel: () => void }) {
    const [password, setPassword] = useState('');
    const [sending, setSending] = useState(false);
    const [problem, setProblem] = useState<ConfirmProblem | null>(null);

    useEffect(() => () => setPassword(''), []);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (sending || password === '') {
            return;
        }

        setSending(true);
        setProblem(null);

        void requestJson('POST', '/user/confirm-password', { password }, { waitMs: 30_000 }).then((arrived) => {
            setSending(false);
            setPassword('');

            if (arrived !== null && (arrived.status === 200 || arrived.status === 201 || arrived.status === 204)) {
                onConfirmed();
            } else if (arrived?.status === 422) {
                setProblem('refused');
            } else if (arrived?.status === 429) {
                setProblem({ seconds: retrySeconds(arrived.retryAfter) });
            } else if (arrived?.status === 419) {
                setProblem('reload');
            } else {
                setProblem('lost');
            }
        });
    };

    const formId = 'bk-recent-auth';

    return (
        <Dialog
            open={true}
            onOpenChange={(open) => (open ? undefined : onCancel())}
            title={t('ux.state.recent_auth.title')}
            description={<p>{t('ux.state.recent_auth.body')}</p>}
            dismissible={!sending}
            actions={
                <>
                    <Button label={t('ux.action.cancel')} variant={'secondary'} onClick={onCancel} unavailable={sending} />
                    <Button label={t('ux.state.recent_auth.confirm')} type={'submit'} form={formId} busy={sending} />
                </>
            }
        >
            <form id={formId} className={'bk-form'} noValidate={true} onSubmit={submit}>
                {problem === 'refused' && <StateNotice state={PANEL_STATES.refused} />}
                {problem === 'reload' && <StateNotice state={PANEL_STATES.reload} />}
                {problem === 'lost' && <StateNotice state={PANEL_STATES.lost} />}
                {problem !== null && typeof problem === 'object' && <StateNotice state={PANEL_STATES.throttled} body={throttledBody(problem.seconds)} />}
                <Field label={t('ux.state.recent_auth.password')} required={true}>
                    <PasswordInput value={password} onValueChange={setPassword} autoComplete={'current-password'} data-initial-focus={true} />
                </Field>
            </form>
        </Dialog>
    );
}
