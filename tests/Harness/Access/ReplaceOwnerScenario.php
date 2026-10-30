<?php

namespace Tests\Harness\Access;

use App\Actions\Access\ReplaceOwner;
use App\Domain\Access\OwnerProcedureRefused;
use Tests\Harness\Race\Scenario;

/**
 * OP-03's Action in a race worker (RACE-21, RACE-30; TASK-011): one replacement
 * of the named outgoing account. It answers the new account, or the reason of its
 * refusal.
 */
final class ReplaceOwnerScenario implements Scenario
{
    public function __construct(private readonly ReplaceOwner $replace) {}

    /**
     * @param  array<string, mixed>  $arguments  outgoing, name, email
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $outgoing = $arguments['outgoing'] ?? null;
        $name = $arguments['name'] ?? null;
        $email = $arguments['email'] ?? null;

        if (! is_int($outgoing) || ! is_string($name) || ! is_string($email)) {
            return ['outcome' => 'BAD_ARGUMENTS'];
        }

        try {
            $result = ($this->replace)($outgoing, $name, $email, 'Pergantian Pemilik untuk pengujian.');
        } catch (OwnerProcedureRefused $refusal) {
            return ['refused' => $refusal->reason];
        }

        return ['outcome' => 'REPLACED', 'owner' => $result->accountId];
    }
}
