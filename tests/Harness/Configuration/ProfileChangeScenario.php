<?php

namespace Tests\Harness\Configuration;

use App\Actions\Commands\CommandContext;
use App\Actions\Configuration\ChangeBusinessProfile;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Media\MediaStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Scenario;

/**
 * RC-32's Action in a race worker (RACE-01, RACE-02, RACE-25; TASK-010 review
 * finding R-01): one BusinessProfile change of an actor with the given intent,
 * key, epoch, expected revision and shop name, and, when a path is given, a
 * new logo read from that file, as a multipart request carries it. It answers
 * its outcome, or the code of its refusal.
 */
final class ProfileChangeScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  actor, intent, key, epoch, revision, shop and optionally logo (a path)
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        // Never the real private disk: a worker that runs as another user than
        // PHP-FPM would create its folder, which FPM could then not write in
        // (evidence finding F-8).
        Storage::fake(MediaStore::DISK);

        $path = $arguments['logo'] ?? null;
        $shop = $arguments['shop'] ?? null;
        $revision = $arguments['revision'] ?? null;
        $logo = is_string($path) ? new SplFileInfo($path) : null;
        $payload = [
            'shopName' => is_string($shop) ? $shop : TokoContoh::SHOP,
            'address' => null,
            'contact' => null,
            'email' => null,
            'documentHeader' => null,
            'documentFooter' => null,
            'documentIdentity' => null,
            'accentToken' => 'green',
            'logoAction' => $logo === null ? 'keep' : 'replace',
        ];

        if ($logo !== null) {
            $payload['logo'] = $logo;
        }

        $envelope = new Envelope(
            ChangeBusinessProfile::OPERATION,
            1,
            self::text($arguments, 'intent'),
            self::text($arguments, 'key'),
            self::text($arguments, 'epoch'),
            ['revisionId' => is_int($revision) ? $revision : 0],
            $payload,
        );
        $actor = $arguments['actor'] ?? null;

        try {
            $outcome = app(ChangeBusinessProfile::class)->execute(new CommandContext(is_int($actor) ? $actor : 0, $envelope, CarbonImmutable::now('UTC')));
        } catch (CommandRefused $refusal) {
            return ['refused' => $refusal->error->value];
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
