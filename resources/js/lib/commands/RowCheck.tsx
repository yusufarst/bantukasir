import { useCallback, useEffect, useRef, useState } from 'react';
import { StillSubmitting } from '@/components/ui/States';
import type { CommandResult } from '@/lib/commands/answers';
import { checkStatus, recheckDelay } from '@/lib/commands/client';
import { committedText } from '@/lib/commands/families';
import { leaveSignedInSession } from '@/lib/browserSession';
import { t } from '@/lib/i18n';

// The status check of one listed request (UXI-055, UXI-058 on UXP-006; UXI-149 on UXP-207, TASK-032):
// "Cek status" for one operation and intent, its answer, the automatic repeats while the original is
// still running — after 3, 6 and 12 seconds, then every 15 seconds while the page is visible — and the
// conclusion its page announces (INTERACTION_CONTRACTS section 2.1; COPY_DECK section 14.2). Moved here
// from UXP-006 by TASK-032, so that the Owner's check of a cashier's checkout on UXP-207 is the same.

/** No result page: the row's page names none. */
function noResultPage(): string | null {
    return null;
}

export type CheckView ={ readonly kind: 'idle' } | { readonly kind: 'checking' } | { readonly kind: 'in_progress' } | { readonly kind: 'no_answer' } | { readonly kind: 'epoch_stale' };

/**
 * The status check of one row: its view and "Cek status". A conclusive answer calls onResolved, then
 * onConcluded with its text and, for a committed request, the result page that resultPageOf gives.
 */
export function useRowCheck(
    request: { operation: string; sourceIntentId: string; recoveryEpoch: string | null },
    epoch: string | null,
    onConcluded: (text: string, href?: string | null) => void,
    onResolved: () => void,
    resultPageOf: (operation: string, result: CommandResult | null) => string | null = noResultPage,
) {
    const [view, setView] = useState<CheckView>({ kind: 'idle' });
    const timer = useRef<number | null>(null);
    const done = useRef(0);
    const live = useRef(true);

    useEffect(
        () => () => {
            live.current = false;

            if (timer.current !== null) {
                window.clearTimeout(timer.current);
            }
        },
        [],
    );

    const run = useCallback(
        async (background: boolean): Promise<void> => {
            const sentEpoch = request.recoveryEpoch ?? epoch;

            if (sentEpoch === null) {
                setView({ kind: 'no_answer' });

                return;
            }

            setView({ kind: 'checking' });
            const answer = await checkStatus({ operation: request.operation, sourceIntentId: request.sourceIntentId, recoveryEpoch: sentEpoch }, background);

            if (!live.current) {
                return;
            }

            switch (answer.kind) {
                case 'committed':
                    onResolved();
                    onConcluded(committedText(request.operation), resultPageOf(request.operation, answer.result));

                    return;
                case 'not_committed':
                    onResolved();
                    onConcluded(t('commands.resolution.not_committed'));

                    return;
                case 'epoch_stale':
                    setView({ kind: 'epoch_stale' });

                    return;
                case 'in_progress': {
                    setView({ kind: 'in_progress' });
                    const delay = recheckDelay(done.current);
                    done.current += 1;
                    const repeat = (): void => {
                        if (!live.current) {
                            return;
                        }

                        timer.current = document.visibilityState === 'visible' ? null : window.setTimeout(repeat, delay);

                        if (timer.current === null) {
                            void run(true);
                        }
                    };

                    timer.current = window.setTimeout(repeat, delay);

                    return;
                }
                case 'not_run':
                    if (answer.reason === 'signed_out') {
                        leaveSignedInSession();

                        return;
                    }

                    setView({ kind: 'no_answer' });

                    return;
                case 'no_answer':
                    setView({ kind: 'no_answer' });
            }
        },
        [request.operation, request.sourceIntentId, request.recoveryEpoch, epoch, onConcluded, onResolved, resultPageOf],
    );

    const check = (): void => {
        if (view.kind === 'checking') {
            return;
        }

        done.current = 0;

        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        void run(false);
    };

    return { view, check };
}

/** The note under a row after a check that did not conclude (COPY_DECK section 14.2). */
export function CheckNote({ view }: { view: CheckView }) {
    const text = view.kind === 'no_answer' ? t('commands.resolution.no_answer') : view.kind === 'in_progress' ? t('commands.resolution.in_progress') : null;

    return (
        <>
            {view.kind === 'checking' && <StillSubmitting active={true} />}
            <p className={'bk-meta'} role={'status'}>
                {text}
            </p>
        </>
    );
}
