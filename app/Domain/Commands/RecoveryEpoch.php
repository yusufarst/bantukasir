<?php

namespace App\Domain\Commands;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The installation's recovery epoch (ADR-007 section 6; SCHEMA section 5.3,
 * `recovery_epoch`; guard G-12): one value of at least 128 random bits from
 * the server's secure random source. The bootstrap issues it (TASK-010,
 * SEC-OP-01) and the after-restore procedure replaces it with a value never
 * issued before (TASK-037, SEC-OP-04). The application handles it as
 * lowercase hexadecimal, as pages receive it in the shared props and send it
 * back unchanged in every envelope; the database stores its bytes.
 *
 * Reading it needs no lock: it changes only in the after-restore procedure,
 * which runs before the application accepts traffic again.
 */
final class RecoveryEpoch
{
    /** Bytes of a new epoch: 256 random bits, above the minimum of 128. */
    public const int BYTES = 32;

    /** The bytes an envelope's epoch may have: at least 128 bits, bounded. */
    public const int MIN_BYTES = 16;

    public const int MAX_BYTES = 64;

    public function __construct(private readonly Clock $clock) {}

    /**
     * The current epoch as hexadecimal, or null before the bootstrap.
     */
    public function current(): ?string
    {
        $epoch = DB::scalar("select encode(epoch, 'hex') from recovery_epoch where id = 1");

        return is_string($epoch) ? $epoch : null;
    }

    /**
     * Issues the first epoch, in the caller's transaction; the database
     * refuses a second one.
     */
    public function issue(): string
    {
        self::requireTransaction();
        $epoch = bin2hex(random_bytes(self::BYTES));
        DB::insert("insert into recovery_epoch (id, epoch, set_at) values (1, decode(?, 'hex'), ?)", [$epoch, $this->now()]);

        return $epoch;
    }

    /**
     * Replaces the epoch with a new random value, in the caller's transaction
     * (the after-restore procedure of TASK-037).
     */
    public function replace(): string
    {
        self::requireTransaction();
        $epoch = bin2hex(random_bytes(self::BYTES));
        $changed = DB::update("update recovery_epoch set epoch = decode(?, 'hex'), set_at = ? where id = 1", [$epoch, $this->now()]);

        if ($changed !== 1) {
            throw new LogicException('There is no recovery epoch to replace.');
        }

        return $epoch;
    }

    /**
     * Whether a value is an epoch as an envelope carries it: lowercase
     * hexadecimal of MIN_BYTES to MAX_BYTES bytes.
     */
    public static function wellFormed(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(?:[0-9a-f]{2}){'.self::MIN_BYTES.','.self::MAX_BYTES.'}$/', $value) === 1;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The recovery epoch changes in the transaction of its procedure.');
        }
    }
}
