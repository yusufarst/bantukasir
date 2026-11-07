<?php

namespace App\Domain\Cash;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The cash registers of the Cash module (SCHEMA section 5.10; BR-CASH-01 to BR-CASH-03; D-042),
 * which only this module writes (STK-12). It opens no transaction of its own: the Action that calls
 * it owns the transaction and the locks (TR-TX-01). A register is never deleted. Its lock is that of
 * TR §7.1 step 3 and SCHEMA section 7: the update lock (FOR NO KEY UPDATE), which a session open
 * and a configuration change both take, so that they never interleave (RACE-07, RACE-26).
 */
final class Registers
{
    /** The index that holds a code's uniqueness without regard to letter case (SCH-14). */
    public const array CODE_INDEX = ['cash_registers_code_ci_uniq'];

    public function __construct(private readonly Clock $clock) {}

    /**
     * Adds a register at version 1 and returns its internal key. The database refuses a code that
     * another register holds in any letter case (CODE_INDEX).
     */
    public function insert(string $code, string $label, bool $active): int
    {
        self::requireTransaction();
        $now = $this->now();

        return DB::table('cash_registers')->insertGetId(['code' => $code, 'label' => $label, 'active' => $active, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * The register under its update lock (FOR NO KEY UPDATE), or null.
     */
    public function lock(int $id): ?RegisterRecord
    {
        self::requireTransaction();

        return self::recordOf(DB::table('cash_registers')->where('id', $id)->lock('for no key update')->first());
    }

    /**
     * Changes the register whose update lock the caller holds, one version on.
     */
    public function change(RegisterRecord $register, string $code, string $label, bool $active): void
    {
        self::requireTransaction();

        $changed = DB::table('cash_registers')->where('id', $register->id)->update(['code' => $code, 'label' => $label, 'active' => $active, 'version' => $register->version + 1, 'updated_at' => $this->now()]);

        if ($changed !== 1) {
            throw new LogicException('A locked register exists.');
        }
    }

    private static function recordOf(?object $row): ?RegisterRecord
    {
        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $code = $values['code'] ?? null;
        $label = $values['label'] ?? null;
        $active = $values['active'] ?? null;
        $version = $values['version'] ?? null;

        if (! is_int($id) || ! is_string($code) || ! is_string($label) || ! is_bool($active) || ! is_int($version)) {
            throw new LogicException('A register row holds its columns (SCHEMA section 5.10).');
        }

        return new RegisterRecord($id, $code, $label, $active, $version);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A register is locked and changed inside the transaction of its Action (TR-TX-01).');
        }
    }
}
