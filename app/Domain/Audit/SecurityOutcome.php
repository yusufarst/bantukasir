<?php

namespace App\Domain\Audit;

/**
 * The outcome code of a security log entry: SUCCEEDED or REFUSED only, the two
 * that COPY_DECK labels (audit.security.outcome.*); the event type carries the
 * kind of failure, and a further code needs its label through change control
 * first (INTERACTION_CONTRACTS section 8).
 */
enum SecurityOutcome: string
{
    case Succeeded = 'SUCCEEDED';
    case Refused = 'REFUSED';
}
