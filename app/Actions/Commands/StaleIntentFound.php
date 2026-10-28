<?php

namespace App\Actions\Commands;

use RuntimeException;

/**
 * Raised inside the fence's transaction of a status check when, under the
 * fence, the intent turns out to be of an earlier epoch (ResolveIntent;
 * review finding R-02): the fence rolls back and the check answers
 * EPOCH_STALE with its report. Never leaves ResolveIntent.
 */
final class StaleIntentFound extends RuntimeException
{
    public function __construct(public readonly string $epoch, public readonly int $actorId)
    {
        parent::__construct('The intent belongs to an earlier recovery epoch.');
    }
}
