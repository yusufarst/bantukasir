<?php

namespace App\Actions\Access;

use App\Database\CommandPoints;
use App\Domain\Access\Accounts;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\OwnerProcedureRefused;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * OP-02, the Owner's recovery (SECURITY_MODEL SEC-OP-02; TECHNICAL_RULES section
 * 7.2, "Owner recovery"; OPERATIONS_RUNBOOK RB-10; TASK-011 scope item 3). An
 * operator procedure, without an envelope (TR-CMD-07); in one transaction, under
 * the update lock of the one ACTIVE SUPER_ADMIN account (step 2.2): a RESET link,
 * replacing any earlier one, every sign-in session of the account revoked, and
 * the audit events OWNER_RECOVERY and LINK_ISSUE with the procedure as actor and
 * the operator's note. Name, email and role are unchanged. The token never
 * reaches the audit. The security log entries are written by the procedure after
 * the transaction (SEC-LOG-03).
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the link, 2 the sessions, 3 OWNER_RECOVERY,
 * 4 LINK_ISSUE.
 */
final class RecoverOwner
{
    public const string PROCESS = 'owner_recovery';

    public function __construct(
        private readonly Accounts $accounts,
        private readonly OneTimeLinks $links,
        private readonly AuditLog $audit,
        private readonly CommandPoints $points,
    ) {}

    /**
     * @throws OwnerProcedureRefused
     */
    public function __invoke(string $operatorNote): OwnerLinkResult
    {
        return DB::transaction(function () use ($operatorNote): OwnerLinkResult {
            $id = DB::table('users')->where('role_super_admin', true)->where('status', 'ACTIVE')->value('id');
            $owner = is_int($id) ? $this->accounts->lockForUpdate($id) : null;
            $this->points->afterLock('2.2');

            if ($owner === null || ! $owner->role_super_admin || ! $owner->isActive()) {
                throw new OwnerProcedureRefused(OwnerProcedureRefused::NO_ACTIVE_OWNER);
            }

            $token = $this->links->issue($owner);
            $this->points->afterWrite(1);

            $revoked = $this->accounts->revokeSessions($owner->id);
            $this->points->afterWrite(2);

            $actor = Actor::operatorProcedure(self::PROCESS);
            $subject = ['type' => 'user', 'id' => $owner->id];
            $this->audit->record(AuditCategory::Security, 'OWNER_RECOVERY', $actor, ['sessionsRevoked' => $revoked], subject: $subject, operatorNote: $operatorNote);
            $this->points->afterWrite(3);

            $this->audit->record(AuditCategory::Security, 'LINK_ISSUE', $actor, ['kind' => 'RESET'], subject: $subject, operatorNote: $operatorNote);
            $this->points->afterWrite(4);

            $this->points->beforeCommit();

            return new OwnerLinkResult($owner->id, OneTimeLinks::url($owner, $token), $revoked > 0 ? $owner->id : null);
        });
    }
}
