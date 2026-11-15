<?php

namespace App\Domain\Operations\Reconciliation;

use App\Domain\Costing\Replay\ProjectionChange;
use App\Domain\Costing\Replay\ReplayLedger;
use App\Domain\Costing\Replay\ReplayRefused;
use App\Domain\Costing\Replay\ValuationReplay;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The comparison of one product's facts with its projections (SCHEMA guard G-03; BR-INV-45;
 * ADR-008 section 5; TASK-036 scope item 2), in the Operations module, which reads the Inventory and
 * Costing tables and calls the Costing replay (STACK_AND_MODULES section 2.1). Its reads are those of
 * the caller's transaction: a snapshot of the reconciliation, or the product's guard of the block and
 * of the rebuild, under which no writer of the product can change it (TR §7.1 step 6). It writes
 * nothing.
 *
 * The stock on hand is the sum of the movements and the last place their highest — sums, which
 * PostgreSQL's numeric adds exactly. The valuation and every issue and return projection are
 * replayed from the product's first movement by the application's own arithmetic, the same path as
 * a posting and a correction (ValuationReplay), never by SQL multiplication, division or rounding
 * (ADR-008 section 5). A valid UNKNOWN cost is no mismatch (BR-INV-45): only a difference between the
 * replay and what is stored is.
 */
final class ProductReconciliation
{
    /** The balance's stock on hand or last place differs from the movements, or a balance stands without one. */
    public const string BALANCE = 'BALANCE_MISMATCH';

    /** The valuation's value, status or last place differs from the replay, or is missing. */
    public const string VALUATION = 'VALUATION_MISMATCH';

    /** An issue projection differs from the replay, or a cancelled manual issue's is not CANCELLED. */
    public const string ISSUE_PROJECTION = 'ISSUE_PROJECTION_MISMATCH';

    /** A return projection differs from the replay. */
    public const string RETURN_PROJECTION = 'RETURN_PROJECTION_MISMATCH';

    /** The stored history cannot be replayed as a valid history (BR-COST-24). */
    public const string HISTORY = 'HISTORY_INVALID';

    public function __construct(private readonly ReplayLedger $ledger) {}

    /**
     * What the facts give for the product's projections.
     *
     * @throws ReplayRefused UNSAFE_HISTORY when the facts do not form a valid history
     */
    public function recompute(int $productId, int $precision): ProductRecomputation
    {
        [$movements, $onHand, $lastSequence] = $this->position($productId, $precision);

        if ($movements === 0 || $onHand === null) {
            return ProductRecomputation::none($productId);
        }

        try {
            $start = $this->ledger->before($productId, $precision, 1);
            // Compared, not used: a stored projection that cannot be read is one to rewrite (R-01).
            $entries = $this->ledger->from($productId, $precision, 1, $movements, true);
            $result = ValuationReplay::recompute($start, $entries, $onHand, static function (): void {});
            $cancelled = array_map(static fn (int $allocation): ProjectionChange => ProjectionChange::cancelled($allocation), $this->ledger->cancelledIssues($productId, 1));
        } catch (LogicException) {
            // A projection missing or of a status that its movement cannot have, as the ledger reads
            // it: the history cannot be replayed as stored.
            throw ReplayRefused::unsafeHistory();
        }

        return new ProductRecomputation($productId, $movements, $onHand, $lastSequence, $result->value, [...$cancelled, ...$result->changes], $result->returnChanges);
    }

    /**
     * The detail code of the product's first mismatch, in the order of the constants above, or null
     * when every projection equals what its facts give.
     */
    public function mismatchOf(int $productId, int $precision): ?string
    {
        try {
            [$movements, $onHand, $lastSequence] = $this->position($productId, $precision);
        } catch (ReplayRefused) {
            return self::HISTORY;
        }

        if (! $this->balanceAgrees($productId, $precision, $onHand, $lastSequence)) {
            return self::BALANCE;
        }

        try {
            $facts = $this->recompute($productId, $precision);
        } catch (ReplayRefused) {
            return self::HISTORY;
        }

        if (! $this->valuationAgrees($productId, $movements === 0 ? null : $facts->value, $lastSequence)) {
            return self::VALUATION;
        }

        if ($facts->issueChanges !== []) {
            return self::ISSUE_PROJECTION;
        }

        return $facts->returnChanges !== [] ? self::RETURN_PROJECTION : null;
    }

    /**
     * The number of the product's movements, the sum of their quantities and their highest place;
     * null for the last two without a movement.
     *
     * @return array{int, Quantity|null, int|null}
     *
     * @throws ReplayRefused UNSAFE_HISTORY when the sum is no stock on hand
     */
    private function position(int $productId, int $precision): array
    {
        $row = DB::selectOne('select count(*) as movements, coalesce(sum(quantity), 0) as quantity, max(product_sequence) as last_sequence from stock_movements where product_id = ?', [$productId]);
        $values = is_object($row) ? (array) $row : [];
        $movements = $values['movements'] ?? null;
        $quantity = $values['quantity'] ?? null;
        $last = $values['last_sequence'] ?? null;

        if (! is_int($movements) || ! is_string($quantity) || ($last !== null && ! is_int($last))) {
            throw new LogicException('The sums of the movements hold their types.');
        }

        if ($movements === 0) {
            return [0, null, null];
        }

        try {
            return [$movements, Quantity::fromDatabase($quantity, $precision)->assertStock(), $last];
        } catch (NumberOutOfRange|NonCanonicalNumber) {
            throw ReplayRefused::unsafeHistory();
        }
    }

    private function balanceAgrees(int $productId, int $precision, ?Quantity $onHand, ?int $lastSequence): bool
    {
        $row = DB::selectOne('select on_hand, last_sequence from stock_balances where product_id = ?', [$productId]);

        if (! is_object($row) || $onHand === null) {
            return ! is_object($row) && $onHand === null;
        }

        $values = (array) $row;

        try {
            return Quantity::fromDatabase($values['on_hand'] ?? null, $precision)->compareTo($onHand) === 0 && ($values['last_sequence'] ?? null) === $lastSequence;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function valuationAgrees(int $productId, ?Cost $value, ?int $lastSequence): bool
    {
        $row = DB::selectOne('select value_status, value, last_sequence from inventory_valuations where product_id = ?', [$productId]);

        if (! is_object($row) || $value === null) {
            return ! is_object($row) && $value === null;
        }

        $values = (array) $row;

        try {
            $stored = Cost::fromDatabase($values['value'] ?? null, $values['value_status'] ?? null);
        } catch (InvalidArgumentException) {
            return false;
        }

        if (($values['last_sequence'] ?? null) !== $lastSequence || $stored->isKnown() !== $value->isKnown()) {
            return false;
        }

        return ! $value->isKnown() || $stored->compareTo($value) === 0;
    }
}
