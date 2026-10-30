<?php

namespace App\Actions\Access;

use App\Database\CommandPoints;
use App\Database\SqlState;
use App\Domain\Access\Accounts;
use App\Domain\Access\AccountStatus;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\OwnerProcedureRefused;
use App\Domain\Access\Role;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OP-03, the Owner's replacement (SECURITY_MODEL SEC-OP-03; TECHNICAL_RULES
 * section 7.2, "Owner replacement"; OPERATIONS_RUNBOOK RB-11; G-08; RACE-21,
 * RACE-30; TASK-011 scope item 3). An operator procedure, without an envelope
 * (TR-CMD-07); in one transaction, under the update lock of the outgoing account
 * named by its internal identifier (step 2.2), which must be the one ACTIVE
 * SUPER_ADMIN account, so that a repeated run finds it DEACTIVATED and is refused:
 * its sign-in sessions and link revoked, the account deactivated — never deleted
 * — then, so that the partial unique index never sees two active Owners (SCHEMA
 * section 5.1), a new SUPER_ADMIN account with its FIRST_PASSWORD link, and the
 * audit events with the procedure as actor and the operator's note. The token
 * never reaches the audit. An OPEN cash session of the outgoing Owner stays OPEN
 * and unchanged: nothing here touches it (D-087; TASK-032 proves it).
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the sessions, 2 the link, 3 the outgoing
 * account, 4 the new account, 5 its link, 6 OWNER_REPLACEMENT, 7 USER_DEACTIVATE,
 * 8 USER_CREATE, 9 LINK_ISSUE.
 */
final class ReplaceOwner
{
    public const string PROCESS = 'owner_replacement';

    /** The unique keys whose conflict means the new account's email was taken meanwhile. */
    private const array EMAIL_KEYS = ['users_email_ci_uniq'];

    public function __construct(
        private readonly Accounts $accounts,
        private readonly OneTimeLinks $links,
        private readonly AuditLog $audit,
        private readonly CommandPoints $points,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws OwnerProcedureRefused
     */
    public function __invoke(int $outgoingId, string $name, string $email, string $operatorNote): OwnerLinkResult
    {
        try {
            return DB::transaction(function () use ($outgoingId, $name, $email, $operatorNote): OwnerLinkResult {
                $outgoing = $this->accounts->lockForUpdate($outgoingId);
                $this->points->afterLock('2.2');

                if ($outgoing === null || ! $outgoing->role_super_admin || ! $outgoing->isActive()) {
                    throw new OwnerProcedureRefused(OwnerProcedureRefused::NOT_THE_ACTIVE_OWNER);
                }

                if ($this->accounts->findByEmail($email) !== null) {
                    throw new OwnerProcedureRefused(OwnerProcedureRefused::EMAIL_HELD);
                }

                $revoked = $this->accounts->revokeSessions($outgoing->id);
                $this->points->afterWrite(1);

                $this->links->revoke($outgoing);
                $this->points->afterWrite(2);

                $this->accounts->change($outgoing, ['status' => AccountStatus::Deactivated->value, 'deactivated_at' => $this->clock->now()]);
                $this->points->afterWrite(3);

                $owner = $this->accounts->create($name, $email, [Role::SuperAdmin]);
                $this->points->afterWrite(4);

                $token = $this->links->issue($owner);
                $this->points->afterWrite(5);

                $actor = Actor::operatorProcedure(self::PROCESS);
                $this->audit->record(AuditCategory::Security, 'OWNER_REPLACEMENT', $actor, ['outgoing' => $outgoing->id, 'incoming' => $owner->id, 'sessionsRevoked' => $revoked], subject: ['type' => 'user', 'id' => $owner->id], operatorNote: $operatorNote);
                $this->points->afterWrite(6);

                $this->audit->record(AuditCategory::Security, 'USER_DEACTIVATE', $actor, subject: ['type' => 'user', 'id' => $outgoing->id], operatorNote: $operatorNote);
                $this->points->afterWrite(7);

                $this->audit->record(AuditCategory::Security, 'USER_CREATE', $actor, ['roles' => [Role::SuperAdmin->value]], subject: ['type' => 'user', 'id' => $owner->id], operatorNote: $operatorNote);
                $this->points->afterWrite(8);

                $this->audit->record(AuditCategory::Security, 'LINK_ISSUE', $actor, ['kind' => 'FIRST_PASSWORD'], subject: ['type' => 'user', 'id' => $owner->id], operatorNote: $operatorNote);
                $this->points->afterWrite(9);

                $this->points->beforeCommit();

                return new OwnerLinkResult($owner->id, OneTimeLinks::url($owner, $token), $revoked > 0 ? $outgoing->id : null);
            });
        } catch (Throwable $failure) {
            if (SqlState::uniqueOn($failure, self::EMAIL_KEYS)) {
                throw new OwnerProcedureRefused(OwnerProcedureRefused::EMAIL_HELD);
            }

            throw $failure;
        }
    }
}
