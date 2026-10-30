<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Database\SqlState;
use App\Domain\Access\Capability;
use App\Domain\Access\Role;
use App\Domain\Access\StaffRoles;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use Throwable;

/**
 * RC-35 `user.create` (INTERACTION_CONTRACTS RC-35 to RC-42; SECURITY_MODEL
 * SEC-ACC-02, SEC-ACC-03, SEC-ACC-05; CAP-25, CAP-33): an ACTIVE staff account
 * without a usable password — CASHIER, OPERATIONS_ADMIN or both, never
 * SUPER_ADMIN — and its FIRST_PASSWORD link, returned once in this answer only,
 * which leads to RP-606. An email held by an existing account is refused, also
 * one of a deactivated account.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the account, 2 the link, 3 USER_CREATE,
 * 4 LINK_ISSUE, then the result.
 */
final class CreateStaffAccount extends UserManagementAction
{
    public const string OPERATION = 'user.create';

    private const array EMAIL_KEYS = ['users_email_ci_uniq'];

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap25];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $email = self::text($payload['email'] ?? null);
        $roles = StaffRoles::fromNames(self::items($payload['roles'] ?? null));

        $this->refuseHeldEmail($email, null);

        try {
            $account = $this->accounts->create(self::text($payload['displayName'] ?? null), $email, $roles);
        } catch (Throwable $failure) {
            throw SqlState::uniqueOn($failure, self::EMAIL_KEYS)
                ? new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.email' => [['key' => 'access.user_form.email_taken', 'params' => []]]])
                : $failure;
        }

        $this->written();

        $token = $this->links->issue($account);
        $this->written();

        $subject = ['type' => self::RESULT_TYPE, 'id' => $account->id];
        $this->services->audit->record(AuditCategory::Security, 'USER_CREATE', self::actorOf($actor), ['roles' => array_map(static fn (Role $role): string => $role->value, $roles)], subject: $subject, commandId: $commandId);
        $this->written();

        $this->services->audit->record(AuditCategory::Security, 'LINK_ISSUE', self::actorOf($actor), ['kind' => 'FIRST_PASSWORD'], subject: $subject, commandId: $commandId);
        $this->written();

        $this->handOver($this->links::url($account, $token), $this->linkExpiry());

        return self::result($account);
    }

    /**
     * Committed now, the result leads to the link's one-time page (RP-606).
     */
    protected function presentNow(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $shown = $this->present($viewer, $commandId, $result);

        return $shown === null ? null : [...$shown, 'location' => $shown['location'].'/link'];
    }
}
