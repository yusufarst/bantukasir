<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\AccountStatus;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-38 `user.deactivate` (INTERACTION_CONTRACTS RC-35 to RC-42; SECURITY_MODEL
 * SEC-ACC-08; BR-USR-05, BR-USR-09; RACE-04; CAP-28): an ACTIVE staff account
 * under the target lock, its sign-in sessions and link revoked in the same
 * transaction. A command of that account already running holds the share lock
 * of its actor guard, so one or the other commits first and the winner is in
 * force (RACE-04). An OPEN cash session of the account stays OPEN and unchanged
 * (D-069 (d), RACE-28): nothing here touches it.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the sessions, 2 the link, 3 the account,
 * 4 USER_DEACTIVATE, then the result.
 */
final class DeactivateAccount extends UserManagementAction
{
    public const string OPERATION = 'user.deactivate';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap28];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $target = $this->staffTarget($context, $actor);

        if (! $target->isActive()) {
            throw self::notAllowedInState();
        }

        $this->endSessions($target);
        $this->revokeLink($target);

        $this->changeAccount($target, ['status' => AccountStatus::Deactivated->value, 'deactivated_at' => $this->services->clock->now()]);
        $this->written();

        $this->services->audit->record(AuditCategory::Security, 'USER_DEACTIVATE', self::actorOf($actor), subject: ['type' => self::RESULT_TYPE, 'id' => $target->id], commandId: $commandId);
        $this->written();

        return self::result($target);
    }
}
