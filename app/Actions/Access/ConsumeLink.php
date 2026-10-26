<?php

namespace App\Actions\Access;

use App\Database\CommandPoints;
use App\Domain\Access\Accounts;
use App\Domain\Access\LinkInvalid;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * RA-06, the consumption of a one-time link (ROUTE_CONTRACTS section 4;
 * INTERACTION_CONTRACTS IC-13; SECURITY_MODEL SEC-ACC-06; TECHNICAL_RULES
 * section 7.2, "One-time link consumption"). An authentication endpoint with
 * no command envelope (TR-CMD-07): in one transaction, under the target
 * account's update lock (step 2.2), the account must exist and be ACTIVE —
 * an anonymized account is never ACTIVE — and the token must be its current,
 * unexpired link; then the password is set, the link is deleted, every
 * sign-in session of the account is revoked and the audit event LINK_CONSUME
 * is written (step 8). Any failure is LinkInvalid, the uniform LINK_INVALID,
 * and writes nothing. Whichever of a consumption, a revocation, a re-issue or
 * a deactivation takes the account lock first wins (RACE-20): a link is
 * consumed at most once.
 *
 * The password has passed AICWDF-COMPAT-8 (App\Domain\Access\PasswordProfile)
 * in the FormRequest. The security log entries — LINK_FAILED, or
 * SESSION_REVOKED for the sessions it ended — are written by the caller after
 * the transaction (SEC-LOG-03).
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the password, 2 the link, 3 the
 * sessions, 4 the audit event.
 */
final class ConsumeLink
{
    public function __construct(
        private readonly Accounts $accounts,
        private readonly OneTimeLinks $links,
        private readonly AuditLog $audit,
        private readonly CommandPoints $points,
    ) {}

    /**
     * @return array{userId: int, sessionsRevoked: int}
     *
     * @throws LinkInvalid
     */
    public function __invoke(int $userId, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): array
    {
        return DB::transaction(function () use ($userId, $token, $password): array {
            $account = $this->accounts->lockForUpdate($userId);
            $this->points->afterLock('2.2');

            if ($account === null || ! $account->isActive() || ! $this->links->matches($account, $token)) {
                throw new LinkInvalid($account?->id);
            }

            $hadPassword = $account->password !== null;

            $this->accounts->setPassword($account, $password);
            $this->points->afterWrite(1);

            $this->links->revoke($account);
            $this->points->afterWrite(2);

            $revoked = $this->accounts->revokeSessions($account->id);
            $this->points->afterWrite(3);

            $this->audit->record(
                AuditCategory::Security,
                'LINK_CONSUME',
                Actor::user($account->id, $account->display_name),
                changeData: ['kind' => $hadPassword ? 'RESET' : 'FIRST_PASSWORD', 'sessionsRevoked' => $revoked],
                subject: ['type' => 'user', 'id' => $account->id],
            );
            $this->points->afterWrite(4);

            $this->points->beforeCommit();

            return ['userId' => $account->id, 'sessionsRevoked' => $revoked];
        });
    }
}
