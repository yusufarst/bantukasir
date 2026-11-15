<?php

namespace App\Actions\Operations;

use App\Actions\Commands\CommandServices;
use App\Actions\Commands\CommandTransaction;
use App\Database\SqlState;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\Fingerprint;
use App\Domain\Commands\Operations;
use App\Domain\Commands\StoredResult;
use App\Domain\Inventory\PostingBlocks;
use App\Domain\Operations\Reconciliation\BlockOutcome;
use App\Domain\Operations\Reconciliation\NoBlock;
use App\Domain\Operations\Reconciliation\ProductReconciliation;
use LogicException;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * `posting_block.block`, the enveloped command of SC-01 (INTERACTION_CONTRACTS section 7; ROUTE_CONTRACTS
 * SC-01 and section 6; TECHNICAL_RULES section 7.2, "Reconciliation block (scheduled command) or reopening
 * (Owner)"; SCHEMA sections 5.3 and 5.7; BR-INV-45; G-03, G-11; RACE-24). The process actor
 * `reconciliation` blocks the postings of one product whose facts and projections the run's snapshot
 * found to differ, in one transaction of TR §7.1:
 *
 * 1. the command identity of the process, with a source intent and a key derived from the run and the
 *    product, so that a repeat of the run's block of that product writes it once (SCHEMA section 5.3);
 * 2. the epoch check, guard G-12, before any fact;
 * 3. the product's guard, update (step 6), which every posting of the product takes too: a posting
 *    that took it first has committed, and the comparison below sees it; one that comes after waits,
 *    then finds the block and is refused POSTING_BLOCKED (RACE-24);
 * 4. under the guard, the product already blocked — by an earlier run — or its facts and projections
 *    agreeing after all end the transaction with nothing written, its identity included;
 * 5. the block with its detail code, the audit event RECONCILIATION_BLOCK and the result.
 *
 * A bounded heavy command: the comparison under the guard replays the product's whole history. Write
 * steps (TEST_STRATEGY TS-21): the block, the audit event, then the result.
 */
final class BlockPostings
{
    public const string OPERATION = 'posting_block.block';

    public const string PROCESS = 'reconciliation';

    /** The result's type and the audit's subject: a posting block (SCHEMA section 5.7). */
    public const string BLOCK = 'posting_block';

    /** The identity's unique keys, whose conflict means that this block of the run committed already. */
    private const array IDENTITY_KEYS = ['command_identities_intent_unique', 'command_identities_process_key'];

    private int $writeStep = 0;

    public function __construct(
        private readonly CommandServices $services,
        private readonly Products $products,
        private readonly PostingBlocks $blocks,
        private readonly ProductReconciliation $reconciliation,
    ) {}

    /**
     * Blocks the product's postings for this run, if its facts and projections still differ under its
     * guard.
     *
     * @throws CommandRefused EPOCH_STALE or SERVICE_BUSY, with nothing written
     */
    public function __invoke(string $runKey, int $productId): BlockOutcome
    {
        $services = $this->services;
        $epoch = $services->epoch->current() ?? throw new LogicException('The bootstrap inserts the recovery epoch (SEC-OP-01).');
        $intent = Uuid::uuid5(Uuid::NAMESPACE_URL, 'reconciliation/'.$runKey.'/product/'.$productId)->toString();
        $key = Uuid::uuid5(Uuid::NAMESPACE_URL, 'reconciliation/'.$runKey.'/product/'.$productId.'/key')->toString();
        $envelope = new Envelope(self::OPERATION, Operations::VERSION, $intent, $key, $epoch, [], ['productId' => $productId]);
        $fingerprint = Fingerprint::of($envelope->operation, $envelope->version, 'process:'.self::PROCESS, [], $envelope->payload);
        $this->writeStep = 0;

        try {
            CommandTransaction::run(function () use ($services, $envelope, $fingerprint, $productId): void {
                $now = $services->clock->now()->format('Y-m-d H:i:s.uP');
                $commandId = $services->identities->insertProcessCommand($envelope, 'SCHEDULED_COMMAND', self::PROCESS, $fingerprint, $now);
                $services->points->afterIdentity();

                // G-12, before any fact; the epoch needs no lock (SCHEMA section 5.3).
                if ($services->epoch->current() !== $envelope->recoveryEpoch) {
                    throw CommandRefused::of(ErrorCode::EpochStale);
                }

                $product = $this->products->lockForPosting([$productId])[$productId] ?? throw new LogicException('A product that the run read exists: products are never removed.');
                $services->points->afterLock('6');

                if ($this->blocks->blockedAmong([$productId]) !== []) {
                    throw new NoBlock(BlockOutcome::AlreadyBlocked);
                }

                $detail = $this->reconciliation->mismatchOf($productId, $product->quantityPrecision) ?? throw new NoBlock(BlockOutcome::Agrees);
                $actor = Actor::scheduledCommand(self::PROCESS);
                $block = $this->blocks->block($productId, $actor, $detail, $commandId);
                $this->written();

                $services->audit->record(
                    AuditCategory::Business,
                    'RECONCILIATION_BLOCK',
                    $actor,
                    ['detailCode' => $detail],
                    subject: ['type' => self::BLOCK, 'id' => $block],
                    commandId: $commandId,
                    productId: $productId,
                );
                $this->written();

                $services->identities->recordResult($commandId, new StoredResult(self::BLOCK, $block));
                $this->written();
                $services->points->beforeCommit();
            }, true);
        } catch (NoBlock $none) {
            return $none->outcome;
        } catch (Throwable $failure) {
            if (SqlState::uniqueOn($failure, self::IDENTITY_KEYS)) {
                // This run's block of the product committed in an earlier attempt.
                return BlockOutcome::AlreadyBlocked;
            }

            if (SqlState::of($failure) === SqlState::LOCK_NOT_AVAILABLE) {
                throw CommandRefused::of(ErrorCode::ServiceBusy);
            }

            throw $failure;
        }

        return BlockOutcome::Written;
    }

    private function written(): void
    {
        $this->services->points->afterWrite(++$this->writeStep);
    }
}
