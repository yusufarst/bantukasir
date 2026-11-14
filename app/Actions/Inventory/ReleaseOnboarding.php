<?php

namespace App\Actions\Inventory;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Domain\Inventory\OnboardingState;
use App\Models\User;

/**
 * RC-13 `onboarding.release` (INTERACTION_CONTRACTS RC-13; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2, "Onboarding set or release"; BR-INV-24, BR-INV-28; D-071, D-072; WF-24; G-26; RACE-32;
 * CAP-15). The Owner alone, with recent authentication, which the route requires, ends the onboarding
 * period after the checks of BR-INV-28 that the review shows: the durable release record, then the
 * state RELEASED at the next version. The release is final: no command changes the state again, and the
 * record travels with the business data, so a reinstallation, a server migration or the restore of an
 * earlier backup never makes onboarding available again (D-071, D-072).
 *
 * The lock: the onboarding state, update (step 2.3). Under it the state must be ACTIVE at the version
 * reviewed, never released and not held for a restore reconciliation; anything else is STALE_VERSION
 * with nothing written, also for a second release, concurrent or not (RACE-32).
 *
 * Write steps (TEST_STRATEGY TS-21): the release record with the state, the audit event, then the
 * result.
 */
final class ReleaseOnboarding extends CommandAction
{
    public const string OPERATION = 'onboarding.release';

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
        OnboardingChecks::requireOpen($status, $context->envelope->expected);

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $this->onboarding->release($status, $actor->id, $commandId, $at);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'ONBOARDING_RELEASE',
            Actor::user($actor->id, $actor->display_name),
            ['from' => $status->state, 'to' => OnboardingState::RELEASED, 'version' => $status->version + 1],
            subject: ['type' => StartOnboarding::ONBOARDING, 'id' => 1],
            commandId: $commandId,
        );
        $this->written();

        return new StoredResult(StartOnboarding::ONBOARDING, null);
    }
}
