<?php

namespace Tests\Harness\Access;

use App\Actions\Commands\CommandActions;
use App\Actions\Commands\CommandContext;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Catalog\Import\CsvImportFiles;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Media\MediaStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use LogicException;
use SplFileInfo;
use Tests\Harness\Race\Scenario;

/**
 * One command of RC-35 to RC-42 in a race worker (RACE-01, RACE-02, RACE-04,
 * RACE-16, RACE-20; TASK-011), or of RC-17 to RC-24 (RACE-01, RACE-02, RACE-04,
 * RACE-05, RACE-23, RACE-29; TASK-015), or RC-25, RC-26 and RC-47 (RACE-01, RACE-02;
 * TASK-018), or RC-31 (RACE-01, RACE-02, RACE-22, RACE-29; TASK-019): the operation's Action, for an actor,
 * with the given intent, key, epoch, expected versions and payload, received
 * now. It answers its outcome, or the code of its refusal.
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

        // RC-25's photo, a path as a worker's arguments carry it, read as an upload is;
        // never the real private disk (TASK-010 finding F-8).
        if (is_array($payload) && is_string($payload['photo'] ?? null)) {
            Storage::fake(MediaStore::DISK);
            $payload['photo'] = new SplFileInfo($payload['photo']);
        }

        // RC-31's temporary area, the one of the test that stored the file (TASK-019).
        $csvRoot = $arguments['csvRoot'] ?? null;

        if (is_string($csvRoot)) {
            config(['filesystems.disks.'.CsvImportFiles::DISK.'.root' => $csvRoot]);
            Storage::forgetDisk(CsvImportFiles::DISK);
        }

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
