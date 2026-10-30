<?php

namespace Tests\Harness\Access;

use App\Actions\Access\BootstrapInput;
use App\Actions\Access\BootstrapInstallation;
use App\Domain\Access\BootstrapRefused;
use Tests\Harness\Race\Scenario;

/**
 * OP-01's Action in a race worker (RACE-30; TASK-010): the bootstrap of the
 * installation, reporting INSTALLED with the new Owner, or the refusal's
 * reason.
 */
final class BootstrapScenario implements Scenario
{
    public function __construct(private readonly BootstrapInstallation $bootstrap) {}

    /**
     * @param  array<string, mixed>  $arguments  name, email and shop
     * @return array{outcome: string, owner?: int}
     */
    public function run(array $arguments): array
    {
        $name = $arguments['name'] ?? null;
        $email = $arguments['email'] ?? null;
        $shop = $arguments['shop'] ?? null;

        if (! is_string($name) || ! is_string($email) || ! is_string($shop)) {
            return ['outcome' => 'BAD_ARGUMENTS'];
        }

        try {
            $result = ($this->bootstrap)(new BootstrapInput($name, $email, $shop, 'Uji balapan instalasi.'));

            return ['outcome' => 'INSTALLED', 'owner' => $result->ownerId];
        } catch (BootstrapRefused $refusal) {
            return ['outcome' => $refusal->reason];
        }
    }
}
