<?php

namespace App\Domain\Access;

use RuntimeException;

/**
 * Any failure of a one-time link — no such account, no link, a wrong,
 * expired, used, replaced or revoked token, an account that is not ACTIVE —
 * answered with the one uniform LINK_INVALID (SECURITY_MODEL SEC-ACC-06,
 * TR-ERR-04). It carries the account the link named, when one exists, for
 * the security log only (subject_user_id), never for the answer.
 */
final class LinkInvalid extends RuntimeException
{
    public function __construct(public readonly ?int $namedUserId)
    {
        parent::__construct('LINK_INVALID');
    }
}
