<?php

namespace App\Queries\Sales;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use App\Queries\Inventory\Column as Read;
use Illuminate\Support\Facades\DB;

/**
 * The Sale of a SALE_ISSUE as the source of RP-403 (INTERACTION_INVENTORY UXI-224; COPY_DECK section 9.4,
 * `inventory.movement.source_sale`; SEC-AZ-03; TASK-031, review R-05), read by the Sales module for the
 * movement's controller, which composes it with the Inventory module's read (STK-10): the Sale's number and
 * time, and its key only for a viewer in the Sale's scope — the Owner (CAP-22), or the Sale's own cashier
 * with CAP-21 — so that for anyone else the source is its number as text, without a link.
 */
final class SaleSources
{
    private const string COMMITTED_AT = "to_char(s.committed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as committed_at";

    /**
     * @return array{kind: string, id: int|null, at: string, number: string}|null
     */
    public function ofLine(int $saleLine, User $viewer): ?array
    {
        $row = DB::table('sale_lines as l')
            ->join('sales as s', 's.id', '=', 'l.sale_id')
            ->where('l.id', $saleLine)
            ->select(['s.id', 's.sale_number', 's.actor_user_id'])
            ->selectRaw(self::COMMITTED_AT)
            ->first();

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $own = Read::int($values, 'actor_user_id') === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);
        $linked = $own || Capabilities::allows($viewer, Capability::Cap22);

        return ['kind' => 'SALE', 'id' => $linked ? Read::int($values, 'id') : null, 'at' => Read::text($values, 'committed_at'), 'number' => Read::text($values, 'sale_number')];
    }
}
