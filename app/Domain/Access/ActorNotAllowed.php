<?php

namespace App\Domain\Access;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * The refusal of the actor guard (SEC-AZ-04, RACE-04): the account is no
 * longer ACTIVE, or no longer holds a capability the command needs. The
 * transaction rolls back, the command writes nothing, and the request is
 * answered FORBIDDEN (TECHNICAL_RULES section 4) with an AUTHORIZATION_REFUSED
 * entry of the security log, written after the rollback (bootstrap/app.php).
 */
final class ActorNotAllowed extends AuthorizationException
{
    public function __construct()
    {
        parent::__construct('The actor is no longer allowed to run this command.');
    }
}
