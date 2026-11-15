<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Inventory\Onboarding\OpeningStock;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\PostingGuard;
use App\Models\User;
use LogicException;

/**
 * RC-14 `onboarding.withdraw_zero_count` (INTERACTION_CONTRACTS RC-14; ROUTE_CONTRACTS section 6;
 * TECHNICAL_RULES section 7.2, "Opening stock"; BR-INV-27, BR-INV-47; D-087; WF-25; G-22, G-26;
 * RACE-31; CAP-15). The Owner alone, with recent authentication, which the route requires, withdraws a
 * zero count before the release, with a reason: a withdrawal record linked to it — the zero count
 * stays, append-only — after which the product is not counted and may be counted again (RC-11).
 *
 * The zero count, a fact, is read without a lock to choose its product's guard (SCH-31); one that does
 * not exist is NOT_FOUND. The locks in the order of TR §7.1: the onboarding state, share (step 2.3),
 * under which onboarding is ACTIVE, never released and not held, at the version reviewed (STALE_VERSION);
 * the product, update (step 6), under which the zero count is not withdrawn yet (STALE_VERSION), which
 * only a withdrawal under the same guard changes, so a second withdrawal, concurrent or not, writes
 * nothing (RACE-31).
 *
 * Write steps (TEST_STRATEGY TS-21): the withdrawal, the audit event, then the result.
 */
final class WithdrawZeroCount extends CommandAction
{
    public const string OPERATION = 'onboarding.withdraw_zero_count';

    /** The result's type and its subject in the audit (SCHEMA section 5.3). */
    private const string ZERO_COUNT = 'onboarding_zero_count';

    public function __construct(
        CommandServices $services,
        private readonly OnboardingState $onboarding,
        private readonly PostingGuard $guard,
        private readonly OpeningStock $entries,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap15];
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap15)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => ConfirmOpeningStock::ONBOARDING_PAGE];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $zeroCountId = $payload['zeroCountId'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (! is_int($zeroCountId) || ! is_string($reason)) {
            throw new LogicException('The payload was validated before the Action.');
        }

        // The zero count, a fact, read without a lock to choose its product's guard (SCH-31).
        $zeroCount = $this->entries->zeroCount($zeroCountId) ?? throw CommandRefused::of(ErrorCode::NotFound);

        $status = $this->onboarding->lockForEntry();
        $this->services->points->afterLock('2.3');
        OnboardingChecks::requireOpen($status, $context->envelope->expected);

        $this->guard->lockProducts([$zeroCount->productId]);
        $this->services->points->afterLock('6');

        // Read again under the guard, which every withdrawal of it holds (RACE-31).
        $held = $this->entries->zeroCount($zeroCountId) ?? throw new LogicException('A zero count is never removed.');

        if ($held->withdrawn) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $this->entries->withdraw($held, $reason, $actor->id, $actor->display_name, $commandId, $at);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'ZERO_COUNT_WITHDRAW',
            Actor::user($actor->id, $actor->display_name),
            ['zeroCountId' => $held->id, 'productId' => $held->productId],
            subject: ['type' => self::ZERO_COUNT, 'id' => $held->id],
            reason: $reason,
            commandId: $commandId,
            productId: $held->productId,
        );
        $this->written();

        return new StoredResult(self::ZERO_COUNT, $held->id);
    }
}
