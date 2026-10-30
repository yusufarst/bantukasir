<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\AccountStatus;
use App\Domain\Access\Capability;
use App\Domain\Access\Role;
use App\Domain\Access\StaffRoles;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-36 `user.update` (INTERACTION_CONTRACTS RC-35 to RC-42; SECURITY_MODEL
 * SEC-ACC-04, SEC-SESS-04; BR-USR-04; CAP-27): a staff account's display name,
 * email and roles, never to or from SUPER_ADMIN, under the target lock at the
 * reviewed version. A role change revokes the account's sign-in sessions and
 * link; an email change revokes its link, before the email changes. A request
 * that changes nothing is refused.
 *
 * Write steps (TEST_STRATEGY TS-21), each when it applies: the link, the
 * sessions, the account, ROLE_CHANGE, DETAILS_CHANGE, then the result.
 */
final class UpdateStaffAccount extends UserManagementAction
{
    public const string OPERATION = 'user.update';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap27];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $target = $this->staffTarget($context, $actor);

        if ($target->status() === AccountStatus::Anonymized) {
            throw self::notAllowedInState();
        }

        $payload = $context->envelope->payload;
        $name = self::text($payload['displayName'] ?? null);
        $email = self::text($payload['email'] ?? null);
        $roles = StaffRoles::fromNames(self::items($payload['roles'] ?? null));
        $before = array_map(static fn (Role $role): string => $role->value, $target->roles());
        $after = array_map(static fn (Role $role): string => $role->value, $roles);
        $rolesChanged = $before !== $after;
        $emailChanged = $email !== $target->email;

        $details = [];

        if ($name !== $target->display_name) {
            $details[] = 'displayName';
        }

        if ($emailChanged) {
            $details[] = 'email';
            $this->refuseHeldEmail($email, $target->id);
        }

        if ($details === [] && ! $rolesChanged) {
            throw self::notAllowedInState();
        }

        if ($emailChanged || $rolesChanged) {
            $this->revokeLink($target);
        }

        if ($rolesChanged) {
            $this->endSessions($target);
        }

        $this->changeAccount($target, ['display_name' => $name, 'email' => $email, ...StaffRoles::columns($roles)]);
        $this->written();

        $subject = ['type' => self::RESULT_TYPE, 'id' => $target->id];

        if ($rolesChanged) {
            $this->services->audit->record(AuditCategory::Security, 'ROLE_CHANGE', self::actorOf($actor), ['from' => $before, 'to' => $after], subject: $subject, commandId: $commandId);
            $this->written();
        }

        if ($details !== []) {
            $this->services->audit->record(AuditCategory::Security, 'DETAILS_CHANGE', self::actorOf($actor), ['changed' => $details], subject: $subject, commandId: $commandId);
            $this->written();
        }

        return self::result($target);
    }
}
