<?php

namespace App\Actions\Operations;

use App\Actions\Costing\CorrectCost;
use App\Database\CommandPoints;
use App\Database\SqlState;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Fingerprint;
use App\Domain\Commands\Operations;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Commands\StoredResult;
use App\Domain\Costing\AcquisitionCost;
use App\Domain\Costing\Allocations\IssueAllocations;
use App\Domain\Costing\Allocations\ReturnAllocations;
use App\Domain\Costing\CostCorrections;
use App\Domain\Costing\CostIncompleteMarks;
use App\Domain\Costing\InventoryValuations;
use App\Domain\Costing\ProductValuation;
use App\Domain\Costing\Replay\ProductReplay;
use App\Domain\Costing\Replay\ReplayBudget;
use App\Domain\Costing\Replay\ReplayRefused;
use App\Domain\Inventory\PostingBlocks;
use App\Domain\Operations\Reconciliation\RepairRefused;
use App\Models\User;
use App\Support\Numbers\Cost;
use App\Support\Time\Clock;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * OP-06, the cost recovery (SECURITY_MODEL SEC-OP-06 and section 14; ROUTE_CONTRACTS OP-06; OPERATIONS_RUNBOOK
 * RB-12; SCHEMA section 5.8, clearing (c); D-034, D-063, D-088; BR-COST-27; G-07, G-11, G-15, G-21; TASK-036
 * scope item 5). An operator procedure, without an envelope of the Owner's (TR-CMD-07), run outside opening
 * hours (OPR-04) on the Owner's written decision naming the mark, the cost source, the corrected amount and
 * the reason. It runs the validation and the replay of RC-15 with a larger bound set for the run — at most
 * ten times the movement cap and 10 minutes for its transaction (config reconciliation.recovery_factor and
 * recovery_budget) — under the product's guard, and publishes in one transaction the correction fact with
 * the Owner as its actor, the corrected valuation and projections, a clearance of each active mark of that
 * cost source with RECOVERY_PROCEDURE, the audit event COST_CORRECTION with the procedure as actor and the
 * operator's note, and its result; or it refuses and changes nothing.
 *
 * Never for an unsafe history: a mark of UNSAFE_HISTORY is refused, as is any replay that the larger bound
 * still refuses (RB-12 step 2). Its run is recorded under a command identity of the procedure, of the
 * operation of RC-15, whose intent is derived from the mark, so that a repeated run with the same mark
 * returns the first result and writes nothing (SEC-OP-06).
 *
 * Write steps (TEST_STRATEGY TS-21): the identity, the correction, the valuation and projections, the
 * clearances, the audit event, then the result.
 */
final class RecoverCost
{
    public const string PROCESS = 'cost_recovery';

    /** The result's type, as RC-15's (SCHEMA section 5.3). */
    private const string CORRECTION = 'cost_correction';

    private const array IDENTITY_KEYS = ['command_identities_intent_unique', 'command_identities_process_key'];

    /** The session's three limits, set before BEGIN and after the transaction, never inside it. */
    private const string SET_LIMITS = 'select pg_catalog.set_config(\'transaction_timeout\', ?, false), pg_catalog.set_config(\'statement_timeout\', ?, false), pg_catalog.set_config(\'idle_in_transaction_session_timeout\', ?, false)';

    private int $writeStep = 0;

    public function __construct(
        private readonly Clock $clock,
        private readonly CommandPoints $points,
        private readonly CommandIdentities $identities,
        private readonly RecoveryEpoch $epoch,
        private readonly AuditLog $audit,
        private readonly Products $products,
        private readonly PostingBlocks $blocks,
        private readonly CostCorrections $corrections,
        private readonly CostIncompleteMarks $marks,
        private readonly InventoryValuations $valuations,
        private readonly IssueAllocations $allocations,
        private readonly ReturnAllocations $returns,
        private readonly ProductReplay $replay,
    ) {}

    /**
     * Publishes the Owner's correction of the mark's cost source and clears its marks; or returns the first
     * result of a run with this mark.
     *
     * @return array{correction: int, repeated: bool}
     *
     * @throws RepairRefused with nothing written
     */
    public function __invoke(int $markId, int $evidenceId, Cost $new, ?string $zeroNote, string $reason, string $operatorNote): array
    {
        $intent = Uuid::uuid5(Uuid::NAMESPACE_URL, 'cost-recovery/mark/'.$markId)->toString();
        $first = $this->firstResult($intent);

        if ($first !== null) {
            return ['correction' => $first, 'repeated' => true];
        }

        $mark = $this->marks->markOf($markId) ?? throw new RepairRefused('No such mark: nothing was changed.');

        if ($mark['evidenceId'] !== $evidenceId) {
            throw new RepairRefused('The mark belongs to another cost source than the one named: nothing was changed.');
        }

        if ($mark['rejectionCode'] === CostIncompleteMarks::UNSAFE_HISTORY) {
            throw new RepairRefused('The mark is of an unsafe history, which the cost recovery never forces: correct the history first, then the Owner repeats the correction (RB-12 step 2). Nothing was changed.');
        }

        if (! $mark['active']) {
            throw new RepairRefused('The mark is already cleared: nothing was changed.');
        }

        if ($new->isKnown() && $new->amount()->isZero() !== ($zeroNote !== null)) {
            throw new RepairRefused('A zero cost needs its evidence or reason, and only a zero takes one (BR-COST-04): nothing was changed.');
        }

        $source = $this->corrections->source($evidenceId) ?? throw new RepairRefused('No such cost source: nothing was changed.');
        $epoch = $this->epoch->current() ?? throw new LogicException('The bootstrap inserts the recovery epoch (SEC-OP-01).');
        $key = Uuid::uuid5(Uuid::NAMESPACE_URL, 'cost-recovery/mark/'.$markId.'/key')->toString();
        $payload = ['evidenceId' => $evidenceId, 'markId' => $markId, 'newCost' => ['amount' => $new->toCanonical(), 'zeroEvidence' => $zeroNote], 'reason' => $reason];
        $envelope = new Envelope(CorrectCost::OPERATION, Operations::VERSION, $intent, $key, $epoch, [], $payload);
        $fingerprint = Fingerprint::of($envelope->operation, $envelope->version, 'process:'.self::PROCESS, [], $payload);
        $this->writeStep = 0;

        try {
            $correction = $this->inBudget(function () use ($envelope, $fingerprint, $source, $markId, $new, $zeroNote, $reason, $operatorNote): int {
                $now = $this->clock->now();
                $budget = ReplayBudget::lasting($this->clock, config()->integer('reconciliation.recovery_budget'));
                $commandId = $this->identities->insertProcessCommand($envelope, 'OPERATOR_PROCEDURE', self::PROCESS, $fingerprint, $now->format('Y-m-d H:i:s.uP'));
                $this->written();

                // The Owner, whose correction it is, under the share lock of step 2.1 (G-16).
                $owner = User::query()->where('role_super_admin', true)->where('status', 'ACTIVE')->sharedLock()->first() ?? throw new RepairRefused('No active Owner account: nothing was changed.');
                $this->points->afterLock('2.1');
                $product = $this->products->lockForPosting([$source->productId])[$source->productId] ?? throw new LogicException('A cost source names its product (SCHEMA section 5.8).');
                $this->points->afterLock('6');

                // Under the guard, as RC-15: not blocked (G-11), and the mark still active.
                if ($this->blocks->blockedAmong([$source->productId]) !== []) {
                    throw new RepairRefused('The product\'s postings are blocked by a reconciliation incident; handle it first (RB-04). Nothing was changed.');
                }

                $active = $this->marks->activeOf($source->evidenceId);

                if (! in_array($markId, $active, true)) {
                    throw new RepairRefused('The mark was cleared meanwhile: nothing was changed.');
                }

                $valuation = $this->valuations->lock([$source->productId])[$source->productId] ?? throw new LogicException('A valuation is read for its product.');

                if (! $valuation->stored) {
                    throw new LogicException('A product with a cost source has a published valuation (ADR-008 section 4.2).');
                }

                $this->allocations->lockProjectionsFrom($source->productId, $source->productSequence);
                $this->returns->lockProjectionsFrom($source->productId, $source->productSequence);
                $this->points->afterLock('7');

                $latest = $this->corrections->latest($source->evidenceId);
                $correctionNo = ($latest === null ? 0 : $latest->correctionNo) + 1;
                $old = $latest === null ? $source->original : $latest->newAmount;
                $at = $this->clock->now()->format('Y-m-d H:i:s.uP');
                $number = $valuation->publicationNo + 1;
                $correction = $this->corrections->insert($source, $correctionNo, $old, $new, $zeroNote, $reason, $owner->id, $owner->display_name, $commandId, $at);
                $this->written();

                try {
                    $cap = config()->integer('costing.replay.movement_cap') * min(10, max(1, config()->integer('reconciliation.recovery_factor')));
                    $result = $this->replay->recompute($source->productId, $product->quantityPrecision, $source->productSequence, $budget, $cap);
                    $budget->check();
                } catch (ReplayRefused $refusal) {
                    throw new RepairRefused(sprintf('The replay with the larger bound was refused (%s): nothing was changed, and the mark stays.', $refusal->rejection));
                }

                $this->valuations->publish(new ProductValuation($source->productId, $result->value, $number, $valuation->lastSequence, true), $at);
                $this->allocations->republish($result->changes, $number, $at);
                $this->returns->republish($result->returnChanges, $number, $at);
                $this->written();

                $actor = Actor::operatorProcedure(self::PROCESS);
                $this->marks->clearByRecovery($active, $source, $actor, $commandId, $at);
                $this->written();

                // The values and the reason are the Owner's alone in the audit (BR-AUD-02, D-047).
                $this->audit->record(
                    AuditCategory::Business,
                    'COST_CORRECTION',
                    $actor,
                    [
                        'evidenceId' => $source->evidenceId,
                        'movementId' => $source->movementId,
                        'correctionNo' => $correctionNo,
                        'publicationNo' => $number,
                        'projectionsRewritten' => count($result->changes) + count($result->returnChanges),
                        'marksCleared' => count($active),
                        'markId' => $markId,
                    ],
                    ['old' => self::valueOf($old), 'new' => self::valueOf($new), 'zeroNote' => $zeroNote, 'reason' => $reason],
                    subject: ['type' => self::CORRECTION, 'id' => $correction],
                    operatorNote: $operatorNote,
                    commandId: $commandId,
                    productId: $source->productId,
                );
                $this->written();

                $this->identities->recordResult($commandId, new StoredResult(self::CORRECTION, $correction));
                $this->written();
                $this->points->beforeCommit();

                return $correction;
            });
        } catch (Throwable $failure) {
            if (SqlState::uniqueOn($failure, self::IDENTITY_KEYS)) {
                // A run with this mark committed meanwhile: its result is the answer.
                $first = $this->firstResult($intent);

                if ($first !== null) {
                    return ['correction' => $first, 'repeated' => true];
                }
            }

            if (SqlState::budgetEnded($failure) || SqlState::statementTimedOut($failure)) {
                throw new RepairRefused('The recovery passed its 10 minutes: nothing was changed, and the mark stays.');
            }

            throw $failure;
        }

        return ['correction' => $correction, 'repeated' => false];
    }

    /**
     * The correction of the first committed run with this intent, or null.
     */
    private function firstResult(string $intent): ?int
    {
        $identity = $this->identities->byIntent(CorrectCost::OPERATION, $intent);

        return $identity === null ? null : $this->identities->resultOf($identity->id)->ref;
    }

    /**
     * The work in one transaction whose budget is the recovery's, set for the session immediately before
     * BEGIN and restored after it, never inside it (TASK-004), with the statements bounded by it too.
     *
     * @param  callable(): int  $work
     */
    private function inBudget(callable $work): int
    {
        $connection = DB::connection();
        $seconds = config()->integer('reconciliation.recovery_budget');

        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('The recovery\'s transaction is the only one on its connection (TR-TX-01).');
        }

        // The session's own limits, put back afterwards as they were (review R-05).
        $previous = (array) $connection->selectOne("select current_setting('transaction_timeout') as t, current_setting('statement_timeout') as s, current_setting('idle_in_transaction_session_timeout') as i");

        try {
            // The idle limit too, so that a long step of the replay in PHP is not ended as idle (review R-05).
            $connection->statement(self::SET_LIMITS, [$seconds.'s', $seconds.'s', $seconds.'s']);

            return $connection->transaction(static fn (): int => $work(), 1);
        } finally {
            self::restore($connection, $previous);
        }
    }

    /**
     * @param  array<array-key, mixed>  $previous
     */
    private static function restore(Connection $connection, array $previous): void
    {
        if ($connection->getRawPdo() === null) {
            return;
        }

        try {
            $connection->statement(self::SET_LIMITS, [self::setting($previous, 't'), self::setting($previous, 's'), self::setting($previous, 'i')]);
        } catch (Throwable) {
            $connection->disconnect();
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function setting(array $values, string $name): string
    {
        $value = $values[$name] ?? null;

        return is_string($value) ? $value : throw new LogicException('A session setting reads as text.');
    }

    private function written(): void
    {
        $this->points->afterWrite(++$this->writeStep);
    }

    /**
     * @return array{status: string, amount: string|null}
     */
    private static function valueOf(Cost $value): array
    {
        return ['status' => $value->isKnown() ? AcquisitionCost::KNOWN : AcquisitionCost::UNKNOWN, 'amount' => $value->isKnown() ? $value->toCanonical() : null];
    }
}
