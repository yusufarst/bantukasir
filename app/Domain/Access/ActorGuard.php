<?php

namespace App\Domain\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The in-transaction guard of the actor (SECURITY_MODEL SEC-AZ-04;
 * TECHNICAL_RULES section 7.1 step 2.1, RACE-04, TR-LOCK-02; TASK-007). Inside
 * the command's transaction, under the lock of the acting user's account, the
 * server checks again that the account is ACTIVE and still holds every
 * capability the command needs, so that a deactivation or a role change that
 * commits first is the one in force: the command then writes no fact
 * (ActorNotAllowed, answered FORBIDDEN).
 *
 * The account is share-locked (step 2.1). When the command's target of step
 * 2.2 is the actor's own account — the own password change, the Owner's own
 * name and email — the guard takes that update lock instead, once
 * (TR-LOCK-05). The actor is the sign-in session's (SEC-AZ-05): the caller
 * passes the identity it read from the session, never one from the request.
 * TASK-008's Action base calls it for every command.
 */
final class ActorGuard
{
    /**
     * @param  list<Capability>  $needed  every one of them must open its gate
     *
     * @throws ActorNotAllowed when the account is gone, not ACTIVE, or no longer holds a capability
     */
    public function hold(int $actorId, array $needed, bool $ownAccountIsTarget = false): User
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The actor guard runs inside the command\'s transaction (SEC-AZ-04).');
        }

        $query = User::query()->whereKey($actorId);
        $actor = ($ownAccountIsTarget ? $query->lock('for no key update') : $query->sharedLock())->first();

        if (! $actor instanceof User || ! $actor->isActive()) {
            throw new ActorNotAllowed;
        }

        foreach ($needed as $capability) {
            if (! Capabilities::allows($actor, $capability)) {
                throw new ActorNotAllowed;
            }
        }

        return $actor;
    }
}
