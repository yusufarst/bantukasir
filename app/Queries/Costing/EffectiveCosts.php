<?php

namespace App\Queries\Costing;

use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Cost;
use Illuminate\Support\Facades\DB;

/**
 * The reads of a cost source's value after the Owner's corrections (SCHEMA section 5.8; D-034,
 * D-047; BR-COST-07, BR-COST-20 to BR-COST-22; BR-INV-15; TASK-025): the effective value of an
 * evidence is the new amount of its correction with the highest number, or its original amount when
 * it has none — UNKNOWN until completed, never zero (BR-COST-03) — and the original is the cost at
 * confirmation. A query that reads `acquisition_cost_evidence as e` selects the columns below beside
 * `e.cost_status`, `e.total_amount` and `e.zero_cost_note`.
 */
final class EffectiveCosts
{
    /** The new amount of the evidence's latest correction, or null. */
    public const string CORRECTED_AMOUNT = '(select c.new_amount from cost_corrections c where c.evidence_id = e.id order by c.correction_no desc limit 1) as corrected_amount';

    /** The zero's evidence or reason of the evidence's latest correction, or null. */
    public const string CORRECTED_NOTE = '(select c.zero_cost_note from cost_corrections c where c.evidence_id = e.id order by c.correction_no desc limit 1) as corrected_note';

    /** The number of the evidence's latest correction, null for none. */
    public const string CORRECTION_NO = '(select max(c.correction_no) from cost_corrections c where c.evidence_id = e.id) as correction_no';

    /** Whether the evidence carries an active mark, one without a clearance (BR-COST-27). */
    public const string MARKED = 'exists (select 1 from cost_incomplete_marks k where k.evidence_id = e.id and not exists (select 1 from cost_incomplete_mark_clearances x where x.mark_id = k.id)) as marked';

    /** The most corrections that one read of their history gives. */
    public const int MAX_HISTORY = 1000;

    private const string RECORDED_AT = "to_char(c.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /**
     * The effective value of a row: its status and its canonical amount, none for UNKNOWN.
     *
     * @param  array<array-key, mixed>  $row
     * @return array{status: string, amount: string|null}
     */
    public static function current(array $row): array
    {
        $corrected = Read::nullableText($row, 'corrected_amount');

        return $corrected === null ? self::confirmed($row) : self::valueOf(Cost::fromDatabase($corrected));
    }

    /**
     * The value at confirmation of a row, the evidence as it was posted (D-047).
     *
     * @param  array<array-key, mixed>  $row
     * @return array{status: string, amount: string|null}
     */
    public static function confirmed(array $row): array
    {
        return self::valueOf(Cost::fromDatabase(Read::nullableText($row, 'total_amount'), Read::text($row, 'cost_status')));
    }

    /**
     * The zero's evidence or reason of the effective value, or null.
     *
     * @param  array<array-key, mixed>  $row
     */
    public static function zeroNote(array $row): ?string
    {
        return Read::nullableText($row, 'corrected_amount') === null ? Read::nullableText($row, 'zero_cost_note') : Read::nullableText($row, 'corrected_note');
    }

    /**
     * The number of the latest correction of a row, 0 for none.
     *
     * @param  array<array-key, mixed>  $row
     */
    public static function correctionNo(array $row): int
    {
        return Read::nullableInt($row, 'correction_no') ?? 0;
    }

    /**
     * The corrections of these cost sources, the Owner's history (CAP-12, CAP-20; UXI-247), newest
     * first within each, at most MAX_HISTORY in all: the old value — null for UNKNOWN — the new one,
     * a zero's evidence or reason, the reason, the time and who corrected it.
     *
     * @param  list<int>  $evidenceIds
     * @return array<int, list<array{correctionNo: int, oldAmount: string|null, newAmount: string, zeroNote: string|null, reason: string, recordedAt: string, actorDisplayName: string}>>
     */
    public static function history(array $evidenceIds): array
    {
        if ($evidenceIds === []) {
            return [];
        }

        $rows = DB::table('cost_corrections as c')
            ->whereIn('c.evidence_id', $evidenceIds)
            ->orderBy('c.evidence_id')
            ->orderByDesc('c.correction_no')
            ->limit(self::MAX_HISTORY)
            ->select(['c.evidence_id', 'c.correction_no', 'c.old_status', 'c.old_amount', 'c.new_amount', 'c.zero_cost_note', 'c.reason', 'c.actor_display_name'])
            ->selectRaw(self::RECORDED_AT)
            ->get();
        $history = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $old = Cost::fromDatabase(Read::nullableText($values, 'old_amount'), Read::text($values, 'old_status'));
            $history[Read::int($values, 'evidence_id')][] = [
                'correctionNo' => Read::int($values, 'correction_no'),
                'oldAmount' => $old->isKnown() ? $old->toCanonical() : null,
                'newAmount' => Cost::fromDatabase(Read::text($values, 'new_amount'))->toCanonical(),
                'zeroNote' => Read::nullableText($values, 'zero_cost_note'),
                'reason' => Read::text($values, 'reason'),
                'recordedAt' => Read::text($values, 'recorded_at'),
                'actorDisplayName' => Read::text($values, 'actor_display_name'),
            ];
        }

        return $history;
    }

    /**
     * @return array{status: string, amount: string|null}
     */
    private static function valueOf(Cost $cost): array
    {
        return ['status' => $cost->isKnown() ? 'KNOWN' : 'UNKNOWN', 'amount' => $cost->isKnown() ? $cost->toCanonical() : null];
    }
}
