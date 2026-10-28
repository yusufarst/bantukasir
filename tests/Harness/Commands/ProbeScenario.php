<?php

namespace Tests\Harness\Commands;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use Carbon\CarbonImmutable;
use Tests\Harness\Race\Scenario;

/**
 * The probe operation's Action in a race worker (TS-20; AC-008-02, AC-008-04,
 * AC-008-05): one command of an actor with the given intent, key, epoch and
 * note, received now. It answers its outcome, or the code of its refusal.
 */
final class ProbeScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  actor, intent, key, epoch, note
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        Probe::register();

        $envelope = new Envelope(
            Probe::OPERATION,
            1,
            self::text($arguments, 'intent'),
            self::text($arguments, 'key'),
            self::text($arguments, 'epoch'),
            [],
            ['note' => self::text($arguments, 'note')],
        );
        $actor = $arguments['actor'] ?? null;

        try {
            $outcome = app(ProbeAction::class)->execute(new CommandContext(is_int($actor) ? $actor : 0, $envelope, CarbonImmutable::now('UTC')));
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
    public static function text(array $arguments, string $name): string
    {
        $value = $arguments[$name] ?? null;

        return is_string($value) ? $value : '';
    }
}
