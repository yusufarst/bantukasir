<?php

namespace Tests\Harness\Commands;

use App\Actions\Commands\ResolveIntent;
use App\Domain\Commands\CommandRefused;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;
use Tests\Harness\Race\Scenario;

/**
 * RC-50's Action in a race worker (TS-20; AC-008-02, RACE-27, RACE-33): the
 * status check of a checker for the probe's intent and epoch. It answers the
 * outcome, or the code of its refusal.
 */
final class ResolveScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  checker, intent, epoch
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        Probe::register();

        $checker = User::query()->find($arguments['checker'] ?? null);

        if (! $checker instanceof User) {
            throw new RuntimeException('The checker does not exist.');
        }

        try {
            $resolution = app(ResolveIntent::class)($checker, Probe::OPERATION, ProbeScenario::text($arguments, 'intent'), ProbeScenario::text($arguments, 'epoch'), CarbonImmutable::now('UTC'));
        } catch (CommandRefused $refusal) {
            return ['refused' => $refusal->error->value];
        }

        return ['outcome' => $resolution->outcome, 'commandId' => $resolution->commandId];
    }
}
