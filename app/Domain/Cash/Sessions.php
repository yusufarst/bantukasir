<?php

namespace App\Domain\Cash;

use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The cash sessions of the Cash module (SCHEMA section 5.10; BR-CASH-04 to BR-CASH-06, BR-CASH-11),
 * which only this module writes (STK-12). It opens no transaction of its own (TR-TX-01). Opening a
 * session is an insert at TR §7.1 step 8: the clock, the business date and the counter are taken at
 * that step, next to each other, after every guard has passed, so that a definite refusal consumes
 * no number (SCH-21, TR-TX-10, G-18). Nothing serializes opens for numbering: no counter row, no
 * advisory lock, no table lock (SCH-25). A gap left by a rolled-back open is valid (SCH-26).
 */
final class Sessions
{
    public const string OPEN = 'OPEN';

    /** The partial unique indexes of BR-CASH-06: one OPEN session per register and per user. */
    public const array ONE_OPEN_PER_REGISTER = ['cash_sessions_one_open_per_register'];

    public const array ONE_OPEN_PER_USER = ['cash_sessions_one_open_per_user'];

    public function __construct(private readonly Clock $clock) {}

    /**
     * Whether a session is OPEN on the register, read under the register's lock (TR-VAL-08).
     */
    public function openOn(int $registerId): bool
    {
        self::requireTransaction();

        return DB::table('cash_sessions')->where('register_id', $registerId)->where('status', self::OPEN)->exists();
    }

    /**
     * Whether the user is responsible for an OPEN session. A concurrent open by the same user on
     * another register is refused by the unique index at the insert (ONE_OPEN_PER_USER).
     */
    public function openFor(int $userId): bool
    {
        self::requireTransaction();

        return DB::table('cash_sessions')->where('responsible_user_id', $userId)->where('status', self::OPEN)->exists();
    }

    /**
     * Opens the session at step 8: the time and its business date, then the counter, then the row,
     * OPEN with an activity version of 0. The database refuses a second OPEN session of the
     * register or of the user (ONE_OPEN_PER_REGISTER, ONE_OPEN_PER_USER).
     *
     * @param  numeric-string  $openingCash  whole rupiah, zero or more, validated by the caller
     */
    public function open(int $registerId, int $userId, string $displayName, string $openingCash, int $commandId): OpenedSession
    {
        self::requireTransaction();

        $openedAt = $this->clock->now();
        $date = BusinessDate::ofInstant($openedAt);
        $counter = DB::scalar("select pg_catalog.nextval('cash_session_number_seq')");

        if (! is_int($counter)) {
            throw new LogicException('The session number sequence gives a whole number (SCH-20).');
        }

        $number = SessionNumber::of($date, $counter);
        $at = $openedAt->format('Y-m-d H:i:s.uP');
        $id = DB::table('cash_sessions')->insertGetId([
            'session_number' => $number,
            'session_number_seq' => $counter,
            'opened_business_date' => $date->toString(),
            'register_id' => $registerId,
            'responsible_user_id' => $userId,
            'responsible_display_name' => $displayName,
            'opening_cash' => $openingCash,
            'opened_at' => $at,
            'open_command_id' => $commandId,
            'status' => self::OPEN,
            'activity_version' => 0,
            'updated_at' => $at,
        ]);

        return new OpenedSession((int) $id, $number);
    }

    /**
     * The session of a Sale under its update lock (TR §7.1 step 4; SCHEMA section 7: FOR NO KEY
     * UPDATE), only when the user is its responsible user: another user's session is never locked,
     * so that a request naming it holds no other till (G-04). A close that the lock waited for is read
     * as it left the session. Null when the session does not exist or is another user's.
     */
    public function lockForSale(int $sessionId, int $userId): ?GuardedSession
    {
        self::requireTransaction();

        $row = DB::table('cash_sessions')->where('id', $sessionId)->where('responsible_user_id', $userId)->lock('for no key update')->first(['id', 'session_number', 'status']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $number = $values['session_number'] ?? null;
        $status = $values['status'] ?? null;

        if (! is_int($id) || ! is_string($number) || ! is_string($status)) {
            throw new LogicException('A session holds its columns (SCHEMA section 5.10).');
        }

        return new GuardedSession($id, $number, $status === self::OPEN);
    }

    /**
     * One more Sale or drawer-funded refund in the session whose lock the caller holds: its activity
     * version one on, as a relative increment in one statement (BR-CASH-10; SCH-33), which a close
     * compares with the version it reviewed (RACE-08).
     */
    public function recordActivity(int $sessionId, string $at): void
    {
        self::requireTransaction();

        $changed = DB::table('cash_sessions')->where('id', $sessionId)->where('status', self::OPEN)->increment('activity_version', 1, ['updated_at' => $at]);

        if ($changed !== 1) {
            throw new LogicException('The locked session is OPEN (G-04).');
        }
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A session is read and opened inside the transaction of its Action (TR-TX-01).');
        }
    }
}
