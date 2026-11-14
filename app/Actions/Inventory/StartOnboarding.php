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
use App\Domain\Inventory\OnboardingState;
use App\Models\User;

/**
 * RC-12 `onboarding.start` (INTERACTION_CONTRACTS RC-12; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2, "Onboarding set or release"; BR-INV-24; D-071, D-072; WF-24; G-10, G-26; RACE-04,
 * RACE-32; CAP-15). The Owner alone, with recent authentication, which the route requires, starts the
 * one onboarding period of these business data at a documented cutover: the state ACTIVE at the next
 * version, whose time and actor the audit records. From its commit ordinary postings and Sales are
 * refused (G-10).
 *
 * The lock: the onboarding state, update (step 2.3), which waits for every posting that holds its share
 * lock and makes every later one wait for this commit (RACE-04). Under it the state must be NOT_STARTED
 * at the version reviewed, with no release record and no restore hold that waits for its
 * reconciliation; anything else is STALE_VERSION with nothing written, also when two starts race or a
 * start follows a release (RACE-32; D-071, D-072).
 *
 * Write steps (TEST_STRATEGY TS-21): the state, the audit event, then the result.
 */
final class StartOnboarding extends CommandAction
{
    public const string OPERATION = 'onboarding.start';

    /** The result's type and its subject in the audit (SCHEMA section 5.3): the one state row. */
    public const string ONBOARDING = 'onboarding';

    public function __construct(CommandServices $services, private readonly OnboardingState $onboarding)
    {
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
        $status = $this->onboarding->lockForChange();
        $this->services->points->afterLock('2.3');

        if ($status->version !== OnboardingChecks::version($context->envelope->expected) || ! $status->canStart()) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $this->onboarding->start($status, $actor->id, $at);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'ONBOARDING_START',
            Actor::user($actor->id, $actor->display_name),
            ['from' => $status->state, 'to' => OnboardingState::ACTIVE, 'version' => $status->version + 1],
            subject: ['type' => self::ONBOARDING, 'id' => 1],
            commandId: $commandId,
        );
        $this->written();

        return new StoredResult(self::ONBOARDING, null);
    }
}
