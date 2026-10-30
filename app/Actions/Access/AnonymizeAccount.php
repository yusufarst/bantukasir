<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\AccountStatus;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-40 `user.anonymize` (INTERACTION_CONTRACTS RC-35 to RC-42; SECURITY_MODEL
 * SEC-ACC-09; BR-USR-11, BR-USR-12; D-043, D-048; RACE-16; CAP-29): a DEACTIVATED
 * staff account, or a former Owner deactivated by OP-03, with a reason. The
 * display name becomes `access.user.anonymized_name` with `:nomor` the account's
 * internal identifier, the email `anonymized-<id>@users.invalid`, the password
 * none; its link and sessions are revoked. Only the current profile changes:
 * every frozen display name elsewhere stays (SCHEMA section 3.5, G-16), and the
 * account is never reactivated.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the sessions, 2 the link, 3 the account,
 * 4 USER_ANONYMIZE, then the result.
 */
final class AnonymizeAccount extends UserManagementAction
{
    public const string OPERATION = 'user.anonymize';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap29];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $target = $this->target($context, $actor);

        if ($target->status() !== AccountStatus::Deactivated) {
            throw self::notAllowedInState();
        }

        $this->endSessions($target);
        $this->revokeLink($target);

        $this->changeAccount($target, [
            'display_name' => self::text(__('access.user.anonymized_name', ['nomor' => (string) $target->id])),
            'email' => sprintf('anonymized-%d@users.invalid', $target->id),
            'password' => null,
            'status' => AccountStatus::Anonymized->value,
            'anonymized_at' => $this->services->clock->now(),
        ]);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Security,
            'USER_ANONYMIZE',
            self::actorOf($actor),
            subject: ['type' => self::RESULT_TYPE, 'id' => $target->id],
            reason: self::text($context->envelope->payload['reason'] ?? null),
            commandId: $commandId,
        );
        $this->written();

        return self::result($target);
    }
}
