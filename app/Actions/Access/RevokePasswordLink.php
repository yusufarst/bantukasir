<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-42 `user.revoke_link` (INTERACTION_CONTRACTS RC-35 to RC-42; SECURITY_MODEL
 * SEC-ACC-04; CAP-26; RACE-20): the link of a staff account, under its lock. A
 * link consumed, revoked or expired meanwhile is STALE_VERSION.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the link, 2 LINK_REVOKE, then the result.
 */
final class RevokePasswordLink extends UserManagementAction
{
    public const string OPERATION = 'user.revoke_link';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap26];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $target = $this->staffTarget($context, $actor);

        if ($this->links->activeUntil($target) === null) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $this->revokeLink($target);

        $this->services->audit->record(AuditCategory::Security, 'LINK_REVOKE', self::actorOf($actor), subject: ['type' => self::RESULT_TYPE, 'id' => $target->id], commandId: $commandId);
        $this->written();

        return self::result($target);
    }
}
