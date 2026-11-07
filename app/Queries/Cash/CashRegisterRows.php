<?php

namespace App\Queries\Cash;

use App\Domain\Cash\Sessions;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The cash registers for the pages of the Cash module (ROUTE_CONTRACTS RP-104, RP-609 and RP-610;
 * BR-CASH-01 to BR-CASH-03): by code, then internal identifier, each with the responsible user of
 * its OPEN session, if any. No page takes a paging parameter, so each list is bounded instead; a
 * shop's drawers stay far below the bound (BR-CASH-03).
 *
 * @phpstan-type RegisterRow array{id: int, code: string, label: string, active: bool, version: int, openBy: string|null}
 * @phpstan-type AvailableRegister array{id: int, code: string, label: string}
 */
final class CashRegisterRows
{
    public const int LIST_BOUND = 500;

    /**
     * Every register with its OPEN-session state (RP-609).
     *
     * @return list<RegisterRow>
     */
    public function list(): array
    {
        $rows = [];

        foreach (self::query()->get() as $row) {
            $rows[] = self::rowOf($row);
        }

        return $rows;
    }

    /**
     * One register for its form (RP-610), or NOT_FOUND for a guessed identifier (SEC-AZ-06).
     *
     * @return RegisterRow
     *
     * @throws NotFound
     */
    public function one(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : self::query()->where('r.id', $key)->first();

        if ($row === null) {
            throw new NotFound;
        }

        return self::rowOf($row);
    }

    /**
     * The registers a session may open on now: active and without an OPEN session (RP-104;
     * UXI-094), read without a lock — the open checks them again under the register's lock.
     *
     * @return list<AvailableRegister>
     */
    public function available(): array
    {
        $rows = [];

        foreach (self::query()->where('r.active', true)->whereNull('s.id')->get() as $row) {
            $register = self::rowOf($row);
            $rows[] = ['id' => $register['id'], 'code' => $register['code'], 'label' => $register['label']];
        }

        return $rows;
    }

    private static function query(): Builder
    {
        return DB::table('cash_registers as r')
            ->leftJoin('cash_sessions as s', static function (JoinClause $join): void {
                $join->on('s.register_id', '=', 'r.id')->where('s.status', '=', Sessions::OPEN);
            })
            ->orderBy('r.code')
            ->orderBy('r.id')
            ->limit(self::LIST_BOUND)
            ->select(['r.id', 'r.code', 'r.label', 'r.active', 'r.version', 's.id as session_id', 's.responsible_display_name']);
    }

    /**
     * @return RegisterRow
     */
    private static function rowOf(object $row): array
    {
        $values = (array) $row;
        $id = $values['id'] ?? null;
        $code = $values['code'] ?? null;
        $label = $values['label'] ?? null;
        $active = $values['active'] ?? null;
        $version = $values['version'] ?? null;
        $openBy = $values['responsible_display_name'] ?? null;

        if (! is_int($id) || ! is_string($code) || ! is_string($label) || ! is_bool($active) || ! is_int($version) || ($openBy !== null && ! is_string($openBy))) {
            throw new LogicException('A register row holds its columns (SCHEMA section 5.10).');
        }

        return ['id' => $id, 'code' => $code, 'label' => $label, 'active' => $active, 'version' => $version, 'openBy' => $openBy];
    }
}
