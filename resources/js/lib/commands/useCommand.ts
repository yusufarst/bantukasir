import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { leaveSignedInSession } from '@/lib/browserSession';
import type { CommandResult, HandedLink, HeldCode, Refusal } from '@/lib/commands/answers';
import { announceSettled, checkStatus, freeze, recheckDelay, requestJson, sendCommand, type CommandDefinition, type FrozenRequest } from '@/lib/commands/client';
import { blockingRequest } from '@/lib/commands/families';
import { keepReference, releaseReference } from '@/lib/commands/references';
import { currentUnresolved, forgetReceipt, refreshReceipts, useUnresolved, type UnresolvedRequest } from '@/lib/commands/unresolved';

// The client states of a page's command (INTERACTION_CONTRACTS sections 1.3,
// 1.5, 1.7 and 2.1; NAVIGATION_CONTRACTS NC-13 (a), (b) and (e); ADR-007
// section 5; UI_STATES UXS-06, UXS-11 and UXS-14 to UXS-16; TASK-009 scope
// items 1, 3 and 4). The final action freezes the request and sends it; a
// second press, tap or Enter while it is SUBMITTING sends nothing. The
// reference is kept before the request leaves and released only when the
// server reports its intent resolved, or that nothing ran. While the user has
// an unresolved request of the page's family, the page offers no new final
// action and opens on that request's panel (UXS-14 or UXS-15).

/** What the panel shows after a status check that did not conclude. */
export type CheckNote = 'no_answer' | 'in_progress';

export type CommandPhase =
    | { readonly kind: 'idle' }
    | { readonly kind: 'submitting' }
    /** `link`: the one-time link of RC-35 or RC-41, in the answer that committed it only; a repeat or a status check has none. */
    | { readonly kind: 'committed'; readonly result: CommandResult | null; readonly link?: HandedLink }
    | { readonly kind: 'rejected'; readonly refusal: Refusal }
    /** `throttled`: a repeat of the request was refused by 429 (UXS-12) while the earlier attempt stays uncertain. */
    | { readonly kind: 'uncertain'; readonly checking: boolean; readonly note: CheckNote | null; readonly throttled?: bigint }
    | { readonly kind: 'held'; readonly code: HeldCode; readonly checking: boolean; readonly note: CheckNote | null }
    | { readonly kind: 'not_committed' }
    | { readonly kind: 'recent_auth' }
    | { readonly kind: 'reload' }
    | { readonly kind: 'throttled'; readonly seconds: bigint };

/** The request a page's phase is about: the frozen one of this page, or an unresolved one it opened on (NC-13 (b)). */
export interface CommandTarget {
    readonly operation: string;
    readonly family: string;
    readonly sourceIntentId: string;
    readonly idempotencyKey: string;
    readonly recoveryEpoch: string | null;
    /** The frozen request, with its payload, while this page holds it; null after a reload. */
    readonly frozen: FrozenRequest | null;
}

export interface CommandControl {
    readonly phase: CommandPhase;
    readonly target: CommandTarget | null;
    /** The user's unresolved request that blocks a new command of this family (NC-13 (a)), or null. */
    readonly blockedBy: UnresolvedRequest | null;
    /**
     * Whether the phase is about this page's own frozen request. A page may have
     * adopted another request of its family (NC-13 (b)), of another operation:
     * its success text and its result page are not that request's.
     */
    readonly ownRequest: boolean;
    /** Whether "Kirim ulang" can send the same frozen request: only while this page holds its payload. */
    readonly canResend: boolean;
    readonly submit: (payload: Readonly<Record<string, unknown>>, expected?: Readonly<Record<string, unknown>>) => void;
    readonly resend: () => void;
    readonly check: () => void;
    /** After RA-03 succeeded: the same frozen request is sent (UXS-11). */
    readonly confirmed: () => void;
    /** "Batal" of the recent-authentication dialog: back to the review, nothing sent. */
    readonly cancel: () => void;
    /** After a refusal or NOT_COMMITTED: the draft is editable again; a new confirmation is a new intent. */
    readonly startAgain: () => void;
}

interface SharedProps {
    readonly auth: { readonly user: { readonly id: number } } | null;
    readonly recoveryEpoch: string | null;
    readonly [key: string]: unknown;
}

function targetOf(frozen: FrozenRequest): CommandTarget {
    return { ...frozen, frozen };
}

/** The phase a page opens on for an unresolved request it did not send itself (NC-13 (b)). */
function adoptedPhase(request: UnresolvedRequest): CommandPhase {
    if (request.receipt?.outcome === 'HELD') {
        return { kind: 'held', code: request.receipt.epoch === 'earlier' ? 'EPOCH_STALE' : 'IDEMPOTENCY_CONFLICT', checking: false, note: null };
    }

    return { kind: 'uncertain', checking: false, note: null };
}

/** Whether the confirmation of SEC-SESS-05 is still valid (RA-04); unknown counts as not. */
async function recentlyConfirmed(): Promise<boolean> {
    const arrived = await requestJson('GET', '/user/confirmed-password-status', undefined, { waitMs: 30_000 });
    const body = arrived?.body;

    return arrived?.status === 200 && typeof body === 'object' && body !== null && 'confirmed' in body && body.confirmed === true;
}

export function useCommand(command: CommandDefinition & { readonly recentAuth?: boolean }): CommandControl {
    const props = usePage<SharedProps>().props;
    const userId = props.auth?.user.id ?? null;
    const epoch = props.recoveryEpoch;
    const unresolved = useUnresolved();
    const [phase, setPhase] = useState<CommandPhase>({ kind: 'idle' });
    const [target, setTarget] = useState<CommandTarget | null>(null);
    const busy = useRef(false);
    /**
     * The intent of this page's frozen request once an attempt of it may have
     * reached the server (an uncertain or held answer): a later attempt that
     * runs nothing never releases its reference (SEC-CH-05; ADR-007 section 5).
     */
    const reached = useRef<string | null>(null);
    const rechecks = useRef<{ timer: number | null; done: number }>({ timer: null, done: 0 });
    const live = useRef(true);

    const blockedBy = useMemo(() => blockingRequest(unresolved.union, command.operation, command.family), [unresolved.union, command.operation, command.family]);
    const ownRequest = target !== null && target.frozen !== null;

    useEffect(() => {
        live.current = true;
        // The page of a family reads the user's receipts when it opens (section 1.4).
        void refreshReceipts(true);

        return () => {
            live.current = false;

            if (rechecks.current.timer !== null) {
                window.clearTimeout(rechecks.current.timer);
            }
        };
    }, []);

    // NC-13 (b): opened while a request of the family is unresolved, the page
    // shows that request's panel, never a new draft.
    useEffect(() => {
        if (phase.kind === 'idle' && target === null && blockedBy !== null) {
            setTarget({ ...blockedBy, frozen: null });
            setPhase(adoptedPhase(blockedBy));
        }
    }, [phase.kind, target, blockedBy]);

    // A request this page only adopted (NC-13 (b)), which another tab of the
    // browser or another device then resolved: its panel has nothing left to
    // do, so the page returns to its draft (OPEN_ITEMS T-34 (f), TASK-010
    // finding F-11). A check of this page's own is settled by that check.
    const adoptedGone = blockedBy === null && target !== null && target.frozen === null && (phase.kind === 'uncertain' || phase.kind === 'held') && !phase.checking;

    useEffect(() => {
        if (adoptedGone) {
            // A repeat of a status check still waiting would bring the panel back.
            if (rechecks.current.timer !== null) {
                window.clearTimeout(rechecks.current.timer);
                rechecks.current.timer = null;
            }

            setTarget(null);
            setPhase({ kind: 'idle' });
        }
    }, [adoptedGone]);

    const settle = useCallback(
        (request: { operation: string; sourceIntentId: string }) => {
            if (userId !== null) {
                releaseReference(userId, request);
            }

            forgetReceipt(request);
            announceSettled();
        },
        [userId],
    );

    const send = useCallback(
        async (frozen: FrozenRequest): Promise<void> => {
            if (userId === null) {
                return;
            }

            busy.current = true;
            setTarget(targetOf(frozen));
            setPhase({ kind: 'submitting' });
            keepReference(userId, frozen);
            const answer = await sendCommand(frozen);
            busy.current = false;

            if (!live.current) {
                return;
            }

            switch (answer.kind) {
                case 'committed':
                    settle(frozen);
                    setPhase({ kind: 'committed', result: answer.result, ...(answer.link === undefined ? {} : { link: answer.link }) });

                    return;
                case 'rejected':
                    settle(frozen);
                    setPhase({ kind: 'rejected', refusal: answer.refusal });

                    return;
                case 'uncertain':
                    reached.current = frozen.sourceIntentId;
                    setPhase({ kind: 'uncertain', checking: false, note: null });

                    return;
                case 'held':
                    reached.current = frozen.sourceIntentId;
                    setPhase({ kind: 'held', code: answer.code, checking: false, note: null });

                    return;
                case 'not_run': {
                    // This attempt ran nothing. Its reference is released only
                    // when no earlier attempt of the same intent may have
                    // reached the server; otherwise the request stays
                    // unresolved, with "Kirim ulang" and "Cek status" (UXS-14),
                    // and across a sign-out (SEC-CH-05; review finding R-01).
                    const earlier = reached.current === frozen.sourceIntentId;

                    if (!earlier) {
                        releaseReference(userId, frozen);
                    }

                    if (answer.reason === 'signed_out') {
                        leaveSignedInSession();
                    } else if (answer.reason === 'reload') {
                        // After the reload the page opens on the request's
                        // panel when the reference is kept (NC-13 (b)).
                        setPhase({ kind: 'reload' });
                    } else if (answer.reason === 'recent_auth') {
                        setPhase({ kind: 'recent_auth' });
                    } else if (earlier) {
                        setPhase({ kind: 'uncertain', checking: false, note: null, throttled: answer.seconds });
                    } else {
                        setPhase({ kind: 'throttled', seconds: answer.seconds });
                    }
                }
            }
        },
        [userId, settle],
    );

    const submit = useCallback(
        (payload: Readonly<Record<string, unknown>>, expected: Readonly<Record<string, unknown>> = {}): void => {
            // A second press, tap or Enter while a request is out sends nothing
            // (BR-SALE-18; INTERACTION_CONTRACTS section 1.7).
            if (busy.current || userId === null || epoch === null) {
                return;
            }

            if (phase.kind === 'throttled' && target?.frozen !== null && target !== null) {
                void send(target.frozen);

                return;
            }

            // A request this page only adopted that committed is not this draft, and
            // its recovery ended the block (NC-13 (e)): the draft may send its own.
            const draftOpen = phase.kind === 'idle' || phase.kind === 'rejected' || phase.kind === 'not_committed' || (phase.kind === 'committed' && !ownRequest);

            if (!draftOpen || blockedBy !== null) {
                return;
            }

            busy.current = true;
            setPhase({ kind: 'submitting' });

            void (async () => {
                // Read the user's receipts again before the request leaves: a
                // request of the family sent from another device blocks it
                // (NC-13); a failed read leaves the block to what is known.
                await refreshReceipts(true);
                busy.current = false;

                if (!live.current) {
                    return;
                }

                if (blockingRequest(currentUnresolved().union, command.operation, command.family) !== null) {
                    setTarget(null);
                    setPhase({ kind: 'idle' });

                    return;
                }

                const frozen = freeze(command, payload, expected, epoch);

                if (command.recentAuth === true && !(await recentlyConfirmed())) {
                    setTarget(targetOf(frozen));
                    setPhase({ kind: 'recent_auth' });

                    return;
                }

                await send(frozen);
            })();
        },
        [userId, epoch, phase.kind, target, ownRequest, blockedBy, command, send],
    );

    const resend = useCallback((): void => {
        if (!busy.current && target?.frozen !== null && target !== null && (phase.kind === 'uncertain' || phase.kind === 'throttled')) {
            void send(target.frozen);
        }
    }, [target, phase.kind, send]);

    const check = useCallback((): void => {
        if (target === null || epoch === null || (phase.kind !== 'uncertain' && phase.kind !== 'held') || phase.checking) {
            return;
        }

        const request = { operation: target.operation, sourceIntentId: target.sourceIntentId, recoveryEpoch: target.recoveryEpoch ?? epoch };
        // A check leaves the panel as it was, without an earlier 429's notice.
        const before = phase.kind === 'uncertain' ? ({ kind: 'uncertain', checking: false, note: null } as const) : phase;

        const run = async (background: boolean): Promise<void> => {
            setPhase({ ...before, checking: true, note: null });
            const answer = await checkStatus(request, background);

            if (!live.current) {
                return;
            }

            switch (answer.kind) {
                case 'committed':
                    settle(request);
                    setPhase({ kind: 'committed', result: answer.result });

                    return;
                case 'not_committed':
                    settle(request);
                    setPhase({ kind: 'not_committed' });

                    return;
                case 'epoch_stale':
                    setPhase({ kind: 'held', code: 'EPOCH_STALE', checking: false, note: null });

                    return;
                case 'in_progress': {
                    setPhase({ ...before, checking: false, note: 'in_progress' });
                    const delay = recheckDelay(rechecks.current.done);
                    rechecks.current.done += 1;

                    // Repeated as a background request while the page is visible (RT-10).
                    const repeat = (): void => {
                        if (!live.current) {
                            return;
                        }

                        if (document.visibilityState !== 'visible') {
                            rechecks.current.timer = window.setTimeout(repeat, delay);

                            return;
                        }

                        rechecks.current.timer = null;
                        void run(true);
                    };

                    rechecks.current.timer = window.setTimeout(repeat, delay);

                    return;
                }
                case 'not_run':
                    if (answer.reason === 'signed_out') {
                        leaveSignedInSession();

                        return;
                    }

                    setPhase({ ...before, checking: false, note: 'no_answer' });

                    return;
                case 'no_answer':
                    // The state stays as it was (D-084).
                    setPhase({ ...before, checking: false, note: 'no_answer' });
            }
        };

        rechecks.current.done = 0;

        if (rechecks.current.timer !== null) {
            window.clearTimeout(rechecks.current.timer);
            rechecks.current.timer = null;
        }

        void run(false);
    }, [target, epoch, phase, settle]);

    const confirmed = useCallback((): void => {
        if (phase.kind === 'recent_auth' && target?.frozen !== null && target !== null) {
            void send(target.frozen);
        }
    }, [phase.kind, target, send]);

    const cancel = useCallback((): void => {
        if (phase.kind !== 'recent_auth') {
            return;
        }

        // "Batal" of a repeat of an uncertain request returns to its panel;
        // of a first attempt, to the review with nothing sent.
        if (target !== null && reached.current === target.sourceIntentId) {
            setPhase({ kind: 'uncertain', checking: false, note: null });
        } else {
            setTarget(null);
            setPhase({ kind: 'idle' });
        }
    }, [phase.kind, target]);

    const startAgain = useCallback((): void => {
        // An adopted request that committed is another command's: the draft of this page goes on.
        if (phase.kind === 'rejected' || phase.kind === 'not_committed' || (phase.kind === 'committed' && !ownRequest)) {
            setTarget(null);
            setPhase({ kind: 'idle' });
        }
    }, [phase.kind, ownRequest]);

    return { phase, target, blockedBy, ownRequest, canResend: ownRequest, submit, resend, check, confirmed, cancel, startAgain };
}
