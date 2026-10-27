// The client timers of the sign-in session (SECURITY_MODEL SEC-SESS-02,
// SEC-CH-04; UI_STATES UXS-23; ADAPTIVE_AND_ACCESSIBILITY UXA-21; TASK-007),
// as pure arithmetic on the browser's count of milliseconds (Date.now() of a
// timer), so that they can be tested without a browser. A change of the
// device's clock can only move the browser's view: earlier, and the server,
// whose sign-in session is still alive, sends the user home again; or later,
// and the server, which has ended it, answers the next request with the
// sign-in page.
//
// Every signed-in answer carries, in its shared props, the seconds left until
// the idle limit and until the absolute limit, counted by the server when it
// made the answer (App\DTO\Access\SignedInData). The browser counts them from
// the moment the answer arrived, which is never earlier than the moment the
// server counted them: so its deadlines are never before the server's, and
// when a timer ends the session the server has already ended it. A user
// activity request (RA-07) answered 204 restarts the idle limit from its
// answer. The browser's own clock never decides anything else.

/** The limits of the sign-in session, as the shared props carry them. */
export interface SessionTimes {
    readonly idleLimit: number;
    readonly idleEndsIn: number;
    readonly absoluteEndsAt: string;
    readonly absoluteEndsIn: number;
}

/** When each limit is reached, in the timer's milliseconds. */
export interface Deadlines {
    readonly idleAt: number;
    readonly absoluteAt: number;
}

/** UXA-21: the sign-in-ending dialog appears at least 60 seconds before the idle limit; here two minutes. */
export const WARNING_LEAD_SECONDS = 120;

/** UXA-21: the user is told when the 12-hour limit ends, from 15 minutes before it. */
export const ABSOLUTE_NOTICE_SECONDS = 15 * 60;

/** SEC-SESS-02: while the user types, at most one user-activity request a minute. */
export const ACTIVITY_INTERVAL_SECONDS = 60;

/**
 * SEC-CH-04: how long a tab whose page another tab's sign-out has hidden
 * waits for that tab to report the server's answer before it loads the
 * application's address anyway. Longer than the proxy's wait for the
 * application, 125 seconds (ENVIRONMENTS section 7), itself above the
 * application's limit of 60 seconds a request (TECHNICAL_RULES section 9): by
 * then the tab that signs out has had an answer, or the proxy's timeout, and
 * has reported it, unless it was closed first.
 */
export const SIGN_OUT_WAIT_SECONDS = 130;

const MILLISECONDS = 1000;

/** The deadlines of the times an answer carried, counted from when it arrived. */
export function deadlinesOf(times: SessionTimes, receivedAt: number): Deadlines {
    return {
        idleAt: receivedAt + times.idleEndsIn * MILLISECONDS,
        absoluteAt: receivedAt + times.absoluteEndsIn * MILLISECONDS,
    };
}

/**
 * The deadlines after a user activity request was answered at `answeredAt`:
 * the idle limit counts from then; the absolute limit never moves.
 */
export function afterActivity(deadlines: Deadlines, answeredAt: number, idleLimit: number): Deadlines {
    return { idleAt: Math.max(deadlines.idleAt, answeredAt + idleLimit * MILLISECONDS), absoluteAt: deadlines.absoluteAt };
}

/**
 * The later of two reports of the same sign-in session — from this tab and
 * from another tab of the same browser, whose answers are as true as this
 * tab's: the idle limit only ever moves later, and the absolute limit is the
 * session's own.
 */
export function laterOf(own: Deadlines | null, other: Deadlines): Deadlines {
    return own === null ? other : { idleAt: Math.max(own.idleAt, other.idleAt), absoluteAt: Math.min(own.absoluteAt, other.absoluteAt) };
}

export type SessionView =
    | { readonly kind: 'active'; readonly absoluteNotice: boolean }
    | { readonly kind: 'warning'; readonly secondsLeft: number; readonly absoluteNotice: boolean }
    | { readonly kind: 'ended' };

/**
 * What the shell shows at `now`: the session has ended when either limit is
 * reached; the sign-in-ending dialog (UXS-23) shows in the last two minutes
 * before the idle limit, but never against the 12-hour limit, which "Tetap
 * masuk" cannot extend (UXI-034); the notice of the 12-hour limit shows in its
 * last 15 minutes.
 */
export function viewAt(now: number, deadlines: Deadlines): SessionView {
    if (now >= deadlines.idleAt || now >= deadlines.absoluteAt) {
        return { kind: 'ended' };
    }

    const absoluteNotice = deadlines.absoluteAt - now <= ABSOLUTE_NOTICE_SECONDS * MILLISECONDS;
    const idleLeft = deadlines.idleAt - now;

    if (deadlines.idleAt < deadlines.absoluteAt && idleLeft <= WARNING_LEAD_SECONDS * MILLISECONDS) {
        return { kind: 'warning', secondsLeft: Math.ceil(idleLeft / MILLISECONDS), absoluteNotice };
    }

    return { kind: 'active', absoluteNotice };
}

/** The time left, for "dalam :durasi": whole minutes, rounded up, from a minute; seconds below it. */
export function timeLeft(seconds: number): { readonly amount: number; readonly unit: 'minute' | 'second' } {
    return seconds >= 60 ? { amount: Math.ceil(seconds / 60), unit: 'minute' } : { amount: Math.max(seconds, 0), unit: 'second' };
}

/** Whether a user activity request may be sent now (SEC-SESS-02: at most once a minute). */
export function mayReportActivity(now: number, lastUserRequestAt: number): boolean {
    return now - lastUserRequestAt >= ACTIVITY_INTERVAL_SECONDS * MILLISECONDS;
}
