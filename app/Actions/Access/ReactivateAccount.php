<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\AccountStatus;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-39 `user.reactivate` (INTERACTION_CONTRACTS RC-35 to RC-42; BR-USR-10;
 * RACE-16, RACE-30; CAP-28, CAP-33): a DEACTIVATED staff account that has not
 * been anonymized, with its current roles. Never a SUPER_ADMIN account: a former
 * Owner is refused FORBIDDEN by the target check, and the partial unique index
 * would refuse a second active Owner anyway.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the account, 2 USER_REACTIVATE, then the
 * result.
 */
final class ReactivateAccount extends UserManagementAction
{
    public const string OPERATION = 'user.reactivate';

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

        if ($target->status() !== AccountStatus::Deactivated) {
            throw self::notAllowedInState();
        }

        $this->changeAccount($target, ['status' => AccountStatus::Active->value, 'deactivated_at' => null]);
        $this->written();

        $this->services->audit->record(AuditCategory::Security, 'USER_REACTIVATE', self::actorOf($actor), subject: ['type' => self::RESULT_TYPE, 'id' => $target->id], commandId: $commandId);
        $this->written();

        return self::result($target);
    }
}
