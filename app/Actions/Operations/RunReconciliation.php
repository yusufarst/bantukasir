<?php

namespace App\Actions\Operations;

use App\Domain\Operations\Heartbeat;
use App\Domain\Operations\Reconciliation\BlockOutcome;
use App\Domain\Operations\Reconciliation\MappingReconciliation;
use App\Domain\Operations\Reconciliation\ProductReconciliation;
use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use App\Domain\Operations\Reconciliation\RunningTotalsReconciliation;
use App\Domain\Operations\Reconciliation\RunSummary;
use App\Domain\Operations\Reconciliation\Snapshot;
use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * One run of SC-01 `reconciliation:run` (ROUTE_CONTRACTS SC-01; ENVIRONMENTS section 13; SCHEMA guard
 * G-03 and section 5.12; BR-INV-45, BR-INV-46; OPERATIONS_RUNBOOK RB-04; TASK-036 scope item 2). It
 * reads; it writes only the blocks of the products whose facts and projections differ and its own row:
 *
 * - every product, in key order and in batches, each read in a snapshot of its own and compared
 *   (ProductReconciliation); a product that differs gets `posting_block.block`, which checks it again
 *   under its guard (BlockPostings, RACE-24), so another product's postings never wait for it;
 * - for a run of every product, the running totals of every Sale and the active mapping of every
 *   token, each batch in a snapshot of its own: a difference is an incident to report, never a block
 *   (G-03, BR-INV-46);
 * - one row of `reconciliation_runs`: CLEAN, MISMATCH — a product blocked or already blocked, or a
 *   difference reported — or FAILED when the run did not complete, which then fails as it failed;
 * - the reconciliation check of the heartbeat monitor pinged only after a completed run of every
 *   product (AC-036-07), never after a failed one or a run for one product (RB-04 step 6).
 *
 * Bounded: the run ends FAILED once it passes the budget of config reconciliation.run_budget, below the
 * hour of the scheduler's no-overlap lock; each read and each block is bounded by its transaction's
 * budget. The caller holds the no-overlap lock (TR-RUN-04).
 */
final class RunReconciliation
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ProductReconciliation $products,
        private readonly RunningTotalsReconciliation $totals,
        private readonly MappingReconciliation $mappings,
        private readonly BlockPostings $block,
        private readonly ReconciliationRuns $runs,
        private readonly Heartbeat $heartbeat,
    ) {}

    /**
     * Runs the reconciliation of every product, or of the one named, and records it.
     *
     * @throws InvalidArgumentException the product named does not exist; nothing is recorded
     * @throws Throwable the run failed, after its FAILED row was recorded
     */
    public function __invoke(?int $productId, string $trigger): RunSummary
    {
        $named = $productId === null ? [] : self::rows(DB::select('select id, quantity_precision from products where id = ?', [$productId]));

        if ($productId !== null && $named === []) {
            throw new InvalidArgumentException('The product does not exist.');
        }

        $started = $this->clock->now();
        $deadline = $started->addSeconds(config()->integer('reconciliation.run_budget'));
        $batch = max(1, config()->integer('reconciliation.batch'));
        $runKey = (string) Str::uuid();
        $scope = $productId === null ? ReconciliationRuns::ALL : ReconciliationRuns::PRODUCT;
        $checked = 0;
        $mismatches = 0;
        $written = 0;
        $sales = [];
        $tokens = [];

        try {
            $after = 0;

            do {
                $rows = $productId === null ? self::rows(DB::select('select id, quantity_precision from products where id > ? order by id limit ?', [$after, $batch])) : $named;

                foreach ($rows as [$id, $precision]) {
                    self::withinBudget($this->clock->now(), $deadline);
                    $checked++;
                    $after = $id;
                    $detail = Snapshot::read(fn (): ?string => $this->products->mismatchOf($id, $precision));

                    if ($detail === null) {
                        continue;
                    }

                    $outcome = ($this->block)($runKey, $id);

                    if ($outcome !== BlockOutcome::Agrees) {
                        $mismatches++;
                        $written += $outcome === BlockOutcome::Written ? 1 : 0;
                    }
                }
            } while ($productId === null && count($rows) === $batch);

            if ($productId === null) {
                $sales = $this->differences(fn (int $from): array => $this->totals->after($from, $batch), $deadline);
                $tokens = $this->differences(fn (int $from): array => $this->mappings->after($from, $batch), $deadline);
            }
        } catch (Throwable $failure) {
            try {
                $this->record($started, ReconciliationRuns::FAILED, $checked, $written, count($sales) + count($tokens), $scope, $trigger);
            } catch (Throwable $unrecorded) {
                report($unrecorded);
            }

            throw $failure;
        }

        $outcome = $mismatches > 0 || $sales !== [] || $tokens !== [] ? ReconciliationRuns::MISMATCH : ReconciliationRuns::CLEAN;
        $runId = $this->record($started, $outcome, $checked, $written, count($sales) + count($tokens), $scope, $trigger);
        $pinged = $scope === ReconciliationRuns::ALL && $this->heartbeat->ping(Heartbeat::RECONCILIATION);

        return new RunSummary($runId, $outcome, $checked, $mismatches, $written, $sales, $tokens, $pinged);
    }

    /**
     * The keys that differ, read batch after batch, each in a snapshot of its own.
     *
     * @param  callable(int): array{last: int|null, differing: list<int>}  $page
     * @return list<int>
     */
    private function differences(callable $page, CarbonImmutable $deadline): array
    {
        $differing = [];
        $after = 0;

        while (true) {
            self::withinBudget($this->clock->now(), $deadline);
            $read = Snapshot::read(static fn (): array => $page($after));

            if ($read['last'] === null) {
                return $differing;
            }

            $differing = [...$differing, ...$read['differing']];
            $after = $read['last'];
        }
    }

    private function record(CarbonImmutable $started, string $outcome, int $checked, int $written, int $differences, string $scope, string $trigger): int
    {
        $finished = $this->clock->now();

        return $this->runs->record(
            $started->format('Y-m-d H:i:s.uP'),
            ($finished->lessThan($started) ? $started : $finished)->format('Y-m-d H:i:s.uP'),
            $outcome,
            $checked,
            $written,
            $differences,
            $scope,
            $trigger,
        );
    }

    /**
     * The products of these rows, each with its precision.
     *
     * @param  array<array-key, mixed>  $rows
     * @return list<array{int, int}>
     */
    private static function rows(array $rows): array
    {
        $products = [];

        foreach ($rows as $row) {
            $values = is_object($row) ? (array) $row : [];
            $id = $values['id'] ?? null;
            $precision = $values['quantity_precision'] ?? null;

            if (! is_int($id) || ! is_int($precision)) {
                throw new LogicException('A product has its key and its precision.');
            }

            $products[] = [$id, $precision];
        }

        return $products;
    }

    private static function withinBudget(CarbonImmutable $now, CarbonImmutable $deadline): void
    {
        if ($now->greaterThanOrEqualTo($deadline)) {
            throw new RuntimeException('The reconciliation passed its budget (config reconciliation.run_budget).');
        }
    }
}
