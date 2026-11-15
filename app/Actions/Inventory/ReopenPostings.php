<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Inventory\PostingBlocks;
use App\Models\User;
use LogicException;

/**
 * RC-16 `posting_block.reopen` (INTERACTION_CONTRACTS RC-16 and section 1.5; ROUTE_CONTRACTS section 6;
 * TECHNICAL_RULES section 7.2, "Reconciliation block (scheduled command) or reopening (Owner)"; SCHEMA
 * section 5.7; BR-INV-45, BR-INV-46; WF-27; G-11; RACE-24; CAP-18). The Owner alone, with recent
 * authentication, which the route requires, reopens the postings of a product that the daily
 * reconciliation blocked, once its facts are checked and reconciled (OPERATIONS_RUNBOOK RB-04 step 7):
 * the reopening of the block, with an optional reason. Nothing else changes; the block stays as it was
 * written (BR-AUD-03).
 *
 * The locks in the order of TR §7.1: the block's product, update (step 6), read from the block, a fact,
 * without a lock (SCH-31); a block that does not exist is NOT_FOUND. Under the guard, which every
 * posting of the product and the block's writer take too, the block must still be open: a second
 * reopening, concurrent or not, is STALE_VERSION with nothing written.
 *
 * Write steps (TEST_STRATEGY TS-21): the reopening, the audit event, then the result.
 */
final class ReopenPostings extends CommandAction
{
    public const string OPERATION = 'posting_block.reopen';

    /** The result's type and the audit's subject: a posting block (SCHEMA section 5.7). */
    public const string BLOCK = 'posting_block';

    /** RP-415, the incident's page (ROUTE_CONTRACTS RP-415). */
    public const string INCIDENTS_PAGE = '/stock/incidents';

    public function __construct(
        CommandServices $services,
        private readonly Products $products,
        private readonly PostingBlocks $blocks,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap18];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap18)) {
            return null;
        }

        $block = $result->ref ?? throw new LogicException('A reopening result names its block.');

        return ['type' => $result->type, 'id' => $block, 'location' => self::INCIDENTS_PAGE.'/'.$block];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $blockId = $payload['blockId'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (! is_int($blockId) || ($reason !== null && ! is_string($reason))) {
            throw new LogicException('The payload was validated before the Action.');
        }

        $productId = $this->blocks->productOf($blockId) ?? throw CommandRefused::of(ErrorCode::NotFound);
        $this->products->lockForPosting([$productId]);
        $this->services->points->afterLock('6');

        if ($this->blocks->isReopened($blockId)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        $this->blocks->reopen($blockId, $actor->id, $actor->display_name, $reason, $commandId);
        $this->written();

        // The reason is free text of the Owner's, and stays the Owner's (BR-AUD-02).
        $this->services->audit->record(
            AuditCategory::Business,
            'POSTING_REOPEN',
            Actor::user($actor->id, $actor->display_name),
            ['blockId' => $blockId],
            $reason === null ? null : ['reason' => $reason],
            subject: ['type' => self::BLOCK, 'id' => $blockId],
            commandId: $commandId,
            productId: $productId,
        );
        $this->written();

        return new StoredResult(self::BLOCK, $blockId);
    }
}
