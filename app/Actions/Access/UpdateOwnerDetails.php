<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-37 `owner.update_details` (INTERACTION_CONTRACTS RC-35 to RC-42;
 * SECURITY_MODEL SEC-ACC-10; TR-LOCK-05; CAP-27): the Owner's own display name
 * and email. The Owner's own account is locked once, with the update lock of
 * step 2.2, taken by the actor guard; an email change revokes its link first.
 * The role is never touched. A request that changes nothing is refused.
 *
 * Write steps (TEST_STRATEGY TS-21), each when it applies: the link, the
 * account, DETAILS_CHANGE, then the result.
 */
final class UpdateOwnerDetails extends UserManagementAction
{
    public const string OPERATION = 'owner.update_details';

    /** The page of the result (ROUTE_CONTRACTS RP-003). */
    public const string LOCATION = '/account';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap27];
    }

    protected function ownAccountIsTarget(): bool
    {
        return true;
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $this->services->points->afterLock('2.2');

        if ($actor->version !== ($context->envelope->expected['userVersion'] ?? null)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $payload = $context->envelope->payload;
        $name = self::text($payload['displayName'] ?? null);
        $email = self::text($payload['email'] ?? null);
        $emailChanged = $email !== $actor->email;

        $details = [];

        if ($name !== $actor->display_name) {
            $details[] = 'displayName';
        }

        if ($emailChanged) {
            $details[] = 'email';
            $this->refuseHeldEmail($email, $actor->id);
        }

        if ($details === []) {
            throw self::notAllowedInState();
        }

        if ($emailChanged) {
            $this->revokeLink($actor);
        }

        // The audit event names the actor as the transaction found them (G-16).
        $auditActor = self::actorOf($actor);

        $this->changeAccount($actor, ['display_name' => $name, 'email' => $email]);
        $this->written();

        $this->services->audit->record(AuditCategory::Security, 'DETAILS_CHANGE', $auditActor, ['changed' => $details], subject: ['type' => self::RESULT_TYPE, 'id' => $actor->id], commandId: $commandId);
        $this->written();

        return self::result($actor);
    }

    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        if (! Capabilities::allows($viewer, Capability::Cap27)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => self::LOCATION];
    }
}
