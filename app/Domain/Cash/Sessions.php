<?php

namespace App\Domain\Cash;

use App\Support\Numbers\Money;
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

    public const string CLOSED = 'CLOSED';

    /** The partial unique indexes of BR-CASH-06: one OPEN session per register and per user. */
    public const array ONE_OPEN_PER_REGISTER = ['cash_sessions_one_open_per_register'];

    public const array ONE_OPEN_PER_USER = ['cash_sessions_one_open_per_user'];

    /** The columns of a session that a close reads under its lock. */
    private const array CLOSING = ['id', 'session_number', 'status', 'activity_version', 'opening_cash', 'opened_at', 'responsible_display_name', 'register_id', 'responsible_user_id'];

    public function __construct(private readonly Clock $clock) {}

    /**
     * The path of RP-206, UXP-206 "Detail sesi kas", of a session by its internal key (ROUTE_CONTRACTS
     * RP-206; TASK-030): the result page of its close.
     */
    public static function detailPath(int $sessionId): string
    {
        return '/cash-sessions/'.$sessionId;
    }

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
     * The session of a close under the close's update lock (TR §7.1 step 4; SCHEMA section 7: FOR
     * UPDATE, because the close changes its status), only when the user is its responsible user:
     * another user's session is never locked by an own close (G-19). The same lock as a Sale's, so a
     * close and a Sale of the session never interleave (RACE-08). A Sale or a close that the lock
     * waited for is read as it left the session. Null when the session does not exist or is another
     * user's.
     */
    public function lockForClose(int $sessionId, int $userId): ?ClosingSession
    {
        self::requireTransaction();

        return self::closing(DB::table('cash_sessions')->where('id', $sessionId)->where('responsible_user_id', $userId)->lockForUpdate()->first(self::CLOSING));
    }

    /**
     * The session of an Owner-assisted close under the same update lock as an own close's (TR §7.1 step
     * 4: the target session; RACE-09), only when the actor is not its responsible user: the Owner's own
     * session is closed by RC-03, never here (INTERACTION_CONTRACTS RC-04; G-19). The responsible user
     * may be deactivated (D-069 (d), D-087). Null when the session does not exist or is the actor's own.
     */
    public function lockForAssistedClose(int $sessionId, int $actorId): ?ClosingSession
    {
        self::requireTransaction();

        return self::closing(DB::table('cash_sessions')->where('id', $sessionId)->where('responsible_user_id', '<>', $actorId)->lockForUpdate()->first(self::CLOSING));
    }

    /**
     * The session selected as the funding source of a drawer-funded refund, under the update lock of a
     * Sale's (TR §7.1 step 4; SCHEMA section 7: FOR NO KEY UPDATE), whoever its responsible user is: the
     * Owner selects the drawer the money comes from (BR-RFD-07; RC-05). The register is not locked: it
     * keeps its code and label while a session is OPEN on it (BR-CASH-02). A close that the lock waited
     * for is read as it left the session, CLOSED (RACE-13). Null when the session does not exist.
     */
    public function lockForRefund(int $sessionId): ?FundingSession
    {
        self::requireTransaction();

        $row = DB::table('cash_sessions')->where('id', $sessionId)->lock('for no key update')->first(['id', 'session_number', 'status', 'opening_cash', 'register_id']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $number = $values['session_number'] ?? null;
        $status = $values['status'] ?? null;
        $opening = $values['opening_cash'] ?? null;
        $register = $values['register_id'] ?? null;

        if (! is_int($id) || ! is_string($number) || ! is_string($status) || (! is_int($opening) && ! is_string($opening)) || ! is_int($register)) {
            throw new LogicException('A session holds its columns (SCHEMA section 5.10).');
        }

        return new FundingSession($id, $number, $status === self::OPEN, Money::fromDatabase($opening), $register);
    }

    /**
     * The session of an Owner review note under a share lock (TR §7.2, family Owner review note; SCHEMA
     * section 7: FOR SHARE), so that a close never interleaves with it: its number and whether it is
     * CLOSED. Null when it does not exist.
     *
     * @return array{number: string, closed: bool}|null
     */
    public function lockForNote(int $sessionId): ?array
    {
        self::requireTransaction();

        $row = DB::table('cash_sessions')->where('id', $sessionId)->sharedLock()->first(['session_number', 'status']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $number = $values['session_number'] ?? null;
        $status = $values['status'] ?? null;

        if (! is_string($number) || ! is_string($status)) {
            throw new LogicException('A session holds its columns (SCHEMA section 5.10).');
        }

        return ['number' => $number, 'closed' => $status === self::CLOSED];
    }

    private static function closing(?object $row): ?ClosingSession
    {
        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $number = $values['session_number'] ?? null;
        $status = $values['status'] ?? null;
        $version = $values['activity_version'] ?? null;
        $opening = $values['opening_cash'] ?? null;
        $openedAt = $values['opened_at'] ?? null;
        $name = $values['responsible_display_name'] ?? null;
        $register = $values['register_id'] ?? null;
        $responsible = $values['responsible_user_id'] ?? null;

        if (! is_int($id) || ! is_string($number) || ! is_string($status) || ! is_int($version) || (! is_int($opening) && ! is_string($opening))
            || ! is_string($openedAt) || ! is_string($name) || ! is_int($register) || ! is_int($responsible)) {
            throw new LogicException('A session holds its columns (SCHEMA section 5.10).');
        }

        return new ClosingSession($id, $number, $status === self::OPEN, $version, Money::fromDatabase($opening), $openedAt, $name, $register, $responsible);
    }

    /**
     * The close of the session whose lock the caller holds: CLOSED, before its close row is added,
     * which names the session as CLOSED (SCHEMA section 5.10). The presence key then refuses a commit
     * without the close (EXC-44), and the close's key, a commit without its report.
     */
    public function close(int $sessionId, string $at): void
    {
        self::requireTransaction();

        $changed = DB::table('cash_sessions')->where('id', $sessionId)->where('status', self::OPEN)->update(['status' => self::CLOSED, 'updated_at' => $at]);

        if ($changed !== 1) {
            throw new LogicException('The locked session is OPEN (G-04).');
        }
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
