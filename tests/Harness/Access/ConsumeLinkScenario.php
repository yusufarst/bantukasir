<?php

namespace Tests\Harness\Access;

use App\Actions\Access\ConsumeLink;
use App\Domain\Access\LinkInvalid;
use Tests\Harness\Race\Scenario;

/**
 * RA-06's Action in a race worker (RACE-20; TASK-006): the consumption of a
 * one-time link, reporting CONSUMED or the uniform LINK_INVALID.
 */
final class ConsumeLinkScenario implements Scenario
{
    public function __construct(private readonly ConsumeLink $consume) {}

    /**
     * @param  array<string, mixed>  $arguments  user, token and password
     * @return array{outcome: string}
     */
    public function run(array $arguments): array
    {
        $user = $arguments['user'] ?? null;
        $token = $arguments['token'] ?? null;
        $password = $arguments['password'] ?? null;

        if (! is_int($user) || ! is_string($token) || ! is_string($password)) {
            return ['outcome' => 'BAD_ARGUMENTS'];
        }

        try {
            ($this->consume)($user, $token, $password);

            return ['outcome' => 'CONSUMED'];
        } catch (LinkInvalid) {
            return ['outcome' => 'LINK_INVALID'];
        }
    }
}
