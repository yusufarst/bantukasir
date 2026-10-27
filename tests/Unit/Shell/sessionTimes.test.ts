import { describe, expect, it } from 'vitest';
import { ABSOLUTE_NOTICE_SECONDS, ACTIVITY_INTERVAL_SECONDS, WARNING_LEAD_SECONDS, afterActivity, deadlinesOf, laterOf, mayReportActivity, timeLeft, viewAt } from '@/lib/sessionTimes';

// The client timers of the sign-in session (AC-007-06; SECURITY_MODEL
// SEC-SESS-02; UI_STATES UXS-23; ADAPTIVE_AND_ACCESSIBILITY UXA-21; TASK-007):
// the deadlines counted from when an answer arrived, never before the
// server's; the dialog at least 60 seconds before the idle limit and never
// against the 12-hour limit; the 12-hour notice in its last 15 minutes; the
// end when either limit is reached; one user-activity request a minute.

const MINUTE = 60_000;
const times = { idleLimit: 1800, idleEndsIn: 1800, absoluteEndsAt: '2026-10-17T14:00:00Z', absoluteEndsIn: 12 * 3600 };

describe('the deadlines', () => {
    it('count from when the answer arrived', () => {
        expect(deadlinesOf(times, 1_000)).toEqual({ idleAt: 1_000 + 30 * MINUTE, absoluteAt: 1_000 + 12 * 60 * MINUTE });
        expect(deadlinesOf({ ...times, idleEndsIn: 1500 }, 0).idleAt).toBe(25 * MINUTE);
    });

    it('move the idle limit later after a user-activity request, and the absolute one never', () => {
        const deadlines = deadlinesOf(times, 0);

        expect(afterActivity(deadlines, 10 * MINUTE, 1800)).toEqual({ idleAt: 40 * MINUTE, absoluteAt: deadlines.absoluteAt });
        expect(afterActivity({ idleAt: 50 * MINUTE, absoluteAt: deadlines.absoluteAt }, 10 * MINUTE, 1800).idleAt).toBe(50 * MINUTE);
    });

    it('take the later idle limit and the earlier absolute end of two tabs of one sign-in session', () => {
        expect(laterOf(null, { idleAt: 5, absoluteAt: 9 })).toEqual({ idleAt: 5, absoluteAt: 9 });
        expect(laterOf({ idleAt: 7, absoluteAt: 10 }, { idleAt: 5, absoluteAt: 9 })).toEqual({ idleAt: 7, absoluteAt: 9 });
    });
});

describe('what the shell shows', () => {
    const deadlines = deadlinesOf(times, 0);

    it('nothing while there is time, the dialog in the last two minutes, the end at the limit', () => {
        expect(WARNING_LEAD_SECONDS).toBeGreaterThanOrEqual(60);
        expect(viewAt(0, deadlines)).toEqual({ kind: 'active', absoluteNotice: false });
        expect(viewAt(28 * MINUTE - 1, deadlines).kind).toBe('active');
        expect(viewAt(28 * MINUTE, deadlines)).toEqual({ kind: 'warning', secondsLeft: 120, absoluteNotice: false });
        expect(viewAt(30 * MINUTE - 500, deadlines)).toEqual({ kind: 'warning', secondsLeft: 1, absoluteNotice: false });
        expect(viewAt(30 * MINUTE, deadlines)).toEqual({ kind: 'ended' });
    });

    it('never offers "Tetap masuk" against the 12-hour limit, which is told in its last 15 minutes', () => {
        const late = { idleAt: 12 * 60 * MINUTE + 10 * MINUTE, absoluteAt: 12 * 60 * MINUTE };

        expect(ABSOLUTE_NOTICE_SECONDS).toBe(15 * 60);
        expect(viewAt(12 * 60 * MINUTE - 16 * MINUTE, late)).toEqual({ kind: 'active', absoluteNotice: false });
        expect(viewAt(12 * 60 * MINUTE - 15 * MINUTE, late)).toEqual({ kind: 'active', absoluteNotice: true });
        expect(viewAt(12 * 60 * MINUTE - 1 * MINUTE, late)).toEqual({ kind: 'active', absoluteNotice: true });
        expect(viewAt(12 * 60 * MINUTE, late)).toEqual({ kind: 'ended' });
    });

    it('says the time left in minutes from a minute, in seconds below it', () => {
        expect(timeLeft(120)).toEqual({ amount: 2, unit: 'minute' });
        expect(timeLeft(61)).toEqual({ amount: 2, unit: 'minute' });
        expect(timeLeft(60)).toEqual({ amount: 1, unit: 'minute' });
        expect(timeLeft(59)).toEqual({ amount: 59, unit: 'second' });
        expect(timeLeft(-1)).toEqual({ amount: 0, unit: 'second' });
    });
});

describe('the user-activity request while typing', () => {
    it('is sent at most once a minute', () => {
        expect(ACTIVITY_INTERVAL_SECONDS).toBe(60);
        expect(mayReportActivity(59_999, 0)).toBe(false);
        expect(mayReportActivity(60_000, 0)).toBe(true);
    });
});
