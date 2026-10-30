<?php

namespace Tests\Harness\Access;

use App\Actions\Commands\CommandActions;
use App\Actions\Commands\CommandContext;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use Carbon\CarbonImmutable;
use LogicException;
use Tests\Harness\Race\Scenario;

/**
 * One command of RC-35 to RC-42 in a race worker (RACE-01, RACE-02, RACE-04,
 * RACE-16, RACE-20; TASK-011): the operation's Action, for an actor, with the
 * given intent, key, epoch, expected versions and payload, received now. It
 * answers its outcome, or the code of its refusal.
 */
final class UserCommandScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  operation, actor, intent, key, epoch, expected, payload
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $operation = self::text($arguments, 'operation');
        $action = CommandActions::for($operation) ?? throw new LogicException('The operation has an Action.');
        $expected = $arguments['expected'] ?? [];
        $payload = $arguments['payload'] ?? [];
        $actor = $arguments['actor'] ?? null;
        $envelope = new Envelope(
            $operation,
            1,
            self::text($arguments, 'intent'),
            self::text($arguments, 'key'),
            self::text($arguments, 'epoch'),
            is_array($expected) ? $expected : [],
            is_array($payload) ? $payload : [],
        );

        try {
            $outcome = $action->execute(new CommandContext(is_int($actor) ? $actor : 0, $envelope, CarbonImmutable::now('UTC')));
        } catch (CommandRefused $refusal) {
            return ['refused' => $refusal->error->value];
        } catch (ActorNotAllowed) {
            return ['refused' => 'FORBIDDEN'];
        }

        return ['outcome' => 'COMMITTED', 'repeated' => $outcome->repeated, 'commandId' => $outcome->commandId];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function text(array $arguments, string $name): string
    {
        $value = $arguments[$name] ?? null;

        return is_string($value) ? $value : '';
    }
}
