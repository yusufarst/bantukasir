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
 * status check of a checker for an intent and epoch of the probe or, when
 * named, of another operation (RACE-15 of RC-05, TASK-034). It answers the
 * outcome, or the code of its refusal.
 */
final class ResolveScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  checker, intent, epoch, and optionally operation
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        Probe::register();

        $checker = User::query()->find($arguments['checker'] ?? null);

        if (! $checker instanceof User) {
            throw new RuntimeException('The checker does not exist.');
        }

        $operation = array_key_exists('operation', $arguments) ? ProbeScenario::text($arguments, 'operation') : Probe::OPERATION;

        try {
            $resolution = app(ResolveIntent::class)($checker, $operation, ProbeScenario::text($arguments, 'intent'), ProbeScenario::text($arguments, 'epoch'), CarbonImmutable::now('UTC'));
        } catch (CommandRefused $refusal) {
            return ['refused' => $refusal->error->value];
        }

        return ['outcome' => $resolution->outcome, 'commandId' => $resolution->commandId];
    }
}
